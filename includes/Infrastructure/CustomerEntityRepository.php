<?php

namespace BusinessApp\Infrastructure;

use BusinessApp\Domain\CustomerEntity;

class CustomerEntityRepository
{
    private $wpdb;
    private $tableName;

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->tableName = $wpdb->prefix . 'businessapp_customer_entities';
    }

    public function create($customerId, $entityName, $dynamicFields, $schemaSnapshot)
    {
        $now = current_time('mysql');
        
        $this->wpdb->insert(
            $this->tableName,
            [
                'customer_id' => $customerId,
                'entity_name' => $entityName,
                'dynamic_fields' => json_encode($dynamicFields),
                'schema_snapshot' => json_encode($schemaSnapshot),
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        return $this->getById($this->wpdb->insert_id);
    }

    public function update($id, $data)
    {
        $data['updated_at'] = current_time('mysql');
        if (isset($data['dynamic_fields'])) {
            $data['dynamic_fields'] = json_encode($data['dynamic_fields']);
        }

        $this->wpdb->update(
            $this->tableName,
            $data,
            ['id' => $id]
        );

        return $this->getById($id);
    }

    public function delete($id)
    {
        $this->wpdb->delete(
            $this->tableName,
            ['id' => $id]
        );
        return true;
    }

    public function getById($id)
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$this->tableName} WHERE id = %d", $id)
        );

        if (!$row) {
            return null;
        }

        return $this->mapRowToCustomerEntity($row);
    }

    public function getByCustomerId($customerId)
    {
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare("SELECT * FROM {$this->tableName} WHERE customer_id = %d ORDER BY created_at DESC", $customerId)
        );

        return array_map([$this, 'mapRowToCustomerEntity'], $rows);
    }

    private function mapRowToCustomerEntity($row)
    {
        return new CustomerEntity(
            $row->id,
            $row->customer_id,
            $row->entity_name,
            json_decode($row->dynamic_fields, true) ?: [],
            json_decode($row->schema_snapshot, true) ?: [],
            $row->created_at,
            $row->updated_at
        );
    }
}
