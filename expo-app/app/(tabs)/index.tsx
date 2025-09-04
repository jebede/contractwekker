import React, { useState, useEffect } from 'react';
import {
  StyleSheet,
  ScrollView,
  Alert,
  Platform,
  KeyboardAvoidingView,
  TextInput,
  TouchableOpacity,
  SafeAreaView,
  Modal,
  View,
} from 'react-native';
import { LinearGradient } from 'expo-linear-gradient';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { ThemedText } from '@/components/ThemedText';
import { ThemedView } from '@/components/ThemedView';
import * as Notifications from 'expo-notifications';
import Constants from 'expo-constants';
import { Picker } from '@react-native-picker/picker';
import DateTimePicker from '@react-native-community/datetimepicker';

// Configure notification handling
Notifications.setNotificationHandler({
  handleNotification: async () => ({
    shouldShowAlert: true,
    shouldPlaySound: false,
    shouldSetBadge: false,
  }),
});

interface Product {
  id: string;
  name: string;
}

// Helper functions for date formatting
function formatDateForDisplay(dateString: string): string {
  if (!dateString) return '';
  const parts = dateString.split('-');
  if (parts.length === 3) {
    return `${parts[2]}-${parts[1]}-${parts[0]}`; // Convert YYYY-MM-DD to DD-MM-YYYY
  }
  return dateString;
}

function formatDateForAPI(date: Date): string {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

function getTodayString(): string {
  const today = new Date();
  return formatDateForAPI(today);
}

async function registerForPushNotificationsAsync() {
  let token;

  if (Platform.OS === 'android') {
    Notifications.setNotificationChannelAsync('default', {
      name: 'default',
      importance: Notifications.AndroidImportance.MAX,
      vibrationPattern: [0, 250, 250, 250],
      lightColor: '#FF231F7C',
    });
  }

  const { status: existingStatus } = await Notifications.getPermissionsAsync();
  let finalStatus = existingStatus;

  if (existingStatus !== 'granted') {
    const { status } = await Notifications.requestPermissionsAsync();
    finalStatus = status;
  }

  if (finalStatus !== 'granted') {
    Alert.alert('Fout', 'Push notificatie toestemming is vereist voor deze functie!');
    return null;
  }

  try {
    const projectId = Constants?.expoConfig?.extra?.eas?.projectId ?? Constants?.easConfig?.projectId;
    if (!projectId) {
      throw new Error('Project ID not found');
    }
    token = (await Notifications.getExpoPushTokenAsync({ projectId })).data;
  } catch (e) {
    console.log('Error getting push token:', e);
    token = `development-token-${Date.now()}`;
  }

  return token;
}

function calculateSmartEarlyReminderDays(alertPeriod: string, endDate: string): number {
  // Map periods to approximate days
  const periodToDays: { [key: string]: number } = {
    '1_month': 30,
    '3_months': 90,
    '1_year': 365,
    '2_years': 730,
    '3_years': 1095
  };
  
  if (alertPeriod === 'custom' && endDate) {
    // Custom alerts are set 1 month before end date, so calculate from that
    const endDateObj = new Date(endDate);
    const alertDate = new Date(endDate);
    alertDate.setMonth(alertDate.getMonth() - 1);
    
    const now = new Date();
    let daysUntilAlert = Math.floor((alertDate.getTime() - now.getTime()) / (1000 * 60 * 60 * 24));
    
    // If alert date would be in the past or very soon, calculate from today to end date
    if (daysUntilAlert <= 0) {
      // Alert would fire immediately, so calculate days from now to end date
      daysUntilAlert = Math.floor((endDateObj.getTime() - now.getTime()) / (1000 * 60 * 60 * 24));
      
      if (daysUntilAlert > 0 && daysUntilAlert < 90) {
        // Use half of the remaining period
        return Math.max(1, Math.floor(daysUntilAlert / 2));
      } else if (daysUntilAlert > 0) {
        // More than 90 days until end date, but alert is immediate
        // Use a reasonable default for immediate alerts
        return Math.min(30, daysUntilAlert - 1);
      }
    } else if (daysUntilAlert < 90) {
      // Normal case: alert is in the future but less than 90 days
      return Math.max(1, Math.floor(daysUntilAlert / 2));
    }
  } else if (alertPeriod !== 'custom') {
    const periodDays = periodToDays[alertPeriod];
    if (periodDays && periodDays < 120) {
      // For periods less than 120 days (1 month, 3 months), use half the period
      return Math.floor(periodDays / 2);
    }
  }
  
  // For longer periods (1 year+), keep default of 60 days
  return 60;
}

function getSummaryText(alertPeriod: string, isPeriodic: boolean, disableEarlyReminder: boolean, earlyReminderDays: number): string {
  let frequency = '';
  
  if (isPeriodic) {
    switch (alertPeriod) {
      case '1_month':
        frequency = 'elke maand';
        break;
      case '3_months':
        frequency = 'elke 3 maanden';
        break;
      case '1_year':
        frequency = 'elk jaar';
        break;
      case '2_years':
        frequency = 'elke 2 jaar';
        break;
      case '3_years':
        frequency = 'elke 3 jaar';
        break;
      case 'custom':
        frequency = 'eenmalig op de einddatum';
        break;
      default:
        frequency = 'elk jaar';
    }
  } else {
    frequency = 'eenmalig';
  }

  let earlyText = '';
  if (!disableEarlyReminder) {
    earlyText = ` Je ontvangt ${earlyReminderDays} dagen van tevoren een extra herinnering.`;
  }

  return `We sturen je ${frequency} een contractwekker.${earlyText}`;
}

export default function HomeScreen() {
  const [products, setProducts] = useState<Product[]>([]);
  const [selectedProduct, setSelectedProduct] = useState('');
  const [customProductName, setCustomProductName] = useState('');
  const [alertPeriod, setAlertPeriod] = useState('1_year');
  const [startDate, setStartDate] = useState(getTodayString());
  const [endDate, setEndDate] = useState('');
  const [isPeriodic, setIsPeriodic] = useState(true);
  const [disableEarlyReminder, setDisableEarlyReminder] = useState(false);
  const [earlyReminderDays, setEarlyReminderDays] = useState(60);
  const [earlyReminderDaysText, setEarlyReminderDaysText] = useState('60');
  const [userOverrideEarlyDays, setUserOverrideEarlyDays] = useState(false);
  const [showSettingsModal, setShowSettingsModal] = useState(false);
  const [email, setEmail] = useState('');
  const [showStartDatePicker, setShowStartDatePicker] = useState(false);
  const [showEndDatePicker, setShowEndDatePicker] = useState(false);
  const [tempStartDate, setTempStartDate] = useState(getTodayString());
  const [tempEndDate, setTempEndDate] = useState('');

  useEffect(() => {
    loadProducts();
    loadSavedEmail();
  }, []);
  
  const loadSavedEmail = async () => {
    try {
      const savedEmail = await AsyncStorage.getItem('contractwekker_email');
      if (savedEmail) {
        setEmail(savedEmail);
      }
    } catch (error) {
      console.error('Failed to load saved email:', error);
    }
  };
  
  const saveEmail = async (emailValue: string) => {
    try {
      if (emailValue) {
        await AsyncStorage.setItem('contractwekker_email', emailValue);
      }
    } catch (error) {
      console.error('Failed to save email:', error);
    }
  };

  const resetForm = () => {
    setSelectedProduct('');
    setCustomProductName('');
    setAlertPeriod('1_year');
    setStartDate(getTodayString());
    setEndDate('');
    setIsPeriodic(true);
    setDisableEarlyReminder(false);
    setEarlyReminderDays(60);
    setEarlyReminderDaysText('60');
    setUserOverrideEarlyDays(false);
    setEmail('');
  };
  
  // Update summary text and early reminder days when alert period changes
  useEffect(() => {
    // Force custom alerts to be non-periodic
    if (alertPeriod === 'custom') {
      setIsPeriodic(false);
    }
    
    // Calculate smart default for early reminder days if user hasn't manually overridden
    if (!userOverrideEarlyDays) {
      const smartDays = calculateSmartEarlyReminderDays(alertPeriod, endDate);
      setEarlyReminderDays(smartDays);
      setEarlyReminderDaysText(smartDays.toString());
    }
  }, [alertPeriod, endDate, userOverrideEarlyDays]);

  const loadProducts = async () => {
    try {
      const apiUrl = Constants.expoConfig?.extra?.apiUrl || 'http://contractwekker.test/api.php';
      const response = await fetch(`${apiUrl}?action=get_products`);
      const data = await response.json();
      setProducts(data);
    } catch (error) {
      console.error('Failed to load products:', error);
      Alert.alert('Fout', 'Kon producten niet laden');
    }
  };

  const handleSubmit = async () => {
    if (!selectedProduct || !alertPeriod) {
      Alert.alert('Fout', 'Vul alle verplichte velden in');
      return;
    }

    if (selectedProduct === 'other' && !customProductName.trim()) {
      Alert.alert('Fout', 'Vul een naam in voor je contract');
      return;
    }

    if (!startDate) {
      Alert.alert('Fout', 'Vul een begindatum in voor je contract');
      return;
    }

    if (alertPeriod === 'custom' && !endDate) {
      Alert.alert('Fout', 'Vul een einddatum in voor je contract');
      return;
    }

    if (!email.trim()) {
      Alert.alert('Fout', 'Vul een e-mailadres in');
      return;
    }

    try {
      const apiUrl = Constants.expoConfig?.extra?.apiUrl || 'http://contractwekker.test/api.php';
      
      // Get or generate push token for the alert
      const pushToken = await registerForPushNotificationsAsync() || `development-token-${Date.now()}`;
      
      // Store the push token for settings screen
      await AsyncStorage.setItem('contractwekker_push_token', pushToken);
      
      const payload = {
        product_id: selectedProduct,
        custom_product_name: selectedProduct === 'other' ? customProductName : null,
        alert_period: alertPeriod,
        start_date: startDate,
        end_date: alertPeriod === 'custom' ? endDate : null,
        is_periodic: isPeriodic ? 1 : 0,
        send_early_reminder: !disableEarlyReminder,
        early_reminder_days: earlyReminderDays,
        email: email.trim(),
        push_token: pushToken,
      };
      
      const response = await fetch(`${apiUrl}?action=create_alert`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(payload),
      });
      
      const data = await response.json();

      if (data.success) {
        Alert.alert(
          'Succes!', 
          'Je wekker is ingesteld! Je ontvangt zowel e-mail als pushnotificaties op dit apparaat.\n\nLet op: We doen ons best om ervoor te zorgen dat je tijdig bericht ontvangt, maar het kan voorkomen dat berichten niet aankomen (bijv. door spamfilters). Voeg daarom ook noreply@contractwekker.nl toe aan je contacten. Houd daarnaast zelf altijd je contracten in de gaten als extra zekerheid.'
        );
        resetForm();
      } else {
        Alert.alert('Fout', data.error || 'Er is een fout opgetreden');
      }
    } catch (error) {
      console.error('Submit error:', error);
      Alert.alert('Fout', 'Er is een fout opgetreden bij het instellen van de wekker');
    }
  };

  return (
    <LinearGradient
      colors={['#4facfe', '#00f2fe']}
      start={{x: 0, y: 0}}
      end={{x: 1, y: 1}}
      style={styles.container}
    >
      <SafeAreaView style={styles.flex}>
        <KeyboardAvoidingView 
          style={styles.flex} 
          behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
        >
        <ScrollView style={styles.scrollView} contentContainerStyle={styles.scrollContent}>
        <LinearGradient
          colors={['#4facfe', '#00f2fe']}
          start={{x: 0, y: 0}}
          end={{x: 1, y: 1}}
          style={styles.headerContainer}
        >
          <ThemedText type="title" style={styles.title}>⏰ Contractwekker</ThemedText>
          <ThemedText style={styles.subtitle}>
            Vergeet nooit meer je contract op te zeggen of over te stappen
          </ThemedText>
        </LinearGradient>

        <ThemedView style={styles.formContainer}>
          <ThemedView style={styles.formGroup}>
            <ThemedText style={styles.label}>Soort contract</ThemedText>
            <Picker
              selectedValue={selectedProduct}
              style={styles.picker}
              onValueChange={(value) => setSelectedProduct(value)}
            >
              <Picker.Item label="Kies een contracttype..." value="" />
              {products.map((product) => (
                <Picker.Item 
                  key={product.id} 
                  label={product.name} 
                  value={product.name === 'Anders' ? 'other' : product.id} 
                />
              ))}
            </Picker>
          </ThemedView>

          {selectedProduct === 'other' && (
            <ThemedView style={styles.formGroup}>
              <ThemedText style={styles.label}>Naam van je contract</ThemedText>
              <ThemedView style={styles.inputContainer}>
                <TextInput
                  style={styles.input}
                  value={customProductName}
                  onChangeText={setCustomProductName}
                  placeholder="Bijv. Netflix, Spotify, etc."
                  placeholderTextColor="#666"
                />
              </ThemedView>
            </ThemedView>
          )}

          <ThemedView style={styles.formGroup}>
            <ThemedText style={styles.label}>Begindatum contract</ThemedText>
            <TouchableOpacity
              style={styles.inputContainer}
              onPress={() => {
                setTempStartDate(startDate);
                setShowStartDatePicker(true);
              }}
            >
              <ThemedText style={styles.dateText}>
                {formatDateForDisplay(startDate) || 'Selecteer datum'}
              </ThemedText>
            </TouchableOpacity>
          </ThemedView>

          <ThemedView style={styles.formGroup}>
            <ThemedText style={styles.label}>Geef seintje na</ThemedText>
            <Picker
              selectedValue={alertPeriod}
              style={styles.picker}
              onValueChange={(value) => setAlertPeriod(value)}
            >
              <Picker.Item label="1 maand" value="1_month" />
              <Picker.Item label="3 maanden" value="3_months" />
              <Picker.Item label="1 jaar" value="1_year" />
              <Picker.Item label="2 jaar" value="2_years" />
              <Picker.Item label="3 jaar" value="3_years" />
              <Picker.Item label="Anders (geef op)" value="custom" />
            </Picker>
          </ThemedView>

          {alertPeriod === 'custom' && (
            <ThemedView style={styles.formGroup}>
              <ThemedText style={styles.label}>Einddatum contract</ThemedText>
              <TouchableOpacity
                style={styles.inputContainer}
                onPress={() => {
                  setTempEndDate(endDate);
                  setShowEndDatePicker(true);
                }}
              >
                <ThemedText style={styles.dateText}>
                  {formatDateForDisplay(endDate) || 'Selecteer datum'}
                </ThemedText>
              </TouchableOpacity>
            </ThemedView>
          )}

          <ThemedView style={[styles.formGroup, styles.summaryBox]}>
            <ThemedText style={styles.summaryText}>
              {getSummaryText(alertPeriod, isPeriodic, disableEarlyReminder, earlyReminderDays)}
            </ThemedText>
            <TouchableOpacity 
              style={styles.editButton}
              onPress={() => {
                setEarlyReminderDaysText(disableEarlyReminder ? '' : earlyReminderDays.toString());
                setUserOverrideEarlyDays(false); // Reset override flag when opening modal
                setShowSettingsModal(true);
              }}
            >
              <ThemedText style={styles.editButtonText}>✏️ Wijzig</ThemedText>
            </TouchableOpacity>
          </ThemedView>

          <ThemedView style={styles.formGroup}>
            <ThemedText style={styles.label}>E-mailadres</ThemedText>
            <ThemedView style={styles.inputContainer}>
              <TextInput
                style={styles.input}
                value={email}
                onChangeText={(value) => {
                  setEmail(value);
                  saveEmail(value);
                }}
                placeholder="je@email.com"
                placeholderTextColor="#666"
                keyboardType="email-address"
                autoCapitalize="none"
              />
            </ThemedView>
          </ThemedView>

          <TouchableOpacity style={styles.submitButton} onPress={handleSubmit}>
            <ThemedText style={styles.submitButtonText}>
              🔔 Wekker instellen
            </ThemedText>
          </TouchableOpacity>
        </ThemedView>
        </ScrollView>
        </KeyboardAvoidingView>
      </SafeAreaView>

      <Modal
        animationType="slide"
        transparent={true}
        visible={showSettingsModal}
        onRequestClose={() => setShowSettingsModal(false)}
      >
        <View style={styles.modalOverlay}>
          <View style={styles.modalContent}>
            <ThemedText style={styles.modalTitle}>Instellingen aanpassen</ThemedText>
            
            <ThemedView style={styles.modalSection}>
              <ThemedText style={styles.modalSectionTitle}>Herhaling</ThemedText>
              <TouchableOpacity
                style={[
                  styles.modalOption, 
                  isPeriodic && styles.modalOptionSelected,
                  alertPeriod === 'custom' && styles.modalOptionDisabled
                ]}
                onPress={() => {
                  if (alertPeriod !== 'custom') {
                    setIsPeriodic(true);
                  }
                }}
                disabled={alertPeriod === 'custom'}
              >
                <ThemedText style={[
                  styles.modalOptionText, 
                  isPeriodic && styles.modalOptionTextSelected,
                  alertPeriod === 'custom' && styles.modalOptionTextDisabled
                ]}>
                  Periodiek herhalen
                </ThemedText>
              </TouchableOpacity>
              <TouchableOpacity
                style={[
                  styles.modalOption, 
                  !isPeriodic && styles.modalOptionSelected,
                  alertPeriod === 'custom' && styles.modalOptionDisabled
                ]}
                onPress={() => {
                  if (alertPeriod !== 'custom') {
                    setIsPeriodic(false);
                  }
                }}
                disabled={alertPeriod === 'custom'}
              >
                <ThemedText style={[
                  styles.modalOptionText, 
                  !isPeriodic && styles.modalOptionTextSelected,
                  alertPeriod === 'custom' && styles.modalOptionTextDisabled
                ]}>
                  Eenmalig
                </ThemedText>
              </TouchableOpacity>
            </ThemedView>

            <ThemedView style={styles.modalSection}>
              <ThemedText style={styles.modalSectionTitle}>Vroege herinnering</ThemedText>
              <TouchableOpacity
                style={[styles.modalOption, !disableEarlyReminder && styles.modalOptionSelected]}
                onPress={() => setDisableEarlyReminder(false)}
              >
                <ThemedText style={[styles.modalOptionText, !disableEarlyReminder && styles.modalOptionTextSelected]}>
                  Stuur vroege herinnering
                </ThemedText>
              </TouchableOpacity>
              <TouchableOpacity
                style={[styles.modalOption, disableEarlyReminder && styles.modalOptionSelected]}
                onPress={() => setDisableEarlyReminder(true)}
              >
                <ThemedText style={[styles.modalOptionText, disableEarlyReminder && styles.modalOptionTextSelected]}>
                  Geen vroege herinnering
                </ThemedText>
              </TouchableOpacity>
              
              {!disableEarlyReminder && (
                <ThemedView style={styles.daysInputContainer}>
                  <ThemedText style={styles.daysInputLabel}>Aantal dagen van tevoren:</ThemedText>
                  <ThemedView style={styles.daysInputWrapper}>
                    <TextInput
                      style={styles.daysInput}
                      value={earlyReminderDaysText}
                      onChangeText={(text) => {
                        setEarlyReminderDaysText(text);
                        setUserOverrideEarlyDays(true); // Mark as user override when manually typing
                      }}
                      placeholder="60"
                      keyboardType="numeric"
                      maxLength={3}
                    />
                    <ThemedText style={styles.daysInputSuffix}>dagen</ThemedText>
                  </ThemedView>
                </ThemedView>
              )}
            </ThemedView>

            <ThemedView style={styles.modalButtons}>
              <TouchableOpacity
                style={[styles.modalButton, styles.modalButtonSecondary]}
                onPress={() => setShowSettingsModal(false)}
              >
                <ThemedText style={styles.modalButtonTextSecondary}>Annuleer</ThemedText>
              </TouchableOpacity>
              <TouchableOpacity
                style={[styles.modalButton, styles.modalButtonPrimary]}
                onPress={() => {
                  // Handle early reminder days input
                  const days = parseInt(earlyReminderDaysText);
                  if (earlyReminderDaysText.trim() === '' || days === 0 || isNaN(days)) {
                    // Empty, 0, or invalid input = disable early reminders
                    setDisableEarlyReminder(true);
                    setEarlyReminderDays(60); // Keep default for when re-enabled
                    setEarlyReminderDaysText('60');
                  } else if (days >= 1 && days <= 365) {
                    // Valid input = enable early reminders with specified days
                    setDisableEarlyReminder(false);
                    setEarlyReminderDays(days);
                    setEarlyReminderDaysText(days.toString());
                  } else {
                    // Invalid range = default to 60 days
                    setEarlyReminderDays(60);
                    setEarlyReminderDaysText('60');
                  }
                  setShowSettingsModal(false);
                }}
              >
                <ThemedText style={styles.modalButtonTextPrimary}>Opslaan</ThemedText>
              </TouchableOpacity>
            </ThemedView>
          </View>
        </View>
      </Modal>

      {/* Start Date Picker Modal */}
      <Modal
        animationType="fade"
        transparent={true}
        visible={showStartDatePicker}
        onRequestClose={() => setShowStartDatePicker(false)}
      >
        <View style={styles.datePickerModalOverlay}>
          <View style={styles.datePickerModalContent}>
            <ThemedText style={styles.datePickerTitle}>Selecteer begindatum</ThemedText>
            <DateTimePicker
              value={tempStartDate ? new Date(tempStartDate) : new Date()}
              mode="date"
              display="spinner"
              onChange={(event, selectedDate) => {
                if (selectedDate) {
                  const formattedDate = formatDateForAPI(selectedDate);
                  setTempStartDate(formattedDate);
                }
              }}
              style={styles.datePicker}
            />
            <View style={styles.datePickerButtons}>
              <TouchableOpacity
                style={[styles.datePickerButton, styles.datePickerButtonSecondary]}
                onPress={() => {
                  setTempStartDate(startDate); // Reset to original
                  setShowStartDatePicker(false);
                }}
              >
                <ThemedText style={styles.datePickerButtonTextSecondary}>Annuleer</ThemedText>
              </TouchableOpacity>
              <TouchableOpacity
                style={[styles.datePickerButton, styles.datePickerButtonPrimary]}
                onPress={() => {
                  setStartDate(tempStartDate);
                  setShowStartDatePicker(false);
                }}
              >
                <ThemedText style={styles.datePickerButtonTextPrimary}>Selecteer</ThemedText>
              </TouchableOpacity>
            </View>
          </View>
        </View>
      </Modal>

      {/* End Date Picker Modal */}
      <Modal
        animationType="fade"
        transparent={true}
        visible={showEndDatePicker}
        onRequestClose={() => setShowEndDatePicker(false)}
      >
        <View style={styles.datePickerModalOverlay}>
          <View style={styles.datePickerModalContent}>
            <ThemedText style={styles.datePickerTitle}>Selecteer einddatum</ThemedText>
            <DateTimePicker
              value={tempEndDate ? new Date(tempEndDate) : new Date()}
              mode="date"
              display="spinner"
              onChange={(event, selectedDate) => {
                if (selectedDate) {
                  const formattedDate = formatDateForAPI(selectedDate);
                  setTempEndDate(formattedDate);
                }
              }}
              style={styles.datePicker}
            />
            <View style={styles.datePickerButtons}>
              <TouchableOpacity
                style={[styles.datePickerButton, styles.datePickerButtonSecondary]}
                onPress={() => {
                  setTempEndDate(endDate); // Reset to original
                  setShowEndDatePicker(false);
                }}
              >
                <ThemedText style={styles.datePickerButtonTextSecondary}>Annuleer</ThemedText>
              </TouchableOpacity>
              <TouchableOpacity
                style={[styles.datePickerButton, styles.datePickerButtonPrimary]}
                onPress={() => {
                  setEndDate(tempEndDate);
                  // Recalculate smart default when end date changes (if not manually overridden)
                  if (!userOverrideEarlyDays && alertPeriod === 'custom') {
                    const smartDays = calculateSmartEarlyReminderDays('custom', tempEndDate);
                    setEarlyReminderDays(smartDays);
                    setEarlyReminderDaysText(smartDays.toString());
                  }
                  setShowEndDatePicker(false);
                }}
              >
                <ThemedText style={styles.datePickerButtonTextPrimary}>Selecteer</ThemedText>
              </TouchableOpacity>
            </View>
          </View>
        </View>
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
  scrollView: {
    flex: 1,
  },
  scrollContent: {
    flexGrow: 1,
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
  formContainer: {
    padding: 30,
    flex: 1,
  },
  formGroup: {
    marginBottom: 25,
  },
  label: {
    fontSize: 16,
    fontWeight: '600',
    marginBottom: 8,
    color: '#555',
  },
  picker: {
    backgroundColor: '#fafbfc',
    borderRadius: 12,
    minHeight: 50,
  },
  inputContainer: {
    backgroundColor: '#fafbfc',
    borderRadius: 12,
    borderWidth: 2,
    borderColor: '#e1e5e9',
  },
  input: {
    padding: 16,
    fontSize: 16,
    color: '#333',
  },
  dateText: {
    padding: 16,
    fontSize: 16,
    color: '#333',
  },
  checkboxGroup: {
    backgroundColor: '#f8f9fa',
    padding: 15,
    borderRadius: 12,
    borderWidth: 2,
    borderColor: '#e1e5e9',
  },
  checkbox: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 5,
  },
  checkboxIcon: {
    fontSize: 20,
    marginRight: 12,
  },
  checkboxLabel: {
    fontSize: 16,
    fontWeight: '500',
  },
  periodicText: {
    fontSize: 14,
    color: '#666',
    marginTop: 5,
  },
  summaryBox: {
    backgroundColor: '#f8f9ff',
    borderRadius: 12,
    padding: 20,
    borderWidth: 2,
    borderColor: '#e1e5f0',
    marginBottom: 20,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  summaryText: {
    fontSize: 16,
    color: '#333',
    flex: 1,
    lineHeight: 22,
    paddingRight: 15,
  },
  editButton: {
    backgroundColor: '#4facfe',
    paddingHorizontal: 12,
    paddingVertical: 8,
    borderRadius: 8,
    flexShrink: 0,
  },
  editButtonText: {
    color: 'white',
    fontSize: 14,
    fontWeight: '600',
  },
  daysInputContainer: {
    marginBottom: 15,
  },
  daysInputLabel: {
    fontSize: 14,
    fontWeight: '500',
    color: '#555',
    marginBottom: 8,
  },
  daysInputWrapper: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#ffffff',
    borderRadius: 8,
    borderWidth: 2,
    borderColor: '#e1e5e9',
    paddingHorizontal: 12,
    paddingVertical: 8,
  },
  daysInput: {
    flex: 1,
    fontSize: 16,
    color: '#333',
    padding: 4,
    textAlign: 'center',
    minWidth: 50,
  },
  daysInputSuffix: {
    fontSize: 14,
    color: '#666',
    marginLeft: 8,
  },
  modalOverlay: {
    flex: 1,
    backgroundColor: 'rgba(0, 0, 0, 0.5)',
    justifyContent: 'center',
    alignItems: 'center',
    padding: 20,
  },
  modalContent: {
    backgroundColor: 'white',
    borderRadius: 20,
    padding: 25,
    width: '100%',
    maxWidth: 400,
    maxHeight: '80%',
  },
  modalTitle: {
    fontSize: 20,
    fontWeight: '700',
    textAlign: 'center',
    marginBottom: 25,
    color: '#333',
  },
  modalSection: {
    marginBottom: 25,
  },
  modalSectionTitle: {
    fontSize: 16,
    fontWeight: '600',
    marginBottom: 12,
    color: '#555',
  },
  modalOption: {
    backgroundColor: '#f8f9fa',
    borderRadius: 10,
    padding: 15,
    marginBottom: 8,
    borderWidth: 2,
    borderColor: '#e1e5e9',
  },
  modalOptionSelected: {
    backgroundColor: '#e6f3ff',
    borderColor: '#4facfe',
  },
  modalOptionText: {
    fontSize: 16,
    color: '#666',
  },
  modalOptionTextSelected: {
    color: '#4facfe',
    fontWeight: '600',
  },
  modalOptionDisabled: {
    backgroundColor: '#f5f5f5',
    borderColor: '#e0e0e0',
    opacity: 0.5,
  },
  modalOptionTextDisabled: {
    color: '#999',
  },
  modalButtons: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    gap: 15,
    marginTop: 10,
  },
  modalButton: {
    flex: 1,
    paddingVertical: 15,
    borderRadius: 10,
    alignItems: 'center',
  },
  modalButtonPrimary: {
    backgroundColor: '#4facfe',
  },
  modalButtonSecondary: {
    backgroundColor: '#f8f9fa',
    borderWidth: 1,
    borderColor: '#e1e5e9',
  },
  modalButtonTextPrimary: {
    color: 'white',
    fontSize: 16,
    fontWeight: '600',
  },
  modalButtonTextSecondary: {
    color: '#666',
    fontSize: 16,
    fontWeight: '600',
  },
  submitButton: {
    backgroundColor: '#ff7d04',
    padding: 16,
    borderRadius: 12,
    alignItems: 'center',
    marginTop: 20,
    marginBottom: 40,
    shadowColor: '#000',
    shadowOffset: {
      width: 0,
      height: 2,
    },
    shadowOpacity: 0.25,
    shadowRadius: 3.84,
    elevation: 5,
  },
  submitButtonText: {
    color: 'white',
    fontSize: 18,
    fontWeight: '600',
  },
  datePickerModalOverlay: {
    flex: 1,
    backgroundColor: 'rgba(0, 0, 0, 0.5)',
    justifyContent: 'center',
    alignItems: 'center',
    padding: 20,
  },
  datePickerModalContent: {
    backgroundColor: 'white',
    borderRadius: 20,
    padding: 25,
    width: '100%',
    maxWidth: 350,
    alignItems: 'center',
  },
  datePickerTitle: {
    fontSize: 18,
    fontWeight: '600',
    marginBottom: 20,
    color: '#333',
    textAlign: 'center',
  },
  datePicker: {
    width: '100%',
    backgroundColor: 'transparent',
  },
  datePickerButtons: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    gap: 15,
    marginTop: 20,
    width: '100%',
  },
  datePickerButton: {
    flex: 1,
    paddingVertical: 12,
    borderRadius: 10,
    alignItems: 'center',
  },
  datePickerButtonPrimary: {
    backgroundColor: '#4facfe',
  },
  datePickerButtonSecondary: {
    backgroundColor: '#f8f9fa',
    borderWidth: 1,
    borderColor: '#e1e5e9',
  },
  datePickerButtonTextPrimary: {
    color: 'white',
    fontSize: 16,
    fontWeight: '600',
  },
  datePickerButtonTextSecondary: {
    color: '#666',
    fontSize: 16,
    fontWeight: '600',
  },
});