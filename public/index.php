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
            <div class="checkbox-group">
                <input type="checkbox" id="is_periodic" name="is_periodic" value="1" checked>
                <div>
                    <label for="is_periodic">Periodiek herhalen</label>
                    <div class="periodic-text" id="periodicText">
                        Stuur elke 1 jaar een herinnering
                    </div>
                </div>
            </div>
        </div>

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

<script>
    const alertPeriodSelect = document.getElementById('alert_period');
    const periodicCheckbox = document.getElementById('is_periodic');
    const periodicText = document.getElementById('periodicText');
    const productSelect = document.getElementById('product');
    const emailInput = document.getElementById('email');
    
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
    });

    // Update periodic text based on selected period
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
        
        periodicText.textContent = text;
    }


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