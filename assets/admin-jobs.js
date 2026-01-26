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
        const workflowSettings = window.businessappData.settings.workflow || {};
        const jobStatuses = workflowSettings.job || {
            'planned': 'Planned',
            'in_progress': 'In Progress',
            'on_hold': 'On Hold',
            'completed': 'Completed',
            'cancelled': 'Cancelled'
        };

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
                    el('td', null, el('span', { className: `businessapp-badge ${job.status}` }, jobStatuses[job.status] || job.status)),
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
        const [isGeneratingInvoice, setIsGeneratingInvoice] = useState(false);

        // Lock logic: Job is locked if it was already completed/cancelled AND the user hasn't set it to active yet.
        const isLocked = job && ['completed', 'cancelled'].includes(job.status) && ['completed', 'cancelled'].includes(formData.status);
        
        // Workflow Settings
        const workflowSettings = window.businessappData.settings.workflow || {};
        const jobStatuses = workflowSettings.job || {
            'planned': 'Planned',
            'in_progress': 'In Progress',
            'on_hold': 'On Hold',
            'completed': 'Completed',
            'cancelled': 'Cancelled'
        };

        const handleChange = (key, value) => {
            setFormData({ ...formData, [key]: value });
        };

        const handleQuoteChange = (quoteId) => {
            const updates = { quote_id: quoteId };
            if (quoteId) {
                const quote = quotes.find(q => q.id == quoteId);
                if (quote) {
                    if (quote.customer_id) updates.customer_id = quote.customer_id;
                    if (!formData.title && quote.title) updates.title = quote.title; // Auto-fill title if empty
                    
                    // Copy items if job has no items
                    if ((!formData.items || formData.items.length === 0) && quote.items && quote.items.length > 0) {
                        updates.items = quote.items.map(item => ({
                            description: item.description,
                            qty: item.qty,
                            unit: item.unit,
                            unit_price: item.unit_price,
                            type: item.type || 'labor'
                        }));
                    }
                }
            }
            setFormData(prev => ({ ...prev, ...updates }));
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

        const handleGenerateInvoice = async () => {
            if (!confirm(__('Are you sure you want to generate an invoice for this job?', 'businessapp'))) {
                return;
            }
        
            setIsGeneratingInvoice(true);
            try {
                const response = await apiFetch({
                    path: `/businessapp/v1/invoices/from-job/${job.id}`,
                    method: 'POST'
                });
                alert(__('Invoice generated successfully!', 'businessapp'));
            } catch (error) {
                console.error('Error generating invoice:', error);
                alert(__('Failed to generate invoice: ', 'businessapp') + (error.message || 'Unknown error'));
            } finally {
                setIsGeneratingInvoice(false);
            }
        };

        // For new jobs, enforce Quote selection first
        const isNewJob = job.id === 0;
        const hasSelectedQuote = formData.quote_id > 0;

        return el('div', { className: 'job-editor' },
            isLocked && el('div', { className: 'notice notice-warning inline', style: { marginBottom: '20px' } },
                el('p', null, __('This job is locked because it is completed or cancelled. Change status to Active to edit details.', 'businessapp'))
            ),

            el(PanelBody, { title: __('Job Details', 'businessapp') },
                // Quote Selection - Primary for New Jobs
                el(PanelRow, null,
                    el(SelectControl, {
                        label: __('Start from Quote', 'businessapp'),
                        value: formData.quote_id,
                        options: [
                            { label: __('Select Quote', 'businessapp'), value: 0 },
                            ...quotes.map(q => ({ label: q.title ? `${q.title} (Q-${q.id})` : `Q-${q.id}`, value: q.id }))
                        ],
                        onChange: (val) => handleQuoteChange(val),
                        disabled: isLocked,
                        help: isNewJob ? __('Select a quote to automatically fill customer and job details.', 'businessapp') : ''
                    })
                ),

                // Only show other fields if not a new job OR if a quote has been selected
                (!isNewJob || hasSelectedQuote) && el(Fragment, null,
                    el(PanelRow, null,
                        el(TextControl, {
                            label: __('Title', 'businessapp'),
                            value: formData.title,
                            onChange: (val) => handleChange('title', val),
                            disabled: isLocked
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
                            onChange: (val) => handleChange('customer_id', val),
                            disabled: true, // Customer is drawn from Quote (read-only per requirement)
                            help: __('Customer is linked to the selected Quote.', 'businessapp')
                        })
                    ),
                    el(PanelRow, null,
                        el(SelectControl, {
                            label: __('Status', 'businessapp'),
                            value: formData.status,
                            options: Object.entries(jobStatuses).map(([value, label]) => ({ label, value })),
                            onChange: (val) => handleChange('status', val)
                        })
                    ),
                    el(PanelRow, null,
                        el(TextareaControl, {
                            label: __('Notes', 'businessapp'),
                            value: formData.notes,
                            onChange: (val) => handleChange('notes', val),
                            disabled: isLocked
                        })
                    ),
                    el(PanelRow, null,
                        el(TextControl, {
                            label: __('Start Date', 'businessapp'),
                            type: 'datetime-local',
                            value: formData.start_date || '',
                            onChange: (val) => handleChange('start_date', val),
                            disabled: isLocked
                        })
                    ),
                    el(PanelRow, null,
                        el(TextControl, {
                            label: __('End Date', 'businessapp'),
                            type: 'datetime-local',
                            value: formData.end_date || '',
                            onChange: (val) => handleChange('end_date', val),
                            disabled: isLocked
                        })
                    )
                )
            ),

            (!isNewJob || hasSelectedQuote) && formData.dynamic_fields && Object.keys(formData.dynamic_fields).length > 0 && el(PanelBody, { title: __('Additional Info', 'businessapp'), initialOpen: false },
                Object.entries(formData.dynamic_fields).map(([key, value]) => 
                    el(PanelRow, { key: key },
                        el(TextControl, { label: key, value: value, readOnly: true })
                    )
                )
            ),

            (!isNewJob || hasSelectedQuote) && el(PanelBody, { title: __('Items', 'businessapp'), initialOpen: true },
                el('table', { className: 'widefat striped' },
                    el('thead', null,
                        el('tr', null,
                            el('th', { style: { width: '100px' } }, __('Type', 'businessapp')),
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
                                    el(SelectControl, {
                                        value: item.type || 'labor',
                                        options: [
                                            { label: 'Labor', value: 'labor' },
                                            { label: 'Material', value: 'material' },
                                            { label: 'Fee', value: 'fee' }
                                        ],
                                        onChange: (val) => handleItemChange(index, 'type', val),
                                        disabled: isLocked
                                    })
                                ),
                                el('td', null,
                                    el(TextControl, {
                                        value: item.description,
                                        onChange: (val) => handleItemChange(index, 'description', val),
                                        disabled: isLocked
                                    })
                                ),
                                el('td', null,
                                    el(TextControl, {
                                        type: 'number',
                                        value: item.qty,
                                        onChange: (val) => handleItemChange(index, 'qty', val),
                                        disabled: isLocked
                                    })
                                ),
                                el('td', null,
                                    el(TextControl, {
                                        value: item.unit,
                                        onChange: (val) => handleItemChange(index, 'unit', val),
                                        disabled: isLocked
                                    })
                                ),
                                el('td', null,
                                    el(TextControl, {
                                        type: 'number',
                                        value: item.unit_price,
                                        onChange: (val) => handleItemChange(index, 'unit_price', val),
                                        disabled: isLocked
                                    })
                                ),
                                el('td', null,
                                    el(Button, { isSmall: true, isDestructive: true, onClick: () => handleRemoveItem(index), disabled: isLocked }, '×')
                                )
                            )
                        ),
                        (!formData.items || formData.items.length === 0) && el('tr', null, el('td', { colSpan: 6 }, __('No items. Add one below.', 'businessapp')))
                    )
                ),
                el('div', { style: { marginTop: '10px' } },
                    el(Button, { isSecondary: true, onClick: handleAddItem, disabled: isLocked }, __('Add Item', 'businessapp'))
                )
            ),

            (!isNewJob || hasSelectedQuote) && el('div', { className: 'editor-actions', style: { marginTop: '20px' } },
                el(Button, { isPrimary: true, onClick: () => onSave(formData), disabled: isLocked && formData.status === job.status }, __('Save Job', 'businessapp')),
                el(Button, { isSecondary: true, onClick: onCancel, style: { marginLeft: '10px' } }, __('Cancel', 'businessapp')),
                job.status === 'completed' && el(Button, { 
                    isSecondary: true, 
                    onClick: handleGenerateInvoice, 
                    isBusy: isGeneratingInvoice,
                    disabled: isGeneratingInvoice,
                    style: { marginLeft: '10px' } 
                }, __('Generate Invoice', 'businessapp'))
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
