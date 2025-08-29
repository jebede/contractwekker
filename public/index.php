<?php
// Set page variables
$page_title = 'Contractwekker - Tijdig contract opzeggen of overstappen';
$meta_description = 'Stel eenvoudig herinneringen in voor je contracten en ontvang op tijd een seintje om op te zeggen. Gratis, veilig en zonder gedoe.';
$canonical_url = 'https://contractwekker.nl';
$header_subtitle = 'Vergeet nooit meer je contract op te zeggen of over te stappen';

// Include header
include 'views/header.php';
?>

<div class="form-container">
    <form id="contractForm" action="register.php" method="POST">

        <div class="form-group">
            <label for="product">Soort contract</label>
            <div class="custom-select">
                <select id="product" name="product_id" required>
                    <option value="">Kies een contracttype...</option>
                </select>
            </div>
            <div id="customProduct" class="custom-period">
                <label for="custom_product_name">Naam van je contract</label>
                <input type="text" id="custom_product_name" name="custom_product_name" placeholder="Bijv. Netflix, Spotify, etc.">
            </div>
        </div>

        <div class="form-group">
            <label for="alert_period">Geef seintje over</label>
            <div class="custom-select">
                <select id="alert_period" name="alert_period" required>
                    <option value="">Kies een periode...</option>
                    <option value="1_month">1 maand</option>
                    <option value="3_months">3 maanden</option>
                    <option value="1_year" selected>1 jaar</option>
                    <option value="2_years">2 jaar</option>
                    <option value="3_years">3 jaar</option>
                    <option value="custom">Anders (geef op)</option>
                </select>
            </div>
            <div id="contractEndDate" class="custom-period">
                <label for="end_date">Einddatum contract</label>
                <input type="date" id="end_date" name="end_date" placeholder="Kies einddatum">
            </div>
        </div>

        <div class="form-group">
            <div class="summary-box">
                <div class="summary-text" id="summaryText">
                    We sturen je elk jaar een contractwekker. Je ontvangt 60 dagen van tevoren een extra herinnering.
                </div>
                <button type="button" class="edit-button" onclick="openSettingsModal()">
                    ✏️ Wijzig
                </button>
            </div>
        </div>

        <!-- Hidden inputs for form submission -->
        <input type="hidden" id="is_periodic" name="is_periodic" value="1">
        <input type="hidden" id="disable_early_reminder" name="disable_early_reminder" value="">
        <input type="hidden" id="early_reminder_days" name="early_reminder_days" value="60">

        <div class="form-group">
            <label for="email">E-mailadres</label>
            <input type="email" id="email" name="email" required placeholder="Bijv. jouwnaam@gmail.com">
        </div>

        <div class="honeypot">
            <input type="text" name="website" tabindex="-1" autocomplete="off">
        </div>

        <button type="submit" class="submit-btn">
            🔔 Wekker instellen
        </button>
    </form>
</div>

<!-- Settings Modal -->
<div id="settingsModal" class="modal-overlay" style="display: none;">
    <div class="modal-content">
        <h3>Instellingen aanpassen</h3>
        
        <div class="modal-section">
            <h4>Herhaling</h4>
            <div class="modal-option" data-option="periodic" data-value="true">
                <span>Periodiek herhalen</span>
            </div>
            <div class="modal-option" data-option="periodic" data-value="false">
                <span>Eenmalig</span>
            </div>
        </div>

        <div class="modal-section">
            <h4>Vroege herinnering</h4>
            <div class="modal-option" data-option="early" data-value="true">
                <span>Stuur vroege herinnering</span>
            </div>
            <div class="modal-option" data-option="early" data-value="false">
                <span>Geen vroege herinnering</span>
            </div>
            
            <div id="daysInputSection" style="margin-top: 15px;">
                <label for="modalEarlyDays">Aantal dagen van tevoren:</label>
                <div class="days-input-wrapper">
                    <input type="number" id="modalEarlyDays" value="60" min="1" max="365">
                    <span>dagen</span>
                </div>
            </div>
        </div>

        <div class="modal-buttons">
            <button type="button" class="modal-button secondary" onclick="closeSettingsModal()">
                Annuleer
            </button>
            <button type="button" class="modal-button primary" onclick="saveSettings()">
                Opslaan
            </button>
        </div>
    </div>
</div>

<script>
    const alertPeriodSelect = document.getElementById('alert_period');
    const periodicCheckbox = document.getElementById('is_periodic');
    const productSelect = document.getElementById('product');
    const emailInput = document.getElementById('email');
    const disableEarlyReminderCheckbox = document.getElementById('disable_early_reminder');
    const earlyReminderDaysInput = document.getElementById('early_reminder_days');
    
    let currentSettings = {
        isPeriodic: true,
        sendEarlyReminder: true,
        earlyReminderDays: 60
    };
    
    function getSummaryText(alertPeriod, isPeriodic, sendEarlyReminder, earlyReminderDays) {
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
        if (sendEarlyReminder) {
            earlyText = ` Je ontvangt ${earlyReminderDays} dagen van tevoren een extra herinnering.`;
        }

        return `We sturen je ${frequency} een contractwekker.${earlyText}`;
    }
    
    function updateSummaryText() {
        const summaryElement = document.getElementById('summaryText');
        const alertPeriod = alertPeriodSelect.value;
        summaryElement.textContent = getSummaryText(
            alertPeriod, 
            currentSettings.isPeriodic, 
            currentSettings.sendEarlyReminder, 
            currentSettings.earlyReminderDays
        );
    }
    
    // Load saved email from session storage on page load
    const savedEmail = sessionStorage.getItem('contractwekker_email');
    if (savedEmail) {
        emailInput.value = savedEmail;
    }
    
    // Save email to session storage when user types
    emailInput.addEventListener('input', function() {
        if (this.value) {
            sessionStorage.setItem('contractwekker_email', this.value);
        }
    });

    // Initialize summary text
    updateSummaryText();
    
    // Load products
    fetch('get_products.php')
        .then(response => response.json())
        .then(products => {
            products.forEach(product => {
                const option = document.createElement('option');
                option.value = product.id;
                option.textContent = product.name;
                if (product.name === 'Anders') {
                    option.value = 'other';
                }
                productSelect.appendChild(option);
            });
        });

    // Handle product selection
    productSelect.addEventListener('change', function() {
        const customProductDiv = document.getElementById('customProduct');
        if (this.value === 'other') {
            customProductDiv.classList.add('show');
            document.getElementById('custom_product_name').required = true;
        } else {
            customProductDiv.classList.remove('show');
            document.getElementById('custom_product_name').required = false;
        }
    });

    // Handle custom period visibility
    alertPeriodSelect.addEventListener('change', function() {
        const contractEndDateDiv = document.getElementById('contractEndDate');
        if (this.value === 'custom') {
            contractEndDateDiv.classList.add('show');
        } else {
            contractEndDateDiv.classList.remove('show');
        }
        updatePeriodicText();
        
        // Update summary text immediately when period changes
        updateSummaryText();
    });

    // Update periodic text based on selected period (legacy - kept for compatibility)
    function updatePeriodicText() {
        const period = alertPeriodSelect.value;
        let text = '';
        
        if (period === '1_month') {
            text = 'Stuur elke 1 maand een herinnering';
        } else if (period === '3_months') {
            text = 'Stuur elke 3 maanden een herinnering';
        } else if (period === '1_year') {
            text = 'Stuur elke 1 jaar een herinnering';
        } else if (period === '2_years') {
            text = 'Stuur elke 2 jaar een herinnering';
        } else if (period === '3_years') {
            text = 'Stuur elke 3 jaar een herinnering';
        } else if (period === 'custom') {
            text = 'Stuur 1 maand voor einddatum een herinnering';
        }
        
        // Only update if periodicText element exists (for backward compatibility)
        if (window.periodicText) {
            periodicText.textContent = text;
        }
    }

    // Modal functions
    function openSettingsModal() {
        const modal = document.getElementById('settingsModal');
        modal.style.display = 'flex';
        
        // Update modal with current settings
        updateModalSelections();
    }
    
    function closeSettingsModal() {
        const modal = document.getElementById('settingsModal');
        modal.style.display = 'none';
    }
    
    function updateModalSelections() {
        // Update periodic options
        const periodicOptions = document.querySelectorAll('[data-option="periodic"]');
        periodicOptions.forEach(option => {
            const isSelected = (option.dataset.value === 'true') === currentSettings.isPeriodic;
            option.classList.toggle('selected', isSelected);
        });
        
        // Update early reminder options
        const earlyOptions = document.querySelectorAll('[data-option="early"]');
        earlyOptions.forEach(option => {
            const isSelected = (option.dataset.value === 'true') === currentSettings.sendEarlyReminder;
            option.classList.toggle('selected', isSelected);
        });
        
        // Update days input
        const modalDaysInput = document.getElementById('modalEarlyDays');
        modalDaysInput.value = currentSettings.earlyReminderDays;
        
        // Show/hide days input section
        const daysSection = document.getElementById('daysInputSection');
        daysSection.style.display = currentSettings.sendEarlyReminder ? 'block' : 'none';
    }
    
    function saveSettings() {
        // Update form inputs
        periodicCheckbox.value = currentSettings.isPeriodic ? '1' : '';
        disableEarlyReminderCheckbox.value = currentSettings.sendEarlyReminder ? '' : '1';
        earlyReminderDaysInput.value = currentSettings.earlyReminderDays;
        
        // Update summary text
        updateSummaryText();
        
        // Close modal
        closeSettingsModal();
    }
    
    // Handle modal option clicks
    document.addEventListener('click', function(e) {
        if (e.target.closest('.modal-option')) {
            const option = e.target.closest('.modal-option');
            const optionType = option.dataset.option;
            const value = option.dataset.value === 'true';
            
            if (optionType === 'periodic') {
                currentSettings.isPeriodic = value;
            } else if (optionType === 'early') {
                currentSettings.sendEarlyReminder = value;
                // Show/hide days input
                const daysSection = document.getElementById('daysInputSection');
                daysSection.style.display = value ? 'block' : 'none';
            }
            
            updateModalSelections();
        }
    });
    
    // Handle days input change
    document.getElementById('modalEarlyDays').addEventListener('input', function(e) {
        const days = parseInt(e.target.value) || 60;
        if (days >= 1 && days <= 365) {
            currentSettings.earlyReminderDays = days;
        }
    });
    
    // Close modal on overlay click
    document.getElementById('settingsModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeSettingsModal();
        }
    });

    // Form validation
    document.getElementById('contractForm').addEventListener('submit', function(e) {
        const honeypot = document.querySelector('input[name="website"]').value;
        if (honeypot) {
            e.preventDefault();
            return false;
        }
        
        const alertPeriod = alertPeriodSelect.value;
        if (alertPeriod === 'custom') {
            const endDate = document.getElementById('end_date').value;
            if (!endDate) {
                e.preventDefault();
                alert('Vul een einddatum in voor je contract.');
                return false;
            }
        }
        
        const productId = productSelect.value;
        if (productId === 'other') {
            const customProductName = document.getElementById('custom_product_name').value;
            if (!customProductName.trim()) {
                e.preventDefault();
                alert('Vul een naam in voor je contract.');
                return false;
            }
        }
    });
</script>

<?php include 'views/footer.php'; ?>