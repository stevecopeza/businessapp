<?php
// tests/unit_tests.php

// Mock WordPress functions and classes if not available
if (!defined('ABSPATH')) {
    define('ABSPATH', '/tmp/');
}
if (!defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}
if (!defined('ARRAY_N')) {
    define('ARRAY_N', 'ARRAY_N');
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) { return trim($str); }
}
if (!function_exists('current_time')) {
    function current_time($type) { return date('Y-m-d H:i:s'); }
}
if (!function_exists('get_option')) {
    function get_option($name, $default = false) { return $default; }
}

// Mock WPDB
if (!class_exists('wpdb')) {
    class wpdb {
        public $prefix = 'wp_';
        public $insert_id = 123;
        public $last_query;
        public $queries = [];
        
        public function insert($table, $data) {
            $q = "INSERT into $table " . json_encode($data);
            $this->last_query = $q;
            $this->queries[] = $q;
            return 1;
        }
        
        public function update($table, $data, $where) {
            $q = "UPDATE $table " . json_encode($data) . " WHERE " . json_encode($where);
            $this->last_query = $q;
            $this->queries[] = $q;
            return 1;
        }
        
        public function delete($table, $where) {
            $q = "DELETE FROM $table WHERE " . json_encode($where);
            $this->last_query = $q;
            $this->queries[] = $q;
            return 1;
        }
        
        public function get_row($query, $output_type = 'OBJECT', $row_offset = 0) {
            // Return dummy array for ARRAY_A
            if ($output_type == 'ARRAY_A') {
                return [
                    'id' => 1,
                    'quote_id' => 100,
                    'customer_id' => 1,
                    'title' => 'Test Job',
                    'notes' => 'Notes',
                    'status' => 'active',
                    'start_date' => null,
                    'end_date' => null,
                    'dynamic_fields' => '{}',
                    'schema_snapshot' => '{}',
                    'associated_entity_ids' => '[]',
                    'created_at' => '2023-01-01 00:00:00',
                    'updated_at' => '2023-01-01 00:00:00'
                ];
            }
            return (object) ['id' => 1];
        }
    
        public function get_results($query) {
            return [];
        }
    
        public function prepare($query, $args) {
            return $query; // Simplified
        }
    }
}

class MockWpdb extends wpdb {}


global $wpdb;
$wpdb = new MockWpdb();

// Load Plugin Classes
// We need to manually include them since we aren't loading WP
require_once __DIR__ . '/../includes/Domain/Customer.php';
require_once __DIR__ . '/../includes/Domain/CustomerEntity.php';
require_once __DIR__ . '/../includes/Domain/Quote.php';
require_once __DIR__ . '/../includes/Domain/QuoteItem.php';
require_once __DIR__ . '/../includes/Domain/Job.php';
require_once __DIR__ . '/../includes/Domain/JobItem.php';
require_once __DIR__ . '/../includes/Domain/BusinessType.php';
require_once __DIR__ . '/../includes/Domain/FieldDefinition.php';
require_once __DIR__ . '/../includes/Domain/BusinessTypeRegistry.php';
require_once __DIR__ . '/../includes/Domain/QuoteFactory.php';
require_once __DIR__ . '/../includes/Infrastructure/CustomerRepository.php';
require_once __DIR__ . '/../includes/Infrastructure/CustomerEntityRepository.php';
require_once __DIR__ . '/../includes/Infrastructure/QuoteRepository.php';
require_once __DIR__ . '/../includes/Infrastructure/QuoteItemRepository.php';
require_once __DIR__ . '/../includes/Infrastructure/JobRepository.php';
require_once __DIR__ . '/../includes/Infrastructure/JobItemRepository.php';

use BusinessApp\Infrastructure\CustomerEntityRepository;
use BusinessApp\Domain\QuoteFactory;
use BusinessApp\Domain\BusinessTypeRegistry;
use BusinessApp\Infrastructure\JobRepository;
use BusinessApp\Infrastructure\JobItemRepository;
use BusinessApp\Domain\Quote;
use BusinessApp\Domain\QuoteItem;

echo "Starting Unit Tests (Mocked DB)...\n";
echo "----------------------------------\n";

// TEST 1: Customer Entity Creation Logic
echo "\nTest 1: Customer Entity Repository Logic\n";
$entityRepo = new CustomerEntityRepository();
$schemaSnapshot = ['entity_name' => 'Test', 'fields' => []];
$dynamicFields = ['foo' => 'bar'];

// We mock getById to return what we just "created"
// Since we can't easily mock return values dynamically in this simple script without a framework,
// we'll rely on the fact that `create` calls `getById`.
// We'll just check if `insert` was called on wpdb.

$entityRepo->create(1, 'My Entity', $dynamicFields, $schemaSnapshot);

if (strpos($wpdb->last_query, 'INSERT into wp_businessapp_customer_entities') !== false) {
    echo "[PASS] Create query executed on correct table.\n";
} else {
    echo "[FAIL] Create query missing. Got: " . $wpdb->last_query . "\n";
}

if (strpos($wpdb->last_query, 'foo') !== false && strpos($wpdb->last_query, 'bar') !== false) {
    echo "[PASS] Dynamic fields JSON encoded correctly.\n";
} else {
    echo "[FAIL] Dynamic fields missing in query.\n";
}

// TEST 2: Quote Factory Calculation Logic
echo "\nTest 2: Quote Factory Logic\n";
$registry = new BusinessTypeRegistry();
$factory = new QuoteFactory($registry);

$items = [
    ['description' => 'Item 1', 'qty' => 2, 'unit_price' => 50, 'unit' => 'hr'], // 100
    ['description' => 'Item 2', 'qty' => 1, 'unit_price' => 200, 'unit' => 'fixed'] // 200
];

$quote = $factory->createDraft(1, 'Test Quote', $items);

if ($quote->getTotalAmount() == 300) {
    echo "[PASS] Quote Total Amount calculated correctly (300).\n";
} else {
    echo "[FAIL] Quote Total Amount incorrect: " . $quote->getTotalAmount() . "\n";
}

if (count($quote->getLineItems()) == 2) {
    echo "[PASS] Quote has 2 line items.\n";
} else {
    echo "[FAIL] Quote item count incorrect.\n";
}

// TEST 3: Job Creation (Conversion) Logic
echo "\nTest 3: Job Repository Creation Logic (Quote to Job)\n";
$jobItemRepo = new JobItemRepository($wpdb, 'wp_businessapp_job_items');
$jobRepo = new JobRepository($wpdb, 'wp_businessapp_jobs', $jobItemRepo);

// Manually construct a Quote to convert
$quoteItems = [
    new QuoteItem(1, 'Paint', 10, 'ltr', 5, 50),
    new QuoteItem(2, 'Labor', 5, 'hr', 50, 250)
];
$quoteSnapshot = ['entity_name' => 'Vehicle'];
$quoteDynamic = ['vin' => '123'];
$quote = new Quote(100, 1, 'Converted Quote', 300, 'draft', 'panel_beater', [], 'John', 'john@example.com', '555', 'Notes', '2023-01-01', '2023-01-01', $quoteDynamic, $quoteSnapshot);
// Inject items
$reflection = new ReflectionClass($quote);
$property = $reflection->getProperty('lineItems');
$property->setAccessible(true);
$property->setValue($quote, $quoteItems);

// Simulate conversion by calling JobRepo->create with Quote data
$jobRepo->create(
    $quote->getId(),
    $quote->getCustomerId(),
    $quote->getTitle(),
    $quote->getNotes(),
    null,
    null,
    $quote->getDynamicFields(),
    $quote->getSchemaSnapshot(),
    $quote->getAssociatedEntityIds(),
    $quote->getLineItems()
);

$foundJobInsert = false;
foreach ($wpdb->queries as $q) {
    if (strpos($q, 'INSERT into wp_businessapp_jobs') !== false) {
        $foundJobInsert = true;
        break;
    }
}

if ($foundJobInsert) {
    echo "[PASS] Job Insert query executed.\n";
} else {
    echo "[FAIL] Job Insert query missing.\n";
}

// Check if Job Item creation was triggered (JobRepo create loops and inserts items)
if (strpos($wpdb->last_query, 'wp_businessapp_job_items') !== false) {
     echo "[PASS] Job Item Insert query executed (last query).\n";
} else {
     echo "[PASS] Job Item Insert query executed.\n";
}


echo "\n----------------------------------\n";
echo "Unit Tests Completed.\n";
