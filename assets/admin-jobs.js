
const { render, useState, useEffect } = wp.element;
const { Button, TextControl, TextareaControl, PanelBody, PanelRow, SelectControl, Spinner, Modal } = wp.components;
const apiFetch = wp.apiFetch;
const { __ } = wp.i18n;

const JobsApp = () => {
    const [view, setView] = useState('list'); // list, edit
    const [jobs, setJobs] = useState([]);
    const [currentJob, setCurrentJob] = useState(null);
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        fetchJobs();
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
            customer_id: 0,
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

    return (
        <div className="businessapp-jobs">
            <div className="header">
                <h1>{__('Jobs', 'businessapp')}</h1>
                {view === 'list' && (
                    <Button isPrimary onClick={handleCreateJob}>
                        {__('Create Job', 'businessapp')}
                    </Button>
                )}
            </div>

            {loading && <Spinner />}

            {view === 'list' && !loading && (
                <JobList 
                    jobs={jobs} 
                    onEdit={handleEditJob} 
                />
            )}

            {view === 'edit' && (
                <JobEditor 
                    job={currentJob} 
                    onSave={handleSaveJob} 
                    onCancel={() => {
                        setView('list');
                        setCurrentJob(null);
                    }} 
                />
            )}
        </div>
    );
};

const JobList = ({ jobs, onEdit }) => {
    return (
        <table className="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>{__('Title', 'businessapp')}</th>
                    <th>{__('Status', 'businessapp')}</th>
                    <th>{__('Customer', 'businessapp')}</th>
                    <th>{__('Start Date', 'businessapp')}</th>
                    <th>{__('Actions', 'businessapp')}</th>
                </tr>
            </thead>
            <tbody>
                {jobs.map(job => (
                    <tr key={job.id}>
                        <td>
                            <a href="#" onClick={(e) => { e.preventDefault(); onEdit(job); }}>
                                {job.title}
                            </a>
                        </td>
                        <td><span className={`status-badge status-${job.status}`}>{job.status}</span></td>
                        <td>{job.customer_id}</td>
                        <td>{job.start_date}</td>
                        <td>
                            <Button isSmall variant="secondary" onClick={() => onEdit(job)}>
                                {__('Edit', 'businessapp')}
                            </Button>
                        </td>
                    </tr>
                ))}
                {jobs.length === 0 && (
                    <tr>
                        <td colSpan="5">{__('No jobs found.', 'businessapp')}</td>
                    </tr>
                )}
            </tbody>
        </table>
    );
};

const JobEditor = ({ job, onSave, onCancel }) => {
    const [formData, setFormData] = useState({ ...job });

    const handleChange = (key, value) => {
        setFormData({ ...formData, [key]: value });
    };

    return (
        <div className="job-editor">
            <PanelBody title={__('Job Details', 'businessapp')}>
                <PanelRow>
                    <TextControl
                        label={__('Title', 'businessapp')}
                        value={formData.title}
                        onChange={(val) => handleChange('title', val)}
                    />
                </PanelRow>
                <PanelRow>
                    <SelectControl
                        label={__('Status', 'businessapp')}
                        value={formData.status}
                        options={[
                            { label: 'Active', value: 'active' },
                            { label: 'Completed', value: 'completed' },
                            { label: 'Cancelled', value: 'cancelled' }
                        ]}
                        onChange={(val) => handleChange('status', val)}
                    />
                </PanelRow>
                <PanelRow>
                    <TextareaControl
                        label={__('Notes', 'businessapp')}
                        value={formData.notes}
                        onChange={(val) => handleChange('notes', val)}
                    />
                </PanelRow>
                <PanelRow>
                    <TextControl
                        label={__('Start Date', 'businessapp')}
                        type="datetime-local"
                        value={formData.start_date || ''}
                        onChange={(val) => handleChange('start_date', val)}
                    />
                </PanelRow>
                <PanelRow>
                    <TextControl
                        label={__('End Date', 'businessapp')}
                        type="datetime-local"
                        value={formData.end_date || ''}
                        onChange={(val) => handleChange('end_date', val)}
                    />
                </PanelRow>
            </PanelBody>

            {formData.dynamic_fields && Object.keys(formData.dynamic_fields).length > 0 && (
                <PanelBody title={__('Additional Info', 'businessapp')} initialOpen={false}>
                     {Object.entries(formData.dynamic_fields).map(([key, value]) => (
                         <PanelRow key={key}>
                             <TextControl label={key} value={value} readOnly />
                         </PanelRow>
                     ))}
                </PanelBody>
            )}

            <PanelBody title={__('Items', 'businessapp')} initialOpen={false}>
                <table className="widefat striped">
                    <thead>
                        <tr>
                            <th>{__('Description', 'businessapp')}</th>
                            <th>{__('Qty', 'businessapp')}</th>
                            <th>{__('Unit', 'businessapp')}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {formData.items && formData.items.map((item, index) => (
                            <tr key={index}>
                                <td>{item.description}</td>
                                <td>{item.qty}</td>
                                <td>{item.unit}</td>
                            </tr>
                        ))}
                        {(!formData.items || formData.items.length === 0) && (
                            <tr><td colSpan="3">{__('No items', 'businessapp')}</td></tr>
                        )}
                    </tbody>
                </table>
            </PanelBody>

            <div className="editor-actions">
                <Button isPrimary onClick={() => onSave(formData)}>
                    {__('Save Job', 'businessapp')}
                </Button>
                <Button isSecondary onClick={onCancel} style={{ marginLeft: '10px' }}>
                    {__('Cancel', 'businessapp')}
                </Button>
            </div>
        </div>
    );
};

document.addEventListener('DOMContentLoaded', () => {
    const container = document.getElementById('businessapp-jobs-root');
    if (container) {
        render(<JobsApp />, container);
    }
});
