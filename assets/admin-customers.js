jQuery(document).ready(function($) {
    const config = window.BusinessAppConfig;
    let currentCustomer = null;
    let currentSchema = null;

    // --- Initialization ---
    if ($('#businessapp-customers-view').length) {
        loadCustomers();
    }

    // --- Event Listeners ---

    // Filter Change
    $('#businessapp-customer-status-filter').on('change', function() {
        loadCustomers();
    });

    // Open New Customer Modal
    $('#businessapp-new-customer-btn').on('click', function() {
        openNewCustomerModal();
    });

    // Close Modals
    $('.businessapp-modal-close, .businessapp-modal-cancel').on('click', function() {
        $('.businessapp-modal').hide();
    });

    // Back to Customers List
    $('#businessapp-back-to-customers').on('click', function(e) {
        e.preventDefault();
        $('#businessapp-customer-detail-view').hide();
        $('#businessapp-customers-view').show();
        $('.businessapp-dashboard-header h1').text('Customers');
        $('#businessapp-new-customer-btn').show();
        currentCustomer = null;
    });

    // Submit New Customer Form
    $('#businessapp-customer-form').on('submit', function(e) {
        e.preventDefault();
        createCustomer();
    });

    // Submit New Entity Form
    $('#businessapp-entity-form').on('submit', function(e) {
        e.preventDefault();
        saveEntity();
    });

    // --- Functions ---

    function loadCustomers() {
        const status = $('#businessapp-customer-status-filter').val();
        $('#businessapp-customers-view').html('<div class="businessapp-loading">Loading customers...</div>');

        let url = config.restUrl + 'customers';
        if (status) {
            url += '?status=' + status;
        }

        $.ajax({
            url: url,
            method: 'GET',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', config.nonce);
            },
            success: function(response) {
                renderCustomersList(response);
            },
            error: function(err) {
                console.error(err);
                $('#businessapp-customers-view').html('<div class="businessapp-error">Failed to load customers.</div>');
            }
        });
    }

    function renderCustomersList(customers) {
        if (!customers || customers.length === 0) {
            $('#businessapp-customers-view').html(`
                <div class="businessapp-empty-state">
                    <p>No customers found. Add your first customer!</p>
                </div>
            `);
            return;
        }

        let html = '<table class="wp-list-table widefat fixed striped">';
        html += '<thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Status</th><th>Actions</th></tr></thead>';
        html += '<tbody>';

        customers.forEach(customer => {
            html += `
                <tr>
                    <td><a href="#" class="businessapp-customer-link" data-id="${customer.id}"><strong>${customer.name}</strong></a></td>
                    <td>${customer.email}</td>
                    <td>${customer.phone}</td>
                    <td><span class="businessapp-status-badge ${customer.status}">${customer.status}</span></td>
                    <td>
                        <button class="button button-small businessapp-view-customer-btn" data-id="${customer.id}">View</button>
                    </td>
                </tr>
            `;
        });

        html += '</tbody></table>';
        $('#businessapp-customers-view').html(html);

        // Attach click handlers to new elements
        $('.businessapp-customer-link, .businessapp-view-customer-btn').on('click', function(e) {
            e.preventDefault();
            const id = $(this).data('id');
            loadCustomerDetail(id);
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
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', config.nonce);
            },
            success: function(response) {
                currentSchema = response;
                callback(currentSchema);
            },
            error: function(err) {
                alert('Failed to load form schema.');
                console.error(err);
            }
        });
    }

    function openNewCustomerModal() {
        fetchSchema(function(schema) {
            const container = $('#businessapp-customer-dynamic-fields');
            container.empty();

            if (schema.customer_fields && schema.customer_fields.length > 0) {
                container.append('<h3>Additional Details</h3>');
                schema.customer_fields.forEach(field => {
                    container.append(buildFieldHtml(field, 'dynamic_fields'));
                });
            }

            $('#businessapp-customer-form')[0].reset();
            $('#businessapp-customer-modal').show();
        });
    }

    function buildFieldHtml(field, prefix, value = '') {
        const fieldName = `${prefix}[${field.id}]`;
        let html = `<div class="businessapp-form-group">
            <label for="${fieldName}">${field.label}</label>`;

        if (field.type === 'select' && field.options) {
            html += `<select name="${fieldName}" id="${fieldName}" class="widefat">`;
            field.options.forEach(opt => {
                const selected = opt == value ? 'selected' : '';
                html += `<option value="${opt}" ${selected}>${opt}</option>`;
            });
            html += `</select>`;
        } else if (field.type === 'number') {
            html += `<input type="number" name="${fieldName}" id="${fieldName}" class="widefat" step="any" value="${value}">`;
        } else if (field.type === 'checkbox') {
             const checked = value ? 'checked' : '';
             html += `<input type="checkbox" name="${fieldName}" id="${fieldName}" value="1" ${checked}>`;
        } else {
            html += `<input type="text" name="${fieldName}" id="${fieldName}" class="widefat" value="${value}">`;
        }

        html += `</div>`;
        return html;
    }

    function createCustomer() {
        const formData = $('#businessapp-customer-form').serializeArray();
        const data = {};
        
        // Helper to structure data correctly, especially dynamic_fields array
        formData.forEach(item => {
            if (item.name.startsWith('dynamic_fields[')) {
                if (!data.dynamic_fields) data.dynamic_fields = {};
                const key = item.name.match(/\[(.*?)\]/)[1];
                data.dynamic_fields[key] = item.value;
            } else {
                data[item.name] = item.value;
            }
        });

        $.ajax({
            url: config.restUrl + 'customers',
            method: 'POST',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', config.nonce);
            },
            contentType: 'application/json',
            data: JSON.stringify(data),
            success: function(response) {
                $('#businessapp-customer-modal').hide();
                loadCustomers();
            },
            error: function(err) {
                alert('Failed to create customer. Please check input.');
                console.error(err);
            }
        });
    }

    function loadCustomerDetail(id) {
        $('#businessapp-customers-view').hide();
        $('#businessapp-new-customer-btn').hide();
        $('#businessapp-customer-detail-view').show();
        $('#businessapp-customer-detail-content').html('<div class="businessapp-loading">Loading details...</div>');

        $.when(
            $.ajax({
                url: config.restUrl + 'customers/' + id,
                method: 'GET',
                beforeSend: function(xhr) { xhr.setRequestHeader('X-WP-Nonce', config.nonce); }
            }),
            $.ajax({
                url: config.restUrl + 'customers/' + id + '/entities',
                method: 'GET',
                beforeSend: function(xhr) { xhr.setRequestHeader('X-WP-Nonce', config.nonce); }
            })
        ).done(function(customerRes, entitiesRes) {
            const customer = customerRes[0];
            const entities = entitiesRes[0];
            currentCustomer = customer;
            renderCustomerDetail(customer, entities);
        }).fail(function(err) {
            $('#businessapp-customer-detail-content').html('<div class="businessapp-error">Failed to load customer details.</div>');
            console.error(err);
        });
    }

    function renderCustomerDetail(customer, entities) {
        $('.businessapp-dashboard-header h1').text(customer.name);

        let html = `
            <div class="businessapp-detail-card">
                <div class="businessapp-detail-header">
                    <h2>Contact Information</h2>
                    <span class="businessapp-status-badge ${customer.status}">${customer.status}</span>
                </div>
                <div class="businessapp-detail-grid">
                    <div class="businessapp-detail-item">
                        <label>Email</label>
                        <div>${customer.email || '-'}</div>
                    </div>
                    <div class="businessapp-detail-item">
                        <label>Phone</label>
                        <div>${customer.phone || '-'}</div>
                    </div>
                </div>
            </div>
        `;

        // Render Dynamic Fields from Snapshot
        if (customer.schema_snapshot && customer.schema_snapshot.fields && customer.schema_snapshot.fields.length > 0) {
            html += `
                <div class="businessapp-detail-card">
                    <h2>Additional Details</h2>
                    <div class="businessapp-detail-grid">
            `;
            
            customer.schema_snapshot.fields.forEach(field => {
                const value = customer.dynamic_fields && customer.dynamic_fields[field.id] ? customer.dynamic_fields[field.id] : '-';
                html += `
                    <div class="businessapp-detail-item">
                        <label>${field.label}</label>
                        <div>${value} ${field.unit ? field.unit : ''}</div>
                    </div>
                `;
            });

            html += `</div></div>`;
        }

        // Entities Section
        fetchSchema(function(schema) {
            const entityName = schema.entity_name || 'Associated Entity';
            
            html += `
                <div class="businessapp-detail-card">
                    <div class="businessapp-detail-header">
                        <h2>${entityName}s</h2>
                        <button id="businessapp-add-entity-btn" class="button button-secondary">Add ${entityName}</button>
                    </div>
                    <div id="businessapp-entities-list">
            `;

            if (entities && entities.length > 0) {
                html += '<table class="wp-list-table widefat fixed striped"><thead><tr>';
                
                html += '<th>Name</th>'; 
                
                // Add headers for the first few dynamic fields of the current schema
                if (schema.entity_fields) {
                    schema.entity_fields.slice(0, 3).forEach(f => {
                        html += `<th>${f.label}</th>`;
                    });
                }
                
                html += '<th>Actions</th></tr></thead><tbody>';

                entities.forEach(entity => {
                    html += `<tr>`;
                    html += `<td>${entity.entity_name || '#' + entity.id}</td>`;

                    if (schema.entity_fields) {
                        schema.entity_fields.slice(0, 3).forEach(f => {
                            const val = entity.dynamic_fields && entity.dynamic_fields[f.id] ? entity.dynamic_fields[f.id] : '-';
                            html += `<td>${val}</td>`;
                        });
                    }
                    html += `
                        <td>
                            <button class="button button-small businessapp-edit-entity-btn" data-id="${entity.id}">Edit</button>
                            <button class="button button-small businessapp-delete-entity-btn" data-id="${entity.id}" style="color: #b32d2e;">Delete</button>
                        </td>
                    `;
                    html += `</tr>`;
                });

                html += '</tbody></table>';
            } else {
                html += `<p>No ${entityName}s found.</p>`;
            }

            html += `</div></div>`;

            $('#businessapp-customer-detail-content').html(html);

            // Re-bind Add Entity button
            $('#businessapp-add-entity-btn').on('click', function() {
                openEntityModal(schema, null);
            });

            // Bind Edit/Delete buttons
            $('.businessapp-edit-entity-btn').on('click', function() {
                const id = $(this).data('id');
                const entity = entities.find(e => e.id == id);
                openEntityModal(schema, entity);
            });

            $('.businessapp-delete-entity-btn').on('click', function() {
                if (confirm('Are you sure you want to delete this entity?')) {
                    const id = $(this).data('id');
                    deleteEntity(customer.id, id);
                }
            });
        });
    }

    function openEntityModal(schema, entity) {
        const container = $('#businessapp-entity-dynamic-fields');
        container.empty();
        
        const isEdit = !!entity;
        
        // Determine fields source: Snapshot (if edit) or Global Schema (if new)
        let fields = [];
        let entityName = schema.entity_name || 'Entity';

        if (isEdit && entity.schema_snapshot && entity.schema_snapshot.fields) {
            fields = entity.schema_snapshot.fields;
            if (entity.schema_snapshot.entity_name) {
                entityName = entity.schema_snapshot.entity_name;
            }
        } else if (schema.entity_fields) {
            fields = schema.entity_fields;
        }

        $('#businessapp-entity-modal-title').text(isEdit ? 'Edit ' + entityName : 'Add ' + entityName);
        $('#businessapp-entity-customer-id').val(currentCustomer.id);

        // Hidden ID field for edit mode
        if ($('#businessapp-entity-id').length === 0) {
            $('#businessapp-entity-form').prepend('<input type="hidden" id="businessapp-entity-id" name="entity_id">');
        }
        $('#businessapp-entity-id').val(isEdit ? entity.id : '');

        // Add "Entity Name" field
        const nameValue = isEdit ? (entity.entity_name || '') : '';
        container.append(`
            <div class="businessapp-form-group">
                <label>Name / Identifier</label>
                <input type="text" name="entity_name" class="widefat" value="${nameValue}" required>
            </div>
        `);

        if (fields.length > 0) {
            fields.forEach(field => {
                let value = '';
                if (isEdit && entity.dynamic_fields && entity.dynamic_fields[field.id]) {
                    value = entity.dynamic_fields[field.id];
                }
                container.append(buildFieldHtml(field, 'dynamic_fields', value));
            });
        }
        
        $('#businessapp-entity-form')[0].reset();
        $('#businessapp-entity-customer-id').val(currentCustomer.id); // Reset clears hidden field too
        $('#businessapp-entity-id').val(isEdit ? entity.id : '');
        
        // Re-populate values after reset (because reset clears them)
        if (isEdit) {
             $('[name="entity_name"]').val(nameValue);
             if (fields.length > 0) {
                 fields.forEach(field => {
                     if (entity.dynamic_fields && entity.dynamic_fields[field.id]) {
                         const fieldName = `dynamic_fields[${field.id}]`;
                         const el = $(`[name="${fieldName}"]`);
                         if (el.attr('type') === 'checkbox') {
                             el.prop('checked', true);
                         } else {
                             el.val(entity.dynamic_fields[field.id]);
                         }
                     }
                 });
             }
        }

        $('#businessapp-entity-modal').show();
    }

    function saveEntity() {
        const customerId = $('#businessapp-entity-customer-id').val();
        const entityId = $('#businessapp-entity-id').val();
        const formData = $('#businessapp-entity-form').serializeArray();
        const data = {};
        
        formData.forEach(item => {
            if (item.name === 'entity_id' || item.name === 'customer_id') return;
            
            if (item.name.startsWith('dynamic_fields[')) {
                if (!data.dynamic_fields) data.dynamic_fields = {};
                const key = item.name.match(/\[(.*?)\]/)[1];
                data.dynamic_fields[key] = item.value;
            } else {
                data[item.name] = item.value;
            }
        });

        const isEdit = !!entityId;
        const url = isEdit 
            ? config.restUrl + 'customers/' + customerId + '/entities/' + entityId
            : config.restUrl + 'customers/' + customerId + '/entities';
            
        $.ajax({
            url: url,
            method: 'POST',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', config.nonce);
            },
            contentType: 'application/json',
            data: JSON.stringify(data),
            success: function(response) {
                $('#businessapp-entity-modal').hide();
                loadCustomerDetail(customerId); 
            },
            error: function(err) {
                alert('Failed to save entity.');
                console.error(err);
            }
        });
    }

    function deleteEntity(customerId, entityId) {
        $.ajax({
            url: config.restUrl + 'customers/' + customerId + '/entities/' + entityId,
            method: 'DELETE',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', config.nonce);
            },
            success: function(response) {
                loadCustomerDetail(customerId);
            },
            error: function(err) {
                alert('Failed to delete entity.');
                console.error(err);
            }
        });
    }

});
