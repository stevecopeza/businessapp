(function(wp) {
    const { element, components, i18n, apiFetch } = wp;
    const { render, useState, useEffect, createElement: el, Fragment } = element;
    const { Button, TextControl, TextareaControl, PanelBody, PanelRow, SelectControl, Spinner, Modal } = components;
    const { __ } = i18n;

    // Configure API
    if (window.businessappData) {
        apiFetch.use(apiFetch.createNonceMiddleware(window.businessappData.nonce));
        apiFetch.use(apiFetch.createRootURLMiddleware(window.businessappData.root));
    }

    const JobsApp = () => {
        const [view, setView] = useState('list'); // list, edit
        const [jobs, setJobs] = useState([]);
        const [customers, setCustomers] = useState([]);
        const [quotes, setQuotes] = useState([]);
        const [currentJob, setCurrentJob] = useState(null);
        const [loading, setLoading] = useState(false);

        useEffect(() => {
            fetchJobs();
            fetchCustomers();
            fetchQuotes();
        }, []);

        const fetchJobs = async () => {
            setLoading(true);
            try {
                const response = await apiFetch({ path: '/businessapp/v1/jobs' });
                setJobs(response);
            } catch (error) {
                console.error('Error fetching jobs:', error);
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

        const fetchQuotes = async () => {
            try {
                const response = await apiFetch({ path: '/businessapp/v1/quotes' });
                setQuotes(response);
            } catch (error) {
                console.error('Error fetching quotes:', error);
            }
        };

        const handleEditJob = (job) => {
            setCurrentJob(job);
            setView('edit');
        };

        const handleCreateJob = () => {
            setCurrentJob({
                id: 0,
                title: '',
                notes: '',
                status: 'active',
                customer_id: '',
                quote_id: 0,
                start_date: '',
                end_date: ''
            });
            setView('edit');
        };

        const handleSaveJob = async (jobData) => {
            setLoading(true);
            try {
                const isNew = jobData.id === 0;
                const path = isNew ? '/businessapp/v1/jobs' : `/businessapp/v1/jobs/${jobData.id}`;
                const method = 'POST';

                const savedJob = await apiFetch({
                    path,
                    method,
                    data: jobData
                });

                if (isNew) {
                    setJobs([savedJob, ...jobs]);
                } else {
                    setJobs(jobs.map(j => j.id === savedJob.id ? savedJob : j));
                }

                setView('list');
                setCurrentJob(null);
            } catch (error) {
                console.error('Error saving job:', error);
                alert('Failed to save job');
            } finally {
                setLoading(false);
            }
        };

        return el('div', { className: 'businessapp-jobs wrap' },
            el('div', { className: 'header', style: { display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '20px' } },
                el('h1', { className: 'wp-heading-inline' }, __('Jobs', 'businessapp')),
                view === 'list' && el(Button, { isPrimary: true, onClick: handleCreateJob }, __('Create Job', 'businessapp'))
            ),

            loading && el(Spinner),

            view === 'list' && !loading && el(JobList, { jobs: jobs, customers: customers, quotes: quotes, onEdit: handleEditJob }),

            view === 'edit' && el(JobEditor, {
                job: currentJob,
                customers: customers,
                quotes: quotes,
                onSave: handleSaveJob,
                onCancel: () => {
                    setView('list');
                    setCurrentJob(null);
                }
            })
        );
    };

    const JobList = ({ jobs, customers, quotes, onEdit }) => {
        const getCustomerName = (id) => {
            const customer = customers.find(c => c.id == id);
            return customer ? customer.name : id;
        };

        const getQuoteRef = (id) => {
            const quote = quotes.find(q => q.id == id);
            return quote ? (quote.title || `Q-${quote.id}`) : (id ? `Q-${id}` : '-');
        };

        return el('table', { className: 'wp-list-table widefat fixed striped' },
            el('thead', null,
                el('tr', null,
                    el('th', null, __('Title', 'businessapp')),
                    el('th', null, __('Status', 'businessapp')),
                    el('th', null, __('Customer', 'businessapp')),
                    el('th', null, __('Quote', 'businessapp')),
                    el('th', null, __('Start Date', 'businessapp')),
                    el('th', null, __('Actions', 'businessapp'))
                )
            ),
            el('tbody', null,
                jobs.length > 0 ? jobs.map(job => el('tr', { key: job.id },
                    el('td', null,
                        el('a', { href: '#', onClick: (e) => { e.preventDefault(); onEdit(job); } }, job.title)
                    ),
                    el('td', null, el('span', { className: `businessapp-badge ${job.status}` }, job.status)),
                    el('td', null, getCustomerName(job.customer_id)),
                    el('td', null, getQuoteRef(job.quote_id)),
                    el('td', null, job.start_date),
                    el('td', null,
                        el(Button, { isSmall: true, variant: 'secondary', onClick: () => onEdit(job) }, __('Edit', 'businessapp'))
                    )
                )) : el('tr', null, el('td', { colSpan: 6 }, __('No jobs found.', 'businessapp')))
            )
        );
    };

    const JobEditor = ({ job, customers, quotes, onSave, onCancel }) => {
        const [formData, setFormData] = useState({ items: [], ...job });

        const handleChange = (key, value) => {
            setFormData({ ...formData, [key]: value });
        };

        const handleQuoteChange = (quoteId) => {
            const updates = { quote_id: quoteId };
            // If quote selected and no customer, or to keep sync, auto-select customer?
            // Let's simple: if quote has customer, switch to it.
            if (quoteId) {
                const quote = quotes.find(q => q.id == quoteId);
                if (quote && quote.customer_id) {
                    updates.customer_id = quote.customer_id;
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
            const newItems = [...(formData.items || []), { description: '', qty: 1, unit: 'ea', unit_price: 0 }];
            setFormData({ ...formData, items: newItems });
        };

        const handleRemoveItem = (index) => {
            const newItems = [...(formData.items || [])];
            newItems.splice(index, 1);
            setFormData({ ...formData, items: newItems });
        };

        return el('div', { className: 'job-editor' },
            el(PanelBody, { title: __('Job Details', 'businessapp') },
                el(PanelRow, null,
                    el(TextControl, {
                        label: __('Title', 'businessapp'),
                        value: formData.title,
                        onChange: (val) => handleChange('title', val)
                    })
                ),
                el(PanelRow, null,
                    el(SelectControl, {
                        label: __('Quote', 'businessapp'),
                        value: formData.quote_id,
                        options: [
                            { label: __('Select Quote', 'businessapp'), value: 0 },
                            ...quotes.map(q => ({ label: q.title ? `${q.title} (Q-${q.id})` : `Q-${q.id}`, value: q.id }))
                        ],
                        onChange: (val) => handleQuoteChange(val)
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
                        options: [
                            { label: 'Active', value: 'active' },
                            { label: 'Completed', value: 'completed' },
                            { label: 'Cancelled', value: 'cancelled' }
                        ],
                        onChange: (val) => handleChange('status', val)
                    })
                ),
                el(PanelRow, null,
                    el(TextareaControl, {
                        label: __('Notes', 'businessapp'),
                        value: formData.notes,
                        onChange: (val) => handleChange('notes', val)
                    })
                ),
                el(PanelRow, null,
                    el(TextControl, {
                        label: __('Start Date', 'businessapp'),
                        type: 'datetime-local',
                        value: formData.start_date || '',
                        onChange: (val) => handleChange('start_date', val)
                    })
                ),
                el(PanelRow, null,
                    el(TextControl, {
                        label: __('End Date', 'businessapp'),
                        type: 'datetime-local',
                        value: formData.end_date || '',
                        onChange: (val) => handleChange('end_date', val)
                    })
                )
            ),

            formData.dynamic_fields && Object.keys(formData.dynamic_fields).length > 0 && el(PanelBody, { title: __('Additional Info', 'businessapp'), initialOpen: false },
                Object.entries(formData.dynamic_fields).map(([key, value]) => 
                    el(PanelRow, { key: key },
                        el(TextControl, { label: key, value: value, readOnly: true })
                    )
                )
            ),

            el(PanelBody, { title: __('Items', 'businessapp'), initialOpen: true },
                el('table', { className: 'widefat striped' },
                    el('thead', null,
                        el('tr', null,
                            el('th', null, __('Description', 'businessapp')),
                            el('th', { style: { width: '80px' } }, __('Qty', 'businessapp')),
                            el('th', { style: { width: '80px' } }, __('Unit', 'businessapp')),
                            el('th', { style: { width: '80px' } }, __('Price', 'businessapp')),
                            el('th', { style: { width: '50px' } })
                        )
                    ),
                    el('tbody', null,
                        formData.items && formData.items.map((item, index) => 
                            el('tr', { key: index },
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
                                    el(Button, { isSmall: true, isDestructive: true, onClick: () => handleRemoveItem(index) }, '×')
                                )
                            )
                        ),
                        (!formData.items || formData.items.length === 0) && el('tr', null, el('td', { colSpan: 5 }, __('No items. Add one below.', 'businessapp')))
                    )
                ),
                el('div', { style: { marginTop: '10px' } },
                    el(Button, { isSecondary: true, onClick: handleAddItem }, __('Add Item', 'businessapp'))
                )
            ),

            el('div', { className: 'editor-actions', style: { marginTop: '20px' } },
                el(Button, { isPrimary: true, onClick: () => onSave(formData) }, __('Save Job', 'businessapp')),
                el(Button, { isSecondary: true, onClick: onCancel, style: { marginLeft: '10px' } }, __('Cancel', 'businessapp'))
            )
        );
    };

    document.addEventListener('DOMContentLoaded', () => {
        const container = document.getElementById('businessapp-jobs-root');
        if (container) {
            render(el(JobsApp), container);
        }
    });

})(window.wp);
