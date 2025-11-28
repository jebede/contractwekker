import React, { useState, useEffect, useCallback } from 'react';
import {
  StyleSheet,
  ScrollView,
  TouchableOpacity,
  ActivityIndicator,
  RefreshControl,
  SafeAreaView,
  Alert,
  Modal,
  View,
} from 'react-native';
import { LinearGradient } from 'expo-linear-gradient';
import { ThemedText } from '@/components/ThemedText';
import { ThemedView } from '@/components/ThemedView';
import { WebView } from 'react-native-webview';
import { XMLParser } from 'fast-xml-parser';

interface BlogPost {
  title: string;
  link: string;
  contentSnippet?: string;
  description?: string;
  pubDate?: string;
  content?: string; // Full HTML content
}

export default function BlogsScreen() {
  const [posts, setPosts] = useState<BlogPost[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [selectedPost, setSelectedPost] = useState<BlogPost | null>(null);
  const [webViewLoading, setWebViewLoading] = useState(false);

  const fetchBlogs = useCallback(async () => {
    try {
      const rssUrl = 'https://contractwekker.nl/rss.xml';
      
      // Fetch RSS feed as text
      const response = await fetch(rssUrl);
      const xmlText = await response.text();
      
      // Parse the RSS XML
      const parser = new XMLParser({
        ignoreAttributes: false,
        attributeNamePrefix: '@_',
        textNodeName: '#text',
        parseAttributeValue: false,
        parseTagValue: true,
        trimValues: true,
        ignoreNameSpace: false,
        parseTrueNumberOnly: false,
        arrayMode: false,
      });
      const result = parser.parse(xmlText);
      
      // Extract items from RSS feed
      const items = result.rss?.channel?.item || [];
      
      // Ensure items is an array
      const itemsArray = Array.isArray(items) ? items : [items].filter(Boolean);
      
      const blogPosts: BlogPost[] = itemsArray.map((item: any) => {
        // Extract title, link, description, pubDate, and full content
        // fast-xml-parser returns values directly, not in arrays
        const title = item.title || '';
        const link = item.link || '';
        const description = item.description || '';
        const pubDate = item.pubDate || '';
        
        // Extract full HTML content from content:encoded
        // Try different possible field names (namespace handling varies)
        const fullContent = 
          item['content:encoded'] || 
          item['content:encoded']?.['#text'] ||
          item.content?.encoded ||
          item.content ||
          '';
        
        // Clean up HTML tags from description for excerpt
        const cleanDescription = description
          .replace(/<[^>]*>/g, '') // Remove HTML tags
          .replace(/&nbsp;/g, ' ')
          .replace(/&amp;/g, '&')
          .replace(/&lt;/g, '<')
          .replace(/&gt;/g, '>')
          .replace(/&quot;/g, '"')
          .replace(/&#39;/g, "'")
          .trim();
        
        return {
          title: String(title).trim(),
          link: String(link).trim(),
          description: cleanDescription,
          contentSnippet: cleanDescription,
          pubDate: String(pubDate).trim(),
          content: String(fullContent).trim(),
        };
      });
      
      setPosts(blogPosts);
    } catch (error) {
      console.error('Failed to fetch blogs:', error);
      Alert.alert('Fout', 'Kon blog artikelen niet laden');
      setPosts([]);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, []);

  useEffect(() => {
    fetchBlogs();
  }, [fetchBlogs]);

  const onRefresh = useCallback(() => {
    setRefreshing(true);
    fetchBlogs();
  }, [fetchBlogs]);

  const handlePostPress = (post: BlogPost) => {
    setSelectedPost(post);
  };

  const closeModal = () => {
    setSelectedPost(null);
  };

  const formatDate = (dateString?: string) => {
    if (!dateString) return '';
    try {
      const date = new Date(dateString);
      return date.toLocaleDateString('nl-NL', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
      });
    } catch {
      return '';
    }
  };

  if (loading && !refreshing) {
    return (
      <LinearGradient
        colors={['#4facfe', '#00f2fe']}
        start={{x: 0, y: 0}}
        end={{x: 1, y: 1}}
        style={styles.container}
      >
        <SafeAreaView style={styles.centerContainer}>
          <ActivityIndicator size="large" color="#ffffff" />
          <ThemedText style={styles.loadingText}>Laden...</ThemedText>
        </SafeAreaView>
      </LinearGradient>
    );
  }

  return (
    <LinearGradient
      colors={['#4facfe', '#00f2fe']}
      start={{x: 0, y: 0}}
      end={{x: 1, y: 1}}
      style={styles.container}
    >
      <SafeAreaView style={styles.flex}>
        <ScrollView 
          style={styles.flex}
          refreshControl={
            <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
          }
        >
          <LinearGradient
            colors={['#4facfe', '#00f2fe']}
            start={{x: 0, y: 0}}
            end={{x: 1, y: 1}}
            style={styles.headerContainer}
          >
            <ThemedText type="title" style={styles.title}>📝 Blogs</ThemedText>
            <ThemedText style={styles.subtitle}>
              Lees onze laatste artikelen
            </ThemedText>
          </LinearGradient>

          <ThemedView style={styles.contentContainer}>
            {posts.length === 0 ? (
              <ThemedView style={styles.emptyContainer}>
                <ThemedText style={styles.emptyText}>
                  Geen blog artikelen gevonden.
                </ThemedText>
              </ThemedView>
            ) : (
              posts.map((post, index) => (
                <TouchableOpacity
                  key={index}
                  style={styles.postCard}
                  onPress={() => handlePostPress(post)}
                  activeOpacity={0.7}
                >
                  <ThemedText style={styles.postTitle}>
                    {post.title}
                  </ThemedText>
                  {post.pubDate && (
                    <ThemedText style={styles.postDate}>
                      {formatDate(post.pubDate)}
                    </ThemedText>
                  )}
                  {(post.description || post.contentSnippet) && (
                    <ThemedText style={styles.postExcerpt} numberOfLines={3}>
                      {post.description || post.contentSnippet}
                    </ThemedText>
                  )}
                  <ThemedText style={styles.readMore}>
                    Lees meer →
                  </ThemedText>
                </TouchableOpacity>
              ))
            )}
          </ThemedView>
        </ScrollView>
      </SafeAreaView>

      {/* Blog Detail Modal */}
      <Modal
        visible={selectedPost !== null}
        animationType="slide"
        onRequestClose={closeModal}
        presentationStyle="pageSheet"
      >
        <SafeAreaView style={styles.modalContainer}>
          <View style={styles.modalHeader}>
            <TouchableOpacity onPress={closeModal} style={styles.closeButton}>
              <ThemedText style={styles.closeButtonText}>✕ Sluiten</ThemedText>
            </TouchableOpacity>
            {selectedPost && (
              <ThemedText style={styles.modalTitle} numberOfLines={2}>
                {selectedPost.title}
              </ThemedText>
            )}
          </View>
          {selectedPost && (
            <WebView
              source={{
                html: `
                  <!DOCTYPE html>
                  <html>
                    <head>
                      <meta name="viewport" content="width=device-width, initial-scale=1.0">
                      <style>
                        body {
                          font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
                          padding: 20px;
                          line-height: 1.6;
                          color: #333;
                          max-width: 100%;
                          word-wrap: break-word;
                        }
                        img {
                          max-width: 100%;
                          height: auto;
                          border-radius: 8px;
                          margin: 16px 0;
                        }
                        h1, h2, h3, h4, h5, h6 {
                          margin-top: 24px;
                          margin-bottom: 12px;
                          color: #333;
                        }
                        p {
                          margin-bottom: 16px;
                        }
                        a {
                          color: #4facfe;
                          text-decoration: none;
                        }
                        a:active {
                          color: #00f2fe;
                        }
                        blockquote {
                          border-left: 4px solid #4facfe;
                          padding-left: 16px;
                          margin: 16px 0;
                          color: #666;
                          font-style: italic;
                        }
                        code {
                          background-color: #f5f5f5;
                          padding: 2px 6px;
                          border-radius: 4px;
                          font-family: 'Courier New', monospace;
                        }
                        pre {
                          background-color: #f5f5f5;
                          padding: 16px;
                          border-radius: 8px;
                          overflow-x: auto;
                        }
                      </style>
                    </head>
                    <body>
                      ${selectedPost.content || selectedPost.description || 'Geen inhoud beschikbaar.'}
                    </body>
                  </html>
                `,
              }}
              style={styles.webView}
              onLoadStart={() => setWebViewLoading(true)}
              onLoadEnd={() => setWebViewLoading(false)}
              javaScriptEnabled={true}
              domStorageEnabled={true}
              startInLoadingState={true}
              scalesPageToFit={true}
            />
          )}
          {webViewLoading && (
            <View style={styles.webViewLoading}>
              <ActivityIndicator size="large" color="#4facfe" />
            </View>
          )}
        </SafeAreaView>
      </Modal>
    </LinearGradient>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
  },
  flex: {
    flex: 1,
  },
  centerContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
  },
  loadingText: {
    marginTop: 16,
    fontSize: 16,
    color: '#ffffff',
  },
  headerContainer: {
    padding: 40,
    alignItems: 'center',
  },
  title: {
    fontSize: 32,
    fontWeight: 'bold',
    color: 'white',
    textAlign: 'center',
    marginBottom: 10,
  },
  subtitle: {
    fontSize: 18,
    color: 'white',
    textAlign: 'center',
    opacity: 0.9,
  },
  contentContainer: {
    padding: 20,
  },
  emptyContainer: {
    alignItems: 'center',
    padding: 40,
    backgroundColor: '#f8f9fa',
    borderRadius: 12,
    borderWidth: 2,
    borderColor: '#e1e5e9',
  },
  emptyText: {
    fontSize: 16,
    fontWeight: '600',
    color: '#666',
    textAlign: 'center',
  },
  postCard: {
    backgroundColor: 'white',
    borderRadius: 12,
    padding: 16,
    marginBottom: 12,
    borderWidth: 1,
    borderColor: '#e1e5e9',
    shadowColor: '#000',
    shadowOffset: {
      width: 0,
      height: 1,
    },
    shadowOpacity: 0.1,
    shadowRadius: 2,
    elevation: 2,
  },
  postTitle: {
    fontSize: 18,
    fontWeight: '700',
    color: '#333',
    marginBottom: 8,
  },
  postDate: {
    fontSize: 12,
    color: '#999',
    marginBottom: 12,
  },
  postExcerpt: {
    fontSize: 14,
    color: '#666',
    lineHeight: 20,
    marginBottom: 12,
  },
  readMore: {
    fontSize: 14,
    color: '#4facfe',
    fontWeight: '600',
  },
  modalContainer: {
    flex: 1,
    backgroundColor: '#ffffff',
  },
  modalHeader: {
    padding: 16,
    borderBottomWidth: 1,
    borderBottomColor: '#e1e5e9',
    backgroundColor: '#ffffff',
  },
  closeButton: {
    paddingVertical: 8,
    paddingHorizontal: 12,
    alignSelf: 'flex-start',
    marginBottom: 8,
  },
  closeButtonText: {
    fontSize: 16,
    color: '#4facfe',
    fontWeight: '600',
  },
  modalTitle: {
    fontSize: 20,
    fontWeight: '700',
    color: '#333',
    marginTop: 8,
  },
  webView: {
    flex: 1,
    backgroundColor: '#ffffff',
  },
  webViewLoading: {
    position: 'absolute',
    top: 0,
    left: 0,
    right: 0,
    bottom: 0,
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: 'rgba(255, 255, 255, 0.9)',
  },
});

