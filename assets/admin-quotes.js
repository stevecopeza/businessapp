(function(wp) {
    const { element, components, i18n, apiFetch } = wp;
    const { useState, useEffect, createElement: el, Fragment } = element;
    const { __ } = i18n;
    const { Button, Spinner, TextControl, TextareaControl, SelectControl, CheckboxControl } = components;

    // Configure API
    if (window.businessappData) {
        apiFetch.use(apiFetch.createNonceMiddleware(window.businessappData.nonce));
        apiFetch.use(apiFetch.createRootURLMiddleware(window.businessappData.root));
    }
    
    const activeSchema = window.businessappData ? window.businessappData.active_schema : null;
    const workflowSettings = window.businessappData && window.businessappData.settings && window.businessappData.settings.workflow ? window.businessappData.settings.workflow : {};
    const quoteStatuses = workflowSettings.quote || {
        'draft': 'New',
        'sent': 'Sent',
        'edited': 'Edited',
        'accepted': 'Accepted',
        'rejected': 'Rejected'
    };

    const QuotesApp = () => {
        const [view, setView] = useState('list');
        const [quotes, setQuotes] = useState([]);
        const [currentQuote, setCurrentQuote] = useState(null);
        const [loading, setLoading] = useState(true);
        const [error, setError] = useState(null);

        useEffect(() => {
            fetchQuotes();
        }, []);

        const fetchQuotes = async () => {
            setLoading(true);
            try {
                const data = await apiFetch({ path: '/businessapp/v1/quotes' });
                setQuotes(data);
                setError(null);
            } catch (err) {
                console.error(err);
                setError('Failed to load quotes.');
            } finally {
                setLoading(false);
            }
        };

        const handleCreate = () => {
            setCurrentQuote(null);
            setView('create');
        };

        const handleEdit = async (quoteId) => {
            setLoading(true);
            try {
                const data = await apiFetch({ path: `/businessapp/v1/quotes/${quoteId}` });
                setCurrentQuote(data);
                setView('edit');
            } catch (err) {
                console.error(err);
                alert('Failed to load quote details.');
            } finally {
                setLoading(false);
            }
        };

        const handleView = async (quoteId) => {
             setLoading(true);
            try {
                const data = await apiFetch({ path: `/businessapp/v1/quotes/${quoteId}` });
                setCurrentQuote(data);
                setView('detail');
            } catch (err) {
                console.error(err);
                alert('Failed to load quote details.');
            } finally {
                setLoading(false);
            }
        };

        const handleSave = async (data) => {
            setLoading(true);
            try {
                if (currentQuote && currentQuote.id) {
                    await apiFetch({
                        path: `/businessapp/v1/quotes/${currentQuote.id}`,
                        method: 'POST',
                        data: data
                    });
                } else {
                    await apiFetch({
                        path: '/businessapp/v1/quotes',
                        method: 'POST',
                        data: data
                    });
                }
                setView('list');
                fetchQuotes();
            } catch (err) {
                console.error(err);
                alert('Error saving quote: ' + (err.message || 'Unknown error'));
                setLoading(false);
            }
        };

        const handleTransition = async (action) => {
             if (!currentQuote) return;
             if (!confirm(`Are you sure you want to ${action} this quote?`)) return;
             
             setLoading(true);
             try {
                 const data = await apiFetch({ path: `/businessapp/v1/quotes/${currentQuote.id}/${action}`, method: 'POST' });
                 setCurrentQuote(data);
                 fetchQuotes(); // Refresh list
             } catch (err) {
                 console.error(err);
                 alert(`Failed to ${action} quote: ` + (err.message || 'Unknown error'));
             } finally {
                 setLoading(false);
             }
        };

        const handleConvertToJob = async (quoteId) => {
             if (!confirm('Are you sure you want to convert this quote to a job?')) return;
             setLoading(true);
             try {
                 const job = await apiFetch({ path: `/businessapp/v1/quotes/${quoteId}/convert`, method: 'POST' });
                 alert('Job created successfully! Job ID: ' + job.id);
                 window.location.href = 'admin.php?page=businessapp-jobs';
             } catch (err) {
                 console.error(err);
                 alert('Failed to convert quote: ' + (err.message || 'Unknown error'));
             } finally {
                 setLoading(false);
             }
        };

        if (loading && view === 'list' && quotes.length === 0) {
            return el('div', { style: { padding: '20px', textAlign: 'center' } },
                el(Spinner), ' Loading Quotes...'
            );
        }

        return el('div', { className: 'businessapp-quotes-app' },
            error && el('div', { className: 'notice notice-error' }, el('p', null, error)),
            
            view === 'list' && el(QuoteList, {
                quotes: quotes,
                onCreate: handleCreate,
                onEdit: (quote) => handleEdit(quote.id),
                onView: (quote) => handleView(quote.id)
            }),
            
            (view === 'create' || view === 'edit') && el(QuoteEditor, {
                quote: currentQuote,
                onSave: handleSave,
                onCancel: () => setView('list'),
                loading: loading,
                activeSchema: activeSchema
            }),
            
            view === 'detail' && el(QuoteDetail, {
                quote: currentQuote,
                onBack: () => setView('list'),
                onEdit: () => handleEdit(currentQuote.id),
                onTransition: handleTransition,
                onConvertToJob: handleConvertToJob
            })
        );
    };

    const QuoteList = ({ quotes, onCreate, onEdit, onView }) => {
        return el('div', null,
            el('div', { className: 'businessapp-header', style: { display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '20px', marginTop: '20px' } },
                el('h1', { style: { margin: 0 } }, 'Quotes'),
                el(Button, { isPrimary: true, onClick: onCreate }, 'Create New Quote')
            ),
            el('div', { className: 'businessapp-table-container', style: { background: '#fff', boxShadow: '0 1px 1px rgba(0,0,0,.04)', border: '1px solid #c3c4c7' } },
                el('table', { className: 'wp-list-table widefat fixed striped' },
                    el('thead', null,
                        el('tr', null,
                            el('th', { style: { width: '80px' } }, 'ID'),
                            el('th', null, 'Title'),
                            el('th', null, 'Customer'),
                            el('th', { style: { width: '120px', textAlign: 'right' } }, 'Amount'),
                            el('th', { style: { width: '100px' } }, 'Status'),
                            el('th', { style: { width: '120px' } }, 'Date'),
                            el('th', { style: { width: '150px' } }, 'Actions')
                        )
                    ),
                    el('tbody', null,
                        quotes.map(quote => el('tr', { key: quote.id },
                            el('td', null, `Q-${quote.id}`),
                            el('td', null, 
                                el('a', { 
                                    href: '#', 
                                    onClick: (e) => { e.preventDefault(); onView(quote); },
                                    style: { fontWeight: 'bold' }
                                }, quote.title || '(No Title)')
                            ),
                            el('td', null, quote.customer_name || 'Unknown'),
                            el('td', { style: { textAlign: 'right' } }, `$${Number(quote.total_amount).toFixed(2)}`),
                            el('td', null, el('span', { className: `businessapp-badge ${quote.status}` }, quoteStatuses[quote.status] || quote.status)),
                            el('td', null, quote.created_at ? new Date(quote.created_at).toLocaleDateString() : '-'),
                            el('td', null,
                                el(Button, { isSmall: true, isSecondary: true, onClick: () => onView(quote), style: { marginRight: '5px' } }, 'View'),
                                quote.status === 'draft' && el(Button, { isSmall: true, isSecondary: true, onClick: () => onEdit(quote) }, 'Edit')
                            )
                        )),
                        quotes.length === 0 && el('tr', null,
                            el('td', { colSpan: 7, style: { textAlign: 'center', padding: '20px' } }, 'No quotes found. Create one to get started.')
                        )
                    )
                )
            )
        );
    };

    const QuoteEditor = ({ quote, onSave, onCancel, loading, activeSchema }) => {
        const [title, setTitle] = useState(quote ? quote.title : '');
        const [customerId, setCustomerId] = useState(quote ? quote.customer_id : '');
        const [customerName, setCustomerName] = useState(quote ? quote.customer_name : '');
        const [customerEmail, setCustomerEmail] = useState(quote ? quote.customer_email : '');
        const [customerPhone, setCustomerPhone] = useState(quote ? quote.customer_phone : '');
        const [notes, setNotes] = useState(quote ? quote.notes : '');
        const [items, setItems] = useState(quote && quote.items ? quote.items : []);
        const [dynamicFields, setDynamicFields] = useState(quote && quote.dynamic_fields ? quote.dynamic_fields : {});
        const [associatedEntityIds, setAssociatedEntityIds] = useState(quote && quote.associated_entity_ids ? quote.associated_entity_ids.join(', ') : '');
        const [customers, setCustomers] = useState([]);

        useEffect(() => {
            const fetchCustomers = async () => {
                try {
                    const data = await apiFetch({ path: '/businessapp/v1/customers' });
                    setCustomers(data);
                } catch (err) {
                    console.error('Failed to load customers', err);
                }
            };
            fetchCustomers();
        }, []);

        const handleCustomerChange = (selectedId) => {
            setCustomerId(selectedId);
            if (selectedId) {
                const customer = customers.find(c => c.id == selectedId);
                if (customer) {
                    setCustomerName(customer.name);
                    setCustomerEmail(customer.email);
                    setCustomerPhone(customer.phone);
                }
            }
        };

        const handleAddItem = () => {
            setItems([...items, { description: '', qty: 1, unit_price: 0, unit: 'ea' }]);
        };

        const handleItemChange = (index, field, value) => {
            const newItems = [...items];
            newItems[index][field] = value;
            setItems(newItems);
        };

        const handleRemoveItem = (index) => {
            const newItems = items.filter((_, i) => i !== index);
            setItems(newItems);
        };

        const handleSubmit = () => {
             const data = {
                 title,
                 customer_id: customerId,
                 customer_name: customerName,
                 customer_email: customerEmail,
                 customer_phone: customerPhone,
                 notes,
                 items: items.map(item => ({
                     description: item.description,
                     qty: parseFloat(item.qty) || 0,
                     unit_price: parseFloat(item.unit_price) || 0,
                     unit: item.unit || 'ea'
                 })),
                 dynamic_fields: dynamicFields,
                 associated_entity_ids: associatedEntityIds.split(',').map(s => s.trim()).filter(s => s)
             };
             onSave(data);
        };

        return el('div', null,
            el('div', { className: 'businessapp-header', style: { display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '20px', marginTop: '20px' } },
                el('h1', { style: { margin: 0 } }, quote ? 'Edit Quote' : 'New Quote'),
                el('div', null,
                    el(Button, { isSecondary: true, onClick: onCancel, style: { marginRight: '10px' }, disabled: loading }, 'Cancel'),
                    el(Button, { isPrimary: true, onClick: handleSubmit, isBusy: loading, disabled: loading }, 'Save Quote')
                )
            ),
            el('div', { className: 'businessapp-editor-form', style: { background: '#fff', padding: '20px', border: '1px solid #c3c4c7', maxWidth: '800px' } },
                el(TextControl, { label: 'Title', value: title, onChange: setTitle, placeholder: 'e.g. Bathroom Renovation' }),
                el('div', { style: { display: 'flex', gap: '20px' } },
                    el('div', { style: { flex: 1 } },
                        el(SelectControl, {
                            label: 'Select Customer',
                            value: customerId,
                            options: [
                                { label: 'Select a customer...', value: '' },
                                ...customers.map(c => ({ label: c.name, value: c.id }))
                            ],
                            onChange: handleCustomerChange
                        }),
                        el(TextControl, { label: 'Customer Name', value: customerName, onChange: setCustomerName })
                    ),
                    el('div', { style: { flex: 1 } },
                        el(TextControl, { label: 'Customer Phone', value: customerPhone, onChange: setCustomerPhone }),
                        el(TextControl, { label: 'Customer Email', value: customerEmail, onChange: setCustomerEmail })
                    )
                ),
                el(TextareaControl, { label: 'Notes', value: notes, onChange: setNotes }),

                // Dynamic Fields
                activeSchema && activeSchema.fields && el('div', { style: { marginTop: '20px', marginBottom: '20px', padding: '15px', background: '#f9f9f9', border: '1px solid #ddd' } },
                    el('h3', { style: { marginTop: 0 } }, `${activeSchema.name} Details`),
                    activeSchema.fields.map(field => (
                        el(TextControl, {
                            key: field.id,
                            label: field.label,
                            value: dynamicFields[field.id] || '',
                            onChange: (val) => setDynamicFields({ ...dynamicFields, [field.id]: val }),
                            help: field.description
                        })
                    ))
                ),

                // Associated Entities
                el('div', { style: { marginTop: '20px', marginBottom: '20px' } },
                    el(TextControl, {
                        label: `Associated ${activeSchema && activeSchema.associated_entity_name ? activeSchema.associated_entity_name : 'Entity'} IDs`,
                        value: associatedEntityIds,
                        onChange: setAssociatedEntityIds,
                        help: 'Comma separated IDs'
                    })
                ),
                
                el('h3', null, 'Line Items'),
                el('table', { className: 'widefat striped', style: { marginBottom: '10px' } },
                    el('thead', null,
                        el('tr', null,
                            el('th', null, 'Description'),
                            el('th', { style: { width: '80px' } }, 'Qty'),
                            el('th', { style: { width: '80px' } }, 'Unit'),
                            el('th', { style: { width: '100px' } }, 'Price'),
                            el('th', { style: { width: '100px' } }, 'Total'),
                            el('th', { style: { width: '50px' } }, '')
                        )
                    ),
                    el('tbody', null,
                        items.map((item, index) => el('tr', { key: index },
                            el('td', null, el(TextControl, { value: item.description, onChange: (val) => handleItemChange(index, 'description', val), hideLabelFromVision: true })),
                            el('td', null, el(TextControl, { type: 'number', value: item.qty, onChange: (val) => handleItemChange(index, 'qty', val), hideLabelFromVision: true })),
                            el('td', null, el(TextControl, { value: item.unit, onChange: (val) => handleItemChange(index, 'unit', val), hideLabelFromVision: true })),
                            el('td', null, el(TextControl, { type: 'number', value: item.unit_price, onChange: (val) => handleItemChange(index, 'unit_price', val), hideLabelFromVision: true })),
                            el('td', null, `$${((parseFloat(item.qty) || 0) * (parseFloat(item.unit_price) || 0)).toFixed(2)}`),
                            el('td', null, el(Button, { isDestructive: true, isSmall: true, onClick: () => handleRemoveItem(index), icon: 'trash', label: 'Remove' }))
                        )),
                        items.length === 0 && el('tr', null, el('td', { colSpan: 6 }, 'No items. Add one below.'))
                    )
                ),
                el(Button, { isSecondary: true, onClick: handleAddItem }, 'Add Item')
            )
        );
    };

    const QuoteDetail = ({ quote, onBack, onEdit, onTransition, onConvertToJob }) => {
        return el('div', null,
            el('div', { className: 'businessapp-header', style: { display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '20px', marginTop: '20px' } },
                el('h1', { style: { margin: 0 } }, `Quote Q-${quote.id}`),
                el('div', null,
                    el(Button, { isSecondary: true, onClick: onBack, style: { marginRight: '10px' } }, 'Back'),
                    quote.status === 'draft' && el(Button, { isPrimary: true, onClick: onEdit, style: { marginRight: '10px' } }, 'Edit'),
                    quote.status === 'draft' && el(Button, { isSecondary: true, onClick: () => onTransition('send') }, 'Mark Sent'),
                    quote.status === 'sent' && el(Button, { isPrimary: true, onClick: () => onTransition('accept'), style: { marginRight: '10px' } }, 'Mark Accepted'),
                    quote.status === 'sent' && el(Button, { isDestructive: true, onClick: () => onTransition('reject') }, 'Mark Rejected'),
                    quote.status === 'accepted' && el(Button, { isPrimary: true, onClick: () => onConvertToJob(quote.id) }, 'Convert to Job')
                )
            ),
            el('div', { style: { background: '#fff', padding: '20px', border: '1px solid #c3c4c7', maxWidth: '800px' } },
                el('div', { style: { borderBottom: '1px solid #eee', paddingBottom: '10px', marginBottom: '20px' } },
                    el('h2', { style: { marginTop: 0 } }, quote.title),
                    el('span', { className: `businessapp-badge ${quote.status}` }, quoteStatuses[quote.status] || quote.status)
                ),
                el('div', { style: { display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '20px', marginBottom: '20px' } },
                    el('div', null,
                        el('h3', null, 'Customer'),
                        el('p', null, el('strong', null, 'Name: '), quote.customer_name),
                        el('p', null, el('strong', null, 'Email: '), quote.customer_email),
                        el('p', null, el('strong', null, 'Phone: '), quote.customer_phone || '-')
                    ),
                    el('div', { style: { textAlign: 'right' } },
                        el('h3', null, 'Summary'),
                        el('p', { style: { fontSize: '1.2em', fontWeight: 'bold' } }, `Total: $${Number(quote.total_amount).toFixed(2)}`),
                        el('p', null, `Date: ${new Date(quote.created_at).toLocaleDateString()}`),
                        quote.public_url && el('p', null, el('a', { href: quote.public_url, target: '_blank', rel: 'noopener noreferrer' }, 'View Public Quote'))
                    )
                ),
                
                // Dynamic Fields Display
                quote.dynamic_fields && Object.keys(quote.dynamic_fields).length > 0 && el('div', { style: { marginBottom: '20px', padding: '15px', background: '#f9f9f9' } },
                    el('h3', { style: { marginTop: 0 } }, 'Details'),
                    el('ul', { style: { listStyle: 'none', padding: 0, margin: 0 } },
                        Object.entries(quote.dynamic_fields).map(([key, value]) => el('li', { key: key, style: { marginBottom: '5px' } },
                            el('strong', null, key + ': '), value
                        ))
                    )
                ),

                // Associated Entities Display
                quote.associated_entity_ids && quote.associated_entity_ids.length > 0 && el('div', { style: { marginBottom: '20px' } },
                    el('h3', null, 'Associated Entities'),
                    el('p', null, quote.associated_entity_ids.join(', '))
                ),

                el('h3', null, 'Line Items'),
                el('table', { className: 'widefat striped' },
                    el('thead', null,
                        el('tr', null,
                            el('th', null, 'Description'),
                            el('th', null, 'Qty'),
                            el('th', null, 'Unit'),
                            el('th', null, 'Price'),
                            el('th', null, 'Total')
                        )
                    ),
                    el('tbody', null,
                        quote.items && quote.items.map((item, i) => el('tr', { key: i },
                            el('td', null, item.description),
                            el('td', null, item.qty),
                            el('td', null, item.unit),
                            el('td', null, `$${Number(item.unitPrice || item.unit_price).toFixed(2)}`),
                            el('td', null, `$${(item.qty * (item.unitPrice || item.unit_price)).toFixed(2)}`)
                        ))
                    )
                ),
                quote.notes && el('div', { style: { marginTop: '20px' } },
                    el('h3', null, 'Notes'),
                    el('p', { style: { whiteSpace: 'pre-wrap' } }, quote.notes)
                )
            )
        );
    };

    // Mount
    const container = document.getElementById('businessapp-quotes-app');
    if (container) {
        wp.element.render(el(QuotesApp), container);
    }

})(window.wp);