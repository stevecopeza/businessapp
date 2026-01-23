<?php

namespace BusinessApp\Infrastructure;

use BusinessApp\Domain\Job;

class JobRepository
{
    private $wpdb;
    private $tableName;
    private $jobItemRepository;

    public function __construct(\wpdb $wpdb, $tableName, JobItemRepository $jobItemRepository = null)
    {
        $this->wpdb = $wpdb;
        $this->tableName = $tableName;
        $this->jobItemRepository = $jobItemRepository;
    }

    public function getAll($status = null)
    {
        $sql = "SELECT * FROM {$this->tableName}";
        if ($status) {
            $sql .= $this->wpdb->prepare(" WHERE status = %s", $status);
        }
        $sql .= " ORDER BY created_at DESC";

        $rows = $this->wpdb->get_results($sql, ARRAY_A);

        if (!is_array($rows)) {
            return [];
        }

        return array_map([$this, 'mapRowToJob'], $rows);
    }

    public function getById($id)
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$this->tableName} WHERE id = %d", $id),
            ARRAY_A
        );

        if (!$row) {
            return null;
        }

        return $this->mapRowToJob($row);
    }

    public function create($quoteId, $customerId, $title, $notes = '', $startDate = null, $endDate = null, $dynamicFields = [], $schemaSnapshot = [], $associatedEntityIds = [], $items = [])
    {
        $now = current_time('mysql');
        
        $data = [
            'quote_id' => $quoteId,
            'customer_id' => $customerId,
            'status' => 'active',
            'title' => $title,
            'notes' => $notes,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'dynamic_fields' => json_encode($dynamicFields),
            'schema_snapshot' => json_encode($schemaSnapshot),
            'associated_entity_ids' => json_encode($associatedEntityIds),
            'created_at' => $now,
            'updated_at' => $now
        ];

        $format = ['%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'];

        $this->wpdb->insert($this->tableName, $data, $format);
        $id = $this->wpdb->insert_id;

        if ($this->jobItemRepository && !empty($items)) {
            foreach ($items as $item) {
                // Handle both array (from request) and object (from quote)
                $description = is_array($item) ? $item['description'] : $item->getDescription();
                $qty = is_array($item) ? $item['qty'] : $item->getQty();
                $unit = is_array($item) ? $item['unit'] : $item->getUnit();
                $unitPrice = is_array($item) ? $item['unit_price'] : $item->getUnitPrice();

                $this->jobItemRepository->createItem(
                    $id,
                    $description,
                    $qty,
                    $unit,
                    $unitPrice
                );
            }
        }

        return $this->getById($id);
    }

    public function update($id, $data)
    {
        $data['updated_at'] = current_time('mysql');
        
        // Allowed fields to update
        $updateData = [];
        $format = [];

        if (isset($data['status'])) {
            $updateData['status'] = $data['status'];
            $format[] = '%s';
        }
        if (isset($data['title'])) {
            $updateData['title'] = $data['title'];
            $format[] = '%s';
        }
        if (isset($data['notes'])) {
            $updateData['notes'] = $data['notes'];
            $format[] = '%s';
        }
        if (isset($data['start_date'])) {
            $updateData['start_date'] = $data['start_date'];
            $format[] = '%s';
        }
        if (isset($data['end_date'])) {
            $updateData['end_date'] = $data['end_date'];
            $format[] = '%s';
        }
        
        // New fields
        if (isset($data['dynamic_fields'])) {
             $updateData['dynamic_fields'] = json_encode($data['dynamic_fields']);
             $format[] = '%s';
        }
        if (isset($data['associated_entity_ids'])) {
             $updateData['associated_entity_ids'] = json_encode($data['associated_entity_ids']);
             $format[] = '%s';
        }
        
        $updateData['updated_at'] = $data['updated_at'];
        $format[] = '%s';

        $this->wpdb->update(
            $this->tableName,
            $updateData,
            ['id' => $id],
            $format,
            ['%d']
        );

        return $this->getById($id);
    }

    private function mapRowToJob($row)
    {
        $dynamicFields = !empty($row['dynamic_fields']) ? json_decode($row['dynamic_fields'], true) : [];
        $schemaSnapshot = !empty($row['schema_snapshot']) ? json_decode($row['schema_snapshot'], true) : [];
        $associatedEntityIds = !empty($row['associated_entity_ids']) ? json_decode($row['associated_entity_ids'], true) : [];

        $items = [];
        if ($this->jobItemRepository) {
            $items = $this->jobItemRepository->getItemsForJob((int) $row['id']);
        }

        return new Job(
            (int) $row['id'],
            (int) $row['quote_id'],
            (int) $row['customer_id'],
            (string) $row['status'],
            (string) $row['title'],
            (string) $row['notes'],
            $row['start_date'],
            $row['end_date'],
            $row['created_at'],
            $row['updated_at'],
            is_array($dynamicFields) ? $dynamicFields : [],
            is_array($schemaSnapshot) ? $schemaSnapshot : [],
            is_array($associatedEntityIds) ? $associatedEntityIds : [],
            $items
        );
    }
}
