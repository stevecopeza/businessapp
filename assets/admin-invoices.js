(function(wp) {
    const { element, components, i18n, apiFetch } = wp;
    const { render, useState, useEffect, createElement: el, Fragment } = element;
    const { Button, TextControl, TextareaControl, PanelBody, PanelRow, SelectControl, Spinner } = components;
    const { __ } = i18n;

    if (window.businessappData) {
        apiFetch.use(apiFetch.createNonceMiddleware(window.businessappData.nonce));
        apiFetch.use(apiFetch.createRootURLMiddleware(window.businessappData.root));
    }

    const workflowSettings = window.businessappData && window.businessappData.settings && window.businessappData.settings.workflow ? window.businessappData.settings.workflow : {};
    const invoiceStatuses = workflowSettings.invoice || {
        'draft': 'Draft',
        'sent': 'Sent',
        'paid': 'Paid',
        'outstanding': 'Outstanding',
        'writeoff': 'Writeoff'
    };

    const InvoicesApp = () => {
        const [view, setView] = useState('list'); // list, edit
        const [invoices, setInvoices] = useState([]);
        const [customers, setCustomers] = useState([]);
        const [jobs, setJobs] = useState([]);
        const [currentInvoice, setCurrentInvoice] = useState(null);
        const [loading, setLoading] = useState(false);

        useEffect(() => {
            fetchInvoices();
            fetchCustomers();
            fetchJobs();
        }, []);

        const fetchInvoices = async () => {
            setLoading(true);
            try {
                const response = await apiFetch({ path: '/businessapp/v1/invoices' });
                setInvoices(response);
            } catch (error) {
                console.error('Error fetching invoices:', error);
            } finally {
                setLoading(false);
            }
        };

        const fetchCustomers = async () => {
            try {
                const response = await apiFetch({ path: '/businessapp/v1/customers' });
                setCustomers(response);
            } catch (error) {
                console.error('Error fetching customers:', error);
            }
        };

        const fetchJobs = async () => {
            try {
                const response = await apiFetch({ path: '/businessapp/v1/jobs' });
                setJobs(response);
            } catch (error) {
                console.error('Error fetching jobs:', error);
            }
        };

        const handleEditInvoice = (invoice) => {
            setCurrentInvoice(invoice);
            setView('edit');
        };

        const handleCreateInvoice = () => {
            setCurrentInvoice({
                id: 0,
                title: '',
                notes: '',
                status: 'draft',
                customer_id: '',
                job_id: 0,
                items: []
            });
            setView('edit');
        };

        const handleSaveInvoice = async (invoiceData) => {
            setLoading(true);
            try {
                const isNew = invoiceData.id === 0;
                const path = isNew ? '/businessapp/v1/invoices' : `/businessapp/v1/invoices/${invoiceData.id}`;
                const method = 'POST';

                const savedInvoice = await apiFetch({
                    path,
                    method,
                    data: invoiceData
                });

                if (isNew) {
                    setInvoices([savedInvoice, ...invoices]);
                } else {
                    setInvoices(invoices.map(i => i.id === savedInvoice.id ? savedInvoice : i));
                }

                setView('list');
                setCurrentInvoice(null);
            } catch (error) {
                console.error('Error saving invoice:', error);
                alert('Failed to save invoice');
            } finally {
                setLoading(false);
            }
        };

        const handleSendInvoice = async (invoiceId) => {
            if (!confirm(__('Are you sure you want to send this invoice? This will mark it as sent and generate a public link.', 'businessapp'))) return;
            setLoading(true);
            try {
                const sentInvoice = await apiFetch({
                    path: `/businessapp/v1/invoices/${invoiceId}/send`,
                    method: 'POST'
                });
                setInvoices(invoices.map(i => i.id === sentInvoice.id ? sentInvoice : i));
                alert(__('Invoice sent successfully.', 'businessapp'));
            } catch (error) {
                console.error('Error sending invoice:', error);
                alert('Failed to send invoice: ' + (error.message || 'Unknown error'));
            } finally {
                setLoading(false);
            }
        };

        return el('div', { className: 'businessapp-invoices wrap' },
            el('div', { className: 'header', style: { display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '20px' } },
                el('h1', { className: 'wp-heading-inline' }, __('Invoices', 'businessapp')),
                view === 'list' && el(Button, { isPrimary: true, onClick: handleCreateInvoice }, __('Create Invoice', 'businessapp'))
            ),

            loading && el(Spinner),

            view === 'list' && !loading && el(InvoiceList, { 
                invoices: invoices, 
                customers: customers, 
                jobs: jobs, 
                onEdit: handleEditInvoice,
                onSend: handleSendInvoice
            }),

            view === 'edit' && el(InvoiceEditor, {
                invoice: currentInvoice,
                customers: customers,
                jobs: jobs,
                onSave: handleSaveInvoice,
                onCancel: () => {
                    setView('list');
                    setCurrentInvoice(null);
                }
            })
        );
    };

    const InvoiceList = ({ invoices, customers, jobs, onEdit, onSend }) => {
        const getCustomerName = (id) => {
            const customer = customers.find(c => c.id == id);
            return customer ? customer.name : id;
        };

        const getJobTitle = (id) => {
            const job = jobs.find(j => j.id == id);
            return job ? job.title : (id ? `Job #${id}` : '-');
        };

        const formatCurrency = (amount) => {
            return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(amount);
        };

        return el('table', { className: 'wp-list-table widefat fixed striped' },
            el('thead', null,
                el('tr', null,
                    el('th', null, __('Title', 'businessapp')),
                    el('th', null, __('Status', 'businessapp')),
                    el('th', null, __('Customer', 'businessapp')),
                    el('th', null, __('Job', 'businessapp')),
                    el('th', null, __('Total', 'businessapp')),
                    el('th', null, __('Date', 'businessapp')),
                    el('th', null, __('Actions', 'businessapp'))
                )
            ),
            el('tbody', null,
                invoices.length > 0 ? invoices.map(invoice => el('tr', { key: invoice.id },
                    el('td', null,
                        el('a', { href: '#', onClick: (e) => { e.preventDefault(); onEdit(invoice); } }, invoice.title)
                    ),
                    el('td', null, el('span', { className: `businessapp-badge ${invoice.status}` }, invoiceStatuses[invoice.status] || invoice.status)),
                    el('td', null, getCustomerName(invoice.customer_id)),
                    el('td', null, getJobTitle(invoice.job_id)),
                    el('td', null, formatCurrency(invoice.total_amount)),
                    el('td', null, invoice.created_at),
                    el('td', null,
                        el(Button, { isSmall: true, variant: 'secondary', onClick: () => onEdit(invoice), style: { marginRight: '4px' } }, __('Edit', 'businessapp')),
                        el(Button, { 
                            isSmall: true, 
                            variant: 'secondary', 
                            onClick: () => onSend(invoice.id),
                            disabled: invoice.status !== 'draft',
                            style: { marginRight: '4px' }
                        }, __('Send', 'businessapp')),
                        invoice.public_url && el(Button, {
                            isSmall: true,
                            variant: 'link',
                            href: invoice.public_url,
                            target: '_blank'
                        }, __('View', 'businessapp'))
                    )
                )) : el('tr', null, el('td', { colSpan: 7 }, __('No invoices found.', 'businessapp')))
            )
        );
    };

    const InvoiceEditor = ({ invoice, customers, jobs, onSave, onCancel }) => {
        const [formData, setFormData] = useState({ items: [], ...invoice });

        // Calculate total for display
        const calculateTotal = (items) => {
            return items.reduce((sum, item) => sum + (Number(item.qty) * Number(item.unit_price)), 0);
        };

        const [total, setTotal] = useState(calculateTotal(formData.items || []));

        useEffect(() => {
            setTotal(calculateTotal(formData.items || []));
        }, [formData.items]);

        const handleChange = (key, value) => {
            setFormData({ ...formData, [key]: value });
        };

        const handleJobChange = (jobId) => {
            const updates = { job_id: jobId };
            if (jobId) {
                const job = jobs.find(j => j.id == jobId);
                if (job && job.customer_id) {
                    updates.customer_id = job.customer_id;
                }
            }
            setFormData({ ...formData, ...updates });
        };

        const handleItemChange = (index, key, value) => {
            const newItems = [...(formData.items || [])];
            newItems[index] = { ...newItems[index], [key]: value };
            setFormData({ ...formData, items: newItems });
        };

        const handleAddItem = () => {
            const newItems = [...(formData.items || []), { description: '', qty: 1, unit: 'ea', unit_price: 0, type: 'labor' }];
            setFormData({ ...formData, items: newItems });
        };

        const handleRemoveItem = (index) => {
            const newItems = [...(formData.items || [])];
            newItems.splice(index, 1);
            setFormData({ ...formData, items: newItems });
        };

        const formatCurrency = (amount) => {
            return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(amount);
        };

        return el('div', { className: 'invoice-editor' },
            el(PanelBody, { title: __('Invoice Details', 'businessapp') },
                el(PanelRow, null,
                    el(TextControl, {
                        label: __('Title', 'businessapp'),
                        value: formData.title,
                        onChange: (val) => handleChange('title', val)
                    })
                ),
                el(PanelRow, null,
                    el(SelectControl, {
                        label: __('Job', 'businessapp'),
                        value: formData.job_id,
                        options: [
                            { label: __('Select Job', 'businessapp'), value: 0 },
                            ...jobs.map(j => ({ label: j.title ? `${j.title} (#${j.id})` : `Job #${j.id}`, value: j.id }))
                        ],
                        onChange: (val) => handleJobChange(val)
                    })
                ),
                el(PanelRow, null,
                    el(SelectControl, {
                        label: __('Customer', 'businessapp'),
                        value: formData.customer_id,
                        options: [
                            { label: __('Select Customer', 'businessapp'), value: '' },
                            ...customers.map(c => ({ label: c.name, value: c.id }))
                        ],
                        onChange: (val) => handleChange('customer_id', val)
                    })
                ),
                el(PanelRow, null,
                    el(SelectControl, {
                        label: __('Status', 'businessapp'),
                        value: formData.status,
                        options: Object.entries(invoiceStatuses).map(([value, label]) => ({ label, value })),
                        onChange: (val) => handleChange('status', val)
                    })
                ),
                el(PanelRow, null,
                    el(TextareaControl, {
                        label: __('Notes', 'businessapp'),
                        value: formData.notes,
                        onChange: (val) => handleChange('notes', val)
                    })
                )
            ),

            el(PanelBody, { title: __('Items', 'businessapp'), initialOpen: true },
                el('table', { className: 'widefat striped' },
                    el('thead', null,
                        el('tr', null,
                            el('th', { style: { width: '100px' } }, __('Type', 'businessapp')),
                            el('th', null, __('Description', 'businessapp')),
                            el('th', { style: { width: '80px' } }, __('Qty', 'businessapp')),
                            el('th', { style: { width: '80px' } }, __('Unit', 'businessapp')),
                            el('th', { style: { width: '80px' } }, __('Price', 'businessapp')),
                            el('th', { style: { width: '80px' } }, __('Total', 'businessapp')),
                            el('th', { style: { width: '50px' } })
                        )
                    ),
                    el('tbody', null,
                        formData.items && formData.items.map((item, index) => 
                            el('tr', { key: index },
                                el('td', null,
                                    el(SelectControl, {
                                        value: item.type || 'labor',
                                        options: [
                                            { label: 'Labor', value: 'labor' },
                                            { label: 'Material', value: 'material' },
                                            { label: 'Fee', value: 'fee' }
                                        ],
                                        onChange: (val) => handleItemChange(index, 'type', val)
                                    })
                                ),
                                el('td', null,
                                    el(TextControl, {
                                        value: item.description,
                                        onChange: (val) => handleItemChange(index, 'description', val)
                                    })
                                ),
                                el('td', null,
                                    el(TextControl, {
                                        type: 'number',
                                        value: item.qty,
                                        onChange: (val) => handleItemChange(index, 'qty', val)
                                    })
                                ),
                                el('td', null,
                                    el(TextControl, {
                                        value: item.unit,
                                        onChange: (val) => handleItemChange(index, 'unit', val)
                                    })
                                ),
                                el('td', null,
                                    el(TextControl, {
                                        type: 'number',
                                        value: item.unit_price,
                                        onChange: (val) => handleItemChange(index, 'unit_price', val)
                                    })
                                ),
                                el('td', null,
                                    formatCurrency(Number(item.qty) * Number(item.unit_price))
                                ),
                                el('td', null,
                                    el(Button, { isSmall: true, isDestructive: true, onClick: () => handleRemoveItem(index) }, '×')
                                )
                            )
                        ),
                        (!formData.items || formData.items.length === 0) && el('tr', null, el('td', { colSpan: 7 }, __('No items. Add one below.', 'businessapp')))
                    )
                ),
                el('div', { style: { marginTop: '10px', display: 'flex', justifyContent: 'space-between', alignItems: 'center' } },
                    el(Button, { isSecondary: true, onClick: handleAddItem }, __('Add Item', 'businessapp')),
                    el('h3', null, `${__('Total:', 'businessapp')} ${formatCurrency(total)}`)
                )
            ),

            el('div', { className: 'editor-actions', style: { marginTop: '20px' } },
                el(Button, { isPrimary: true, onClick: () => onSave(formData) }, __('Save Invoice', 'businessapp')),
                el(Button, { isSecondary: true, onClick: onCancel, style: { marginLeft: '10px' } }, __('Cancel', 'businessapp'))
            )
        );
    };

    document.addEventListener('DOMContentLoaded', () => {
        const container = document.getElementById('businessapp-invoices-root');
        if (container) {
            render(el(InvoicesApp), container);
        }
    });

})(window.wp);
