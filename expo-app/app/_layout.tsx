import { DarkTheme, DefaultTheme, ThemeProvider } from '@react-navigation/native';
import { useFonts } from 'expo-font';
import { Stack, useRouter } from 'expo-router';
import { StatusBar } from 'expo-status-bar';
import React, { useState, useEffect } from 'react';
import {
  Modal,
  StyleSheet,
  TouchableOpacity,
  Linking,
  Alert,
} from 'react-native';
import { LinearGradient } from 'expo-linear-gradient';
import Constants from 'expo-constants';
import * as Notifications from 'expo-notifications';
import { ThemedText } from '@/components/ThemedText';
import { ThemedView } from '@/components/ThemedView';
import 'react-native-reanimated';

import { useColorScheme } from '@/hooks/useColorScheme';

export default function RootLayout() {
  const colorScheme = useColorScheme();
  const router = useRouter();
  const [loaded] = useFonts({
    SpaceMono: require('../assets/fonts/SpaceMono-Regular.ttf'),
  });
  const [showVersionModal, setShowVersionModal] = useState(false);
  const [versionMessage, setVersionMessage] = useState('');

  useEffect(() => {
    checkAppVersion();
    const subscription = setupNotificationHandlers();
    
    // Clean up subscription when component unmounts
    return () => subscription.remove();
  }, []);

  const setupNotificationHandlers = () => {
    // Handle notification clicks when app is opened from notification
    const responseSubscription = Notifications.addNotificationResponseReceivedListener(response => {
      const data = response.notification.request.content.data;
      console.log('Notification clicked with data:', data);
      
      handleNotificationData(data);
    });

    // Handle notifications received while app is in foreground
    const receivedSubscription = Notifications.addNotificationReceivedListener(notification => {
      console.log('Notification received in foreground:', notification);
    });

    // Clean up subscriptions when component unmounts
    return {
      remove: () => {
        responseSubscription.remove();
        receivedSubscription.remove();
      }
    };
  };

  const handleNotificationData = (data: any) => {
    // If notification has URL data, open it
    if (data.url && data.url !== 'https://www.contractwekker.nl') {
      // Show alert to user asking if they want to open the link or view contracts
      Alert.alert(
        '⏰ Contractwekker',
        'De contractwekker is zojuist afgegaan! Wil je een nieuw contract vergelijken of al je contracten bekijken?',
        [
          {
            text: 'Mijn contracten',
            onPress: () => router.push('/(tabs)/settings' as any),
          },
          {
            text: 'Vergelijken',
            onPress: () => {
              Linking.openURL(data.url as string).catch(error => {
                console.error('Failed to open deeplink:', error);
                // Silently fallback to settings page instead of showing error
                router.push('/(tabs)/settings' as any);
              });
            },
          },
        ]
      );
    } else {
      // Navigate to settings page to show user's contracts
      router.push('/(tabs)/settings' as any);
    }
  };

  const checkAppVersion = async () => {
    try {
      const currentVersion = Constants.expoConfig?.version || '0.0.0';
      const apiUrl = Constants.expoConfig?.extra?.apiUrl || 'http://contractwekker.test/api.php';
      
      const response = await fetch(`${apiUrl}?action=check_version&version=${currentVersion}`);
      const data = await response.json();
      
      if (!data.is_supported) {
        setVersionMessage(data.message);
        setShowVersionModal(true);
      }
    } catch (error) {
      console.error('Version check failed:', error);
      // Don't block app if version check fails
    }
  };

  const handleDownloadUpdate = () => {
    Linking.openURL('https://www.contractwekker.nl').catch(() => {
      Alert.alert('Error', 'Kon website niet openen. Ga handmatig naar contractwekker.nl');
    });
  };

  if (!loaded) {
    // Async font loading only occurs in development.
    return null;
  }

  return (
    <ThemeProvider value={colorScheme === 'dark' ? DarkTheme : DefaultTheme}>
      <Stack>
        <Stack.Screen name="(tabs)" options={{ headerShown: false }} />
        <Stack.Screen name="+not-found" />
      </Stack>
      <StatusBar style="auto" />
      
      <Modal
        visible={showVersionModal}
        animationType="fade"
        transparent={false}
        onRequestClose={() => {}} // Prevent closing with back button
      >
        <LinearGradient
          colors={['#4facfe', '#00f2fe']}
          start={{x: 0, y: 0}}
          end={{x: 1, y: 1}}
          style={styles.modalContainer}
        >
          <ThemedView style={styles.modalContent}>
            <ThemedText style={styles.modalIcon}>⚠️</ThemedText>
            <ThemedText style={styles.modalTitle}>App update vereist</ThemedText>
            <ThemedText style={styles.modalText}>
              Je app-versie is te oud en wordt niet meer ondersteund.
            </ThemedText>
            <ThemedText style={styles.modalSubtext}>
              {versionMessage}
            </ThemedText>
            <ThemedText style={styles.modalSubtext}>
              Bezoek onze website om de nieuwste versie te downloaden.
            </ThemedText>
            <TouchableOpacity style={styles.downloadButton} onPress={handleDownloadUpdate}>
              <ThemedText style={styles.downloadButtonText}>
                🌐 Ga naar contractwekker.nl
              </ThemedText>
            </TouchableOpacity>
          </ThemedView>
        </LinearGradient>
      </Modal>
    </ThemeProvider>
  );
}

const styles = StyleSheet.create({
  modalContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    padding: 20,
  },
  modalContent: {
    backgroundColor: 'white',
    borderRadius: 20,
    padding: 40,
    alignItems: 'center',
    maxWidth: 400,
    width: '100%',
    shadowColor: '#000',
    shadowOffset: {
      width: 0,
      height: 8,
    },
    shadowOpacity: 0.3,
    shadowRadius: 16,
    elevation: 16,
  },
  modalIcon: {
    fontSize: 80,
    marginBottom: 24,
  },
  modalTitle: {
    fontSize: 28,
    fontWeight: 'bold',
    color: '#dc3545',
    textAlign: 'center',
    marginBottom: 16,
  },
  modalText: {
    fontSize: 16,
    color: '#666',
    textAlign: 'center',
    marginBottom: 12,
    lineHeight: 24,
  },
  modalSubtext: {
    fontSize: 14,
    color: '#999',
    textAlign: 'center',
    lineHeight: 20,
    marginBottom: 30,
  },
  downloadButton: {
    backgroundColor: '#28a745',
    paddingVertical: 16,
    paddingHorizontal: 32,
    borderRadius: 12,
    shadowColor: '#000',
    shadowOffset: {
      width: 0,
      height: 4,
    },
    shadowOpacity: 0.2,
    shadowRadius: 6,
    elevation: 6,
  },
  downloadButtonText: {
    color: 'white',
    fontSize: 18,
    fontWeight: '600',
    textAlign: 'center',
  },
});
