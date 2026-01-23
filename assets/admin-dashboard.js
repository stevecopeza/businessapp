jQuery(document).ready(function ($) {
    const config = window.BusinessAppConfig;
    let currentSchema = null;

    // --- Selectors ---
    const quoteModal = $('#businessapp-create-quote-modal');
    const customerModal = $('#businessapp-inline-customer-modal');
    
    // --- Quote Modal Logic ---

    // Open Quote Modal
    $(document).on('click', '.businessapp-create-quote-btn', function (e) {
        e.preventDefault();
        openQuoteModal();
    });

    // Handle URL Hash for Quote Modal (e.g. from Quotes page "Create New" button)
    if (window.location.hash === '#create-quote') {
        openQuoteModal();
        // Clear hash to prevent reopening on reload
        history.replaceState(null, null, ' ');
    }

    function openQuoteModal() {
        loadCustomersForSelect().then(() => {
            quoteModal.fadeIn(200);
        });
    }

    // Close Quote Modal
    quoteModal.find('.businessapp-modal-close, .businessapp-modal-cancel').on('click', function () {
        quoteModal.fadeOut(200);
    });

    // --- Inline Customer Logic ---

    // Open Inline Customer Modal
    $('#businessapp-open-customer-modal-btn').on('click', function () {
        quoteModal.fadeOut(200, function() {
            openInlineCustomerModal();
        });
    });

    // Close/Cancel Inline Customer Modal (Return to Quote Modal)
    customerModal.find('.businessapp-modal-close-inline, .businessapp-modal-cancel-inline').on('click', function () {
        customerModal.fadeOut(200, function() {
            quoteModal.fadeIn(200);
        });
    });

    // Submit Inline Customer Form
    $('#businessapp-inline-customer-form').on('submit', function (e) {
        e.preventDefault();
        createInlineCustomer();
    });

    // --- Shared / Helper Functions ---

    function loadCustomersForSelect(selectedId = null) {
        const select = $('#quote_customer_id');
        select.prop('disabled', true).html('<option>Loading...</option>');

        return $.ajax({
            url: config.restUrl + 'customers?status=active',
            method: 'GET',
            beforeSend: function (xhr) {
                xhr.setRequestHeader('X-WP-Nonce', config.nonce);
            },
            success: function (customers) {
                let html = '<option value="">Select a customer...</option>';
                customers.forEach(c => {
                    const isSelected = selectedId && (String(c.id) === String(selectedId)) ? 'selected' : '';
                    html += `<option value="${c.id}" ${isSelected}>${c.name}</option>`;
                });
                select.html(html).prop('disabled', false);
            },
            error: function () {
                select.html('<option>Error loading customers</option>');
            }
        });
    }

    function fetchSchema(callback) {
        if (currentSchema) {
            callback(currentSchema);
            return;
        }

        $.ajax({
            url: config.restUrl + 'schema',
            method: 'GET',
            beforeSend: function (xhr) {
                xhr.setRequestHeader('X-WP-Nonce', config.nonce);
            },
            success: function (response) {
                currentSchema = response;
                callback(currentSchema);
            },
            error: function (err) {
                console.error('Failed to load schema', err);
                alert('Failed to load form configuration.');
            }
        });
    }

    function openInlineCustomerModal() {
        fetchSchema(function(schema) {
            const container = $('#businessapp-inline-customer-dynamic-fields');
            container.empty();

            if (schema.customer_fields && schema.customer_fields.length > 0) {
                container.append('<h3>Additional Details</h3>');
                schema.customer_fields.forEach(field => {
                    container.append(buildFieldHtml(field, 'dynamic_fields'));
                });
            }

            $('#businessapp-inline-customer-form')[0].reset();
            customerModal.fadeIn(200);
        });
    }

    function buildFieldHtml(field, prefix) {
        const fieldName = `${prefix}[${field.id}]`;
        let html = `<div class="businessapp-form-group">
            <label for="${fieldName}">${field.label}</label>`;

        if (field.type === 'select' && field.options) {
            html += `<select name="${fieldName}" id="${fieldName}" class="widefat">`;
            field.options.forEach(opt => {
                html += `<option value="${opt}">${opt}</option>`;
            });
            html += `</select>`;
        } else if (field.type === 'number') {
            html += `<input type="number" name="${fieldName}" id="${fieldName}" class="widefat" step="any">`;
        } else if (field.type === 'checkbox') {
             html += `<input type="checkbox" name="${fieldName}" id="${fieldName}" value="1">`;
        } else {
            html += `<input type="text" name="${fieldName}" id="${fieldName}" class="widefat">`;
        }

        html += `</div>`;
        return html;
    }

    function createInlineCustomer() {
        const formData = $('#businessapp-inline-customer-form').serializeArray();
        const data = {};
        
        formData.forEach(item => {
            if (item.name.startsWith('dynamic_fields[')) {
                if (!data.dynamic_fields) data.dynamic_fields = {};
                const key = item.name.match(/\[(.*?)\]/)[1];
                data.dynamic_fields[key] = item.value;
            } else {
                data[item.name] = item.value;
            }
        });

        const submitBtn = $('#businessapp-inline-customer-form button[type="submit"]');
        const originalText = submitBtn.text();
        submitBtn.prop('disabled', true).text('Creating...');

        $.ajax({
            url: config.restUrl + 'customers',
            method: 'POST',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', config.nonce);
            },
            contentType: 'application/json',
            data: JSON.stringify(data),
            success: function(response) {
                // Success!
                submitBtn.prop('disabled', false).text(originalText);
                
                // Hide Customer Modal
                customerModal.fadeOut(200, function() {
                    // Show Quote Modal
                    quoteModal.fadeIn(200);
                    // Reload Select and Select New Customer
                    loadCustomersForSelect(response.id);
                });
            },
            error: function(err) {
                submitBtn.prop('disabled', false).text(originalText);
                alert('Failed to create customer. Please check inputs.');
                console.error(err);
            }
        });
    }

    // Close on click outside (Handles both modals)
    $(window).on('click', function (e) {
        if ($(e.target).is(quoteModal)) {
            quoteModal.fadeOut(200);
        }
        if ($(e.target).is(customerModal)) {
            customerModal.fadeOut(200, function() {
                // If closing inline modal via outside click, should we return to quote modal?
                // Probably yes to preserve flow context.
                quoteModal.fadeIn(200);
            });
        }
    });
});
