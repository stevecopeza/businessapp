document.addEventListener('DOMContentLoaded', function () {
    if (typeof BusinessAppConfig === 'undefined') {
        return;
    }

    var root = document.getElementById('businessapp-root');

    if (!root) {
        return;
    }

    function createElement(tag, className, text) {
        var el = document.createElement(tag);

        if (className) {
            el.className = className;
        }

        if (text) {
            el.textContent = text;
        }

        return el;
    }

    var state = {
        quotes: [],
        loading: false,
        error: null,
        activeView: 'dashboard',
        stats: null,
        statsLoading: false,
        settingsTab: 'business-type',
        activeQuote: null,
        activeQuoteLoading: false,
    };

    function setState(newState) {
        state = Object.assign({}, state, newState);
        render();
    }

    function fetchQuotes() {
        setState({ loading: true, error: null });

        fetch(BusinessAppConfig.restUrl + 'quotes', {
            credentials: 'include',
            headers: {
                'X-WP-Nonce': BusinessAppConfig.nonce,
            },
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('Failed to load quotes');
                }

                return response.json();
            })
            .then(function (data) {
                setState({ quotes: Array.isArray(data) ? data : [], loading: false });
            })
            .catch(function (error) {
                setState({ error: error.message, loading: false });
            });
    }

    function fetchQuote(id) {
        setState({ activeQuoteLoading: true, error: null });

        fetch(BusinessAppConfig.restUrl + 'quotes/' + id, {
            credentials: 'include',
            headers: {
                'X-WP-Nonce': BusinessAppConfig.nonce,
            },
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('Failed to load quote');
                }

                return response.json();
            })
            .then(function (data) {
                setState({ activeQuote: data, activeQuoteLoading: false });
            })
            .catch(function (error) {
                setState({ error: error.message, activeQuoteLoading: false });
            });
    }

    function fetchQuoteStats() {
        setState({ statsLoading: true });

        fetch(BusinessAppConfig.restUrl + 'quote-stats', {
            credentials: 'include',
            headers: {
                'X-WP-Nonce': BusinessAppConfig.nonce,
            },
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('Failed to load quote stats');
                }

                return response.json();
            })
            .then(function (data) {
                setState({ stats: data, statsLoading: false });
            })
            .catch(function (error) {
                setState({ error: error.message, statsLoading: false });
            });
    }

    function fetchSettings() {
        // Don't set global loading here to avoid full page spinner on settings tab switch
        // But we might want a settingsLoading state
        setState({ settingsLoading: true });

        fetch(BusinessAppConfig.restUrl + 'settings', {
            credentials: 'include',
            headers: {
                'X-WP-Nonce': BusinessAppConfig.nonce,
            },
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('Failed to load settings');
                }
                return response.json();
            })
            .then(function (data) {
                setState({ settings: data, settingsLoading: false });
            })
            .catch(function (error) {
                console.error('Error loading settings:', error);
                setState({ settingsLoading: false });
            });
    }

    function saveSettings() {
        setState({ loading: true, error: null });

        fetch(BusinessAppConfig.restUrl + 'settings', {
            method: 'POST',
            credentials: 'include',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': BusinessAppConfig.nonce,
            },
            body: JSON.stringify(state.settings),
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('Failed to save settings');
                }
                return response.json();
            })
            .then(function (data) {
                // Update with returned (sanitized) settings
                setState({ settings: data, loading: false });
                alert('Settings saved successfully.');
            })
            .catch(function (error) {
                setState({ error: error.message, loading: false });
            });
    }

    function postAction(path) {
        setState({ loading: true, error: null });

        fetch(BusinessAppConfig.restUrl + path, {
            method: 'POST',
            credentials: 'include',
            headers: {
                'X-WP-Nonce': BusinessAppConfig.nonce,
            },
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('Action failed');
                }

                return response.json();
            })
            .then(function () {
                fetchQuotes();
                fetchQuoteStats();
                fetchSettings();
            })
            .catch(function (error) {
                setState({ error: error.message, loading: false });
            });
    }

    function handleSendQuote(id) {
        postAction('quotes/' + id + '/send');
    }

    function handleAcceptQuote(id) {
        postAction('quotes/' + id + '/accept');
    }

    function handleRejectQuote(id) {
        postAction('quotes/' + id + '/reject');
    }

    function getItemsFromForm(form) {
        var rows = form.querySelectorAll('[data-role="item-row"]');
        var items = [];

        rows.forEach(function (row) {
            var description = row.getAttribute('data-description') || '';
            var qtyValue = row.getAttribute('data-qty') || '1';
            var unitPriceValue = row.getAttribute('data-unit-price') || '0';
            var qty = parseInt(qtyValue, 10);
            var unitPrice = parseFloat(unitPriceValue);

            if (isNaN(qty) || qty <= 0) {
                qty = 1;
            }

            if (isNaN(unitPrice)) {
                unitPrice = 0;
            }

            var amount = qty * unitPrice;

            if (!description && amount === 0) {
                return;
            }

            items.push({
                description: description,
                quantity: qty,
                unit_price: unitPrice,
                amount: amount,
            });
        });

        return items;
    }

    function recalculateTotalsFromItems(form) {
        var items = getItemsFromForm(form);
        var subtotal = 0;

        items.forEach(function (item) {
            if (!isNaN(item.amount)) {
                subtotal += item.amount;
            }
        });

        var taxRateValue = typeof BusinessAppConfig !== 'undefined' && typeof BusinessAppConfig.taxRate !== 'undefined' ? parseFloat(BusinessAppConfig.taxRate) : 0.1;

        if (isNaN(taxRateValue) || taxRateValue < 0) {
            taxRateValue = 0.1;
        }

        var tax = subtotal * taxRateValue;
        var total = subtotal + tax;

        var totalInput = form.querySelector('input[name="total_amount"]');

        if (totalInput) {
            totalInput.value = total > 0 ? total.toFixed(2) : '';
        }

        var subtotalEl = form.querySelector('[data-role="subtotal"]');
        var taxEl = form.querySelector('[data-role="tax"]');
        var grandTotalEl = form.querySelector('[data-role="grand-total"]');

        if (subtotalEl) {
            subtotalEl.textContent = subtotal > 0 ? subtotal.toFixed(2) : '0.00';
        }

        if (taxEl) {
            taxEl.textContent = tax > 0 ? tax.toFixed(2) : '0.00';
        }

        if (grandTotalEl) {
            grandTotalEl.textContent = total > 0 ? total.toFixed(2) : '0.00';
        }
    }

    function handleCreateQuote(event) {
        event.preventDefault();

        var form = event.target;
        var modeInput = form.querySelector('input[name="action_mode"]');
        var mode = modeInput ? modeInput.value : 'draft';
        var titleInput = form.querySelector('input[name="title"]');
        var amountInput = form.querySelector('input[name="total_amount"]');
        var customerNameInput = form.querySelector('input[name="customer_name"]');
        var customerEmailInput = form.querySelector('input[name="customer_email"]');
        var customerPhoneInput = form.querySelector('input[name="customer_phone"]');
        var notesInput = form.querySelector('textarea[name="notes"]');

        var customerName = customerNameInput ? customerNameInput.value.trim() : '';
        var customerEmail = customerEmailInput ? customerEmailInput.value.trim() : '';
        var customerPhone = customerPhoneInput ? customerPhoneInput.value.trim() : '';
        var title = titleInput ? titleInput.value.trim() : '';
        var totalAmount = amountInput ? parseFloat(amountInput.value || '0') : 0;
        var notes = notesInput ? notesInput.value.trim() : '';

        if (!title) {
            return;
        }

        var items = getItemsFromForm(form);

        var dynamicFields = {};
        if (state.settings && state.settings.active_schema && state.settings.active_schema.fields) {
            state.settings.active_schema.fields.forEach(function(field) {
                var input = form.querySelector('[name="dynamic_' + field.id + '"]');
                if (input) {
                    if (field.type === 'checkbox') {
                        dynamicFields[field.id] = input.checked;
                    } else {
                        dynamicFields[field.id] = input.value;
                    }
                }
            });
        }

        if (!isNaN(totalAmount) && totalAmount === 0 && items.length > 0) {
            totalAmount = 0;

            var taxRateValue = typeof BusinessAppConfig !== 'undefined' && typeof BusinessAppConfig.taxRate !== 'undefined' ? parseFloat(BusinessAppConfig.taxRate) : 0.1;

            if (isNaN(taxRateValue) || taxRateValue < 0) {
                taxRateValue = 0.1;
            }

            var subtotal = 0;

            items.forEach(function (item) {
                if (!isNaN(item.amount)) {
                    subtotal += item.amount;
                }
            });

            totalAmount = subtotal + subtotal * taxRateValue;
        }

        setState({ loading: true, error: null });

        var headers = {
            'X-WP-Nonce': BusinessAppConfig.nonce,
        };

        var body;
        var attachmentsInput = form.querySelector('input[name="attachments"]');
        var hasFiles = attachmentsInput && attachmentsInput.files && attachmentsInput.files.length > 0;

        var payload = {
            customer_id: 0,
            customer_name: customerName,
            customer_email: customerEmail,
            customer_phone: customerPhone,
            title: title,
            total_amount: totalAmount,
            notes: notes,
            items: items,
            dynamic_fields: dynamicFields,
            preview: mode === 'preview',
        };

        if (hasFiles) {
            body = new FormData();
            body.append('data', JSON.stringify(payload));

            Array.prototype.forEach.call(attachmentsInput.files, function (file, index) {
                body.append('attachment_' + index, file);
            });
        } else {
            headers['Content-Type'] = 'application/json';
            body = JSON.stringify(payload);
        }

        fetch(BusinessAppConfig.restUrl + 'quotes', {
            method: 'POST',
            credentials: 'include',
            headers: headers,
            body: body,
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('Failed to create quote');
                }

                return response.json();
            })
            .then(function (data) {
                if (customerNameInput) {
                    customerNameInput.value = '';
                }

                if (customerEmailInput) {
                    customerEmailInput.value = '';
                }

                if (customerPhoneInput) {
                    customerPhoneInput.value = '';
                }

                if (notesInput) {
                    notesInput.value = '';
                }

                if (titleInput) {
                    titleInput.value = '';
                }

                if (amountInput) {
                    amountInput.value = '';
                }

                if (state.settings && state.settings.active_schema && state.settings.active_schema.fields) {
                    state.settings.active_schema.fields.forEach(function(field) {
                        var input = form.querySelector('[name="dynamic_' + field.id + '"]');
                        if (input) {
                            if (field.type === 'checkbox') {
                                input.checked = false;
                            } else {
                                input.value = '';
                            }
                        }
                    });
                }

                var itemsContainer = form.querySelector('[data-role="items-list"]');

                if (itemsContainer) {
                    while (itemsContainer.firstChild) {
                        itemsContainer.removeChild(itemsContainer.firstChild);
                    }
                }

                var attachmentsInput = form.querySelector('input[name="attachments"]');

                if (attachmentsInput) {
                    attachmentsInput.value = '';
                }

                var attachmentsList = form.querySelector('[data-role="attachments-list"]');

                if (attachmentsList) {
                    while (attachmentsList.firstChild) {
                        attachmentsList.removeChild(attachmentsList.firstChild);
                    }
                }

                recalculateTotalsFromItems(form);

                if (modeInput) {
                    modeInput.value = 'draft';
                }

                var createdId = data && typeof data.id !== 'undefined' ? data.id : null;

                if (mode === 'send' && createdId !== null) {
                    postAction('quotes/' + createdId + '/send');
                    setState({ activeView: 'dashboard' });
                    return;
                }

                if (mode === 'preview' && data && data.public_url) {
                    window.open(data.public_url, '_blank');
                }

                fetchQuotes();
                fetchQuoteStats();

                if (mode !== 'preview') {
                    setState({ activeView: 'dashboard' });
                }
            })
            .catch(function (error) {
                setState({ error: error.message, loading: false });
            });
    }

    function render() {
        while (root.firstChild) {
            root.removeChild(root.firstChild);
        }

        var container = createElement('div', 'min-h-screen bg-gray-100 flex');

        if (state.activeView !== 'new-quote') {
            var sidebar = createElement('aside', 'hidden md:flex w-64 bg-gray-50 border-r border-gray-200 flex-col justify-between');
            var nav = createElement('nav');

            var navItems = [
                { id: 'dashboard', label: 'Dashboard' },
                { id: 'quotes', label: 'Quotes' },
                { id: 'jobs', label: 'Jobs' },
                { id: 'customers', label: 'Customers' },
                { id: 'settings', label: 'Settings' },
                { id: 'analytics', label: 'Analytics' },
            ];

            var baseNavClass = 'block w-full text-left px-4 py-2 text-sm rounded-md mb-1';

            navItems.forEach(function (item) {
                var isActive = state.activeView === item.id;
                var classes =
                    baseNavClass +
                    (isActive
                        ? ' bg-blue-600 text-white font-semibold'
                        : ' text-gray-800 hover:bg-gray-200');

                var button = createElement('button', classes, item.label);
                button.type = 'button';

                button.addEventListener('click', function () {
                    setState({ activeView: item.id });
                });

                nav.appendChild(button);
            });

            var newQuoteButton = createElement(
                'button',
                'mt-4 mx-2 mb-2 bg-blue-600 text-white text-sm font-semibold px-4 py-2 rounded-md',
                '+ New Quote'
            );
            newQuoteButton.type = 'button';

            newQuoteButton.addEventListener('click', function () {
                setState({ activeView: 'new-quote' });
            });

            sidebar.appendChild(nav);
            sidebar.appendChild(newQuoteButton);

            container.appendChild(sidebar);
        }

        var mainShell = createElement('div', 'flex-1 flex flex-col');

        var header = createElement('header', 'bg-white border-b border-gray-200');
        var headerInner = createElement('div', 'max-w-6xl mx-auto px-4 h-16 grid grid-cols-3 gap-4 items-center');

        var headerLeft = createElement('div', 'flex items-center');
        var logo = createElement('div', 'flex items-center gap-2 text-lg font-semibold text-gray-900');
        var logoIcon = createElement('div', 'bg-blue-600 text-white px-2 py-1 rounded text-sm', '▦');
        var logoText = createElement('span', '', 'BusinessApp');
        logo.appendChild(logoIcon);
        logo.appendChild(logoText);
        headerLeft.appendChild(logo);

        var headerCenter = createElement('div', 'flex justify-center');
        var search = createElement('div', 'flex items-center bg-gray-100 rounded-md px-3 py-2 w-full max-w-md');
        var searchIcon = createElement('span', 'text-gray-400 text-sm', '🔍');
        var searchInput = createElement('input', 'ml-2 flex-1 bg-transparent border-none outline-none text-sm text-gray-800');
        searchInput.type = 'text';
        searchInput.placeholder = 'Search…';
        search.appendChild(searchIcon);
        search.appendChild(searchInput);
        headerCenter.appendChild(search);

        var headerRight = createElement('div', 'flex justify-end');
        var user = createElement('div', 'flex items-center gap-2');
        var avatar = createElement('div', 'bg-blue-600 text-white w-9 h-9 rounded-full flex items-center justify-center text-sm font-semibold', 'JS');
        var userInfo = createElement('div', 'text-xs text-right');
        var userName = createElement('div', 'font-semibold text-gray-900', 'John Smith');
        var userBusiness = createElement('div', 'text-gray-500', 'Main Repair Shop');
        userInfo.appendChild(userName);
        userInfo.appendChild(userBusiness);
        var chevron = createElement('div', 'text-gray-400 text-xs', '▾');
        user.appendChild(avatar);
        user.appendChild(userInfo);
        user.appendChild(chevron);
        headerRight.appendChild(user);

        headerInner.appendChild(headerLeft);
        headerInner.appendChild(headerCenter);
        headerInner.appendChild(headerRight);
        header.appendChild(headerInner);

        var subHeader = createElement(
            'div',
            'h-11 bg-gradient-to-r from-gray-900 to-gray-700 text-white flex items-center justify-between px-6 text-sm'
        );

        var subTitleText = 'Dashboard';

        if (state.activeView === 'settings') {
            subTitleText = 'Business Settings';
        } else if (state.activeView === 'quotes') {
            subTitleText = 'Quotes';
        } else if (state.activeView === 'jobs') {
            subTitleText = 'Jobs';
        } else if (state.activeView === 'customers') {
            subTitleText = 'Customers';
        } else if (state.activeView === 'analytics') {
            subTitleText = 'Analytics';
        } else if (state.activeView === 'new-quote') {
            subTitleText = 'Create Quote';
        }

        var subTitle = createElement('span', 'font-semibold', subTitleText);
        var subAction = createElement('button', 'text-xs md:text-sm opacity-80 hover:opacity-100', 'Create Quote');
        subAction.type = 'button';

        subAction.addEventListener('click', function () {
            setState({ activeView: 'new-quote' });
        });

        subHeader.appendChild(subTitle);
        subHeader.appendChild(subAction);

        var main = createElement('main', 'flex-1');
        var mainInner;

        if (state.activeView === 'settings') {
            mainInner = createElement('div', 'max-w-6xl mx-auto px-4 py-6');

            var tabsContainer = createElement('div', 'flex items-center justify-between border-b border-gray-200 mb-6');
            var tabsRow = createElement('div', 'flex gap-6');
            var tabs = [
                { id: 'general', label: 'General' },
                { id: 'quote-settings', label: 'Quote Settings' },
                { id: 'templates', label: 'Templates' },
                { id: 'business-type', label: 'Business Type' },
                { id: 'data-units', label: 'Data & Units' },
            ];

            tabs.forEach(function (tab) {
                var isActive = state.settingsTab === tab.id;
                var button = createElement(
                    'button',
                    'pb-2 text-sm border-b-2 -mb-px ' +
                        (isActive
                            ? 'border-blue-600 text-blue-600 font-semibold'
                            : 'border-transparent text-gray-500 hover:text-gray-700'),
                    tab.label
                );

                button.type = 'button';

                button.addEventListener('click', function () {
                    setState({ settingsTab: tab.id, activeView: 'settings' });
                });

                tabsRow.appendChild(button);
            });

            var saveBtn = createElement('button', 'mb-2 inline-flex items-center rounded-md border border-transparent bg-blue-600 px-3 py-1.5 text-xs font-medium text-white shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2', 'Save Changes');
            saveBtn.type = 'button';
            saveBtn.addEventListener('click', saveSettings);

            tabsContainer.appendChild(tabsRow);
            tabsContainer.appendChild(saveBtn);

            mainInner.appendChild(tabsContainer);

            if (state.settingsTab === 'general') {
                var generalPanel = createElement('section', 'bg-white rounded-lg shadow p-5 mb-6');
                var generalTitle = createElement('h2', 'text-lg font-semibold mb-4', 'General Settings');
                generalPanel.appendChild(generalTitle);

                var settings = state.settings || {};
                var general = settings.general || {};
                if (Array.isArray(general)) general = {};

                var generalGrid = createElement('div', 'grid grid-cols-1 md:grid-cols-2 gap-4');

                var bizNameWrap = createElement('div');
                var bizNameLabel = createElement('label', 'block text-xs font-medium text-gray-600 mb-1', 'Business Name');
                var bizNameInput = createElement('input', 'w-full rounded-md border border-gray-300 px-3 py-2 text-sm');
                bizNameInput.value = general.business_name || '';
                bizNameInput.addEventListener('change', function(e) {
                    var newSettings = Object.assign({}, state.settings);
                    var newGeneral = newSettings.general;
                    if (!newGeneral || Array.isArray(newGeneral)) newGeneral = {};
                    newGeneral.business_name = e.target.value;
                    newSettings.general = newGeneral;
                    setState({ settings: newSettings });
                });
                bizNameWrap.appendChild(bizNameLabel);
                bizNameWrap.appendChild(bizNameInput);

                var emailWrap = createElement('div');
                var emailLabel = createElement('label', 'block text-xs font-medium text-gray-600 mb-1', 'Contact Email');
                var emailInput = createElement('input', 'w-full rounded-md border border-gray-300 px-3 py-2 text-sm');
                emailInput.value = general.contact_email || '';
                emailInput.addEventListener('change', function(e) {
                    var newSettings = Object.assign({}, state.settings);
                    var newGeneral = newSettings.general;
                    if (!newGeneral || Array.isArray(newGeneral)) newGeneral = {};
                    newGeneral.contact_email = e.target.value;
                    newSettings.general = newGeneral;
                    setState({ settings: newSettings });
                });
                emailWrap.appendChild(emailLabel);
                emailWrap.appendChild(emailInput);

                var phoneWrap = createElement('div');
                var phoneLabel = createElement('label', 'block text-xs font-medium text-gray-600 mb-1', 'Phone Number');
                var phoneInput = createElement('input', 'w-full rounded-md border border-gray-300 px-3 py-2 text-sm');
                phoneInput.value = general.phone_number || '';
                phoneInput.addEventListener('change', function(e) {
                    var newSettings = Object.assign({}, state.settings);
                    var newGeneral = newSettings.general;
                    if (!newGeneral || Array.isArray(newGeneral)) newGeneral = {};
                    newGeneral.phone_number = e.target.value;
                    newSettings.general = newGeneral;
                    setState({ settings: newSettings });
                });
                phoneWrap.appendChild(phoneLabel);
                phoneWrap.appendChild(phoneInput);

                var currencyWrap = createElement('div');
                var currencyLabel = createElement('label', 'block text-xs font-medium text-gray-600 mb-1', 'Default Currency');
                var currencySelect = createElement('select', 'w-full rounded-md border border-gray-300 px-3 py-2 text-sm');
                var currencyOptZar = createElement('option', null, 'ZAR (R)');
                currencyOptZar.value = 'ZAR';
                var currencyOptUsd = createElement('option', null, 'USD ($)');
                currencyOptUsd.value = 'USD';
                currencySelect.appendChild(currencyOptZar);
                currencySelect.appendChild(currencyOptUsd);
                currencySelect.value = general.currency || 'ZAR';
                currencySelect.addEventListener('change', function(e) {
                    var newSettings = Object.assign({}, state.settings);
                    var newGeneral = newSettings.general;
                    if (!newGeneral || Array.isArray(newGeneral)) newGeneral = {};
                    newGeneral.currency = e.target.value;
                    newSettings.general = newGeneral;
                    setState({ settings: newSettings });
                });
                currencyWrap.appendChild(currencyLabel);
                currencyWrap.appendChild(currencySelect);

                generalGrid.appendChild(bizNameWrap);
                generalGrid.appendChild(emailWrap);
                generalGrid.appendChild(phoneWrap);
                generalGrid.appendChild(currencyWrap);

                generalPanel.appendChild(generalGrid);
                mainInner.appendChild(generalPanel);
            } else if (state.settingsTab === 'quote-settings') {
                var qsPanel = createElement('section', 'bg-white rounded-lg shadow p-5 mb-6');
                var qsTitle = createElement('h2', 'text-lg font-semibold mb-4', 'Quote Settings');
                qsPanel.appendChild(qsTitle);

                var settings = state.settings || {};
                var quoteSettings = settings.quote_settings || {};
                if (Array.isArray(quoteSettings)) quoteSettings = {};

                var checklist = createElement('div', 'mb-4 space-y-2 text-sm');

                [
                    { label: 'Require acceptance before job creation', key: 'require_acceptance', default: true },
                    { label: 'Allow quote revisions', key: 'allow_revisions', default: false },
                    { label: 'Show expiry date on quotes', key: 'show_expiry', default: true },
                ].forEach(function (item) {
                    var row = createElement('label', 'flex items-center gap-2');
                    var checkbox = createElement('input');
                    checkbox.type = 'checkbox';
                    checkbox.checked = typeof quoteSettings[item.key] !== 'undefined' ? quoteSettings[item.key] : item.default;
                    
                    checkbox.addEventListener('change', function(e) {
                         var newSettings = Object.assign({}, state.settings);
                         var newQS = newSettings.quote_settings;
                         if (!newQS || Array.isArray(newQS)) newQS = {};
                         newQS[item.key] = e.target.checked;
                         newSettings.quote_settings = newQS;
                         setState({ settings: newSettings });
                    });
                    
                    row.appendChild(checkbox);
                    row.appendChild(createElement('span', '', item.label));
                    checklist.appendChild(row);
                });

                var qsGrid = createElement('div', 'grid grid-cols-1 md:grid-cols-2 gap-4 mt-4');

                var expiryWrap = createElement('div');
                var expiryLabel = createElement('label', 'block text-xs font-medium text-gray-600 mb-1', 'Default Quote Expiry (days)');
                var expiryInput = createElement('input', 'w-full rounded-md border border-gray-300 px-3 py-2 text-sm');
                expiryInput.value = quoteSettings.default_expiry || '14';
                expiryInput.addEventListener('change', function(e) {
                     var newSettings = Object.assign({}, state.settings);
                     var newQS = newSettings.quote_settings;
                     if (!newQS || Array.isArray(newQS)) newQS = {};
                     newQS.default_expiry = e.target.value;
                     newSettings.quote_settings = newQS;
                     setState({ settings: newSettings });
                });
                expiryWrap.appendChild(expiryLabel);
                expiryWrap.appendChild(expiryInput);

                var taxWrap = createElement('div');
                var taxLabel = createElement('label', 'block text-xs font-medium text-gray-600 mb-1', 'Tax Rate (%)');
                var taxInput = createElement('input', 'w-full rounded-md border border-gray-300 px-3 py-2 text-sm');
                taxInput.value = quoteSettings.tax_rate || '15';
                taxInput.addEventListener('change', function(e) {
                     var newSettings = Object.assign({}, state.settings);
                     var newQS = newSettings.quote_settings;
                     if (!newQS || Array.isArray(newQS)) newQS = {};
                     newQS.tax_rate = e.target.value;
                     newSettings.quote_settings = newQS;
                     setState({ settings: newSettings });
                });
                taxWrap.appendChild(taxLabel);
                taxWrap.appendChild(taxInput);

                qsGrid.appendChild(expiryWrap);
                qsGrid.appendChild(taxWrap);

                qsPanel.appendChild(checklist);
                qsPanel.appendChild(qsGrid);

                mainInner.appendChild(qsPanel);
            } else if (state.settingsTab === 'templates') {
                var tmplPanel = createElement('section', 'bg-white rounded-lg shadow p-5 mb-6');
                var tmplTitle = createElement('h2', 'text-lg font-semibold mb-4', 'Quote Templates');
                tmplPanel.appendChild(tmplTitle);

                var settings = state.settings || {};
                var templates = settings.templates || [];
                if (!Array.isArray(templates)) templates = [];

                var listBox = createElement('div', 'border border-gray-200 rounded-md divide-y divide-gray-200 text-sm');

                if (templates.length === 0) {
                     var emptyRow = createElement('div', 'p-4 text-center text-gray-500 italic', 'No templates yet. Add one below.');
                     listBox.appendChild(emptyRow);
                } else {
                    templates.forEach(function (tmpl, index) {
                        var row = createElement('div', 'flex items-center gap-3 px-3 py-2');
                        
                        var labelInput = createElement('input', 'flex-1 rounded-md border border-gray-300 px-2 py-1 text-sm');
                        labelInput.placeholder = 'Template Name';
                        labelInput.value = tmpl.label || '';
                        labelInput.addEventListener('change', function(e) {
                             var newSettings = Object.assign({}, state.settings);
                             var newTemplates = (newSettings.templates || []).slice();
                             newTemplates[index] = Object.assign({}, newTemplates[index], { label: e.target.value });
                             newSettings.templates = newTemplates;
                             setState({ settings: newSettings });
                        });

                        var priceInput = createElement('input', 'w-24 rounded-md border border-gray-300 px-2 py-1 text-sm text-right');
                        priceInput.placeholder = '0.00';
                        priceInput.type = 'number';
                        priceInput.value = tmpl.price || '';
                        priceInput.addEventListener('change', function(e) {
                             var newSettings = Object.assign({}, state.settings);
                             var newTemplates = (newSettings.templates || []).slice();
                             newTemplates[index] = Object.assign({}, newTemplates[index], { price: e.target.value });
                             newSettings.templates = newTemplates;
                             setState({ settings: newSettings });
                        });

                        var removeBtn = createElement('button', 'text-red-500 hover:text-red-700 font-bold px-2', '×');
                        removeBtn.type = 'button';
                        removeBtn.addEventListener('click', function() {
                             var newSettings = Object.assign({}, state.settings);
                             var newTemplates = (newSettings.templates || []).slice();
                             newTemplates.splice(index, 1);
                             newSettings.templates = newTemplates;
                             setState({ settings: newSettings });
                        });

                        row.appendChild(labelInput);
                        row.appendChild(priceInput);
                        row.appendChild(removeBtn);
                        listBox.appendChild(row);
                    });
                }

                var addTemplate = createElement('button', 'mt-3 text-sm text-blue-600 font-medium hover:text-blue-800', '+ Add Template');
                addTemplate.type = 'button';
                addTemplate.addEventListener('click', function() {
                     var newSettings = Object.assign({}, state.settings);
                     var newTemplates = (newSettings.templates || []).slice();
                     newTemplates.push({ label: '', price: '' });
                     newSettings.templates = newTemplates;
                     setState({ settings: newSettings });
                });

                tmplPanel.appendChild(listBox);
                tmplPanel.appendChild(addTemplate);

                mainInner.appendChild(tmplPanel);
            } else if (state.settingsTab === 'business-type') {
                var btPanel = createElement('section', 'bg-white rounded-lg shadow p-5 mb-6');
                var btTitle = createElement('h2', 'text-lg font-semibold mb-4', 'Business Type Settings');
                btPanel.appendChild(btTitle);

                var settings = state.settings || {};
                var businessType = settings.business_type || {};
                if (Array.isArray(businessType)) businessType = {};

                var btRow = createElement('div', 'mb-4');
                var btLabel = createElement('label', 'block text-xs font-medium text-gray-600 mb-1', 'Business Type');
                var btSelect = createElement('select', 'w-full md:w-72 rounded-md border border-gray-300 px-3 py-2 text-sm');
                ['Panel Beater', 'Gardener', 'Tailor'].forEach(function (label) {
                    var opt = createElement('option', null, label);
                    opt.value = label;
                    btSelect.appendChild(opt);
                });
                btSelect.value = businessType.type || 'Panel Beater';
                btSelect.addEventListener('change', function(e) {
                     var newSettings = Object.assign({}, state.settings);
                     var newBT = newSettings.business_type;
                     if (!newBT || Array.isArray(newBT)) newBT = {};
                     newBT.type = e.target.value;
                     newSettings.business_type = newBT;
                     setState({ settings: newSettings });
                });
                btRow.appendChild(btLabel);
                btRow.appendChild(btSelect);

                btPanel.appendChild(btRow);

                var btColumns = createElement('div', 'grid grid-cols-1 md:grid-cols-2 gap-4');

                var vehicleCard = createElement('div', 'border border-gray-200 rounded-md p-4');
                var vehicleTitle = createElement('h3', 'text-sm font-semibold mb-3', 'Vehicle Fields');
                vehicleCard.appendChild(vehicleTitle);
                
                var currentVehicleFields = businessType.vehicle_fields || ['Make', 'Model', 'VIN', 'Year'];

                ['Make', 'Model', 'VIN', 'Year', 'Colour'].forEach(function (field, index) {
                    var row = createElement('label', 'flex items-center gap-2 text-sm mb-1');
                    var checkbox = createElement('input');
                    checkbox.type = 'checkbox';
                    
                    checkbox.checked = currentVehicleFields.includes(field);
                    
                    checkbox.addEventListener('change', function(e) {
                        var newSettings = Object.assign({}, state.settings);
                        var newBT = newSettings.business_type;
                        if (!newBT || Array.isArray(newBT)) newBT = {};
                        
                        var fields = newBT.vehicle_fields || ['Make', 'Model', 'VIN', 'Year'];
                        if (e.target.checked) {
                            if (!fields.includes(field)) fields.push(field);
                        } else {
                            fields = fields.filter(function(f) { return f !== field; });
                        }
                        newBT.vehicle_fields = fields;
                        newSettings.business_type = newBT;
                        setState({ settings: newSettings });
                    });
                    
                    row.appendChild(checkbox);
                    row.appendChild(createElement('span', '', field));
                    vehicleCard.appendChild(row);
                });

                var optionsCard = createElement('div', 'border border-gray-200 rounded-md p-4');
                var optionsTitle = createElement('h3', 'text-sm font-semibold mb-3', 'Default Options');
                optionsCard.appendChild(optionsTitle);
                
                var currentOptions = businessType.default_options || [];

                ['Bumper Unit', 'Paint Area (m²)', 'Material Finish'].forEach(function (field) {
                    var row = createElement('label', 'flex items-center gap-2 text-sm mb-1');
                    var checkbox = createElement('input');
                    checkbox.type = 'checkbox';
                    
                    checkbox.checked = currentOptions.includes(field);

                    checkbox.addEventListener('change', function(e) {
                        var newSettings = Object.assign({}, state.settings);
                        var newBT = newSettings.business_type;
                        if (!newBT || Array.isArray(newBT)) newBT = {};
                        
                        var opts = newBT.default_options || [];
                        if (e.target.checked) {
                            if (!opts.includes(field)) opts.push(field);
                        } else {
                            opts = opts.filter(function(f) { return f !== field; });
                        }
                        newBT.default_options = opts;
                        newSettings.business_type = newBT;
                        setState({ settings: newSettings });
                    });

                    row.appendChild(checkbox);
                    row.appendChild(createElement('span', '', field));
                    optionsCard.appendChild(row);
                });

                btColumns.appendChild(vehicleCard);
                btColumns.appendChild(optionsCard);

                btPanel.appendChild(btColumns);

                mainInner.appendChild(btPanel);
            } else if (state.settingsTab === 'data-units') {
                var duPanel = createElement('section', 'bg-white rounded-lg shadow p-5 mb-6');
                var duTitle = createElement('h2', 'text-lg font-semibold mb-4', 'Data & Units');
                duPanel.appendChild(duTitle);

                var settings = state.settings || {};
                var dataUnits = settings.data_units || {};
                if (Array.isArray(dataUnits)) dataUnits = {};

                var duGrid = createElement('div', 'grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4');

                var lengthWrap = createElement('div');
                var lengthLabel = createElement('label', 'block text-xs font-medium text-gray-600 mb-1', 'Length Unit');
                var lengthSelect = createElement('select', 'w-full rounded-md border border-gray-300 px-3 py-2 text-sm');
                ['Millimeters', 'Inches'].forEach(function (label) {
                    var opt = createElement('option', null, label);
                    opt.value = label;
                    lengthSelect.appendChild(opt);
                });
                lengthSelect.value = dataUnits.length_unit || 'Millimeters';
                lengthSelect.addEventListener('change', function(e) {
                     var newSettings = Object.assign({}, state.settings);
                     var newDU = newSettings.data_units;
                     if (!newDU || Array.isArray(newDU)) newDU = {};
                     newDU.length_unit = e.target.value;
                     newSettings.data_units = newDU;
                     setState({ settings: newSettings });
                });
                lengthWrap.appendChild(lengthLabel);
                lengthWrap.appendChild(lengthSelect);

                var areaWrap = createElement('div');
                var areaLabel = createElement('label', 'block text-xs font-medium text-gray-600 mb-1', 'Area Unit');
                var areaSelect = createElement('select', 'w-full rounded-md border border-gray-300 px-3 py-2 text-sm');
                ['Square meters', 'Square feet'].forEach(function (label) {
                    var opt = createElement('option', null, label);
                    opt.value = label;
                    areaSelect.appendChild(opt);
                });
                areaSelect.value = dataUnits.area_unit || 'Square meters';
                areaSelect.addEventListener('change', function(e) {
                     var newSettings = Object.assign({}, state.settings);
                     var newDU = newSettings.data_units;
                     if (!newDU || Array.isArray(newDU)) newDU = {};
                     newDU.area_unit = e.target.value;
                     newSettings.data_units = newDU;
                     setState({ settings: newSettings });
                });
                areaWrap.appendChild(areaLabel);
                areaWrap.appendChild(areaSelect);

                var weightWrap = createElement('div');
                var weightLabel = createElement('label', 'block text-xs font-medium text-gray-600 mb-1', 'Weight Unit');
                var weightSelect = createElement('select', 'w-full rounded-md border border-gray-300 px-3 py-2 text-sm');
                ['Kilograms', 'Pounds'].forEach(function (label) {
                    var opt = createElement('option', null, label);
                    opt.value = label;
                    weightSelect.appendChild(opt);
                });
                weightSelect.value = dataUnits.weight_unit || 'Kilograms';
                weightSelect.addEventListener('change', function(e) {
                     var newSettings = Object.assign({}, state.settings);
                     var newDU = newSettings.data_units;
                     if (!newDU || Array.isArray(newDU)) newDU = {};
                     newDU.weight_unit = e.target.value;
                     newSettings.data_units = newDU;
                     setState({ settings: newSettings });
                });
                weightWrap.appendChild(weightLabel);
                weightWrap.appendChild(weightSelect);

                duGrid.appendChild(lengthWrap);
                duGrid.appendChild(areaWrap);
                duGrid.appendChild(weightWrap);

                duPanel.appendChild(duGrid);

                mainInner.appendChild(duPanel);
            }

            var actionsRow = createElement('div', 'flex justify-end');
            var saveChanges = createElement('button', 'inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-semibold rounded-md', 'Save Changes');
            saveChanges.type = 'button';
            actionsRow.appendChild(saveChanges);
            mainInner.appendChild(actionsRow);
        } else if (state.activeView === 'quote-detail') {
            mainInner = createElement('div', 'max-w-4xl mx-auto px-4 py-6');

            if (state.activeQuoteLoading) {
                var loading = createElement('div', 'text-center py-12 text-gray-500', 'Loading quote details...');
                mainInner.appendChild(loading);
            } else if (state.error) {
                var error = createElement('div', 'bg-red-50 p-4 rounded-md text-red-700', state.error);
                mainInner.appendChild(error);
            } else if (!state.activeQuote) {
                var empty = createElement('div', 'text-center py-12 text-gray-500', 'Quote not found.');
                mainInner.appendChild(empty);
            } else {
                var quote = state.activeQuote;

                // Back button
                var backBtn = createElement('button', 'mb-4 text-sm text-gray-500 hover:text-gray-900 flex items-center gap-1', '← Back to Quotes');
                backBtn.addEventListener('click', function () {
                    setState({ activeView: 'dashboard', activeQuote: null });
                });
                mainInner.appendChild(backBtn);

                // Header Card
                var headerCard = createElement('section', 'bg-white rounded-lg shadow overflow-hidden mb-6');
                var headerContent = createElement('div', 'p-6');

                var headerTop = createElement('div', 'flex justify-between items-start');
                var titleBlock = createElement('div');
                var title = createElement('h1', 'text-2xl font-bold text-gray-900', quote.title);
                var meta = createElement('div', 'mt-1 text-sm text-gray-500', 'Quote #' + quote.id + ' • Created ' + (quote.created_at || 'Recently'));
                titleBlock.appendChild(title);
                titleBlock.appendChild(meta);

                var statusBadge = createElement('span', 'inline-flex items-center px-3 py-1 rounded-full text-sm font-medium', quote.status.toUpperCase());
                if (quote.status === 'draft') statusBadge.className += ' bg-gray-100 text-gray-800';
                else if (quote.status === 'sent') statusBadge.className += ' bg-blue-100 text-blue-800';
                else if (quote.status === 'accepted') statusBadge.className += ' bg-green-100 text-green-800';
                else if (quote.status === 'rejected') statusBadge.className += ' bg-red-100 text-red-800';

                headerTop.appendChild(titleBlock);
                headerTop.appendChild(statusBadge);
                headerContent.appendChild(headerTop);

                // Actions
                var actionBar = createElement('div', 'mt-6 flex gap-3 border-t border-gray-100 pt-4');

                if (quote.status === 'draft') {
                    var sendBtn = createElement('button', 'inline-flex justify-center rounded-md border border-transparent bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700', 'Send Quote');
                    sendBtn.addEventListener('click', function () { handleSendQuote(quote.id); });
                    actionBar.appendChild(sendBtn);
                }

                if (quote.public_token) {
                    var previewBtn = createElement('button', 'inline-flex justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50', 'Open Public View');
                    previewBtn.addEventListener('click', function () {
                        // Use BusinessAppConfig.siteUrl if available, otherwise construct from public_url if available in quote
                        var url = quote.public_url;
                        if (url) window.open(url, '_blank');
                    });
                    actionBar.appendChild(previewBtn);
                }

                headerContent.appendChild(actionBar);
                headerCard.appendChild(headerContent);
                mainInner.appendChild(headerCard);

                // Customer & Details Grid
                var grid = createElement('div', 'grid grid-cols-1 md:grid-cols-3 gap-6 mb-6');

                // Customer Card
                var customerCard = createElement('section', 'bg-white rounded-lg shadow p-6');
                customerCard.appendChild(createElement('h3', 'text-lg font-medium text-gray-900 mb-4', 'Customer'));
                var customerList = createElement('dl', 'space-y-3 text-sm');

                [
                    { label: 'Name', value: quote.customer_name },
                    { label: 'Email', value: quote.customer_email },
                    { label: 'Phone', value: quote.customer_phone }
                ].forEach(function (item) {
                    if (item.value) {
                        var div = createElement('div');
                        div.appendChild(createElement('dt', 'text-gray-500', item.label));
                        div.appendChild(createElement('dd', 'font-medium text-gray-900', item.value));
                        customerList.appendChild(div);
                    }
                });
                customerCard.appendChild(customerList);
                grid.appendChild(customerCard);

                // Dynamic Fields Card
                var dynamicCard = createElement('section', 'bg-white rounded-lg shadow p-6 md:col-span-2');
                dynamicCard.appendChild(createElement('h3', 'text-lg font-medium text-gray-900 mb-4', 'Job Details'));

                var dynamicGrid = createElement('dl', 'grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-4 text-sm');

                var fields = quote.dynamic_fields || {};
                var snapshot = quote.schema_snapshot || {};
                var snapshotFields = snapshot.fields || [];

                // Map using snapshot if available, otherwise just keys
                if (snapshotFields.length > 0) {
                    snapshotFields.forEach(function (field) {
                        var value = fields[field.id];
                        // Show even if empty? Maybe not.
                        if (value !== undefined && value !== '' && value !== null) {
                            var div = createElement('div');
                            var labelText = field.label || field.id;
                            div.appendChild(createElement('dt', 'text-gray-500', labelText));

                            var displayValue = value;
                            if (field.type === 'checkbox') {
                                displayValue = value ? 'Yes' : 'No';
                            } else if (field.unit) {
                                displayValue += ' ' + field.unit;
                            } else if (field.units) {
                                // Legacy fallback
                                displayValue += ' ' + field.units;
                            }

                            div.appendChild(createElement('dd', 'font-medium text-gray-900', String(displayValue)));
                            dynamicGrid.appendChild(div);
                        }
                    });
                } else {
                    Object.keys(fields).forEach(function (key) {
                        var value = fields[key];
                        var div = createElement('div');
                        div.appendChild(createElement('dt', 'text-gray-500', key));
                        div.appendChild(createElement('dd', 'font-medium text-gray-900', String(value)));
                        dynamicGrid.appendChild(div);
                    });
                }

                dynamicCard.appendChild(dynamicGrid);
                grid.appendChild(dynamicCard);

                mainInner.appendChild(grid);

                // Line Items
                var itemsCard = createElement('section', 'bg-white rounded-lg shadow overflow-hidden');
                var itemsHeader = createElement('div', 'px-6 py-4 border-b border-gray-200');
                itemsHeader.appendChild(createElement('h3', 'text-lg font-medium text-gray-900', 'Line Items'));

                var table = createElement('table', 'min-w-full divide-y divide-gray-200');
                var thead = createElement('thead', 'bg-gray-50');
                var headRow = createElement('tr');
                ['Description', 'Qty', 'Unit Price', 'Total'].forEach(function (h, i) {
                    var align = i > 0 ? 'text-right' : 'text-left';
                    headRow.appendChild(createElement('th', 'px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider ' + align, h));
                });
                thead.appendChild(headRow);
                table.appendChild(thead);

                var tbody = createElement('tbody', 'bg-white divide-y divide-gray-200');
                (quote.items || []).forEach(function (item) {
                    var row = createElement('tr');
                    row.appendChild(createElement('td', 'px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900', item.description));
                    row.appendChild(createElement('td', 'px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-right', String(item.quantity)));
                    row.appendChild(createElement('td', 'px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-right', Number(item.unit_price).toFixed(2)));
                    row.appendChild(createElement('td', 'px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right', Number(item.amount).toFixed(2)));
                    tbody.appendChild(row);
                });
                table.appendChild(tbody);
                itemsCard.appendChild(table);

                var totalsDiv = createElement('div', 'px-6 py-4 bg-gray-50 flex flex-col items-end gap-2');
                var total = Number(quote.total_amount);

                var totalRow = createElement('div', 'text-lg font-bold text-gray-900', 'Total: ' + total.toFixed(2));
                totalsDiv.appendChild(totalRow);

                itemsCard.appendChild(totalsDiv);

                mainInner.appendChild(itemsCard);
            }
        } else {
            mainInner = createElement('div', 'max-w-6xl mx-auto px-4 py-6 space-y-6');

            var statsGrid = createElement('div', 'grid gap-4 grid-cols-1 sm:grid-cols-2 lg:grid-cols-4');
            var stats = state.stats || {};

            var activeValue = typeof stats.active_quotes === 'number' ? stats.active_quotes : 0;
            var pendingValue = typeof stats.pending_approvals === 'number' ? stats.pending_approvals : 0;
            var acceptedValue = typeof stats.accepted_jobs === 'number' ? stats.accepted_jobs : 0;
            var unpaidValue = typeof stats.unpaid_invoices === 'number' ? stats.unpaid_invoices : 0;

            var activeCard = createElement('div', 'bg-white rounded-lg shadow p-4');
            var activeLabel = createElement('div', 'text-xs font-medium text-gray-500 uppercase tracking-wide', 'Active quotes');
            var activeNumber = createElement('div', 'mt-2 text-2xl font-semibold text-gray-900', String(activeValue));
            activeCard.appendChild(activeLabel);
            activeCard.appendChild(activeNumber);

            var pendingCard = createElement('div', 'bg-white rounded-lg shadow p-4');
            var pendingLabel = createElement('div', 'text-xs font-medium text-gray-500 uppercase tracking-wide', 'Pending approvals');
            var pendingNumber = createElement('div', 'mt-2 text-2xl font-semibold text-gray-900', String(pendingValue));
            pendingCard.appendChild(pendingLabel);
            pendingCard.appendChild(pendingNumber);

            var acceptedCard = createElement('div', 'bg-white rounded-lg shadow p-4');
            var acceptedLabel = createElement('div', 'text-xs font-medium text-gray-500 uppercase tracking-wide', 'Accepted jobs');
            var acceptedNumber = createElement('div', 'mt-2 text-2xl font-semibold text-gray-900', String(acceptedValue));
            acceptedCard.appendChild(acceptedLabel);
            acceptedCard.appendChild(acceptedNumber);

            var unpaidCard = createElement('div', 'bg-white rounded-lg shadow p-4');
            var unpaidLabel = createElement('div', 'text-xs font-medium text-gray-500 uppercase tracking-wide', 'Unpaid invoices');
            var unpaidNumber = createElement('div', 'mt-2 text-2xl font-semibold text-gray-900', String(unpaidValue));
            unpaidCard.appendChild(unpaidLabel);
            unpaidCard.appendChild(unpaidNumber);

            statsGrid.appendChild(activeCard);
            statsGrid.appendChild(pendingCard);
            statsGrid.appendChild(acceptedCard);
            statsGrid.appendChild(unpaidCard);

            var contentGrid = createElement('div', 'grid gap-6 lg:grid-cols-3');

            var formCard = createElement('section', 'bg-white rounded-lg shadow p-4 lg:col-span-1');
            var formTitle = createElement('h2', 'text-lg font-medium text-gray-900 mb-4', 'Create Quote');
            formCard.appendChild(formTitle);

            var form = createElement('form', 'space-y-4');
            form.addEventListener('submit', handleCreateQuote);

            var fieldCustomerName = createElement('div');
            var labelCustomerName = createElement('label', 'block text-sm font-medium text-gray-700', 'Customer name');
            labelCustomerName.htmlFor = 'businessapp_app_customer_name';
            var inputCustomerName = createElement('input', 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm');
            inputCustomerName.type = 'text';
            inputCustomerName.name = 'customer_name';
            inputCustomerName.id = 'businessapp_app_customer_name';
            fieldCustomerName.appendChild(labelCustomerName);
            fieldCustomerName.appendChild(inputCustomerName);

            var fieldCustomerEmail = createElement('div');
            var labelCustomerEmail = createElement('label', 'block text-sm font-medium text-gray-700', 'Customer email');
            labelCustomerEmail.htmlFor = 'businessapp_app_customer_email';
            var inputCustomerEmail = createElement('input', 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm');
            inputCustomerEmail.type = 'email';
            inputCustomerEmail.name = 'customer_email';
            inputCustomerEmail.id = 'businessapp_app_customer_email';
            fieldCustomerEmail.appendChild(labelCustomerEmail);
            fieldCustomerEmail.appendChild(inputCustomerEmail);

            var fieldCustomerPhone = createElement('div');
            var labelCustomerPhone = createElement('label', 'block text-sm font-medium text-gray-700', 'Customer phone');
            labelCustomerPhone.htmlFor = 'businessapp_app_customer_phone';
            var inputCustomerPhone = createElement('input', 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm');
            inputCustomerPhone.type = 'text';
            inputCustomerPhone.name = 'customer_phone';
            inputCustomerPhone.id = 'businessapp_app_customer_phone';
            fieldCustomerPhone.appendChild(labelCustomerPhone);
            fieldCustomerPhone.appendChild(inputCustomerPhone);

            var fieldTitle = createElement('div');
            var labelTitle = createElement('label', 'block text-sm font-medium text-gray-700', 'Title');
            labelTitle.htmlFor = 'businessapp_app_title';
            var inputTitle = createElement('input', 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm');
            inputTitle.type = 'text';
            inputTitle.name = 'title';
            inputTitle.id = 'businessapp_app_title';
            inputTitle.required = true;
            fieldTitle.appendChild(labelTitle);
            fieldTitle.appendChild(inputTitle);

            var fieldAmount = createElement('div');
            var labelAmount = createElement('label', 'block text-sm font-medium text-gray-700', 'Total amount');
            labelAmount.htmlFor = 'businessapp_app_total_amount';
            var inputAmount = createElement('input', 'mt-1 block w-full rounded-md border-gray-200 bg-gray-50 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm');
            inputAmount.type = 'number';
            inputAmount.name = 'total_amount';
            inputAmount.id = 'businessapp_app_total_amount';
            inputAmount.step = '0.01';
            inputAmount.readOnly = true;
            fieldAmount.appendChild(labelAmount);
            fieldAmount.appendChild(inputAmount);

            var fieldNotes = createElement('div');
            var labelNotes = createElement('label', 'block text-sm font-medium text-gray-700', 'Notes');
            labelNotes.htmlFor = 'businessapp_app_notes';
            var textareaNotes = createElement('textarea', 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm');
            textareaNotes.name = 'notes';
            textareaNotes.id = 'businessapp_app_notes';
            textareaNotes.rows = 3;
            fieldNotes.appendChild(labelNotes);
            fieldNotes.appendChild(textareaNotes);

            var itemsSection = createElement('div', 'border border-gray-200 rounded-md p-3');
            var itemsHeader = createElement('div', 'flex items-center justify-between');
            var itemsTitle = createElement('h3', 'text-sm font-medium text-gray-900', 'Line items');
            var itemsHint = createElement('span', 'text-xs text-gray-400', 'Description, quantity, unit price');
            itemsHeader.appendChild(itemsTitle);
            itemsHeader.appendChild(itemsHint);

            var itemsRow = createElement('div', 'mt-2 flex gap-2');

            var fieldItemDescription = createElement('div', 'flex-1');
            var labelItemDescription = createElement('label', 'block text-xs font-medium text-gray-600', 'Description');
            labelItemDescription.htmlFor = 'businessapp_app_item_description';
            var inputItemDescription = createElement('input', 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm');
            inputItemDescription.type = 'text';
            inputItemDescription.name = 'item_description';
            inputItemDescription.id = 'businessapp_app_item_description';
            fieldItemDescription.appendChild(labelItemDescription);
            fieldItemDescription.appendChild(inputItemDescription);

            var fieldItemQty = createElement('div', 'w-20');
            var labelItemQty = createElement('label', 'block text-xs font-medium text-gray-600', 'Qty');
            labelItemQty.htmlFor = 'businessapp_app_item_qty';
            var inputItemQty = createElement('input', 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm');
            inputItemQty.type = 'number';
            inputItemQty.name = 'item_qty';
            inputItemQty.id = 'businessapp_app_item_qty';
            inputItemQty.step = '1';
            inputItemQty.min = '1';
            inputItemQty.value = '1';
            fieldItemQty.appendChild(labelItemQty);
            fieldItemQty.appendChild(inputItemQty);

            var fieldItemAmount = createElement('div', 'w-28');
            var labelItemAmount = createElement('label', 'block text-xs font-medium text-gray-600', 'Unit price');
            labelItemAmount.htmlFor = 'businessapp_app_item_amount';
            var inputItemAmount = createElement('input', 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm');
            inputItemAmount.type = 'number';
            inputItemAmount.name = 'item_amount';
            inputItemAmount.id = 'businessapp_app_item_amount';
            inputItemAmount.step = '0.01';
            fieldItemAmount.appendChild(labelItemAmount);
            fieldItemAmount.appendChild(inputItemAmount);

            var addItemWrapper = createElement('div', 'self-end');
            var addItemButton = createElement('button', 'mt-1 inline-flex items-center rounded-md border border-transparent bg-gray-100 px-3 py-2 text-xs font-medium text-gray-800 shadow-sm hover:bg-gray-200', 'Add item');
            addItemButton.type = 'button';
            addItemWrapper.appendChild(addItemButton);

            itemsRow.appendChild(fieldItemDescription);
            itemsRow.appendChild(fieldItemQty);
            itemsRow.appendChild(fieldItemAmount);
            itemsRow.appendChild(addItemWrapper);

            var itemsList = createElement('div', 'mt-3 space-y-1 border-t border-gray-100 pt-2');
            itemsList.setAttribute('data-role', 'items-list');

            addItemButton.addEventListener('click', function () {
                var description = inputItemDescription.value.trim();
                var qtyValue = inputItemQty.value || '1';
                var unitPriceValue = inputItemAmount.value || '0';
                var qty = parseInt(qtyValue, 10);
                var unitPrice = parseFloat(unitPriceValue);

                if (isNaN(qty) || qty <= 0) {
                    qty = 1;
                }

                if (isNaN(unitPrice)) {
                    unitPrice = 0;
                }

                var amount = qty * unitPrice;

                if (!description && amount === 0) {
                    return;
                }

                var row = createElement('div', 'flex items-center justify-between text-sm');
                row.setAttribute('data-role', 'item-row');
                row.setAttribute('data-description', description);
                row.setAttribute('data-qty', String(qty));
                row.setAttribute('data-unit-price', String(unitPrice));
                row.setAttribute('data-amount', String(amount));

                var left = createElement('div', 'flex-1 text-gray-700', description || '(no description)');
                var right = createElement('div', 'flex items-center gap-3');
                var qtyEl = createElement('span', 'text-gray-600 text-xs', 'x ' + String(qty));
                var amountEl = createElement('span', 'text-gray-900', amount.toFixed(2));
                var removeButton = createElement('button', 'text-xs text-red-600 hover:text-red-800', 'Remove');
                removeButton.type = 'button';

                removeButton.addEventListener('click', function () {
                    itemsList.removeChild(row);
                    recalculateTotalsFromItems(form);
                });

                right.appendChild(qtyEl);
                right.appendChild(amountEl);
                right.appendChild(removeButton);

                row.appendChild(left);
                row.appendChild(right);

                itemsList.appendChild(row);

                inputItemDescription.value = '';
                inputItemQty.value = '1';
                inputItemAmount.value = '';

                recalculateTotalsFromItems(form);
            });

            itemsSection.appendChild(itemsHeader);
            itemsSection.appendChild(itemsRow);
            itemsSection.appendChild(itemsList);

            var attachmentsSection = createElement('div', 'mt-3 border border-dashed border-gray-300 rounded-md p-3 bg-gray-50');
            var attachmentsHeader = createElement('div', 'flex items-center justify-between');
            var attachmentsTitle = createElement('h3', 'text-sm font-medium text-gray-900', 'Attachments');
            var attachmentsHint = createElement('span', 'text-xs text-gray-400', 'Add photos or documents');
            attachmentsHeader.appendChild(attachmentsTitle);
            attachmentsHeader.appendChild(attachmentsHint);
            var attachmentsInput = createElement('input', 'mt-2 block w-full text-sm text-gray-600');
            attachmentsInput.type = 'file';
            attachmentsInput.name = 'attachments';
            attachmentsInput.multiple = true;
            var attachmentsList = createElement('div', 'mt-2 space-y-1 text-xs text-gray-600');
            attachmentsList.setAttribute('data-role', 'attachments-list');

            attachmentsInput.addEventListener('change', function () {
                while (attachmentsList.firstChild) {
                    attachmentsList.removeChild(attachmentsList.firstChild);
                }

                var files = attachmentsInput.files;

                if (!files || files.length === 0) {
                    return;
                }

                Array.prototype.forEach.call(files, function (file) {
                    var row = createElement('div', 'flex items-center justify-between');
                    var nameSpan = createElement('span', 'truncate', file.name);
                    var sizeSpan = createElement('span', 'ml-2 text-gray-400', String(Math.round(file.size / 1024)) + ' KB');
                    row.appendChild(nameSpan);
                    row.appendChild(sizeSpan);
                    attachmentsList.appendChild(row);
                });
            });

            attachmentsSection.appendChild(attachmentsHeader);
            attachmentsSection.appendChild(attachmentsInput);
            attachmentsSection.appendChild(attachmentsList);

            var inputMode = createElement('input');
            inputMode.type = 'hidden';
            inputMode.name = 'action_mode';
            inputMode.value = 'draft';

            var submitRow = createElement('div', 'pt-2 flex flex-wrap items-center gap-2');
            var saveButton = createElement('button', 'inline-flex justify-center rounded-md border border-transparent bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2', 'Save draft');
            saveButton.type = 'submit';
            var previewButton = createElement('button', 'inline-flex justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50', 'Preview');
            previewButton.type = 'button';
            var sendButton = createElement('button', 'inline-flex justify-center rounded-md border border-transparent bg-green-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-600 focus:ring-offset-2', 'Send quote');
            sendButton.type = 'button';

            saveButton.addEventListener('click', function () {
                inputMode.value = 'draft';
            });

            previewButton.addEventListener('click', function () {
                inputMode.value = 'preview';
                form.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
            });

            sendButton.addEventListener('click', function () {
                inputMode.value = 'send';
                form.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
            });

            submitRow.appendChild(saveButton);
            submitRow.appendChild(previewButton);
            submitRow.appendChild(sendButton);

            form.appendChild(fieldCustomerName);
            form.appendChild(fieldCustomerEmail);
            form.appendChild(fieldCustomerPhone);
            form.appendChild(fieldTitle);
            form.appendChild(fieldAmount);
            form.appendChild(fieldNotes);

            if (state.settings && state.settings.active_schema && state.settings.active_schema.fields) {
                var activeSchema = state.settings.active_schema;
                var dynamicFieldsSection = createElement('div', 'space-y-4 border-t border-gray-100 pt-4 mt-4');
                var sectionTitle = createElement('h3', 'text-sm font-medium text-gray-900', activeSchema.name + ' Details');
                dynamicFieldsSection.appendChild(sectionTitle);

                activeSchema.fields.forEach(function(field) {
                    var fieldWrapper = createElement('div');
                    var labelText = field.label;
                    if (field.unit) {
                        labelText += ' (' + field.unit + ')';
                    }
                    var label = createElement('label', 'block text-sm font-medium text-gray-700', labelText);
                    label.htmlFor = 'businessapp_dynamic_' + field.id;
                    
                    var input;
                    if (field.type === 'textarea') {
                        input = createElement('textarea', 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm');
                        input.rows = 3;
                    } else if (field.type === 'select') {
                        input = createElement('select', 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm');
                        if (field.options) {
                            field.options.forEach(function(opt) {
                                 var option = createElement('option', null, opt);
                                 option.value = opt;
                                 input.appendChild(option);
                            });
                        }
                    } else if (field.type === 'checkbox') {
                         fieldWrapper.className = 'flex items-center gap-2';
                         input = createElement('input', 'h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500');
                         input.type = 'checkbox';
                    } else {
                        input = createElement('input', 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm');
                        input.type = field.type === 'number' ? 'number' : 'text';
                    }

                    input.name = 'dynamic_' + field.id;
                    input.id = 'businessapp_dynamic_' + field.id;
                    if (field.required) input.required = true;
                    
                    if (field.type === 'checkbox') {
                        fieldWrapper.appendChild(input);
                        fieldWrapper.appendChild(label);
                    } else {
                        fieldWrapper.appendChild(label);
                        fieldWrapper.appendChild(input);
                    }
                    
                    dynamicFieldsSection.appendChild(fieldWrapper);
                });
                form.appendChild(dynamicFieldsSection);
            }

            form.appendChild(itemsSection);
            form.appendChild(attachmentsSection);
            form.appendChild(inputMode);
            form.appendChild(submitRow);

            formCard.appendChild(form);

            var listCard = createElement('section', 'bg-white rounded-lg shadow p-4 lg:col-span-2');
            var listHeaderRow = createElement('div', 'flex items-center justify-between mb-4');
            var listTitle = createElement('h2', 'text-lg font-medium text-gray-900', 'Quotes');
            listHeaderRow.appendChild(listTitle);

            if (state.loading) {
                var loadingBadge = createElement('span', 'text-xs text-gray-500', 'Loading…');
                listHeaderRow.appendChild(loadingBadge);
            }

            listCard.appendChild(listHeaderRow);

            if (state.error) {
                var errorBox = createElement('div', 'mb-4 rounded-md bg-red-50 p-3 text-sm text-red-700', state.error);
                listCard.appendChild(errorBox);
            }

            if (state.quotes.length === 0 && !state.loading) {
                var empty = createElement('p', 'text-sm text-gray-500', 'No quotes yet.');
                listCard.appendChild(empty);
            } else if (state.quotes.length > 0) {
                var table = createElement('table', 'min-w-full divide-y divide-gray-200 text-sm');
                var thead = createElement('thead', 'bg-gray-50');
                var headRow = createElement('tr');

                ['ID', 'Customer', 'Title', 'Status', 'Total', 'Actions'].forEach(function (heading) {
                    var th = createElement('th', 'px-3 py-2 text-left font-medium text-gray-500 uppercase tracking-wider text-xs', heading);
                    headRow.appendChild(th);
                });

                thead.appendChild(headRow);

                var tbody = createElement('tbody', 'bg-white divide-y divide-gray-200');

                state.quotes.forEach(function (quote) {
                    var row = createElement('tr');

                    var idCell = createElement('td', 'px-3 py-2 whitespace-nowrap text-gray-900', String(quote.id));
                    var customerCell = createElement('td', 'px-3 py-2 whitespace-nowrap text-gray-900', quote.customer_name || '');
                    var titleCell = createElement('td', 'px-3 py-2 whitespace-nowrap text-gray-900', quote.title || '');
                    var statusCell = createElement('td', 'px-3 py-2 whitespace-nowrap text-gray-900', quote.status || '');
                    var amountCell = createElement('td', 'px-3 py-2 whitespace-nowrap text-gray-900', Number(quote.total_amount || 0).toFixed(2));
                    var actionsCell = createElement('td', 'px-3 py-2 whitespace-nowrap text-gray-900');

                    var viewButton = createElement('button', 'inline-flex items-center rounded-md border border-gray-300 bg-white px-2 py-1 text-xs font-medium text-gray-700 shadow-sm hover:bg-gray-50 mr-2', 'View');
                    viewButton.type = 'button';
                    viewButton.addEventListener('click', function () {
                        setState({ activeView: 'quote-detail', activeQuote: null });
                        fetchQuote(quote.id);
                    });
                    actionsCell.appendChild(viewButton);

                    if (quote.status === 'draft') {
                        var sendButton = createElement('button', 'inline-flex items-center rounded-md border border-gray-300 bg-white px-2 py-1 text-xs font-medium text-gray-700 shadow-sm hover:bg-gray-50', 'Send');
                        sendButton.type = 'button';
                        sendButton.addEventListener('click', function () {
                            handleSendQuote(quote.id);
                        });
                        actionsCell.appendChild(sendButton);
                    } else if (quote.status === 'sent') {
                        var acceptButton = createElement('button', 'inline-flex items-center rounded-md border border-transparent bg-green-600 px-2 py-1 text-xs font-medium text-white shadow-sm hover:bg-green-700 mr-2', 'Accept');
                        acceptButton.type = 'button';
                        acceptButton.addEventListener('click', function () {
                            handleAcceptQuote(quote.id);
                        });

                        var rejectButton = createElement('button', 'inline-flex items-center rounded-md border border-transparent bg-red-600 px-2 py-1 text-xs font-medium text-white shadow-sm hover:bg-red-700', 'Reject');
                        rejectButton.type = 'button';
                        rejectButton.addEventListener('click', function () {
                            handleRejectQuote(quote.id);
                        });

                        actionsCell.appendChild(acceptButton);
                        actionsCell.appendChild(rejectButton);
                    }

                    if (quote.public_url) {
                        var linkButton = createElement('button', 'inline-flex items-center rounded-md border border-gray-300 bg-white px-2 py-1 text-xs font-medium text-gray-700 shadow-sm hover:bg-gray-50 ml-2', 'Copy link');
                        linkButton.type = 'button';
                        linkButton.addEventListener('click', function () {
                            var url = quote.public_url;
                            if (navigator.clipboard && navigator.clipboard.writeText) {
                                navigator.clipboard.writeText(url);
                            } else {
                                window.prompt('Public quote link', url);
                            }
                        });
                        actionsCell.appendChild(linkButton);
                    }

                    row.appendChild(idCell);
                    row.appendChild(customerCell);
                    row.appendChild(titleCell);
                    row.appendChild(statusCell);
                    row.appendChild(amountCell);
                    row.appendChild(actionsCell);

                    tbody.appendChild(row);
                });

                table.appendChild(thead);
                table.appendChild(tbody);
                listCard.appendChild(table);
            }

            contentGrid.appendChild(formCard);
            contentGrid.appendChild(listCard);

            var secondaryGrid = createElement('div', 'grid gap-6 lg:grid-cols-2');

            var activityCard = createElement('section', 'bg-white rounded-lg shadow p-4');
            var activityTitle = createElement('h2', 'text-lg font-medium text-gray-900 mb-2', 'Recent activity');
            activityCard.appendChild(activityTitle);
            
            var activityList = createElement('div', 'space-y-3');
            var activityData = (state.dashboardData && state.dashboardData.activity) || [];
            
            if (activityData.length === 0) {
                 activityList.appendChild(createElement('p', 'text-sm text-gray-500', 'No recent activity.'));
            } else {
                 activityData.forEach(function(item) {
                      var row = createElement('div', 'flex items-start space-x-2');
                      var dot = createElement('div', 'w-2 h-2 mt-1.5 rounded-full bg-blue-500 flex-shrink-0');
                      var textDiv = createElement('div', 'text-sm text-gray-700');
                      var desc = createElement('p', 'font-medium', item.description);
                      var sub = createElement('p', 'text-xs text-gray-500', item.title || '');
                      textDiv.appendChild(desc);
                      if (item.title) textDiv.appendChild(sub);
                      row.appendChild(dot);
                      row.appendChild(textDiv);
                      activityList.appendChild(row);
                 });
            }
            activityCard.appendChild(activityList);

            var tasksCard = createElement('section', 'bg-white rounded-lg shadow p-4');
            var tasksTitle = createElement('h2', 'text-lg font-medium text-gray-900 mb-2', 'My tasks');
            tasksCard.appendChild(tasksTitle);
            
            var tasksList = createElement('div', 'space-y-3');
            var tasksData = (state.dashboardData && state.dashboardData.tasks) || [];
            
            if (tasksData.length === 0) {
                 tasksList.appendChild(createElement('p', 'text-sm text-gray-500', 'No pending tasks.'));
            } else {
                 tasksData.forEach(function(task) {
                      var row = createElement('div', 'flex items-center justify-between p-2 bg-gray-50 rounded-md');
                      var label = createElement('span', 'text-sm text-gray-700', task.task);
                      var action = createElement('button', 'text-xs text-indigo-600 hover:text-indigo-800 font-medium', 'View');
                      // simplified action
                      row.appendChild(label);
                      row.appendChild(action);
                      tasksList.appendChild(row);
                 });
            }
            tasksCard.appendChild(tasksList);

            secondaryGrid.appendChild(activityCard);
            secondaryGrid.appendChild(tasksCard);

            mainInner.appendChild(statsGrid);
            mainInner.appendChild(contentGrid);
            mainInner.appendChild(secondaryGrid);
        }

        main.appendChild(mainInner);

        mainShell.appendChild(header);
        mainShell.appendChild(main);

        container.appendChild(mainShell);

        root.appendChild(container);
    }

    function fetchDashboardData() {
        fetch(BusinessAppConfig.restUrl + 'dashboard-data', {
            credentials: 'include',
            headers: {
                'X-WP-Nonce': BusinessAppConfig.nonce,
            },
        })
        .then(function (response) {
            if (!response.ok) throw new Error('Failed to load dashboard data');
            return response.json();
        })
        .then(function (data) {
            setState({ dashboardData: data });
        })
        .catch(function (error) {
            console.error('Error loading dashboard data:', error);
        });
    }

    render();
    fetchQuotes();
    fetchQuoteStats();
    fetchSettings();
    fetchDashboardData();
});
