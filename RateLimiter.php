<?php
/**
 * Simple file-based rate limiter for API and site endpoints
 * Uses IP-based rate limiting with configurable limits per endpoint
 */
class RateLimiter {
    private $storageDir;
    private $defaultLimit;
    private $defaultWindow;
    
    /**
     * @param string $storageDir Directory to store rate limit data
     * @param int $defaultLimit Default number of requests allowed
     * @param int $defaultWindow Default time window in seconds
     */
    public function __construct($storageDir = null, $defaultLimit = 60, $defaultWindow = 60) {
        $this->storageDir = $storageDir ?: sys_get_temp_dir() . '/rate_limiter';
        $this->defaultLimit = $defaultLimit;
        $this->defaultWindow = $defaultWindow;
        
        // Create storage directory if it doesn't exist
        if (!is_dir($this->storageDir)) {
            mkdir($this->storageDir, 0777, true);
        }
        
        // Clean up old files periodically (1% chance on each request)
        if (mt_rand(1, 100) === 1) {
            $this->cleanup();
        }
    }
    
    /**
     * Check if request should be rate limited
     * 
     * @param string $identifier Unique identifier (e.g., IP address, user ID)
     * @param string $endpoint Endpoint being accessed
     * @param int|null $limit Maximum requests allowed (null uses default)
     * @param int|null $window Time window in seconds (null uses default)
     * @return array ['allowed' => bool, 'remaining' => int, 'reset' => int]
     */
    public function check($identifier, $endpoint = 'default', $limit = null, $window = null) {
        $limit = $limit ?? $this->defaultLimit;
        $window = $window ?? $this->defaultWindow;
        
        // Sanitize identifier and endpoint for file storage
        $safeIdentifier = $this->sanitizeKey($identifier);
        $safeEndpoint = $this->sanitizeKey($endpoint);
        $key = $safeIdentifier . '_' . $safeEndpoint;
        
        $filePath = $this->storageDir . '/' . $key . '.json';
        $now = time();
        
        // Load existing data or create new
        $data = $this->loadData($filePath);
        
        // Reset if window has passed
        if ($data['window_start'] + $window < $now) {
            $data = [
                'window_start' => $now,
                'count' => 0,
                'requests' => []
            ];
        }
        
        // Clean old requests from current window
        $data['requests'] = array_filter($data['requests'], function($timestamp) use ($now, $window) {
            return $timestamp > ($now - $window);
        });
        
        // Check if limit reached
        $count = count($data['requests']);
        $allowed = $count < $limit;
        
        // Add current request if allowed
        if ($allowed) {
            $data['requests'][] = $now;
            $data['count'] = count($data['requests']);
            $this->saveData($filePath, $data);
        }
        
        return [
            'allowed' => $allowed,
            'remaining' => max(0, $limit - count($data['requests'])),
            'reset' => $data['window_start'] + $window,
            'limit' => $limit
        ];
    }
    
    /**
     * Apply rate limiting with automatic response
     * 
     * @param string|null $identifier Identifier (null uses IP)
     * @param string $endpoint Endpoint being accessed
     * @param int|null $limit Maximum requests allowed
     * @param int|null $window Time window in seconds
     * @return bool True if request allowed, false if rate limited (sends 429 response)
     */
    public function limit($identifier = null, $endpoint = 'default', $limit = null, $window = null) {
        // Use IP address if no identifier provided
        if ($identifier === null) {
            $identifier = $this->getClientIp();
        }
        
        $result = $this->check($identifier, $endpoint, $limit, $window);
        
        // Set rate limit headers
        header('X-RateLimit-Limit: ' . $result['limit']);
        header('X-RateLimit-Remaining: ' . $result['remaining']);
        header('X-RateLimit-Reset: ' . $result['reset']);
        
        if (!$result['allowed']) {
            http_response_code(429);
            header('Retry-After: ' . ($result['reset'] - time()));
            
            // Return JSON error for API requests
            if (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false) {
                header('Content-Type: application/json');
                echo json_encode([
                    'error' => 'Too many requests',
                    'message' => 'Rate limit exceeded. Please try again later.',
                    'retry_after' => $result['reset'] - time()
                ]);
            } else {
                // HTML error for web requests
                echo '<!DOCTYPE html><html><head><title>429 Too Many Requests</title></head>';
                echo '<body><h1>Too Many Requests</h1>';
                echo '<p>You have exceeded the rate limit. Please try again in ' . ($result['reset'] - time()) . ' seconds.</p>';
                echo '</body></html>';
            }
            exit;
        }
        
        return true;
    }
    
    /**
     * Get client IP address
     */
    private function getClientIp() {
        // Check for proxied IPs
        $headers = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_X_FORWARDED_FOR',       // Proxy
            'HTTP_X_REAL_IP',             // Nginx
            'HTTP_CLIENT_IP',             // Proxy
            'REMOTE_ADDR'                 // Direct connection
        ];
        
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                $ip = trim($ips[0]);
                
                // Validate IP
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
    
    /**
     * Sanitize key for safe file storage
     */
    private function sanitizeKey($key) {
        return preg_replace('/[^a-zA-Z0-9_.-]/', '_', $key);
    }
    
    /**
     * Load data from file
     */
    private function loadData($filePath) {
        if (!file_exists($filePath)) {
            return [
                'window_start' => time(),
                'count' => 0,
                'requests' => []
            ];
        }
        
        $content = file_get_contents($filePath);
        $data = json_decode($content, true);
        
        if (!$data || !isset($data['window_start']) || !isset($data['requests'])) {
            return [
                'window_start' => time(),
                'count' => 0,
                'requests' => []
            ];
        }
        
        return $data;
    }
    
    /**
     * Save data to file
     */
    private function saveData($filePath, $data) {
        file_put_contents($filePath, json_encode($data), LOCK_EX);
    }
    
    /**
     * Clean up old rate limit files
     */
    private function cleanup() {
        $files = glob($this->storageDir . '/*.json');
        $now = time();
        
        foreach ($files as $file) {
            // Remove files older than 1 hour
            if (filemtime($file) < ($now - 3600)) {
                @unlink($file);
            }
        }
    }
    
    /**
     * Reset rate limit for specific identifier and endpoint
     */
    public function reset($identifier, $endpoint = 'default') {
        $safeIdentifier = $this->sanitizeKey($identifier);
        $safeEndpoint = $this->sanitizeKey($endpoint);
        $key = $safeIdentifier . '_' . $safeEndpoint;
        
        $filePath = $this->storageDir . '/' . $key . '.json';
        if (file_exists($filePath)) {
            @unlink($filePath);
        }
    }
}