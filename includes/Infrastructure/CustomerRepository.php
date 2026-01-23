<?php

namespace BusinessApp\Infrastructure;

use BusinessApp\Domain\Customer;

class CustomerRepository
{
    private $wpdb;
    private $tableName;

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->tableName = $wpdb->prefix . 'businessapp_customers';
    }

    public function create($name, $email, $phone, $status, $dynamicFields, $schemaSnapshot)
    {
        $now = current_time('mysql');
        
        $this->wpdb->insert(
            $this->tableName,
            [
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'status' => $status,
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
        // Schema snapshot should NOT be updated, but if passed (e.g. strict overwrite), it will be.
        // Logic layer should ensure snapshot isn't changed.

        $this->wpdb->update(
            $this->tableName,
            $data,
            ['id' => $id]
        );

        return $this->getById($id);
    }

    public function getById($id)
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$this->tableName} WHERE id = %d", $id)
        );

        if (!$row) {
            return null;
        }

        return $this->mapRowToCustomer($row);
    }

    public function getAll($status = null)
    {
        $sql = "SELECT * FROM {$this->tableName}";
        if ($status) {
            $sql .= $this->wpdb->prepare(" WHERE status = %s", $status);
        }
        $sql .= " ORDER BY created_at DESC";

        $rows = $this->wpdb->get_results($sql);

        return array_map([$this, 'mapRowToCustomer'], $rows);
    }

    private function mapRowToCustomer($row)
    {
        return new Customer(
            $row->id,
            $row->name,
            $row->email,
            $row->phone,
            $row->status,
            json_decode($row->dynamic_fields, true) ?: [],
            json_decode($row->schema_snapshot, true) ?: [],
            $row->created_at,
            $row->updated_at
        );
    }
}
