<?php

namespace BusinessApp\Domain;

class Customer
{
    private $id;
    private $name;
    private $email;
    private $phone;
    private $status;
    private $dynamicFields;
    private $schemaSnapshot;
    private $createdAt;
    private $updatedAt;

    public function __construct(
        $id,
        $name,
        $email,
        $phone,
        $status,
        array $dynamicFields,
        array $schemaSnapshot,
        $createdAt,
        $updatedAt
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->email = $email;
        $this->phone = $phone;
        $this->status = $status;
        $this->dynamicFields = $dynamicFields;
        $this->schemaSnapshot = $schemaSnapshot;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    public function getId() { return $this->id; }
    public function getName() { return $this->name; }
    public function getEmail() { return $this->email; }
    public function getPhone() { return $this->phone; }
    public function getStatus() { return $this->status; }
    public function getDynamicFields() { return $this->dynamicFields; }
    public function getSchemaSnapshot() { return $this->schemaSnapshot; }
    public function getCreatedAt() { return $this->createdAt; }
    public function getUpdatedAt() { return $this->updatedAt; }

    public function toArray()
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'status' => $this->status,
            'dynamic_fields' => $this->dynamicFields,
            'schema_snapshot' => $this->schemaSnapshot,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
