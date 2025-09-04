import React, { useState, useEffect, useCallback } from 'react';
import {
  StyleSheet,
  ScrollView,
  Alert,
  TouchableOpacity,
  ActivityIndicator,
  RefreshControl,
  SafeAreaView,
  Linking,
} from 'react-native';
import { LinearGradient } from 'expo-linear-gradient';
import { ThemedText } from '@/components/ThemedText';
import { ThemedView } from '@/components/ThemedView';
import Constants from 'expo-constants';
import * as Notifications from 'expo-notifications';
import AsyncStorage from '@react-native-async-storage/async-storage';

interface Alert {
  id: number;
  product_id: number | null;
  product_name: string | null;
  custom_product_name: string | null;
  alert_period: string;
  first_alert_date: string;
  next_alert_date: string;
  is_periodic: boolean;
  created_at: string;
  deeplink: string | null;
}

export default function SettingsScreen() {
  const [alerts, setAlerts] = useState<Alert[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [pushToken, setPushToken] = useState<string | null>(null);

  useEffect(() => {
    initializePushToken();
  }, []);

  const initializePushToken = async () => {
    try {
      // Try to get stored push token first
      const storedToken = await AsyncStorage.getItem('contractwekker_push_token') || await AsyncStorage.getItem('pushToken');
      if (storedToken) {
        setPushToken(storedToken);
        loadAlerts(storedToken);
        return;
      }

      // Try to get current push token
      const { status: existingStatus } = await Notifications.getPermissionsAsync();
      
      if (existingStatus === 'granted') {
        try {
          const projectId = Constants?.expoConfig?.extra?.eas?.projectId ?? Constants?.easConfig?.projectId;
          if (projectId) {
            const tokenData = await Notifications.getExpoPushTokenAsync({ projectId });
            const token = tokenData.data;
            setPushToken(token);
            await AsyncStorage.setItem('contractwekker_push_token', token);
            await AsyncStorage.setItem('pushToken', token);
            loadAlerts(token);
            return;
          }
        } catch (e) {
          console.log('Error getting push token:', e);
        }
      }
      
      // Fallback: generate development token
      const devToken = `development-token-${Date.now()}`;
      setPushToken(devToken);
      await AsyncStorage.setItem('contractwekker_push_token', devToken);
      await AsyncStorage.setItem('pushToken', devToken);
      loadAlerts(devToken);
    } catch (error) {
      console.error('Error initializing push token:', error);
      setLoading(false);
    }
  };

  const loadAlerts = async (token: string) => {
    try {
      setLoading(true);
      const apiUrl = Constants.expoConfig?.extra?.apiUrl || 'http://contractwekker.test/api.php';
      console.log('Loading alerts with token:', token);
      const response = await fetch(`${apiUrl}?action=get_alerts&push_token=${encodeURIComponent(token)}`);
      console.log('Response status:', response.status);
      const data = await response.json();
      console.log('Alerts response:', data);
      
      if (Array.isArray(data)) {
        setAlerts(data);
        console.log('Loaded', data.length, 'alerts');
      } else {
        console.error('Invalid alerts data:', data);
        if (data.error) {
          console.error('API Error:', data.error);
        }
        setAlerts([]);
      }
    } catch (error) {
      console.error('Failed to load alerts:', error);
      Alert.alert('Fout', 'Kon waarschuwingen niet laden');
      setAlerts([]);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  const onRefresh = useCallback(() => {
    if (pushToken) {
      setRefreshing(true);
      loadAlerts(pushToken);
    }
  }, [pushToken]);

  const deleteAlert = async (alertId: number) => {
    if (!pushToken) {
      Alert.alert('Fout', 'Geen push token beschikbaar');
      return;
    }

    Alert.alert(
      'Waarschuwing verwijderen',
      'Weet je zeker dat je deze waarschuwing wilt verwijderen?',
      [
        { text: 'Annuleren', style: 'cancel' },
        {
          text: 'Verwijderen',
          style: 'destructive',
          onPress: async () => {
            try {
              const apiUrl = Constants.expoConfig?.extra?.apiUrl || 'http://contractwekker.test/api.php';
              const response = await fetch(`${apiUrl}?action=delete_alert`, {
                method: 'DELETE',
                headers: {
                  'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                  id: alertId,
                  push_token: pushToken,
                }),
              });

              const data = await response.json();
              
              if (data.success) {
                setAlerts(alerts.filter(alert => alert.id !== alertId));
                Alert.alert('Succes', 'Waarschuwing verwijderd');
              } else {
                Alert.alert('Fout', data.error || 'Kon waarschuwing niet verwijderen');
              }
            } catch (error) {
              console.error('Delete error:', error);
              Alert.alert('Fout', 'Er is een fout opgetreden bij het verwijderen');
            }
          },
        },
      ]
    );
  };

  const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('nl-NL', {
      year: 'numeric',
      month: 'long',
      day: 'numeric',
    });
  };

  const formatPeriod = (period: string) => {
    const periods: { [key: string]: string } = {
      '1_month': '1 maand',
      '3_months': '3 maanden',
      '1_year': '1 jaar',
      '2_years': '2 jaar',
      '3_years': '3 jaar',
      'custom': 'Aangepast',
    };
    return periods[period] || period;
  };

  const isContractExpired = (nextAlertDate: string) => {
    const today = new Date();
    const alertDate = new Date(nextAlertDate);
    return alertDate <= today;
  };

  const shouldShowDeeplinkButton = (alert: Alert) => {
    // Don't show for "Anders" products (ID 11 or name "Anders") or if no deeplink
    if (alert.product_id === 11 || alert.product_name === 'Anders' || !alert.deeplink || alert.deeplink === '#') {
      return false;
    }
    return true;
  };

  const getDeeplinkButtonText = (alert: Alert) => {
    const productName = alert.custom_product_name || alert.product_name || 'contract';
    const isExpired = isContractExpired(alert.next_alert_date);
    
    if (isExpired) {
      return `Vergelijk ${productName}`;
    } else {
      return 'Alvast vergelijken?';
    }
  };

  const handleDeeplinkPress = async (deeplink: string) => {
    try {
      await Linking.openURL(deeplink);
    } catch (error) {
      Alert.alert('Fout', 'Kon de link niet openen');
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
        <ThemedText type="title" style={styles.title}>📋 Abonnementen</ThemedText>
        <ThemedText style={styles.subtitle}>
          Beheer je contractwaarschuwingen
        </ThemedText>
      </LinearGradient>

      <ThemedView style={styles.contentContainer}>

        <ThemedView style={styles.sectionHeader}>
          <ThemedText type="subtitle" style={styles.sectionTitle}>
            Pushnotificatie-waarschuwingen ({alerts.length})
          </ThemedText>
        </ThemedView>

        {alerts.length === 0 ? (
          <ThemedView style={styles.emptyContainer}>
            <ThemedText style={styles.emptyText}>
              Je hebt nog geen pushnotificatie-waarschuwingen ingesteld.
            </ThemedText>
            <ThemedText style={styles.emptySubtext}>
              Ga naar het hoofdscherm om een nieuwe wekker in te stellen en kies voor pushnotificaties.
            </ThemedText>
          </ThemedView>
        ) : (
          alerts.map((alert) => (
            <ThemedView key={alert.id} style={styles.alertCard}>
              <ThemedView style={styles.alertHeader}>
                <ThemedText style={styles.alertTitle}>
                  {alert.custom_product_name || alert.product_name || 'Onbekend contract'}
                </ThemedText>
                <TouchableOpacity
                  style={styles.deleteButton}
                  onPress={() => deleteAlert(alert.id)}
                >
                  <ThemedText style={styles.deleteButtonText}>🗑️</ThemedText>
                </TouchableOpacity>
              </ThemedView>

              <ThemedView style={styles.alertDetails}>
                <ThemedText style={styles.alertDetail}>
                  📅 Eerste waarschuwing: {formatDate(alert.first_alert_date)}
                </ThemedText>
                <ThemedText style={styles.alertDetail}>
                  🔔 Volgende waarschuwing: {formatDate(alert.next_alert_date)}
                </ThemedText>
                <ThemedText style={styles.alertDetail}>
                  ⏰ Periode: {formatPeriod(alert.alert_period)}
                </ThemedText>
                <ThemedText style={styles.alertDetail}>
                  🔄 Herhaling: {alert.is_periodic ? 'Aan' : 'Uit'}
                </ThemedText>
                <ThemedText style={styles.alertDetail}>
                  📱 Notificatie: Push notificatie
                </ThemedText>
                <ThemedText style={styles.alertDetail}>
                  📝 Aangemaakt: {formatDate(alert.created_at)}
                </ThemedText>
              </ThemedView>

              {shouldShowDeeplinkButton(alert) && (
                <TouchableOpacity 
                  style={styles.deeplinkButton} 
                  onPress={() => handleDeeplinkPress(alert.deeplink!)}
                >
                  <ThemedText style={styles.deeplinkButtonText}>
                    🔗 {getDeeplinkButtonText(alert)}
                  </ThemedText>
                </TouchableOpacity>
              )}
            </ThemedView>
          ))
        )}

        <ThemedView style={styles.infoContainer}>
          <ThemedText style={styles.infoTitle}>ℹ️ Over push notificaties</ThemedText>
          <ThemedText style={styles.infoText}>
            Push notificaties worden verzonden naar dit apparaat. Als je de app verwijdert of herinstallert, 
            zullen je waarschuwingen niet meer werken en moet je ze opnieuw instellen.
          </ThemedText>
        </ThemedView>
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
    color: '#666',
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
  sectionHeader: {
    marginBottom: 15,
  },
  sectionTitle: {
    fontSize: 20,
    fontWeight: '700',
    color: '#333',
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
    marginBottom: 8,
  },
  emptySubtext: {
    fontSize: 14,
    color: '#999',
    textAlign: 'center',
  },
  alertCard: {
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
  alertHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 12,
  },
  alertTitle: {
    fontSize: 18,
    fontWeight: '700',
    color: '#333',
    flex: 1,
  },
  deleteButton: {
    padding: 8,
    borderRadius: 8,
    backgroundColor: '#ffebee',
  },
  deleteButtonText: {
    fontSize: 18,
  },
  alertDetails: {
    gap: 6,
  },
  alertDetail: {
    fontSize: 14,
    color: '#666',
    lineHeight: 20,
  },
  infoContainer: {
    backgroundColor: '#e3f2fd',
    padding: 16,
    borderRadius: 12,
    marginTop: 20,
  },
  infoTitle: {
    fontSize: 16,
    fontWeight: '600',
    color: '#1976d2',
    marginBottom: 8,
  },
  infoText: {
    fontSize: 14,
    color: '#1976d2',
    lineHeight: 20,
  },
  deeplinkButton: {
    backgroundColor: '#4facfe',
    paddingVertical: 12,
    paddingHorizontal: 16,
    borderRadius: 8,
    marginTop: 12,
    alignItems: 'center',
    shadowColor: '#000',
    shadowOffset: {
      width: 0,
      height: 1,
    },
    shadowOpacity: 0.1,
    shadowRadius: 2,
    elevation: 2,
  },
  deeplinkButtonText: {
    color: 'white',
    fontSize: 14,
    fontWeight: '600',
  },
});