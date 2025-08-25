<?php
// Database configuration
class Config {
    private static $env = null;
    
    public static function loadEnv() {
        if (self::$env === null) {
            self::$env = [];
            
            // Try to load .env file
            if (file_exists(__DIR__ . '/.env')) {
                $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $line) {
                    if (strpos(trim($line), '#') === 0) continue;
                    if (strpos($line, '=') !== false) {
                        list($key, $value) = explode('=', $line, 2);
                        self::$env[trim($key)] = trim($value, '"\'');
                    }
                }
            }
            
            // Try to load env.php file as fallback
            if (file_exists(__DIR__ . '/env.php')) {
                $envData = include __DIR__ . '/env.php';
                if (is_array($envData)) {
                    self::$env = array_merge(self::$env, $envData);
                }
            }
        }
        
        return self::$env;
    }
    
    public static function get($key, $default = null) {
        $env = self::loadEnv();
        return isset($env[$key]) ? $env[$key] : $default;
    }
    
    public static function getDatabaseConnection() {
        $env = self::loadEnv();
        
        $host = self::get('DB_HOST', 'localhost');
        $dbname = self::get('DB_NAME', 'contractwekker');
        $username = self::get('DB_USERNAME', 'root');
        $password = self::get('DB_PASSWORD', '');
        $charset = self::get('DB_CHARSET', 'utf8mb4');
        
        try {
            $dsn = "mysql:host={$host};dbname={$dbname};charset={$charset}";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            
            return new PDO($dsn, $username, $password, $options);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw new Exception("Database connection failed");
        }
    }
}

// Security functions
function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function isBot($honeypot) {
    return !empty($honeypot);
}

function calculateNextAlertDate($alertPeriod, $customPeriod = null, $customUnit = null, $endDate = null) {
    $now = new DateTime();
    
    switch ($alertPeriod) {
        case '1_month':
            $now->add(new DateInterval('P1M'));
            break;
        case '3_months':
            $now->add(new DateInterval('P3M'));
            break;
        case '1_year':
            $now->add(new DateInterval('P1Y'));
            break;
        case '2_years':
            $now->add(new DateInterval('P2Y'));
            break;
        case '3_years':
            $now->add(new DateInterval('P3Y'));
            break;
        case 'custom':
            // For custom period, calculate 1 month before the end date
            if (!empty($endDate)) {
                $endDateObj = DateTime::createFromFormat('Y-m-d', $endDate);
                if ($endDateObj) {
                    // Calculate alert date 1 month before end date
                    $endDateObj->sub(new DateInterval('P1M'));
                    return $endDateObj->format('Y-m-d H:i:s');
                }
            }
            break;
    }
    
    return $now->format('Y-m-d H:i:s');
}
?>