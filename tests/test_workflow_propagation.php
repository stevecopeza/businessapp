<?php
// tests/test_workflow_propagation.php

// Mock WordPress environment
if (!defined('ABSPATH')) {
    define('ABSPATH', '/tmp/');
}
if (!defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}

if (!function_exists('current_time')) {
    function current_time($type) { return date('Y-m-d H:i:s'); }
}

// Mock WPDB
if (!class_exists('wpdb')) {
    class wpdb {
        public $prefix = 'wp_';
        public $insert_id = 123;
        public $queries = [];
        public $last_query;
        public $mock_rows = []; // Map query patterns to results

        public function __construct() {}

        public function insert($table, $data, $format) {
            $q = "INSERT into $table " . json_encode($data);
            $this->queries[] = $q;
            return 1;
        }
        
        public function update($table, $data, $where) {
            $q = "UPDATE $table " . json_encode($data);
            $this->queries[] = $q;
            return 1;
        }

        public function prepare($query, $args) {
            if (is_array($args)) {
                foreach ($args as $arg) {
                    $query = preg_replace('/%[sd]/', "'$arg'", $query, 1);
                }
            } else {
                 $query = preg_replace('/%[sd]/', "'$args'", $query, 1);
            }
            return $query;
        }

        public function get_row($query, $output_type = 'OBJECT') {
            $this->queries[] = "GET_ROW: " . $query;
            
            // Simulate getByQuoteId
            if (strpos($query, 'quote_id =') !== false) {
                // Check if we want to simulate an existing job
                if (strpos($query, 'quote_id = \'999\'') !== false) {
                    return [
                        'id' => 999,
                        'quote_id' => 999,
                        'status' => 'planned',
                        'title' => 'Existing Job',
                        'notes' => '',
                        'start_date' => null,
                        'end_date' => null,
                        'created_at' => '2023-01-01',
                        'updated_at' => '2023-01-01',
                        'customer_id' => 1
                    ];
                }
                return null; // Not found
            }
            
            // Simulate getById after create
            return [
                'id' => 123,
                'quote_id' => 100,
                'status' => 'planned',
                'title' => 'New Job',
                'notes' => '',
                'start_date' => null,
                'end_date' => null,
                'created_at' => '2023-01-01',
                'updated_at' => '2023-01-01',
                'customer_id' => 1
            ];
        }

        public function get_results($query, $output_type = 'OBJECT') {
            $this->queries[] = "GET_RESULTS: " . $query;
            return [];
        }
    }
}

// Load Classes
require_once __DIR__ . '/../includes/Domain/Job.php';
require_once __DIR__ . '/../includes/Infrastructure/JobRepository.php';

use BusinessApp\Infrastructure\JobRepository;

$wpdb = new wpdb();
$repo = new JobRepository($wpdb, 'wp_businessapp_jobs');

echo "Testing Job Workflow Propagation...\n";
echo "-----------------------------------\n";

// Test 1: getByQuoteId
echo "Test 1: getByQuoteId for non-existent job\n";
$job = $repo->getByQuoteId(100);
if ($job === null) {
    echo "[PASS] Returned null for non-existent job.\n";
} else {
    echo "[FAIL] Did not return null.\n";
}

echo "Test 2: getByQuoteId for existing job\n";
$job = $repo->getByQuoteId(999);
if ($job && $job->getId() === 999) {
    echo "[PASS] Found existing job.\n";
} else {
    echo "[FAIL] Did not find existing job.\n";
}

// Test 3: Create with specific status
echo "Test 3: Create job with 'planned' status\n";
$repo->create(100, 1, 'New Job', 'Notes', null, null, [], [], [], [], 'planned');

$lastInsert = '';
foreach ($wpdb->queries as $q) {
    if (strpos($q, 'INSERT') === 0) {
        $lastInsert = $q;
    }
}

if (strpos($lastInsert, '"status":"planned"') !== false) {
    echo "[PASS] Inserted with status 'planned'.\n";
} else {
    echo "[FAIL] Status 'planned' not found in insert query: $lastInsert\n";
}
