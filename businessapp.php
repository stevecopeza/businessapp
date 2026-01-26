<?php

/*
Plugin Name: BusinessApp
Description: Quoting and agreement platform for small service businesses, integrated with WordPress.
Version: 0.1.0
Author: BusinessApp
*/

if (!defined('ABSPATH')) {
    exit;
}

define('BUSINESSAPP_DB_VERSION', '10');

require_once __DIR__ . '/includes/Domain/Quote.php';
require_once __DIR__ . '/includes/Domain/QuoteItem.php';
require_once __DIR__ . '/includes/Domain/Invoice.php';
require_once __DIR__ . '/includes/Domain/InvoiceItem.php';
require_once __DIR__ . '/includes/Domain/Job.php';
require_once __DIR__ . '/includes/Domain/JobItem.php';
require_once __DIR__ . '/includes/Domain/Payment.php';
require_once __DIR__ . '/includes/Domain/Customer.php';
require_once __DIR__ . '/includes/Domain/CustomerEntity.php';
require_once __DIR__ . '/includes/Domain/QuoteFactory.php';
require_once __DIR__ . '/includes/Domain/JobFactory.php';
require_once __DIR__ . '/includes/Domain/FieldDefinition.php';
require_once __DIR__ . '/includes/Domain/BusinessType.php';
require_once __DIR__ . '/includes/Domain/BusinessTypeRegistry.php';
require_once __DIR__ . '/includes/Infrastructure/QuoteRepository.php';
require_once __DIR__ . '/includes/Infrastructure/QuoteItemRepository.php';
require_once __DIR__ . '/includes/Infrastructure/InvoiceRepository.php';
require_once __DIR__ . '/includes/Infrastructure/InvoiceItemRepository.php';
require_once __DIR__ . '/includes/Infrastructure/JobRepository.php';
require_once __DIR__ . '/includes/Infrastructure/JobItemRepository.php';
require_once __DIR__ . '/includes/Infrastructure/PaymentRepository.php';
require_once __DIR__ . '/includes/Infrastructure/CustomerRepository.php';
require_once __DIR__ . '/includes/Infrastructure/CustomerEntityRepository.php';

use BusinessApp\Domain\QuoteFactory;
use BusinessApp\Domain\BusinessTypeRegistry;
use BusinessApp\Infrastructure\QuoteItemRepository;
use BusinessApp\Infrastructure\QuoteRepository;
use BusinessApp\Infrastructure\InvoiceRepository;
use BusinessApp\Infrastructure\InvoiceItemRepository;
use BusinessApp\Infrastructure\JobRepository;
use BusinessApp\Infrastructure\JobItemRepository;
use BusinessApp\Infrastructure\PaymentRepository;
use BusinessApp\Infrastructure\CustomerRepository;
use BusinessApp\Infrastructure\CustomerEntityRepository;

final class BusinessApp_Plugin
{
    private static $instance;
    private $quoteFactory;
    private $quoteRepository;
    private $quoteItemRepository;
    private $invoiceRepository;
    private $invoiceItemRepository;
    private $jobRepository;
    private $jobItemRepository;
    private $paymentRepository;
    private $customerRepository;
    private $customerEntityRepository;
    private $businessTypeRegistry;

    public static function instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct()
    {
        global $wpdb;

        $this->businessTypeRegistry = new BusinessTypeRegistry();
        $this->quoteFactory = new QuoteFactory($this->businessTypeRegistry);
        $this->quoteItemRepository = new QuoteItemRepository($wpdb, $wpdb->prefix . 'businessapp_quote_items');
        $this->quoteRepository = new QuoteRepository($wpdb, $wpdb->prefix . 'businessapp_quotes', $this->quoteItemRepository);
        $this->invoiceItemRepository = new InvoiceItemRepository($wpdb, $wpdb->prefix . 'businessapp_invoice_items');
        $this->invoiceRepository = new InvoiceRepository($wpdb, $wpdb->prefix . 'businessapp_invoices', $this->invoiceItemRepository);
        $this->jobItemRepository = new JobItemRepository($wpdb, $wpdb->prefix . 'businessapp_job_items');
        $this->jobRepository = new JobRepository($wpdb, $wpdb->prefix . 'businessapp_jobs', $this->jobItemRepository);
        $this->paymentRepository = new PaymentRepository($wpdb, $wpdb->prefix . 'businessapp_payments');
        $this->customerRepository = new CustomerRepository();
        $this->customerEntityRepository = new CustomerEntityRepository();

        add_action('rest_api_init', [$this, 'register_rest_routes']);
        add_action('admin_menu', [$this, 'register_admin_menu']);
        add_action('admin_post_businessapp_create_quote', [$this, 'handle_admin_create_quote']);
        add_action('admin_post_businessapp_save_settings', [$this, 'handle_admin_save_settings']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
        add_shortcode('businessapp_app', [$this, 'render_frontend_app']);
        add_action('template_redirect', [$this, 'maybe_render_public_quote']);
        add_action('template_redirect', [$this, 'maybe_render_public_invoice']);

        $this->maybe_upgrade_schema();
    }

    public function register_rest_routes()
    {
        register_rest_route(
            'businessapp/v1',
            '/health',
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'handle_health_check'],
                'permission_callback' => '__return_true',
            ]
        );

        register_rest_route(
            'businessapp/v1',
            '/create-payment-intent',
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'handle_create_payment_intent'],
                'permission_callback' => '__return_true',
            ]
        );

        register_rest_route(
            'businessapp/v1',
            '/quotes',
            [
                [
                    'methods'             => 'POST',
                    'callback'            => [$this, 'handle_create_quote'],
                    'permission_callback' => function () {
                        return current_user_can('edit_posts');
                    },
                ],
                [
                    'methods'             => 'GET',
                    'callback'            => [$this, 'handle_list_quotes'],
                    'permission_callback' => function () {
                        return current_user_can('edit_posts');
                    },
                ],
            ]
        );

        register_rest_route(
            'businessapp/v1',
            '/quotes/(?P<id>\d+)',
            [
                [
                    'methods'             => 'GET',
                    'callback'            => [$this, 'handle_get_quote'],
                    'permission_callback' => function () {
                        return current_user_can('edit_posts');
                    },
                ],
                [
                    'methods'             => 'POST',
                    'callback'            => [$this, 'handle_update_quote'],
                    'permission_callback' => function () {
                        return current_user_can('edit_posts');
                    },
                ],
            ]
        );

        register_rest_route(
            'businessapp/v1',
            '/quotes/(?P<id>\d+)/send',
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'handle_send_quote'],
                'permission_callback' => function () {
                    return current_user_can('edit_posts');
                },
            ]
        );

        register_rest_route(
            'businessapp/v1',
            '/quotes/(?P<id>\d+)/accept',
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'handle_accept_quote'],
                'permission_callback' => function () {
                    return current_user_can('edit_posts');
                },
            ]
        );

        register_rest_route(
            'businessapp/v1',
            '/quotes/(?P<id>\d+)/reject',
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'handle_reject_quote'],
                'permission_callback' => function () {
                    return current_user_can('edit_posts');
                },
            ]
        );

        register_rest_route(
            'businessapp/v1',
            '/quote-stats',
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'handle_quote_stats'],
                'permission_callback' => function () {
                    return current_user_can('edit_posts');
                },
            ]
        );

        register_rest_route(
            'businessapp/v1',
            '/settings',
            [
                [
                    'methods'             => 'GET',
                    'callback'            => [$this, 'handle_get_settings'],
                    'permission_callback' => function () {
                        return current_user_can('edit_posts');
                    },
                ],
                [
                    'methods'             => 'POST',
                    'callback'            => [$this, 'handle_update_settings'],
                    'permission_callback' => function () {
                        return current_user_can('edit_posts');
                    },
                ],
            ]
        );

        register_rest_route(
            'businessapp/v1',
            '/dashboard-data',
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'handle_dashboard_data'],
                'permission_callback' => function () {
                    return current_user_can('edit_posts');
                },
            ]
        );

        register_rest_route(
            'businessapp/v1',
            '/create-payment-intent',
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'handle_create_payment_intent'],
                'permission_callback' => '__return_true',
            ]
        );

        register_rest_route(
            'businessapp/v1',
            '/customers',
            [
                [
                    'methods'             => 'GET',
                    'callback'            => [$this, 'handle_get_customers'],
                    'permission_callback' => function () {
                        return current_user_can('edit_posts');
                    },
                ],
                [
                    'methods'             => 'POST',
                    'callback'            => [$this, 'handle_create_customer'],
                    'permission_callback' => function () {
                        return current_user_can('edit_posts');
                    },
                ],
            ]
        );

        register_rest_route(
            'businessapp/v1',
            '/customers/(?P<id>\d+)',
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'handle_get_customer'],
                'permission_callback' => function () {
                    return current_user_can('edit_posts');
                },
            ]
        );

        register_rest_route(
            'businessapp/v1',
            '/customers/(?P<id>\d+)/entities',
            [
                [
                    'methods'             => 'GET',
                    'callback'            => [$this, 'handle_get_customer_entities'],
                    'permission_callback' => function () {
                        return current_user_can('edit_posts');
                    },
                ],
                [
                    'methods'             => 'POST',
                    'callback'            => [$this, 'handle_create_customer_entity'],
                    'permission_callback' => function () {
                        return current_user_can('edit_posts');
                    },
                ],
            ]
        );

        register_rest_route(
            'businessapp/v1',
            '/customers/(?P<id>\d+)/entities/(?P<entity_id>\d+)',
            [
                [
                    'methods'             => 'POST', // Update
                    'callback'            => [$this, 'handle_update_customer_entity'],
                    'permission_callback' => function () {
                        return current_user_can('edit_posts');
                    },
                ],
                [
                    'methods'             => 'DELETE',
                    'callback'            => [$this, 'handle_delete_customer_entity'],
                    'permission_callback' => function () {
                        return current_user_can('edit_posts');
                    },
                ],
            ]
        );

        register_rest_route(
            'businessapp/v1',
            '/schema',
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'handle_get_schema'],
                'permission_callback' => function () {
                    return current_user_can('edit_posts');
                },
            ]
        );

        register_rest_route(
            'businessapp/v1',
            '/jobs',
            [
                [
                    'methods'             => 'GET',
                    'callback'            => [$this, 'handle_list_jobs'],
                    'permission_callback' => function () {
                        return current_user_can('edit_posts');
                    },
                ],
                [
                    'methods'             => 'POST',
                    'callback'            => [$this, 'handle_create_job'],
                    'permission_callback' => function () {
                        return current_user_can('edit_posts');
                    },
                ],
            ]
        );

        register_rest_route(
            'businessapp/v1',
            '/jobs/(?P<id>\d+)',
            [
                [
                    'methods'             => 'GET',
                    'callback'            => [$this, 'handle_get_job'],
                    'permission_callback' => function () {
                        return current_user_can('edit_posts');
                    },
                ],
                [
                    'methods'             => 'POST',
                    'callback'            => [$this, 'handle_update_job'],
                    'permission_callback' => function () {
                        return current_user_can('edit_posts');
                    },
                ],
            ]
        );

        register_rest_route(
            'businessapp/v1',
            '/quotes/(?P<id>\d+)/convert',
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'handle_convert_quote_to_job'],
                'permission_callback' => function () {
                    return current_user_can('edit_posts');
                },
            ]
        );
    }

    public function handle_get_schema()
    {
        $businessTypeSettings = get_option('businessapp_settings_business_type', []);
        $activeTypeId = isset($businessTypeSettings['business_type']) ? $businessTypeSettings['business_type'] : 'panel_beater';
        $activeSchema = $this->businessTypeRegistry->get($activeTypeId);

        if (!$activeSchema) {
            return new \WP_Error('no_schema', 'Active Business Type schema not found', ['status' => 404]);
        }

        $response = [
            'id' => $activeSchema->getId(),
            'name' => $activeSchema->getName(),
            'customer_fields' => [],
            'entity_name' => $activeSchema->getAssociatedEntityName(),
            'entity_fields' => []
        ];

        foreach ($activeSchema->getCustomerFields() as $field) {
            $response['customer_fields'][] = [
                'id' => $field->getId(),
                'label' => $field->getLabel(),
                'type' => $field->getType(),
                'required' => $field->isRequired(),
                'options' => $field->getOptions()
            ];
        }

        if ($activeSchema->getAssociatedEntityFields()) {
            foreach ($activeSchema->getAssociatedEntityFields() as $field) {
                $response['entity_fields'][] = [
                    'id' => $field->getId(),
                    'label' => $field->getLabel(),
                    'type' => $field->getType(),
                    'required' => $field->isRequired(),
                    'options' => $field->getOptions()
                ];
            }
        }

        return rest_ensure_response($response);
    }

    public function handle_list_jobs($request)
    {
        $status = $request->get_param('status');
        $jobs = $this->jobRepository->getAll($status);
        
        $data = array_map([$this, 'job_to_array'], $jobs);
        
        return rest_ensure_response($data);
    }

    public function handle_create_job($request)
    {
        $params = $request->get_json_params();
        
        $quoteId = isset($params['quote_id']) ? (int) $params['quote_id'] : 0;
        $customerId = isset($params['customer_id']) ? (int) $params['customer_id'] : 0;
        $title = isset($params['title']) ? (string) $params['title'] : '';
        $notes = isset($params['notes']) ? (string) $params['notes'] : '';
        $startDate = isset($params['start_date']) ? (string) $params['start_date'] : null;
        $endDate = isset($params['end_date']) ? (string) $params['end_date'] : null;
        
        $dynamicFields = isset($params['dynamic_fields']) ? $params['dynamic_fields'] : [];
        $schemaSnapshot = isset($params['schema_snapshot']) ? $params['schema_snapshot'] : [];
        $associatedEntityIds = isset($params['associated_entity_ids']) ? $params['associated_entity_ids'] : [];
        $items = isset($params['items']) ? $params['items'] : [];

        if (empty($title)) {
            return new \WP_Error('missing_title', 'Title is required', ['status' => 400]);
        }

        $job = $this->jobRepository->create(
            $quoteId, 
            $customerId, 
            $title, 
            $notes, 
            $startDate, 
            $endDate,
            $dynamicFields,
            $schemaSnapshot,
            $associatedEntityIds,
            $items
        );

        return rest_ensure_response($this->job_to_array($job));
    }

    public function handle_get_job($request)
    {
        $id = (int) $request['id'];
        $job = $this->jobRepository->getById($id);

        if (!$job) {
            return new \WP_Error('job_not_found', 'Job not found', ['status' => 404]);
        }

        return rest_ensure_response($this->job_to_array($job));
    }

    public function handle_update_job($request)
    {
        $id = (int) $request['id'];
        $params = $request->get_json_params();

        $job = $this->jobRepository->getById($id);
        if (!$job) {
            return new \WP_Error('job_not_found', 'Job not found', ['status' => 404]);
        }

        $updatedJob = $this->jobRepository->update($id, $params);
        
        return rest_ensure_response($this->job_to_array($updatedJob));
    }

    public function handle_convert_quote_to_job($request)
    {
        $quoteId = (int) $request['id'];
        $quote = $this->quoteRepository->findById($quoteId);

        if (!$quote) {
            return new \WP_Error('quote_not_found', 'Quote not found', ['status' => 404]);
        }

        // Create Job from Quote
        $job = $this->jobRepository->create(
            $quote->getId(),
            $quote->getCustomerId(),
            $quote->getTitle(),
            $quote->getNotes(),
            null, // start_date
            null, // end_date
            $quote->getDynamicFields(),
            $quote->getSchemaSnapshot(),
            $quote->getAssociatedEntityIds(),
            $quote->getLineItems()
        );

        return rest_ensure_response($this->job_to_array($job));
    }

    private function job_to_array($job)
    {
        return $job->toArray();
    }

    public function register_admin_menu()
    {
        add_menu_page(
            'BusinessApp',
            'BusinessApp',
            'manage_options',
            'businessapp-dashboard',
            [$this, 'render_dashboard_page'],
            'dashicons-clipboard',
            26
        );

        $dashboardPage = add_submenu_page(
            'businessapp-dashboard',
            'Dashboard',
            'Dashboard',
            'manage_options',
            'businessapp-dashboard',
            [$this, 'render_dashboard_page']
        );

        $quotesPage = add_submenu_page(
            'businessapp-dashboard',
            'Quotes',
            'Quotes',
            'manage_options',
            'businessapp-quotes',
            [$this, 'render_quotes_page']
        );

        $jobsPage = add_submenu_page(
            'businessapp-dashboard',
            'Jobs',
            'Jobs',
            'manage_options',
            'businessapp-jobs',
            [$this, 'render_jobs_page']
        );

        $invoicesPage = add_submenu_page(
            'businessapp-dashboard',
            'Invoices',
            'Invoices',
            'manage_options',
            'businessapp-invoices',
            [$this, 'render_invoices_page']
        );

        $customersPage = add_submenu_page(
            'businessapp-dashboard',
            'Customers',
            'Customers',
            'manage_options',
            'businessapp-customers',
            [$this, 'render_customers_page']
        );

        $settingsPage = add_submenu_page(
            'businessapp-dashboard',
            'Settings',
            'Settings',
            'manage_options',
            'businessapp-settings',
            [$this, 'render_settings_page']
        );

        $analyticsPage = add_submenu_page(
            'businessapp-dashboard',
            'Analytics',
            'Analytics',
            'manage_options',
            'businessapp-analytics',
            [$this, 'render_analytics_page']
        );

        add_action('admin_print_styles-' . $dashboardPage, [$this, 'enqueue_dashboard_assets']);
        add_action('admin_print_styles-' . $quotesPage, [$this, 'enqueue_quotes_assets']);
        add_action('admin_print_styles-' . $jobsPage, [$this, 'enqueue_jobs_assets']);
        add_action('admin_print_styles-' . $invoicesPage, [$this, 'enqueue_dashboard_assets']);
        add_action('admin_print_styles-' . $customersPage, [$this, 'enqueue_customers_assets']);
        add_action('admin_print_styles-' . $settingsPage, [$this, 'enqueue_settings_assets']);
        add_action('admin_print_styles-' . $analyticsPage, [$this, 'enqueue_dashboard_assets']);
    }

    public function enqueue_dashboard_assets()
    {
        wp_enqueue_style(
            'businessapp-admin-dashboard',
            plugins_url('assets/admin-dashboard.css', __FILE__),
            [],
            '0.1.0'
        );

        wp_enqueue_script(
            'businessapp-admin-dashboard-js',
            plugins_url('assets/admin-dashboard.js', __FILE__),
            ['jquery'],
            '0.1.0',
            true
        );
        
        wp_localize_script(
            'businessapp-admin-dashboard-js',
            'BusinessAppConfig',
            [
                'restUrl' => esc_url_raw(rest_url('businessapp/v1/')),
                'nonce'   => wp_create_nonce('wp_rest'),
            ]
        );
    }

    public function enqueue_quotes_assets()
    {
        wp_enqueue_style(
            'businessapp-admin-dashboard',
            plugins_url('assets/admin-dashboard.css', __FILE__),
            [],
            '0.1.0'
        );

        wp_enqueue_script(
            'businessapp-admin-quotes',
            plugins_url('assets/admin-quotes.js', __FILE__),
            ['wp-element', 'wp-components', 'wp-api-fetch', 'wp-i18n'],
            '0.1.0',
            true
        );

        // Get Active Schema
        $businessTypeSettings = get_option('businessapp_settings_business_type', []);
        $activeTypeId = isset($businessTypeSettings['business_type']) ? $businessTypeSettings['business_type'] : 'panel_beater';
        $activeSchema = null;
        $typeDef = $this->businessTypeRegistry->get($activeTypeId);
        
        if ($typeDef) {
            $activeSchema = $typeDef->toArray();
        }

        wp_localize_script('businessapp-admin-quotes', 'businessappData', [
            'root' => esc_url_raw(rest_url()),
            'nonce' => wp_create_nonce('wp_rest'),
            'adminUrl' => admin_url(),
            'settings' => [
                'business_type' => $businessTypeSettings,
                'general' => get_option('businessapp_settings_general', []),
            ],
            'active_schema' => $activeSchema
        ]);
    }

    public function enqueue_jobs_assets()
    {
        wp_enqueue_style(
            'businessapp-admin-dashboard',
            plugins_url('assets/admin-dashboard.css', __FILE__),
            [],
            '0.1.0'
        );

        wp_enqueue_script(
            'businessapp-admin-jobs',
            plugins_url('assets/admin-jobs.js', __FILE__),
            ['wp-element', 'wp-components', 'wp-api-fetch', 'wp-i18n'],
            '0.1.0',
            true
        );

        // Get Active Schema
        $businessTypeSettings = get_option('businessapp_settings_business_type', []);
        $activeTypeId = isset($businessTypeSettings['business_type']) ? $businessTypeSettings['business_type'] : 'panel_beater';
        $activeSchema = null;
        $typeDef = $this->businessTypeRegistry->get($activeTypeId);
        
        if ($typeDef) {
            $activeSchema = $typeDef->toArray();
        }

        wp_localize_script('businessapp-admin-jobs', 'businessappData', [
            'root' => esc_url_raw(rest_url()),
            'nonce' => wp_create_nonce('wp_rest'),
            'adminUrl' => admin_url(),
            'settings' => [
                'business_type' => $businessTypeSettings,
                'general' => get_option('businessapp_settings_general', []),
            ],
            'active_schema' => $activeSchema
        ]);
    }

    public function enqueue_customers_assets()
    {
        wp_enqueue_style(
            'businessapp-admin-dashboard',
            plugins_url('assets/admin-dashboard.css', __FILE__),
            [],
            '0.1.0'
        );

        wp_enqueue_script(
            'businessapp-admin-customers-js',
            plugins_url('assets/admin-customers.js', __FILE__),
            ['jquery'],
            '0.1.0',
            true
        );

        wp_localize_script(
            'businessapp-admin-customers-js',
            'BusinessAppConfig',
            [
                'restUrl' => esc_url_raw(rest_url('businessapp/v1/')),
                'nonce'   => wp_create_nonce('wp_rest'),
            ]
        );
    }

    public function enqueue_settings_assets()
    {
        wp_enqueue_media();
        
        wp_enqueue_style(
            'businessapp-admin-settings',
            plugins_url('assets/admin-settings.css', __FILE__),
            [],
            '0.1.0'
        );

        wp_enqueue_script(
            'businessapp-admin-settings',
            plugins_url('assets/admin-settings.js', __FILE__),
            ['jquery'],
            '0.1.0',
            true
        );
    }

    public function handle_health_check($request)
    {
        return rest_ensure_response(
            [
                'status'  => 'ok',
                'version' => '0.1.0',
            ]
        );
    }

    private function maybe_upgrade_schema()
    {
        $installedVersion = get_option('businessapp_db_version');

        if ($installedVersion === BUSINESSAPP_DB_VERSION) {
            return;
        }

        $this->create_or_update_tables();

        update_option('businessapp_db_version', BUSINESSAPP_DB_VERSION);
    }

    private function create_or_update_tables()
    {
        global $wpdb;

        $tableName = $wpdb->prefix . 'businessapp_quotes';
        $charsetCollate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$tableName} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            customer_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            customer_name VARCHAR(255) NOT NULL DEFAULT '',
            customer_email VARCHAR(255) NOT NULL DEFAULT '',
            customer_phone VARCHAR(50) NOT NULL DEFAULT '',
            title TEXT NOT NULL,
            notes TEXT NOT NULL,
            total_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
            status VARCHAR(20) NOT NULL,
            payment_status VARCHAR(20) NOT NULL DEFAULT 'unpaid',
            public_token VARCHAR(64) NOT NULL DEFAULT '',
            associated_entity_ids JSON DEFAULT NULL,
            dynamic_fields JSON DEFAULT NULL,
            schema_snapshot JSON DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id)
        ) {$charsetCollate};";

        $itemsTable = $wpdb->prefix . 'businessapp_quote_items';

        $itemsSql = "CREATE TABLE {$itemsTable} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            quote_id BIGINT(20) UNSIGNED NOT NULL,
            description TEXT NOT NULL,
            qty INT NOT NULL DEFAULT 1,
            unit VARCHAR(50) NOT NULL DEFAULT '',
            unit_price DECIMAL(10,2) NOT NULL DEFAULT 0,
            amount DECIMAL(10,2) NOT NULL DEFAULT 0,
            position INT NOT NULL DEFAULT 1,
            PRIMARY KEY  (id),
            KEY quote_id (quote_id)
        ) {$charsetCollate};";

        $attachmentsTable = $wpdb->prefix . 'businessapp_quote_attachments';

        $attachmentsSql = "CREATE TABLE {$attachmentsTable} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            quote_id BIGINT(20) UNSIGNED NOT NULL,
            attachment_id BIGINT(20) UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY quote_id (quote_id)
        ) {$charsetCollate};";

        $customersTable = $wpdb->prefix . 'businessapp_customers';
        $customersSql = "CREATE TABLE {$customersTable} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL DEFAULT '',
            email VARCHAR(255) NOT NULL DEFAULT '',
            phone VARCHAR(50) NOT NULL DEFAULT '',
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            dynamic_fields JSON DEFAULT NULL,
            schema_snapshot JSON DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id)
        ) {$charsetCollate};";

        $entitiesTable = $wpdb->prefix . 'businessapp_customer_entities';
        $entitiesSql = "CREATE TABLE {$entitiesTable} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            customer_id BIGINT(20) UNSIGNED NOT NULL,
            entity_name VARCHAR(255) NOT NULL DEFAULT '',
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            dynamic_fields JSON DEFAULT NULL,
            schema_snapshot JSON DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY customer_id (customer_id)
        ) {$charsetCollate};";

        $jobsTable = $wpdb->prefix . 'businessapp_jobs';
        $jobsSql = "CREATE TABLE {$jobsTable} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            quote_id BIGINT(20) UNSIGNED NOT NULL,
            customer_id BIGINT(20) UNSIGNED NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            title TEXT NOT NULL,
            notes TEXT NOT NULL,
            start_date DATETIME DEFAULT NULL,
            end_date DATETIME DEFAULT NULL,
            dynamic_fields JSON DEFAULT NULL,
            schema_snapshot JSON DEFAULT NULL,
            associated_entity_ids JSON DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY quote_id (quote_id),
            KEY customer_id (customer_id)
        ) {$charsetCollate};";

        $jobItemsTable = $wpdb->prefix . 'businessapp_job_items';
        $jobItemsSql = "CREATE TABLE {$jobItemsTable} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            job_id BIGINT(20) UNSIGNED NOT NULL,
            description TEXT NOT NULL,
            qty INT NOT NULL DEFAULT 1,
            unit VARCHAR(50) NOT NULL DEFAULT '',
            unit_price DECIMAL(10,2) NOT NULL DEFAULT 0,
            amount DECIMAL(10,2) NOT NULL DEFAULT 0,
            position INT NOT NULL DEFAULT 1,
            PRIMARY KEY  (id),
            KEY job_id (job_id)
        ) {$charsetCollate};";

        $paymentsTable = $wpdb->prefix . 'businessapp_payments';
        $paymentsSql = "CREATE TABLE {$paymentsTable} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            invoice_id BIGINT(20) UNSIGNED NOT NULL,
            gateway VARCHAR(50) NOT NULL DEFAULT '',
            transaction_id VARCHAR(255) NOT NULL DEFAULT '',
            amount DECIMAL(10,2) NOT NULL DEFAULT 0,
            currency VARCHAR(10) NOT NULL DEFAULT 'USD',
            status VARCHAR(20) NOT NULL DEFAULT 'completed',
            meta JSON DEFAULT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY invoice_id (invoice_id)
        ) {$charsetCollate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta($sql);
        dbDelta($itemsSql);
        dbDelta($attachmentsSql);
        dbDelta($customersSql);
        dbDelta($entitiesSql);
        dbDelta($jobsSql);
        dbDelta($jobItemsSql);
        dbDelta($paymentsSql);
    }

    public function handle_create_quote($request)
    {
        $params = $request->get_json_params();
        $files = $request->get_file_params();

        // Handle multipart/form-data where 'data' is a JSON string
        if (empty($params) && $request->get_param('data')) {
            $params = json_decode($request->get_param('data'), true);
        }

        $preview = !empty($params['preview']);

        $customerId = isset($params['customer_id']) ? (int) $params['customer_id'] : 0;
        $customerName = isset($params['customer_name']) ? (string) $params['customer_name'] : '';
        $customerEmail = isset($params['customer_email']) ? (string) $params['customer_email'] : '';
        $customerPhone = isset($params['customer_phone']) ? (string) $params['customer_phone'] : '';
        $title = isset($params['title']) ? (string) $params['title'] : '';
        $notes = isset($params['notes']) ? (string) $params['notes'] : '';
        $items = isset($params['items']) && is_array($params['items']) ? $params['items'] : [];
        $associatedEntityIds = isset($params['associated_entity_ids']) && is_array($params['associated_entity_ids']) ? $params['associated_entity_ids'] : [];

        // 1. Determine Active Schema
        $businessTypeSettings = get_option('businessapp_settings_business_type', []);
        $activeTypeId = isset($businessTypeSettings['business_type']) ? $businessTypeSettings['business_type'] : 'panel_beater';
        
        // 2. Extract Dynamic Fields based on Schema
        $activeSchema = $this->businessTypeRegistry->get($activeTypeId);
        $dynamicFields = [];
        if ($activeSchema && isset($params['dynamic_fields']) && is_array($params['dynamic_fields'])) {
            // Only allow fields that exist in the schema
            foreach ($activeSchema->getFields() as $field) {
                $fieldId = $field->getId();
                if (isset($params['dynamic_fields'][$fieldId])) {
                    $dynamicFields[$fieldId] = $params['dynamic_fields'][$fieldId];
                }
            }
        }

        $quote = $this->quoteFactory->createDraft(
            $customerId, 
            $title, 
            $items,
            $activeTypeId,
            $associatedEntityIds,
            $customerName, 
            $customerEmail, 
            $customerPhone, 
            $notes,
            $dynamicFields
        );

        // Tax Calculation
        $total = $quote->getTotalAmount();
        if ($total > 0) {
            $taxRate = $this->get_tax_rate_fraction();
            $totalWithTax = $total + ($total * $taxRate);
            
            $quote->updateDraftDetails(
                $quote->getTitle(),
                $totalWithTax,
                $quote->getNotes(),
                $quote->getDynamicFields(),
                $quote->getAssociatedEntityIds(),
                $quote->getLineItems()
            );
        }

        if ($preview) {
            $quote->setPublicToken(wp_generate_password(32, false, false));
        }

        $quoteId = $this->quoteRepository->save($quote);
        $quote = $this->quoteRepository->findById($quoteId);

        // Handle Attachments
        if (!empty($_FILES)) {
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');

            global $wpdb;
            $attachmentsTable = $wpdb->prefix . 'businessapp_quote_attachments';

            foreach ($_FILES as $key => $file) {
                if ($file['error'] !== UPLOAD_ERR_OK) {
                    continue;
                }

                $attachmentId = media_handle_upload($key, 0);

                if (is_wp_error($attachmentId)) {
                    continue;
                }

                $wpdb->insert(
                    $attachmentsTable,
                    [
                        'quote_id'      => $quote->getId(),
                        'attachment_id' => $attachmentId,
                        'created_at'    => current_time('mysql'),
                    ],
                    ['%d', '%d', '%s']
                );
            }
        }

        return rest_ensure_response($this->quote_to_array($quote));
    }

    public function handle_update_quote($request)
    {
        $id = isset($request['id']) ? (int) $request['id'] : 0;
        $params = $request->get_json_params();
        
        $quote = $this->load_quote_for_change($id);
        if (is_wp_error($quote)) return $quote;
        
        if ($quote->getStatus() !== 'draft') {
            return new \WP_Error('not_draft', 'Only draft quotes can be updated', ['status' => 400]);
        }

        $title = isset($params['title']) ? (string) $params['title'] : $quote->getTitle();
        $notes = isset($params['notes']) ? (string) $params['notes'] : $quote->getNotes();
        $items = isset($params['items']) && is_array($params['items']) ? $params['items'] : [];
        $associatedEntityIds = isset($params['associated_entity_ids']) && is_array($params['associated_entity_ids']) ? $params['associated_entity_ids'] : $quote->getAssociatedEntityIds();
        
        // Update Customer Details
        if (isset($params['customer_id'])) {
            $quote->setCustomerId((int) $params['customer_id']);
        }
        if (isset($params['customer_name'])) {
            $quote->setCustomerName((string) $params['customer_name']);
        }
        if (isset($params['customer_email'])) {
            $quote->setCustomerEmail((string) $params['customer_email']);
        }
        if (isset($params['customer_phone'])) {
            $quote->setCustomerPhone((string) $params['customer_phone']);
        }

        // For dynamic fields, merge with existing or replace? 
        // Usually replace provided keys, keep others? Or replace all?
        // Let's assume replace provided keys.
        $dynamicFields = $quote->getDynamicFields();
        if (isset($params['dynamic_fields']) && is_array($params['dynamic_fields'])) {
            foreach ($params['dynamic_fields'] as $k => $v) {
                $dynamicFields[$k] = $v;
            }
        }

        $this->quoteFactory->updateDraft($quote, $title, $items, $associatedEntityIds, $notes, $dynamicFields);
        
        // Tax
        $total = $quote->getTotalAmount();
        if ($total > 0) {
            $taxRate = $this->get_tax_rate_fraction();
            $totalWithTax = $total + ($total * $taxRate);
            
             $quote->updateDraftDetails(
                $quote->getTitle(),
                $totalWithTax,
                $quote->getNotes(),
                $quote->getDynamicFields(),
                $quote->getAssociatedEntityIds(),
                $quote->getLineItems()
            );
        }

        $this->quoteRepository->save($quote);
        
        return rest_ensure_response($this->quote_to_array($quote));
    }

    public function handle_get_quote($request)
    {
        $id = isset($request['id']) ? (int) $request['id'] : 0;
        $quote = $this->quoteRepository->findById($id);

        if (!$quote) {
            return new \WP_Error(
                'businessapp_quote_not_found',
                'Quote not found',
                [
                    'status' => 404,
                ]
            );
        }

        return rest_ensure_response($this->quote_to_array($quote));
    }

    public function handle_list_quotes($request)
    {
        $quotes = $this->quoteRepository->getAll();

        $items = [];

        foreach ($quotes as $quote) {
            $items[] = [
                'id'           => $quote->getId(),
                'status'       => $quote->getStatus(),
                'customer_id'  => $quote->getCustomerId(),
                'customer_name' => $quote->getCustomerName(),
                'customer_email' => $quote->getCustomerEmail(),
                'customer_phone' => $quote->getCustomerPhone(),
                'title'        => $quote->getTitle(),
                'total_amount' => $quote->getTotalAmount(),
                'notes'        => $quote->getNotes(),
                'public_url'   => $quote->getPublicToken() ? add_query_arg('businessapp_quote_token', $quote->getPublicToken(), home_url('/')) : '',
                'created_at'   => $quote->getCreatedAt(),
            ];
        }

        return rest_ensure_response($items);
    }

    public function handle_quote_stats($request)
    {
        global $wpdb;

        $tableName = $wpdb->prefix . 'businessapp_quotes';

        $active = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$tableName} WHERE status = %s",
                'sent'
            )
        );

        $pending = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$tableName} WHERE status = %s",
                'draft'
            )
        );

        $accepted = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$tableName} WHERE status = %s",
                'accepted'
            )
        );

        $unpaid = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$tableName} WHERE status = %s AND payment_status = %s",
                'accepted',
                'unpaid'
            )
        );

        return rest_ensure_response(
            [
                'active_quotes'     => (int) $active,
                'pending_approvals' => (int) $pending,
                'accepted_jobs'     => (int) $accepted,
                'unpaid_invoices'   => (int) $unpaid,
            ]
        );
    }

    private function get_tax_rate_fraction()
    {
        $settings = get_option('businessapp_settings_general');

        if (!is_array($settings)) {
            return 0.10;
        }

        $rawValue = isset($settings['tax_rate']) ? (float) $settings['tax_rate'] : 10.0;

        if ($rawValue < 0) {
            $rawValue = 0.0;
        }

        return $rawValue / 100;
    }

    private function load_quote_for_change($id)
    {
        $quote = $this->quoteRepository->findById($id);

        if (!$quote) {
            return new \WP_Error(
                'businessapp_quote_not_found',
                'Quote not found',
                [
                    'status' => 404,
                ]
            );
        }

        return $quote;
    }

    private function quote_to_array($quote)
    {
        $items = array_map(function($item) {
            return $item->toArray();
        }, $quote->getLineItems());

        return [
            'id'           => $quote->getId(),
            'status'       => $quote->getStatus(),
            'customer_id'  => $quote->getCustomerId(),
            'customer_name' => $quote->getCustomerName(),
            'customer_email' => $quote->getCustomerEmail(),
            'customer_phone' => $quote->getCustomerPhone(),
            'title'        => $quote->getTitle(),
            'total_amount' => $quote->getTotalAmount(),
            'notes'        => $quote->getNotes(),
            'public_url'   => $quote->getPublicToken() ? add_query_arg('businessapp_quote_token', $quote->getPublicToken(), home_url('/')) : '',
            'created_at'   => $quote->getCreatedAt(),
            'payment_status' => $quote->getPaymentStatus(),
            'dynamic_fields' => $quote->getDynamicFields(),
            'schema_snapshot' => $quote->getSchemaSnapshot(),
            'associated_entity_ids' => $quote->getAssociatedEntityIds(),
            'items' => $items,
        ];
    }

    public function handle_send_quote($request)
    {
        $id = isset($request['id']) ? (int) $request['id'] : 0;

        $quote = $this->load_quote_for_change($id);

        if ($quote instanceof \WP_Error) {
            return $quote;
        }

        try {
            $quote->markSent();
        } catch (\RuntimeException $e) {
            return new \WP_Error(
                'businessapp_invalid_transition',
                $e->getMessage(),
                [
                    'status' => 400,
                ]
            );
        }

        if ($quote->getPublicToken() === '') {
            $quote->setPublicToken(wp_generate_password(32, false, false));
        }

        $this->quoteRepository->save($quote);

        return rest_ensure_response($this->quote_to_array($quote));
    }

    public function handle_accept_quote($request)
    {
        $id = isset($request['id']) ? (int) $request['id'] : 0;

        $quote = $this->load_quote_for_change($id);

        if ($quote instanceof \WP_Error) {
            return $quote;
        }

        try {
            $quote->markAccepted();
        } catch (\RuntimeException $e) {
            return new \WP_Error(
                'businessapp_invalid_transition',
                $e->getMessage(),
                [
                    'status' => 400,
                ]
            );
        }

        $this->quoteRepository->save($quote);

        return rest_ensure_response($this->quote_to_array($quote));
    }

    public function handle_reject_quote($request)
    {
        $id = isset($request['id']) ? (int) $request['id'] : 0;

        $quote = $this->load_quote_for_change($id);

        if ($quote instanceof \WP_Error) {
            return $quote;
        }

        try {
            $quote->markRejected();
        } catch (\RuntimeException $e) {
            return new \WP_Error(
                'businessapp_invalid_transition',
                $e->getMessage(),
                [
                    'status' => 400,
                ]
            );
        }

        $this->quoteRepository->save($quote);

        return rest_ensure_response($this->quote_to_array($quote));
    }

    public function handle_get_settings($request)
    {
        $businessTypeSettings = get_option('businessapp_settings_business_type', []);
        $activeTypeId = isset($businessTypeSettings['business_type']) ? $businessTypeSettings['business_type'] : 'panel_beater';
        
        $activeSchema = null;
        $typeDef = $this->businessTypeRegistry->get($activeTypeId);
        
        if ($typeDef) {
            $activeSchema = $typeDef->toArray();
        }

        return rest_ensure_response([
            'general' => get_option('businessapp_settings_general', []),
            'quote_settings' => get_option('businessapp_settings_workflow', []),
            'templates' => get_option('businessapp_settings_templates', []),
            'business_type' => $businessTypeSettings,
            'active_schema' => $activeSchema,
            'data_units' => get_option('businessapp_settings_units', []),
        ]);
    }

    public function handle_update_settings($request)
    {
        $params = $request->get_json_params();

        if (isset($params['general'])) {
            update_option('businessapp_settings_general', $params['general']);
        }
        if (isset($params['quote_settings'])) {
            update_option('businessapp_settings_workflow', $params['quote_settings']);
        }
        if (isset($params['templates'])) {
            update_option('businessapp_settings_templates', $params['templates']);
        }
        if (isset($params['business_type'])) {
            update_option('businessapp_settings_business_type', $params['business_type']);
        }
        if (isset($params['data_units'])) {
            update_option('businessapp_settings_units', $params['data_units']);
        }

        return $this->handle_get_settings($request);
    }

    public function handle_dashboard_data($request)
    {
        $quotes = $this->quoteRepository->getAll();

        // Sort by updated_at desc for activity
        // Since Quote object doesn't expose created_at/updated_at explicitly in getters yet, 
        // we might need to rely on ID or add getters. 
        // Ideally we should add getUpdatedAt() to Quote.php. 
        // For now, let's assume higher ID is newer or we will add the getter.
        
        // Let's add getCreatedAt/UpdatedAt to Quote.php first to be proper.
        // But assuming I can't edit Quote.php right this second in this block...
        // Actually I can infer activity from status.
        
        $activity = [];
        $tasks = [];

        // Reverse to iterate newest first (if getAll returns ID asc)
        $reversedQuotes = array_reverse($quotes);

        foreach ($reversedQuotes as $quote) {
            // Activity Logic (simplified)
            if (count($activity) < 5) {
                $status = $quote->getStatus();
                $title = $quote->getTitle();
                $customer = $quote->getCustomerName();
                $desc = '';
                
                // Enhance title with dynamic field info
                $dynamicFields = $quote->getDynamicFields();
                $extraInfo = '';
                
                if (isset($dynamicFields['vehicle_make']) && isset($dynamicFields['vehicle_model'])) {
                    $extraInfo = $dynamicFields['vehicle_make'] . ' ' . $dynamicFields['vehicle_model'];
                } elseif (isset($dynamicFields['job_type'])) {
                    $extraInfo = ucfirst($dynamicFields['job_type']);
                } elseif (isset($dynamicFields['property_type'])) {
                    $extraInfo = ucfirst($dynamicFields['property_type']);
                }
                
                if ($extraInfo) {
                    $title .= ' (' . $extraInfo . ')';
                }

                if ($status === 'draft') {
                    $desc = "Draft created for $customer";
                } elseif ($status === 'sent') {
                    $desc = "Quote sent to $customer";
                } elseif ($status === 'accepted') {
                    $desc = "Quote accepted by $customer";
                } elseif ($status === 'rejected') {
                    $desc = "Quote rejected by $customer";
                } elseif ($status === 'paid') {
                     $desc = "Payment received from $customer";
                }

                if ($desc) {
                    $activity[] = [
                        'id' => $quote->getId(),
                        'description' => $desc,
                        'title' => $title,
                        // 'time' => 'Recently' // We need timestamps in Quote object
                    ];
                }
            }

            // Tasks Logic
            if (count($tasks) < 5) {
                $status = $quote->getStatus();
                $paymentStatus = $quote->getPaymentStatus();
                $customer = $quote->getCustomerName();
                $taskMsg = '';

                if ($status === 'draft') {
                    $taskMsg = "Finish and send quote to $customer";
                } elseif ($status === 'sent') {
                    $taskMsg = "Follow up with $customer";
                } elseif ($status === 'accepted' && $paymentStatus === 'unpaid') {
                    $taskMsg = "Send invoice/collect payment from $customer";
                }

                if ($taskMsg) {
                    $tasks[] = [
                        'id' => $quote->getId(),
                        'task' => $taskMsg,
                        'priority' => 'high' // simplified
                    ];
                }
            }
        }

        return rest_ensure_response([
            'activity' => $activity,
            'tasks' => $tasks
        ]);
    }

    public function render_dashboard_page()
    {
        if (!current_user_can('manage_options')) {
            wp_die('Access denied');
        }

        // Fetch quotes and stats
        $quotes = $this->quoteRepository->getAll();
        
        // Calculate stats
        $activeQuotes = 0;
        $pendingApprovals = 0;
        $acceptedJobs = 0;
        $unpaidInvoices = 0;
        
        foreach ($quotes as $quote) {
            $status = $quote->getStatus();
            if ($status === 'sent') {
                $activeQuotes++;
            }
            if ($status === 'draft') {
                $pendingApprovals++;
            }
            if ($status === 'accepted') {
                $acceptedJobs++;
                if ($quote->getPaymentStatus() === 'unpaid') {
                    $unpaidInvoices++;
                }
            }
        }

        // Get recent quotes (max 4)
        $recentQuotes = array_slice($quotes, 0, 4);

        ?>
        <div class="businessapp-dashboard-wrap">
            <div class="businessapp-dashboard-header">
                <h1>Dashboard</h1>
            </div>

            <!-- KPI Cards -->
            <div class="businessapp-kpi-grid">
                <div class="businessapp-kpi-card blue">
                    <div class="businessapp-kpi-number"><?php echo esc_html($activeQuotes); ?></div>
                    <div class="businessapp-kpi-label">Active Quotes</div>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=businessapp-quotes&status=sent')); ?>" class="businessapp-kpi-action">View Quotes</a>
                </div>

                <div class="businessapp-kpi-card orange">
                    <div class="businessapp-kpi-number"><?php echo esc_html($pendingApprovals); ?></div>
                    <div class="businessapp-kpi-label">Pending Approvals</div>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=businessapp-quotes&status=draft')); ?>" class="businessapp-kpi-action">Review</a>
                </div>

                <div class="businessapp-kpi-card dark-blue">
                    <div class="businessapp-kpi-number"><?php echo esc_html($acceptedJobs); ?></div>
                    <div class="businessapp-kpi-label">Accepted Jobs</div>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=businessapp-quotes&status=accepted')); ?>" class="businessapp-kpi-action">View Jobs</a>
                </div>

                <div class="businessapp-kpi-card orange">
                    <div class="businessapp-kpi-number"><?php echo esc_html($unpaidInvoices); ?></div>
                    <div class="businessapp-kpi-label">Unpaid Invoices</div>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=businessapp-quotes&payment_status=unpaid')); ?>" class="businessapp-kpi-action">Manage ▾</a>
                </div>
            </div>

            <!-- Two Column Layout -->
            <div class="businessapp-two-column">
                <!-- Left Column -->
                <div>
                    <!-- Recent Quotes -->
                    <div class="businessapp-section-card">
                        <div class="businessapp-section-header">
                            <h2>Recent Quotes</h2>
                        </div>
                        <div class="businessapp-section-content">
                            <?php if (empty($recentQuotes)) : ?>
                                <div class="businessapp-empty-state">
                                    <p>No quotes yet. Create your first quote to get started!</p>
                                </div>
                            <?php else : ?>
                                <?php foreach ($recentQuotes as $quote) : 
                                    $status = $quote->getStatus();
                                    $badgeClass = '';
                                    $badgeText = '';
                                    
                                    switch ($status) {
                                        case 'draft':
                                            $badgeClass = 'draft';
                                            $badgeText = 'Draft';
                                            break;
                                        case 'sent':
                                            $badgeClass = 'sent';
                                            $badgeText = 'Sent';
                                            break;
                                        case 'accepted':
                                            $badgeClass = 'accepted';
                                            $badgeText = 'Accepted';
                                            break;
                                        default:
                                            $badgeClass = 'awaiting';
                                            $badgeText = 'Awaiting Approval';
                                    }
                                ?>
                                    <div class="businessapp-quote-item">
                                        <div class="businessapp-quote-info">
                                            <div class="businessapp-quote-id">Q-<?php echo esc_html($quote->getId()); ?></div>
                                            <div class="businessapp-quote-details">
                                                <?php echo esc_html($quote->getTitle()); ?> - <?php echo esc_html($quote->getCustomerName()); ?>
                                            </div>
                                            <div class="businessapp-quote-amount">
                                                $<?php echo esc_html(number_format((float) $quote->getTotalAmount(), 2)); ?>
                                            </div>
                                        </div>
                                        <div class="businessapp-quote-actions">
                                            <span class="businessapp-badge <?php echo esc_attr($badgeClass); ?>">
                                                <?php echo esc_html($badgeText); ?>
                                            </span>
                                            <a href="#" class="businessapp-btn-small">Edit</a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Tasks -->
                    <div class="businessapp-section-card" style="margin-top: 24px;">
                        <div class="businessapp-section-header">
                            <h2>Tasks</h2>
                        </div>
                        <div class="businessapp-section-content">
                            <div class="businessapp-task-item">
                                <input type="checkbox" id="task1" />
                                <label for="task1">Follow up with Sarah Lee</label>
                            </div>
                            <div class="businessapp-task-item">
                                <input type="checkbox" id="task2" />
                                <label for="task2">Order parts for Honda Civic</label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column -->
                <div>
                    <!-- Activity Feed -->
                    <div class="businessapp-section-card">
                        <div class="businessapp-section-header">
                            <h2>Activity Feed</h2>
                        </div>
                        <div class="businessapp-section-content">
                            <div class="businessapp-activity-item">
                                <div class="businessapp-activity-icon">👁️</div>
                                <div class="businessapp-activity-content">
                                    <p class="businessapp-activity-text">Mike Johnson viewed Quote Q-1047</p>
                                    <span class="businessapp-activity-time">Just Now</span>
                                </div>
                            </div>
                            <div class="businessapp-activity-item">
                                <div class="businessapp-activity-icon">✏️</div>
                                <div class="businessapp-activity-content">
                                    <p class="businessapp-activity-text">Sarah Lee requested changes to Quote Q-1046</p>
                                    <span class="businessapp-activity-time">30 mins ago</span>
                                </div>
                            </div>
                            <div class="businessapp-activity-item">
                                <div class="businessapp-activity-icon">✅</div>
                                <div class="businessapp-activity-content">
                                    <p class="businessapp-activity-text">James Carter accepted Quote Q-1045</p>
                                    <span class="businessapp-activity-time">Yesterday</span>
                                </div>
                            </div>
                            <div class="businessapp-activity-item">
                                <div class="businessapp-activity-icon">💳</div>
                                <div class="businessapp-activity-content">
                                    <p class="businessapp-activity-text">Emily Davis made a payment on Invoice #1093</p>
                                    <span class="businessapp-activity-time">2 days ago</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Shortcuts -->
                    <div class="businessapp-section-card" style="margin-top: 24px;">
                        <div class="businessapp-section-header">
                            <h2>Shortcuts</h2>
                        </div>
                        <div class="businessapp-shortcuts-grid">
                            <div class="businessapp-shortcut-card">
                                <div class="businessapp-shortcut-icon">📋</div>
                                <div class="businessapp-shortcut-label">Quick Actions</div>
                            </div>
                            <div class="businessapp-shortcut-card">
                                <a href="#" class="businessapp-btn-small businessapp-create-quote-btn">📸 Create Quote</a>
                                <label style="font-size: 12px; margin: 0;">
                                    <input type="checkbox" /> Photo Upload
                                </label>
                                <label style="font-size: 12px; margin: 0;">
                                    <input type="checkbox" /> Add Note
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Create Quote Modal -->
                    <div id="businessapp-create-quote-modal" class="businessapp-modal">
                        <div class="businessapp-modal-content">
                            <div class="businessapp-modal-header">
                                <h3>Create New Quote</h3>
                                <span class="businessapp-modal-close">&times;</span>
                            </div>
                            <form id="businessapp-create-quote-form" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
                                <input type="hidden" name="action" value="businessapp_create_quote">
                                <?php wp_nonce_field('businessapp_create_quote'); ?>
                                
                                <div class="businessapp-modal-body">
                                    <div class="businessapp-form-group">
                                        <label for="quote_title">Title / Reference *</label>
                                        <input type="text" id="quote_title" name="title" required placeholder="e.g. Living Room Renovation">
                                    </div>
                                    
                                    <div class="businessapp-form-group">
                                        <label for="quote_customer_id">Customer *</label>
                                        <div style="display: flex; gap: 8px;">
                                            <select id="quote_customer_id" name="customer_id" required style="flex-grow: 1;">
                                                <option value="">Select a customer...</option>
                                                <!-- Populated via JS -->
                                            </select>
                                            <button type="button" id="businessapp-open-customer-modal-btn" class="button button-secondary">New</button>
                                        </div>
                                    </div>

                                    <!-- Hidden fields for fallback or manual entry if we supported it (omitted for now to enforce selection) -->
                                    <!-- But wait, if user selects "New", we create a customer first. -->
                                    
                                    <div class="businessapp-form-group">
                                        <label for="quote_amount">Estimated Amount ($)</label>
                                        <input type="number" id="quote_amount" name="total_amount" step="0.01" min="0">
                                    </div>
                                    
                                    <div class="businessapp-form-group">
                                        <label for="quote_notes">Notes</label>
                                        <textarea id="quote_notes" name="notes" rows="3" placeholder="Initial requirements..."></textarea>
                                    </div>
                                </div>
                                
                                <div class="businessapp-modal-footer">
                                    <button type="button" class="businessapp-btn-secondary businessapp-modal-cancel">Cancel</button>
                                    <button type="submit" class="businessapp-btn-primary">Create Quote</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Inline Create Customer Modal (Hidden) -->
                    <div id="businessapp-inline-customer-modal" class="businessapp-modal" style="display:none; z-index: 100002;">
                        <div class="businessapp-modal-content">
                            <span class="businessapp-modal-close-inline">&times;</span>
                            <h2>Add New Customer</h2>
                            <form id="businessapp-inline-customer-form">
                                <div class="businessapp-form-group">
                                    <label>Name</label>
                                    <input type="text" name="name" required class="widefat">
                                </div>
                                <div class="businessapp-form-group">
                                    <label>Email</label>
                                    <input type="email" name="email" required class="widefat">
                                </div>
                                <div class="businessapp-form-group">
                                    <label>Phone</label>
                                    <input type="text" name="phone" required class="widefat">
                                </div>
                                <div id="businessapp-inline-customer-dynamic-fields">
                                    <!-- Dynamic fields injected via JS -->
                                </div>
                                <div class="businessapp-form-actions">
                                    <button type="submit" class="button button-primary">Create Customer</button>
                                    <button type="button" class="button businessapp-modal-cancel-inline">Cancel</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public function render_quotes_page()
    {
        if (!current_user_can('manage_options')) {
            wp_die('Access denied');
        }

        ?>
        <div id="businessapp-quotes-app"></div>
        <?php
    }

    public function render_jobs_page()
    {
        if (!current_user_can('manage_options')) {
            wp_die('Access denied');
        }
        ?>
        <div id="businessapp-jobs-root"></div>
        <?php
    }

    public function render_invoices_page()
    {
        if (!current_user_can('manage_options')) {
            wp_die('Access denied');
        }

        // Handle Payment Submission
        $successMessage = '';
        $errorMessage = '';
        if (isset($_POST['action']) && $_POST['action'] === 'businessapp_manual_payment') {
            check_admin_referer('businessapp_manual_payment');
            
            $invoiceId = isset($_POST['invoice_id']) ? (int) $_POST['invoice_id'] : 0;
            $amount = isset($_POST['amount']) ? (float) $_POST['amount'] : 0.0;
            $method = isset($_POST['method']) ? sanitize_text_field($_POST['method']) : 'cash';
            $reference = isset($_POST['reference']) ? sanitize_text_field($_POST['reference']) : '';
            
            if ($invoiceId > 0 && $amount > 0) {
                $invoice = $this->invoiceRepository->getById($invoiceId);
                if ($invoice) {
                    $payment = new \BusinessApp\Domain\Payment(
                        0, // ID auto-generated
                        $invoiceId,
                        'manual', // gateway
                        $reference, // transaction_id
                        $amount,
                        'USD', // Default currency
                        'completed',
                        ['method' => $method], // meta
                        date('Y-m-d H:i:s')
                    );
                    
                    $this->paymentRepository->save($payment);
                    
                    if ($amount >= $invoice->getTotalAmount()) {
                        $invoice->setStatus('paid');
                        $this->invoiceRepository->save($invoice);
                    }
                    
                    $successMessage = 'Payment recorded successfully.';
                } else {
                    $errorMessage = 'Invoice not found.';
                }
            } else {
                $errorMessage = 'Invalid payment details.';
            }
        }

        $invoices = $this->invoiceRepository->getAll();
        
        ?>
        <div class="businessapp-dashboard-wrap">
            <div class="businessapp-dashboard-header">
                <h1>Invoices</h1>
            </div>
            
            <?php if ($successMessage): ?>
                <div class="notice notice-success is-dismissible"><p><?php echo esc_html($successMessage); ?></p></div>
            <?php endif; ?>
            
            <?php if ($errorMessage): ?>
                <div class="notice notice-error is-dismissible"><p><?php echo esc_html($errorMessage); ?></p></div>
            <?php endif; ?>

            <div class="businessapp-section-card">
                <div class="businessapp-section-content">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Invoice #</th>
                                <th>Customer</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Amount</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($invoices)): ?>
                                <tr><td colspan="6">No invoices found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($invoices as $invoice): ?>
                                    <?php 
                                        $customer = $this->customerRepository->getById($invoice->getCustomerId());
                                        $customerName = $customer ? $customer->getName() : 'Unknown';
                                    ?>
                                    <tr>
                                        <td><?php echo esc_html($invoice->getId()); ?></td>
                                        <td><?php echo esc_html($customerName); ?></td>
                                        <td><?php echo esc_html($invoice->getCreatedAt()); ?></td>
                                        <td>
                                            <span class="businessapp-badge status-<?php echo esc_attr($invoice->getStatus()); ?>">
                                                <?php echo esc_html(ucfirst($invoice->getStatus())); ?>
                                            </span>
                                        </td>
                                        <td>$<?php echo esc_html(number_format($invoice->getTotalAmount(), 2)); ?></td>
                                        <td>
                                            <?php if ($invoice->getStatus() !== 'paid'): ?>
                                                <button type="button" class="button button-small businessapp-record-payment-btn" 
                                                    data-invoice-id="<?php echo esc_attr($invoice->getId()); ?>"
                                                    data-amount="<?php echo esc_attr($invoice->getTotalAmount()); ?>"
                                                    >Record Payment</button>
                                            <?php endif; ?>
                                            <a href="<?php echo esc_url(site_url('?businessapp_invoice_token=' . $invoice->getPublicToken())); ?>" target="_blank" class="button button-small">View</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Payment Modal -->
            <div id="businessapp-payment-modal" class="businessapp-modal" style="display:none;">
                <div class="businessapp-modal-content">
                    <div class="businessapp-modal-header">
                        <h3>Record Payment</h3>
                        <span class="businessapp-modal-close">&times;</span>
                    </div>
                    <form method="post" action="">
                        <?php wp_nonce_field('businessapp_manual_payment'); ?>
                        <input type="hidden" name="action" value="businessapp_manual_payment">
                        <input type="hidden" name="invoice_id" id="payment_invoice_id">
                        
                        <div class="businessapp-modal-body">
                            <div class="businessapp-form-group">
                                <label for="payment_amount">Amount ($)</label>
                                <input type="number" id="payment_amount" name="amount" step="0.01" min="0" required>
                            </div>
                            
                            <div class="businessapp-form-group">
                                <label for="payment_method">Payment Method</label>
                                <select id="payment_method" name="method">
                                    <option value="cash">Cash</option>
                                    <option value="check">Check</option>
                                    <option value="bank_transfer">Bank Transfer</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            
                            <div class="businessapp-form-group">
                                <label for="payment_reference">Reference / Note</label>
                                <input type="text" id="payment_reference" name="reference" placeholder="e.g. Check #123">
                            </div>
                        </div>
                        
                        <div class="businessapp-modal-footer">
                            <button type="button" class="businessapp-btn-secondary businessapp-modal-cancel">Cancel</button>
                            <button type="submit" class="businessapp-btn-primary">Record Payment</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <script>
                jQuery(document).ready(function($) {
                    $('.businessapp-record-payment-btn').on('click', function() {
                        var invoiceId = $(this).data('invoice-id');
                        var amount = $(this).data('amount');
                        $('#payment_invoice_id').val(invoiceId);
                        $('#payment_amount').val(amount);
                        $('#businessapp-payment-modal').fadeIn();
                    });
                    
                    $('.businessapp-modal-close, .businessapp-modal-cancel').on('click', function() {
                        $('#businessapp-payment-modal').fadeOut();
                    });
                });
            </script>
        </div>
        <?php
    }

    public function render_customers_page()
    {
        if (!current_user_can('manage_options')) {
            wp_die('Access denied');
        }
        ?>
        <div class="businessapp-dashboard-wrap">
            <div class="businessapp-dashboard-header">
                <h1>Customers</h1>
                <button id="businessapp-new-customer-btn" class="button button-primary">Add Customer</button>
            </div>
            
            <div class="businessapp-filters" style="margin-bottom: 20px;">
                <label for="businessapp-customer-status-filter" style="margin-right: 8px;">Status:</label>
                <select id="businessapp-customer-status-filter">
                    <option value="active" selected>Active</option>
                    <option value="archived">Archived</option>
                    <option value="">All</option>
                </select>
            </div>

            <div id="businessapp-customers-view">
                <!-- Customers List will be rendered here -->
                <div class="businessapp-loading">Loading customers...</div>
            </div>

            <!-- Customer Detail View (Hidden by default) -->
            <div id="businessapp-customer-detail-view" style="display:none;">
                <div class="businessapp-back-nav">
                    <a href="#" id="businessapp-back-to-customers">&larr; Back to Customers</a>
                </div>
                <div id="businessapp-customer-detail-content"></div>
            </div>

            <!-- New Customer Modal -->
            <div id="businessapp-customer-modal" class="businessapp-modal" style="display:none;">
                <div class="businessapp-modal-content">
                    <span class="businessapp-modal-close">&times;</span>
                    <h2>Add New Customer</h2>
                    <form id="businessapp-customer-form">
                        <div class="businessapp-form-group">
                            <label>Name</label>
                            <input type="text" name="name" required class="widefat">
                        </div>
                        <div class="businessapp-form-group">
                            <label>Email</label>
                            <input type="email" name="email" required class="widefat">
                        </div>
                        <div class="businessapp-form-group">
                            <label>Phone</label>
                            <input type="text" name="phone" required class="widefat">
                        </div>
                        <div id="businessapp-customer-dynamic-fields">
                            <!-- Dynamic fields injected via JS -->
                        </div>
                        <div class="businessapp-form-actions">
                            <button type="submit" class="button button-primary">Create Customer</button>
                            <button type="button" class="button businessapp-modal-cancel">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Associated Entity Modal -->
            <div id="businessapp-entity-modal" class="businessapp-modal" style="display:none;">
                <div class="businessapp-modal-content">
                    <span class="businessapp-modal-close">&times;</span>
                    <h2 id="businessapp-entity-modal-title">Add Entity</h2>
                    <form id="businessapp-entity-form">
                        <input type="hidden" name="customer_id" id="businessapp-entity-customer-id">
                        <div id="businessapp-entity-dynamic-fields">
                            <!-- Dynamic fields injected via JS -->
                        </div>
                        <div class="businessapp-form-actions">
                            <button type="submit" class="button button-primary">Save</button>
                            <button type="button" class="button businessapp-modal-cancel">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }

    public function render_analytics_page()
    {
        if (!current_user_can('manage_options')) {
            wp_die('Access denied');
        }
        ?>
        <div class="businessapp-dashboard-wrap">
            <div class="businessapp-dashboard-header">
                <h1>Analytics</h1>
            </div>
            <div class="businessapp-section-card">
                <div class="businessapp-section-content">
                    <p>Analytics dashboard coming soon.</p>
                </div>
            </div>
        </div>
        <?php
    }

    public function render_settings_page()
    {
        if (!current_user_can('manage_options')) {
            wp_die('Access denied');
        }

        $activeTab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'general';

        // Load all settings
        $generalSettings = get_option('businessapp_settings_general', []);
        $businessTypeSettings = get_option('businessapp_settings_business_type', []);
        $paymentSettings = get_option('businessapp_settings_payments', []);

        // Extract general settings with defaults
        $businessName = isset($generalSettings['business_name']) ? $generalSettings['business_name'] : 'Main Repair Shop';
        $businessOwner = isset($generalSettings['business_owner']) ? $generalSettings['business_owner'] : 'John Smith';
        $businessEmail = isset($generalSettings['business_email']) ? $generalSettings['business_email'] : 'info@mainrepair.com';
        $businessPhone = isset($generalSettings['business_phone']) ? $generalSettings['business_phone'] : '(555) 555-0123';
        $businessLogo = isset($generalSettings['business_logo']) ? $generalSettings['business_logo'] : '';
        $invoicePrefix = isset($generalSettings['invoice_prefix']) ? $generalSettings['invoice_prefix'] : 'INV-';
        $taxRate = isset($generalSettings['tax_rate']) ? (float) $generalSettings['tax_rate'] : 10.0;
        $paymentOptions = isset($generalSettings['payment_options']) ? $generalSettings['payment_options'] : ['credit_card', 'bank_transfer', 'cash'];
        $address = isset($generalSettings['address']) ? $generalSettings['address'] : '123 Auto Lane';
        $city = isset($generalSettings['city']) ? $generalSettings['city'] : 'Spfyrrb / City';
        $state = isset($generalSettings['state']) ? $generalSettings['state'] : 'CA';
        $postcode = isset($generalSettings['postcode']) ? $generalSettings['postcode'] : '98765';
        $defaultNote = isset($generalSettings['default_note']) ? $generalSettings['default_note'] : 'Thank you for your business! Let us know if you have any questions.';

        // Extract business type settings
        $businessType = isset($businessTypeSettings['business_type']) ? $businessTypeSettings['business_type'] : 'panel_beater';
        
        // Extract payment settings
        $stripePublishableKey = isset($paymentSettings['stripe_publishable_key']) ? $paymentSettings['stripe_publishable_key'] : '';
        $stripeSecretKey = isset($paymentSettings['stripe_secret_key']) ? $paymentSettings['stripe_secret_key'] : '';
        
        ?>
        <div class="businessapp-settings-wrap">
            <div class="businessapp-settings-header">
                <h1>Settings</h1>
            </div>

            <div class="businessapp-tabs">
                <a href="#" class="businessapp-tab <?php echo $activeTab === 'general' ? 'active' : ''; ?>" data-tab="general">General</a>
                <a href="#" class="businessapp-tab <?php echo $activeTab === 'payments' ? 'active' : ''; ?>" data-tab="payments">Payments</a>
                <a href="#" class="businessapp-tab <?php echo $activeTab === 'quote-settings' ? 'active' : ''; ?>" data-tab="quote-settings">Quote Settings</a>
                <a href="#" class="businessapp-tab <?php echo $activeTab === 'templates' ? 'active' : ''; ?>" data-tab="templates">Templates</a>
                <a href="#" class="businessapp-tab <?php echo $activeTab === 'business-type' ? 'active' : ''; ?>" data-tab="business-type">Business Type</a>
                <a href="#" class="businessapp-tab <?php echo $activeTab === 'data-units' ? 'active' : ''; ?>" data-tab="data-units">Data & Units</a>
            </div>

            <form id="businessapp-settings-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('businessapp_save_settings'); ?>
                <input type="hidden" name="action" value="businessapp_save_settings" />
                <input type="hidden" name="current_tab" id="businessapp-current-tab" value="<?php echo esc_attr($activeTab); ?>" />

                <div class="businessapp-settings-content">
                    <div class="businessapp-success-message"></div>

                    <!-- General Tab -->
                    <div id="businessapp-tab-general" class="businessapp-tab-content">
                        <div class="businessapp-section">
                            <h2 class="businessapp-section-title">Business Information</h2>
                            
                            <div class="businessapp-form-grid">
                                <div class="businessapp-field">
                                    <label for="business_name">Business Name:</label>
                                    <input type="text" id="business_name" name="business_name" value="<?php echo esc_attr($businessName); ?>" />
                                </div>

                                <div class="businessapp-field">
                                    <label for="business_owner">Business Owner:</label>
                                    <input type="text" id="business_owner" name="business_owner" value="<?php echo esc_attr($businessOwner); ?>" />
                                </div>

                                <div class="businessapp-field">
                                    <label for="business_email">Business Email:</label>
                                    <input type="email" id="business_email" name="business_email" value="<?php echo esc_attr($businessEmail); ?>" />
                                </div>

                                <div class="businessapp-field">
                                    <label for="business_phone">Business Phone:</label>
                                    <input type="tel" id="business_phone" name="business_phone" value="<?php echo esc_attr($businessPhone); ?>" />
                                </div>

                                <div class="businessapp-logo-upload">
                                    <div class="businessapp-logo-preview">
                                        <img id="businessapp-logo-preview-img" src="<?php echo esc_url($businessLogo); ?>" alt="Logo Preview" style="<?php echo empty($businessLogo) ? 'display:none;' : ''; ?>" />
                                    </div>
                                    <div class="businessapp-logo-info">
                                        <p><strong>Logo</strong></p>
                                        <button type="button" id="businessapp-upload-logo" class="businessapp-upload-btn">
                                            📤 Upload New Logo
                                        </button>
                                        <input type="hidden" id="businessapp-logo-url" name="business_logo" value="<?php echo esc_attr($businessLogo); ?>" />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="businessapp-section">
                            <h2 class="businessapp-section-title">Invoicing & Payments</h2>
                            
                            <div class="businessapp-form-grid">
                                <div class="businessapp-field">
                                    <label for="invoice_prefix">Invoice Prefix:</label>
                                    <select id="invoice_prefix" name="invoice_prefix">
                                        <option value="INV-" <?php selected($invoicePrefix, 'INV-'); ?>>INV-</option>
                                        <option value="QUOTE-" <?php selected($invoicePrefix, 'QUOTE-'); ?>>QUOTE-</option>
                                        <option value="EST-" <?php selected($invoicePrefix, 'EST-'); ?>>EST-</option>
                                    </select>
                                </div>

                                <div class="businessapp-field">
                                    <label for="default_payment_terms">Default Payment Terms:</label>
                                    <select id="default_payment_terms" name="default_payment_terms">
                                        <option value="net_30">Net 30</option>
                                        <option value="net_15">Net 15</option>
                                        <option value="due_on_receipt">Due on Receipt</option>
                                    </select>
                                </div>

                                <div class="businessapp-field full-width">
                                    <label>Payment Options:</label>
                                    <div class="businessapp-checkbox-list">
                                        <div class="businessapp-checkbox-item">
                                            <input type="checkbox" id="payment_credit_card" name="payment_options[]" value="credit_card" <?php checked(in_array('credit_card', $paymentOptions)); ?> />
                                            <label for="payment_credit_card">Credit Card</label>
                                        </div>
                                        <div class="businessapp-checkbox-item">
                                            <input type="checkbox" id="payment_bank_transfer" name="payment_options[]" value="bank_transfer" <?php checked(in_array('bank_transfer', $paymentOptions)); ?> />
                                            <label for="payment_bank_transfer">Bank Transfer</label>
                                        </div>
                                        <div class="businessapp-checkbox-item">
                                            <input type="checkbox" id="payment_cash" name="payment_options[]" value="cash" <?php checked(in_array('cash', $paymentOptions)); ?> />
                                            <label for="payment_cash">Cash</label>
                                        </div>
                                    </div>
                                </div>

                                <div class="businessapp-field full-width">
                                    <label for="address">Address:</label>
                                    <input type="text" id="address" name="address" value="<?php echo esc_attr($address); ?>" />
                                </div>

                                <div class="businessapp-field">
                                    <label for="city">City:</label>
                                    <input type="text" id="city" name="city" value="<?php echo esc_attr($city); ?>" />
                                </div>

                                <div class="businessapp-field">
                                    <label for="state">State:</label>
                                    <select id="state" name="state">
                                        <option value="CA" <?php selected($state, 'CA'); ?>>CA</option>
                                        <option value="NY" <?php selected($state, 'NY'); ?>>NY</option>
                                        <option value="TX" <?php selected($state, 'TX'); ?>>TX</option>
                                        <option value="FL" <?php selected($state, 'FL'); ?>>FL</option>
                                    </select>
                                </div>

                                <div class="businessapp-field">
                                    <label for="postcode">Postcode:</label>
                                    <input type="text" id="postcode" name="postcode" value="<?php echo esc_attr($postcode); ?>" />
                                </div>

                                <div class="businessapp-field">
                                    <label for="tax_rate">Tax Rate:</label>
                                    <input type="number" id="tax_rate" name="tax_rate" step="0.01" min="0" value="<?php echo esc_attr($taxRate); ?>" />
                                </div>

                                <div class="businessapp-field full-width">
                                    <label for="default_note">Default Note on Quotes & Invoices:</label>
                                    <textarea id="default_note" name="default_note"><?php echo esc_textarea($defaultNote); ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Payments Tab -->
                    <div id="businessapp-tab-payments" class="businessapp-tab-content" style="display:none;">
                        <div class="businessapp-section">
                            <h2 class="businessapp-section-title">Stripe Settings</h2>
                            <p class="businessapp-text-muted">Configure your Stripe keys to enable online payments.</p>
                            
                            <div class="businessapp-form-grid">
                                <div class="businessapp-field full-width">
                                    <label for="stripe_publishable_key">Publishable Key:</label>
                                    <input type="text" id="stripe_publishable_key" name="stripe_publishable_key" value="<?php echo esc_attr($stripePublishableKey); ?>" placeholder="pk_test_..." />
                                </div>

                                <div class="businessapp-field full-width">
                                    <label for="stripe_secret_key">Secret Key:</label>
                                    <input type="password" id="stripe_secret_key" name="stripe_secret_key" value="<?php echo esc_attr($stripeSecretKey); ?>" placeholder="sk_test_..." />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Quote Settings Tab -->
                    <div id="businessapp-tab-quote-settings" class="businessapp-tab-content" style="display:none;">
                        <div class="businessapp-section">
                            <h2 class="businessapp-section-title">Quote Settings</h2>
                            <p class="businessapp-text-muted">Quote settings will be available in a future update.</p>
                        </div>
                    </div>

                    <!-- Templates Tab -->
                    <div id="businessapp-tab-templates" class="businessapp-tab-content" style="display:none;">
                        <div class="businessapp-section">
                            <h2 class="businessapp-section-title">Quote Templates</h2>
                            <p class="businessapp-text-muted">Template management will be available in a future update.</p>
                        </div>
                    </div>

                    <!-- Business Type Tab -->
                    <div id="businessapp-tab-business-type" class="businessapp-tab-content" style="display:none;">
                        <div class="businessapp-section">
                            <h2 class="businessapp-section-title">Business Type Settings</h2>
                            
                            <div class="businessapp-business-type-selector">
                                <label for="businessapp-business-type">Business Type:</label>
                                <select id="businessapp-business-type" name="business_type">
                                    <option value="panel_beater" <?php selected($businessType, 'panel_beater'); ?>>Panel Beater</option>
                                    <option value="gardening" <?php selected($businessType, 'gardening'); ?>>Gardening</option>
                                    <option value="plumbing" <?php selected($businessType, 'plumbing'); ?>>Plumbing</option>
                                    <option value="electrical" <?php selected($businessType, 'electrical'); ?>>Electrical</option>
                                </select>
                                <button type="button" class="businessapp-update-btn">🔄 Update</button>
                            </div>

                            <div class="businessapp-divider"></div>

                            <p class="businessapp-text-muted">
                                The selected business type determines the fields available on the quote form.
                                <br>
                                <strong>Current Schema:</strong> <?php echo esc_html(ucwords(str_replace('_', ' ', $businessType))); ?>
                            </p>


                            <div style="margin-top: 32px;">
                                <h4 style="font-size: 16px; font-weight: 600; margin-bottom: 16px;">Line Item Templates</h4>
                                <div class="businessapp-template-list">
                                    <div class="businessapp-template-item">
                                        <label>
                                            <input type="checkbox" name="templates[]" value="bumper_replacement" />
                                            <span>Bumper Replacement</span>
                                        </label>
                                        <span class="businessapp-template-price">$600.00 ></span>
                                    </div>
                                    <div class="businessapp-template-item">
                                        <label>
                                            <input type="checkbox" name="templates[]" value="door_panel_repair" />
                                            <span>Door Panel Repair</span>
                                        </label>
                                        <span class="businessapp-template-price">$400.00 ></span>
                                    </div>
                                    <div class="businessapp-template-item">
                                        <label>
                                            <input type="checkbox" name="templates[]" value="quarter_panel_repair" />
                                            <span>Quarter Panel Repair</span>
                                        </label>
                                        <span class="businessapp-template-price">$900.00 ></span>
                                    </div>
                                    <div class="businessapp-template-item">
                                        <label>
                                            <input type="checkbox" name="templates[]" value="windshield_replacement" />
                                            <span>Windshield Replacement</span>
                                        </label>
                                        <span class="businessapp-template-price">$2,200.00 ></span>
                                    </div>
                                </div>
                                <button type="button" class="businessapp-add-template-btn">+ Add Template</button>
                            </div>
                        </div>
                    </div>

                    <!-- Data & Units Tab -->
                    <div id="businessapp-tab-data-units" class="businessapp-tab-content" style="display:none;">
                        <div class="businessapp-section">
                            <h2 class="businessapp-section-title">Data & Units</h2>
                            <p class="businessapp-text-muted">Data and units settings will be available in a future update.</p>
                        </div>
                    </div>
                </div>

                <button type="submit" class="businessapp-save-btn">💾 Save Changes</button>
            </form>
        </div>
        <?php
    }

    public function handle_admin_save_settings()
    {
        if (!current_user_can('manage_options')) {
            wp_die('Access denied');
        }

        check_admin_referer('businessapp_save_settings');

        $currentTab = isset($_POST['current_tab']) ? sanitize_key(wp_unslash($_POST['current_tab'])) : 'general';

        // Save General Settings
        $generalSettings = [];
        
        if (isset($_POST['business_name'])) {
            $generalSettings['business_name'] = sanitize_text_field(wp_unslash($_POST['business_name']));
        }
        
        if (isset($_POST['business_owner'])) {
            $generalSettings['business_owner'] = sanitize_text_field(wp_unslash($_POST['business_owner']));
        }
        
        if (isset($_POST['business_email'])) {
            $generalSettings['business_email'] = sanitize_email(wp_unslash($_POST['business_email']));
        }
        
        if (isset($_POST['business_phone'])) {
            $generalSettings['business_phone'] = sanitize_text_field(wp_unslash($_POST['business_phone']));
        }
        
        if (isset($_POST['business_logo'])) {
            $generalSettings['business_logo'] = esc_url_raw(wp_unslash($_POST['business_logo']));
        }
        
        if (isset($_POST['invoice_prefix'])) {
            $generalSettings['invoice_prefix'] = sanitize_text_field(wp_unslash($_POST['invoice_prefix']));
        }
        
        if (isset($_POST['tax_rate'])) {
            $taxRate = (float) wp_unslash($_POST['tax_rate']);
            $generalSettings['tax_rate'] = $taxRate < 0 ? 0.0 : $taxRate;
        }
        
        if (isset($_POST['payment_options']) && is_array($_POST['payment_options'])) {
            $generalSettings['payment_options'] = array_map('sanitize_text_field', wp_unslash($_POST['payment_options']));
        } else {
            $generalSettings['payment_options'] = [];
        }
        
        if (isset($_POST['address'])) {
            $generalSettings['address'] = sanitize_text_field(wp_unslash($_POST['address']));
        }
        
        if (isset($_POST['city'])) {
            $generalSettings['city'] = sanitize_text_field(wp_unslash($_POST['city']));
        }
        
        if (isset($_POST['state'])) {
            $generalSettings['state'] = sanitize_text_field(wp_unslash($_POST['state']));
        }
        
        if (isset($_POST['postcode'])) {
            $generalSettings['postcode'] = sanitize_text_field(wp_unslash($_POST['postcode']));
        }
        
        if (isset($_POST['default_note'])) {
            $generalSettings['default_note'] = sanitize_textarea_field(wp_unslash($_POST['default_note']));
        }

        update_option('businessapp_settings_general', $generalSettings);

        // Save Payment Settings
        $paymentSettings = [];
        
        if (isset($_POST['stripe_publishable_key'])) {
            $paymentSettings['stripe_publishable_key'] = sanitize_text_field(wp_unslash($_POST['stripe_publishable_key']));
        }
        
        if (isset($_POST['stripe_secret_key'])) {
            $paymentSettings['stripe_secret_key'] = sanitize_text_field(wp_unslash($_POST['stripe_secret_key']));
        }
        
        update_option('businessapp_settings_payments', $paymentSettings);

        // Save Business Type Settings
        $businessTypeSettings = [];
        
        if (isset($_POST['business_type'])) {
            $businessTypeSettings['business_type'] = sanitize_text_field(wp_unslash($_POST['business_type']));
        }

        update_option('businessapp_settings_business_type', $businessTypeSettings);

        $redirectUrl = add_query_arg(
            [
                'page'    => 'businessapp-settings',
                'tab'     => $currentTab,
                'updated' => 1,
            ],
            admin_url('admin.php')
        );

        wp_safe_redirect($redirectUrl);
        exit;
    }

    public function handle_admin_create_quote()
    {
        if (!current_user_can('manage_options')) {
            wp_die('Access denied');
        }

        check_admin_referer('businessapp_create_quote');

        $customerName = isset($_POST['customer_name']) ? sanitize_text_field(wp_unslash($_POST['customer_name'])) : '';
        $customerEmail = isset($_POST['customer_email']) ? sanitize_text_field(wp_unslash($_POST['customer_email'])) : '';
        $customerPhone = isset($_POST['customer_phone']) ? sanitize_text_field(wp_unslash($_POST['customer_phone'])) : '';
        $title = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';
        $totalAmount = isset($_POST['total_amount']) ? (float) wp_unslash($_POST['total_amount']) : 0.0;
        $notes = isset($_POST['notes']) ? wp_kses_post(wp_unslash($_POST['notes'])) : '';

        if ($title !== '') {
            // Determine active schema for snapshot
            $businessTypeSettings = get_option('businessapp_settings_business_type', []);
            $activeTypeId = isset($businessTypeSettings['business_type']) ? $businessTypeSettings['business_type'] : 'panel_beater';

            // Create a single line item if total amount is provided but no items (legacy support)
            $lineItems = [];
            if ($totalAmount > 0) {
                $lineItems[] = [
                    'description' => 'Quote Amount',
                    'qty' => 1,
                    'unit' => 'ea',
                    'unit_price' => $totalAmount
                ];
            }

            $quote = $this->quoteFactory->createDraft(
                0, // customerId (0 for ad-hoc)
                $title,
                $lineItems,
                $activeTypeId,
                [], // associatedEntityIds
                $customerName,
                $customerEmail,
                $customerPhone,
                $notes
            );
            
            $this->quoteRepository->save($quote);
        }

        $redirectUrl = add_query_arg(
            [
                'page'    => 'businessapp-dashboard',
                'created' => 1,
            ],
            admin_url('admin.php')
        );

        wp_safe_redirect($redirectUrl);
        exit;
    }

    public function handle_create_payment_intent($request)
    {
        $params = $request->get_json_params();
        $token = isset($params['public_token']) ? sanitize_text_field($params['public_token']) : '';
        
        if (!$token) {
            return new \WP_Error('missing_token', 'Missing public token', ['status' => 400]);
        }
        
        $invoice = $this->invoiceRepository->findByPublicToken($token);
        
        if (!$invoice) {
            return new \WP_Error('not_found', 'Invoice not found', ['status' => 404]);
        }
        
        $paymentSettings = get_option('businessapp_settings_payments', []);
        $secretKey = isset($paymentSettings['stripe_secret_key']) ? $paymentSettings['stripe_secret_key'] : '';
        
        if (!$secretKey) {
            return new \WP_Error('configuration_error', 'Stripe is not configured', ['status' => 500]);
        }
        
        // Calculate amount in cents
        $amount = (int) ($invoice->getTotalAmount() * 100);
        $currency = 'usd'; // Default to USD
        
        $body = [
            'amount' => $amount,
            'currency' => $currency,
            'metadata' => [
                'invoice_id' => $invoice->getId(),
                'customer_id' => $invoice->getCustomerId(),
            ],
            'automatic_payment_methods' => ['enabled' => 'true'],
        ];
        
        $response = wp_remote_post('https://api.stripe.com/v1/payment_intents', [
            'headers' => [
                'Authorization' => 'Bearer ' . $secretKey,
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'body' => http_build_query($body),
        ]);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $responseBody = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($responseBody['error'])) {
            return new \WP_Error('stripe_error', $responseBody['error']['message'], ['status' => 400]);
        }
        
        return rest_ensure_response([
            'clientSecret' => $responseBody['client_secret'],
            'publishableKey' => isset($paymentSettings['stripe_publishable_key']) ? $paymentSettings['stripe_publishable_key'] : '',
        ]);
    }

    public function maybe_render_public_invoice()
    {
        if (!isset($_GET['businessapp_invoice_token'])) {
            return;
        }

        $token = sanitize_text_field(wp_unslash($_GET['businessapp_invoice_token']));
        $invoice = $this->invoiceRepository->findByPublicToken($token);

        if (!$invoice) {
            status_header(404);
            nocache_headers();
            echo '<!doctype html><html><head><meta charset="utf-8"><title>Invoice not found</title></head><body><h1>Invoice not found</h1><p>The invoice link is invalid or has expired.</p></body></html>';
            exit;
        }

        $paymentSettings = get_option('businessapp_settings_payments', []);

        // Handle Payment Success Return
        if (isset($_GET['payment_intent']) && isset($_GET['payment_intent_client_secret']) && isset($_GET['payment_success'])) {
            $paymentIntentId = sanitize_text_field($_GET['payment_intent']);
            $secretKey = isset($paymentSettings['stripe_secret_key']) ? $paymentSettings['stripe_secret_key'] : '';

            if ($secretKey) {
                // Verify with Stripe
                $response = wp_remote_get('https://api.stripe.com/v1/payment_intents/' . $paymentIntentId, [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $secretKey,
                    ]
                ]);

                if (!is_wp_error($response)) {
                    $body = json_decode(wp_remote_retrieve_body($response), true);
                    if (isset($body['status']) && $body['status'] === 'succeeded') {
                        // Check if already recorded
                        $existingPayments = $this->paymentRepository->getByInvoiceId($invoice->getId());
                        $alreadyRecorded = false;
                        foreach ($existingPayments as $p) {
                            if ($p->getTransactionId() === $paymentIntentId) {
                                $alreadyRecorded = true;
                                break;
                            }
                        }

                        if (!$alreadyRecorded) {
                            // Create Payment Record
                            $amount = $body['amount_received'] / 100; // Cents to Dollars
                            $payment = new \BusinessApp\Domain\Payment(
                                0,
                                $invoice->getId(),
                                'stripe',
                                $paymentIntentId,
                                $amount,
                                strtoupper($body['currency']),
                                'completed',
                                ['method' => 'card'],
                                date('Y-m-d H:i:s')
                            );
                            
                            $this->paymentRepository->save($payment);
                            
                            // Update Invoice
                            if ($amount >= $invoice->getTotalAmount()) {
                                $invoice->setStatus('paid');
                                $this->invoiceRepository->save($invoice);
                            }
                        }
                    }
                }
            }
        }

        $publishableKey = isset($paymentSettings['stripe_publishable_key']) ? $paymentSettings['stripe_publishable_key'] : '';
        $isStripeEnabled = !empty($publishableKey) && !empty($paymentSettings['stripe_secret_key']);
        $items = $this->invoiceItemRepository->getItemsForInvoice($invoice->getId());

        status_header(200);
        nocache_headers();
        
        echo '<!doctype html>';
        echo '<html>';
        echo '<head>';
        echo '<meta charset="utf-8" />';
        echo '<title>Invoice #' . esc_html($invoice->getId()) . '</title>';
        echo '<meta name="viewport" content="width=device-width, initial-scale=1" />';
        if ($isStripeEnabled && $invoice->getStatus() !== 'paid') {
            echo '<script src="https://js.stripe.com/v3/"></script>';
        }
        echo '<style>';
        echo 'body{font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif;background:#f9fafb;margin:0;padding:0;}';
        echo '.page{max-width:640px;margin:40px auto;padding:24px;border-radius:8px;background:#ffffff;box-shadow:0 10px 15px -3px rgba(0,0,0,0.1),0 4px 6px -4px rgba(0,0,0,0.1);}';
        echo 'h1{font-size:24px;margin:0 0 16px;}';
        echo '.muted{color:#6b7280;font-size:14px;margin-bottom:4px;}';
        echo '.amount{font-size:24px;font-weight:600;margin:8px 0 16px;}';
        echo 'table{width:100%;border-collapse:collapse;margin-top:16px;font-size:14px;}';
        echo 'th,td{text-align:left;padding:8px 4px;}';
        echo 'th{border-bottom:1px solid #e5e7eb;color:#6b7280;font-weight:500;font-size:12px;text-transform:uppercase;letter-spacing:0.05em;}';
        echo 'tr:nth-child(even) td{background:#f9fafb;}';
        echo '.status{display:inline-block;padding:2px 8px;border-radius:999px;font-size:12px;margin-left:8px;}';
        echo '.status-paid{background:#dcfce7;color:#166534;}';
        echo '.status-unpaid{background:#fee2e2;color:#b91c1c;}';
        echo '#payment-element{margin-top: 24px; margin-bottom: 24px;}';
        echo '#submit{background:#16a34a;color:#ffffff;border:none;padding:12px 24px;border-radius:4px;font-size:16px;font-weight:600;cursor:pointer;width:100%;}';
        echo '#submit:disabled{opacity:0.5;cursor:not-allowed;}';
        echo '#error-message{color:#dc2626;margin-top:12px;font-size:14px;}';
        echo '.hidden{display:none;}';
        echo '</style>';
        echo '</head>';
        echo '<body>';
        echo '<div class="page">';
        
        $customer = $this->customerRepository->getById($invoice->getCustomerId());
        $customerName = $customer ? $customer->getName() : 'Customer';
        
        echo '<div class="muted">Invoice for ' . esc_html($customerName) . '</div>';
        echo '<h1>Invoice #' . esc_html($invoice->getId()) . '</h1>';
        
        $statusClass = 'status-' . ($invoice->getStatus() === 'paid' ? 'paid' : 'unpaid');
        echo '<div><span class="muted">Status</span><span class="status ' . esc_attr($statusClass) . '">' . esc_html(ucfirst($invoice->getStatus())) . '</span></div>';
        
        echo '<div class="amount">$' . esc_html(number_format((float) $invoice->getTotalAmount(), 2)) . '</div>';
        
        if (!empty($items)) {
            echo '<table>';
            echo '<thead><tr><th>Description</th><th>Qty</th><th>Unit price</th><th>Total</th></tr></thead>';
            echo '<tbody>';
            foreach ($items as $item) {
                echo '<tr>';
                echo '<td>' . esc_html($item['description']) . '</td>';
                echo '<td>' . esc_html($item['qty']) . '</td>';
                echo '<td>$' . esc_html(number_format((float) $item['unit_price'], 2)) . '</td>';
                echo '<td>$' . esc_html(number_format((float) $item['total'], 2)) . '</td>';
                echo '</tr>';
            }
            echo '</tbody>';
            echo '</table>';
        }

        if ($isStripeEnabled && $invoice->getStatus() !== 'paid') {
            echo '<div id="payment-form-container">';
            echo '<form id="payment-form">';
            echo '<div id="payment-element"><!--Stripe.js injects the Payment Element--></div>';
            echo '<button id="submit"><div class="spinner hidden" id="spinner"></div><span id="button-text">Pay Now</span></button>';
            echo '<div id="error-message" class="hidden"></div>';
            echo '</form>';
            echo '</div>';

            echo "<script>
            const stripe = Stripe('" . esc_js($publishableKey) . "');
            const token = '" . esc_js($token) . "';
            
            initialize();

            async function initialize() {
                const response = await fetch('/wp-json/businessapp/v1/create-payment-intent', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ public_token: token }),
                });
                
                const { clientSecret } = await response.json();
                
                const appearance = { theme: 'stripe' };
                const elements = stripe.elements({ appearance, clientSecret });
                
                const paymentElementOptions = { layout: 'tabs' };
                const paymentElement = elements.create('payment', paymentElementOptions);
                paymentElement.mount('#payment-element');
                
                const form = document.getElementById('payment-form');
                form.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    setLoading(true);
                    
                    const { error } = await stripe.confirmPayment({
                        elements,
                        confirmParams: {
                            return_url: window.location.href + '&payment_success=true',
                        },
                    });
                    
                    if (error) {
                        showMessage(error.message);
                        setLoading(false);
                    } else {
                        // The UI automatically closes the modal
                    }
                });
            }

            function showMessage(messageText) {
                const messageContainer = document.querySelector('#error-message');
                messageContainer.classList.remove('hidden');
                messageContainer.textContent = messageText;
            }

            function setLoading(isLoading) {
                if (isLoading) {
                    document.querySelector('#submit').disabled = true;
                    document.querySelector('#spinner').classList.remove('hidden');
                    document.querySelector('#button-text').classList.add('hidden');
                } else {
                    document.querySelector('#submit').disabled = false;
                    document.querySelector('#spinner').classList.add('hidden');
                    document.querySelector('#button-text').classList.remove('hidden');
                }
            }
            </script>";
        }

        echo '</div>'; // .page
        echo '</body></html>';
        exit;
    }

    public function maybe_render_public_quote()
    {
        if (!isset($_GET['businessapp_quote_token'])) {
            return;
        }

        $token = sanitize_text_field(wp_unslash($_GET['businessapp_quote_token']));
        $decision = isset($_GET['decision']) ? sanitize_key(wp_unslash($_GET['decision'])) : '';

        $quote = $this->quoteRepository->findByPublicToken($token);

        if (!$quote) {
            status_header(404);
            nocache_headers();
            echo '<!doctype html><html><head><meta charset="utf-8"><title>Quote not found</title></head><body><h1>Quote not found</h1><p>The quote link is invalid or has expired.</p></body></html>';
            exit;
        }

        $message = '';

        if ($decision === 'accept' || $decision === 'reject') {
            try {
                if ($decision === 'accept') {
                    $quote->markAccepted();
                    $message = 'Thank you. You have accepted this quote.';
                } else {
                    $quote->markRejected();
                    $message = 'You have rejected this quote.';
                }

                $this->quoteRepository->save($quote);
            } catch (\RuntimeException $e) {
                $message = $e->getMessage();
            }
        }

        $items = $this->quoteItemRepository->getItemsForQuote($quote->getId());

        $baseUrl = add_query_arg('businessapp_quote_token', $quote->getPublicToken(), home_url('/'));
        $acceptUrl = add_query_arg('decision', 'accept', $baseUrl);
        $rejectUrl = add_query_arg('decision', 'reject', $baseUrl);

        status_header(200);
        nocache_headers();

        echo '<!doctype html>';
        echo '<html>';
        echo '<head>';
        echo '<meta charset="utf-8" />';
        echo '<title>' . esc_html($quote->getTitle()) . '</title>';
        echo '<meta name="viewport" content="width=device-width, initial-scale=1" />';
        echo '<style>';
        echo 'body{font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif;background:#f9fafb;margin:0;padding:0;}';
        echo '.page{max-width:640px;margin:40px auto;padding:24px;border-radius:8px;background:#ffffff;box-shadow:0 10px 15px -3px rgba(0,0,0,0.1),0 4px 6px -4px rgba(0,0,0,0.1);}';
        echo 'h1{font-size:24px;margin:0 0 16px;}';
        echo '.muted{color:#6b7280;font-size:14px;margin-bottom:4px;}';
        echo '.amount{font-size:24px;font-weight:600;margin:8px 0 16px;}';
        echo 'table{width:100%;border-collapse:collapse;margin-top:16px;font-size:14px;}';
        echo 'th,td{text-align:left;padding:8px 4px;}';
        echo 'th{border-bottom:1px solid #e5e7eb;color:#6b7280;font-weight:500;font-size:12px;text-transform:uppercase;letter-spacing:0.05em;}';
        echo 'tr:nth-child(even) td{background:#f9fafb;}';
        echo '.notes{margin-top:16px;padding:12px;border-radius:4px;background:#f3f4f6;font-size:14px;white-space:pre-wrap;}';
        echo '.actions{margin-top:24px;display:flex;gap:12px;}';
        echo '.btn{display:inline-flex;align-items:center;justify-content:center;padding:10px 16px;border-radius:4px;font-size:14px;font-weight:500;text-decoration:none;}';
        echo '.btn-primary{background:#16a34a;color:#ffffff;}';
        echo '.btn-primary:hover{background:#15803d;}';
        echo '.btn-secondary{background:#dc2626;color:#ffffff;}';
        echo '.btn-secondary:hover{background:#b91c1c;}';
        echo '.status{display:inline-block;padding:2px 8px;border-radius:999px;font-size:12px;margin-left:8px;}';
        echo '.status-draft{background:#e5e7eb;color:#374151;}';
        echo '.status-sent{background:#dbeafe;color:#1d4ed8;}';
        echo '.status-accepted{background:#dcfce7;color:#166534;}';
        echo '.status-rejected{background:#fee2e2;color:#b91c1c;}';
        echo '.status-expired{background:#fef3c7;color:#92400e;}';
        echo '.message{margin-top:12px;font-size:14px;color:#374151;}';
        echo '</style>';
        echo '</head>';
        echo '<body>';
        echo '<div class="page">';
        echo '<div class="muted">Quote for ' . esc_html($quote->getCustomerName()) . '</div>';
        echo '<h1>' . esc_html($quote->getTitle()) . '</h1>';

        $statusClass = 'status-' . $quote->getStatus();
        echo '<div><span class="muted">Status</span><span class="status ' . esc_attr($statusClass) . '">' . esc_html(ucfirst($quote->getStatus())) . '</span></div>';

        echo '<div class="amount">' . esc_html(number_format((float) $quote->getTotalAmount(), 2)) . '</div>';

        $schemaSnapshot = $quote->getSchemaSnapshot();
        $dynamicFields = $quote->getDynamicFields();

        if (!empty($schemaSnapshot) && !empty($schemaSnapshot['business_type_id'])) {
            $typeId = $schemaSnapshot['business_type_id'];
            $schema = $this->businessTypeRegistry->get($typeId);
            
            if ($schema) {
                echo '<div class="dynamic-fields" style="margin-bottom: 24px; padding: 16px; background: #f3f4f6; border-radius: 8px;">';
                echo '<h3 style="margin: 0 0 12px; font-size: 16px; color: #374151;">' . esc_html($schema->getName()) . ' Details</h3>';
                echo '<div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px;">';
                
                foreach ($schemaSnapshot['fields'] as $fieldRef) {
                    $fieldId = isset($fieldRef['id']) ? $fieldRef['id'] : '';
                    if (!$fieldId) continue;

                    // Prioritize snapshot data for historical integrity
                    $label = isset($fieldRef['label']) ? $fieldRef['label'] : '';
                    $type = isset($fieldRef['type']) ? $fieldRef['type'] : '';
                    $unit = isset($fieldRef['unit']) ? $fieldRef['unit'] : '';

                    // Fallback to active schema if snapshot data is missing (legacy records)
                    $fieldDef = $schema->getField($fieldId);
                    if ($fieldDef) {
                        if (!$label) $label = $fieldDef->getLabel();
                        if (!$type) $type = $fieldDef->getType();
                        if (!$unit) $unit = $fieldDef->getUnit();
                    }

                    // If we still don't have a label, skip (or show ID?)
                    if (!$label) continue;
                    
                    if (isset($dynamicFields[$fieldId])) {
                        $value = $dynamicFields[$fieldId];
                        
                        // Handle boolean/checkbox
                        if ($type === 'checkbox') {
                            $value = $value ? 'Yes' : 'No';
                        }
                        
                        echo '<div>';
                        echo '<div class="muted" style="font-size: 12px;">' . esc_html($label) . '</div>';
                        echo '<div style="font-weight: 500;">' . esc_html($value) . ($unit ? ' ' . esc_html($unit) : '') . '</div>';
                        echo '</div>';
                    }
                }
                
                echo '</div>';
                echo '</div>';
            }
        }

        if (!empty($items)) {
            echo '<table>';
            echo '<thead><tr><th>Description</th><th>Qty</th><th>Unit price</th><th>Total</th></tr></thead>';
            echo '<tbody>';
            foreach ($items as $item) {
                $description = isset($item['description']) ? $item['description'] : '';
                $qty = isset($item['qty']) ? (int) $item['qty'] : 1;
                $unitPrice = isset($item['unit_price']) ? (float) $item['unit_price'] : 0.0;
                $amount = isset($item['amount']) ? (float) $item['amount'] : 0.0;
                echo '<tr>';
                echo '<td>' . esc_html($description) . '</td>';
                echo '<td>' . esc_html((string) $qty) . '</td>';
                echo '<td>' . esc_html(number_format($unitPrice, 2)) . '</td>';
                echo '<td>' . esc_html(number_format($amount, 2)) . '</td>';
                echo '</tr>';
            }
            echo '</tbody>';
            echo '</table>';
        }

        if ($quote->getNotes() !== '') {
            echo '<div class="notes">' . wp_kses_post($quote->getNotes()) . '</div>';
        }

        if ($quote->getStatus() === 'sent' || $quote->getStatus() === 'discussing') {
            echo '<div class="actions">';
            echo '<a class="btn btn-primary" href="' . esc_url($acceptUrl) . '">Accept quote</a>';
            echo '<a class="btn btn-secondary" href="' . esc_url($rejectUrl) . '">Reject quote</a>';
            echo '</div>';
        }

        if ($message !== '') {
            echo '<div class="message">' . esc_html($message) . '</div>';
        }

        echo '</div>';
        echo '</body>';
        echo '</html>';
        exit;
    }

    public function enqueue_frontend_assets()
    {
        if (!is_singular()) {
            return;
        }

        global $post;

        if (!$post instanceof \WP_Post) {
            return;
        }

        if (strpos($post->post_content, '[businessapp_app]') === false) {
            return;
        }

        wp_enqueue_style(
            'businessapp-tailwind',
            'https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css',
            [],
            '2.2.19'
        );

        wp_enqueue_script(
            'businessapp-app',
            plugins_url('assets/app.js', __FILE__),
            [],
            '0.1.0',
            true
        );

        $taxRate = $this->get_tax_rate_fraction();

        wp_localize_script(
            'businessapp-app',
            'BusinessAppConfig',
            [
                'restUrl' => esc_url_raw(rest_url('businessapp/v1/')),
                'nonce'   => wp_create_nonce('wp_rest'),
                'taxRate' => $taxRate,
            ]
        );
    }

    public function render_frontend_app()
    {
        return '<div id="businessapp-root" class="min-h-screen bg-gray-50"></div>';
    }

    public function handle_get_customers($request)
    {
        $status = isset($request['status']) ? sanitize_key($request['status']) : null;
        $customers = $this->customerRepository->getAll($status);

        $data = array_map(function ($customer) {
            return $customer->toArray();
        }, $customers);

        return rest_ensure_response($data);
    }

    public function handle_create_customer($request)
    {
        $params = $request->get_json_params();

        $name = isset($params['name']) ? sanitize_text_field($params['name']) : '';
        $email = isset($params['email']) ? sanitize_email($params['email']) : '';
        $phone = isset($params['phone']) ? sanitize_text_field($params['phone']) : '';
        $status = 'active';

        // Snapshot Schema
        $businessTypeSettings = get_option('businessapp_settings_business_type', []);
        $activeTypeId = isset($businessTypeSettings['business_type']) ? $businessTypeSettings['business_type'] : 'panel_beater';
        $activeSchema = $this->businessTypeRegistry->get($activeTypeId);

        $schemaSnapshot = [];
        $dynamicFields = [];

        if ($activeSchema) {
            $schemaSnapshot = [
                'business_type_id' => $activeTypeId,
                'fields' => []
            ];

            foreach ($activeSchema->getCustomerFields() as $field) {
                $schemaSnapshot['fields'][] = [
                    'id' => $field->getId(),
                    'label' => $field->getLabel(),
                    'type' => $field->getType(),
                    'unit' => $field->getUnit(),
                    'options' => $field->getOptions() // Important for selects
                ];
            }

            // Extract Dynamic Fields
            if (isset($params['dynamic_fields']) && is_array($params['dynamic_fields'])) {
                foreach ($activeSchema->getCustomerFields() as $field) {
                    $fieldId = $field->getId();
                    if (isset($params['dynamic_fields'][$fieldId])) {
                        $dynamicFields[$fieldId] = $params['dynamic_fields'][$fieldId];
                    }
                }
            }
        }

        $customer = $this->customerRepository->create($name, $email, $phone, $status, $dynamicFields, $schemaSnapshot);

        return rest_ensure_response($customer->toArray());
    }

    public function handle_get_customer($request)
    {
        $id = isset($request['id']) ? (int) $request['id'] : 0;
        $customer = $this->customerRepository->getById($id);

        if (!$customer) {
            return new \WP_Error('not_found', 'Customer not found', ['status' => 404]);
        }

        return rest_ensure_response($customer->toArray());
    }

    public function handle_get_customer_entities($request)
    {
        $customerId = isset($request['id']) ? (int) $request['id'] : 0;
        $entities = $this->customerEntityRepository->getByCustomerId($customerId);

        $data = array_map(function ($entity) {
            return $entity->toArray();
        }, $entities);

        return rest_ensure_response($data);
    }

    public function handle_create_customer_entity($request)
    {
        $customerId = isset($request['id']) ? (int) $request['id'] : 0;
        $params = $request->get_json_params();

        // Snapshot Schema
        $businessTypeSettings = get_option('businessapp_settings_business_type', []);
        $activeTypeId = isset($businessTypeSettings['business_type']) ? $businessTypeSettings['business_type'] : 'panel_beater';
        $activeSchema = $this->businessTypeRegistry->get($activeTypeId);

        // Prefer user-provided identifier (e.g. "Ford F150"), fallback to Schema Type Name (e.g. "Vehicle")
        $userProvidedName = isset($params['entity_name']) ? sanitize_text_field($params['entity_name']) : '';
        $schemaEntityName = $activeSchema ? $activeSchema->getAssociatedEntityName() : 'Entity';
        $entityName = $userProvidedName ?: $schemaEntityName;

        $schemaSnapshot = [];
        $dynamicFields = [];

        if ($activeSchema) {
            $schemaSnapshot = [
                'business_type_id' => $activeTypeId,
                'entity_name' => $schemaEntityName, // Store the Type Name in snapshot for reference
                'fields' => []
            ];

            foreach ($activeSchema->getAssociatedEntityFields() as $field) {
                $schemaSnapshot['fields'][] = [
                    'id' => $field->getId(),
                    'label' => $field->getLabel(),
                    'type' => $field->getType(),
                    'unit' => $field->getUnit(),
                    'options' => $field->getOptions()
                ];
            }

            // Extract Dynamic Fields
            if (isset($params['dynamic_fields']) && is_array($params['dynamic_fields'])) {
                foreach ($activeSchema->getAssociatedEntityFields() as $field) {
                    $fieldId = $field->getId();
                    if (isset($params['dynamic_fields'][$fieldId])) {
                        $dynamicFields[$fieldId] = $params['dynamic_fields'][$fieldId];
                    }
                }
            }
        }

        $entity = $this->customerEntityRepository->create($customerId, $entityName, $dynamicFields, $schemaSnapshot);

        return rest_ensure_response($entity->toArray());
    }

    public function handle_update_customer_entity($request)
    {
        $entityId = isset($request['entity_id']) ? (int) $request['entity_id'] : 0;
        $params = $request->get_json_params();

        $entity = $this->customerEntityRepository->getById($entityId);
        if (!$entity) {
            return new \WP_Error('not_found', 'Entity not found', ['status' => 404]);
        }

        $data = [];
        if (isset($params['entity_name'])) {
            $data['entity_name'] = sanitize_text_field($params['entity_name']);
        }

        if (isset($params['dynamic_fields']) && is_array($params['dynamic_fields'])) {
            // Merge with existing dynamic fields
            $currentFields = $entity->getDynamicFields();
            foreach ($params['dynamic_fields'] as $key => $value) {
                $currentFields[$key] = $value; // We might want to sanitize here depending on field type, but basic sanitization:
                // ideally we check against snapshot schema, but for now allow updating
            }
            $data['dynamic_fields'] = $currentFields;
        }

        $updatedEntity = $this->customerEntityRepository->update($entityId, $data);

        return rest_ensure_response($updatedEntity->toArray());
    }

    public function handle_delete_customer_entity($request)
    {
        $entityId = isset($request['entity_id']) ? (int) $request['entity_id'] : 0;
        
        $entity = $this->customerEntityRepository->getById($entityId);
        if (!$entity) {
            return new \WP_Error('not_found', 'Entity not found', ['status' => 404]);
        }

        $this->customerEntityRepository->delete($entityId);

        return rest_ensure_response(['deleted' => true, 'id' => $entityId]);
    }
}

function businessapp()
{
    return BusinessApp_Plugin::instance();
}

businessapp();
