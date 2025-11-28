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
      });
      const result = parser.parse(xmlText);
      
      // Extract items from RSS feed
      const items = result.rss?.channel?.item || [];
      
      // Ensure items is an array
      const itemsArray = Array.isArray(items) ? items : [items].filter(Boolean);
      
      const blogPosts: BlogPost[] = itemsArray.map((item: any) => {
        // Extract title, link, description, and pubDate
        // fast-xml-parser returns values directly, not in arrays
        const title = item.title || '';
        const link = item.link || '';
        const description = item.description || '';
        const pubDate = item.pubDate || '';
        
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

  const handlePostPress = async (url: string) => {
    try {
      await WebBrowser.openBrowserAsync(url);
    } catch (error) {
      console.error('Failed to open URL:', error);
      Alert.alert('Fout', 'Kon de blog niet openen');
    }
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
                  onPress={() => handlePostPress(post.link)}
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
});

