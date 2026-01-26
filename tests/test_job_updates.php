<?php
// tests/test_job_updates.php

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
        public $mock_status = 'draft';
        
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
            return (object) [
                'id' => 1,
                'customer_id' => 1,
                'entity_name' => 'Mock Entity',
                'dynamic_fields' => '{}',
                'schema_snapshot' => '{}',
                'created_at' => '2023-01-01 00:00:00',
                'updated_at' => '2023-01-01 00:00:00'
            ];
        }
    
        public function get_results($query) {
            return [];
        }
    
        public function get_var($query, $x = 0, $y = 0) {
            if (strpos($query, 'SELECT status') !== false) {
                return $this->mock_status;
            }
            return null;
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
require_once __DIR__ . '/../includes/Domain/Job.php';
require_once __DIR__ . '/../includes/Domain/JobItem.php';
require_once __DIR__ . '/../includes/Infrastructure/JobRepository.php';
require_once __DIR__ . '/../includes/Infrastructure/JobItemRepository.php';

use BusinessApp\Infrastructure\JobRepository;
use BusinessApp\Infrastructure\JobItemRepository;

echo "Starting Job Update Tests...\n";
echo "----------------------------\n";

$jobItemRepo = new JobItemRepository($wpdb, 'wp_businessapp_job_items');
$jobRepo = new JobRepository($wpdb, 'wp_businessapp_jobs', $jobItemRepo);

// Test Data
$jobId = 1;
$data = [
    'title' => 'Updated Job Title',
    'items' => [
        ['description' => 'New Item 1', 'qty' => 5, 'unit' => 'pcs', 'unit_price' => 10],
        ['description' => 'New Item 2', 'qty' => 2, 'unit' => 'hrs', 'unit_price' => 50]
    ]
];

// Perform Update
$jobRepo->update($jobId, $data);

// Verify Queries
$queries = $wpdb->queries;

$hasUpdate = false;
$hasDeleteItems = false;
$hasInsertItem1 = false;
$hasInsertItem2 = false;

foreach ($queries as $q) {
    if (strpos($q, 'UPDATE wp_businessapp_jobs') !== false && strpos($q, 'Updated Job Title') !== false) {
        $hasUpdate = true;
    }
    if (strpos($q, 'DELETE FROM wp_businessapp_job_items') !== false) {
        $hasDeleteItems = true;
    }
    if (strpos($q, 'INSERT into wp_businessapp_job_items') !== false) {
        if (strpos($q, 'New Item 1') !== false) $hasInsertItem1 = true;
        if (strpos($q, 'New Item 2') !== false) $hasInsertItem2 = true;
    }
}

if ($hasUpdate) {
    echo "[PASS] Job table updated.\n";
} else {
    echo "[FAIL] Job table update missing.\n";
}

if ($hasDeleteItems) {
    echo "[PASS] Old items deleted.\n";
} else {
    echo "[FAIL] Old items deletion missing.\n";
}

if ($hasInsertItem1 && $hasInsertItem2) {
    echo "[PASS] New items inserted.\n";
} else {
    echo "[FAIL] New items insertion missing.\n";
}
