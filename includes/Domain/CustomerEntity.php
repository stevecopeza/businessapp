<?php

namespace BusinessApp\Domain;

class CustomerEntity
{
    private $id;
    private $customerId;
    private $entityName;
    private $dynamicFields;
    private $schemaSnapshot;
    private $createdAt;
    private $updatedAt;

    public function __construct(
        $id,
        $customerId,
        $entityName,
        array $dynamicFields,
        array $schemaSnapshot,
        $createdAt,
        $updatedAt
    ) {
        $this->id = $id;
        $this->customerId = $customerId;
        $this->entityName = $entityName;
        $this->dynamicFields = $dynamicFields;
        $this->schemaSnapshot = $schemaSnapshot;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    public function getId() { return $this->id; }
    public function getCustomerId() { return $this->customerId; }
    public function getEntityName() { return $this->entityName; }
    public function getDynamicFields() { return $this->dynamicFields; }
    public function getSchemaSnapshot() { return $this->schemaSnapshot; }
    public function getCreatedAt() { return $this->createdAt; }
    public function getUpdatedAt() { return $this->updatedAt; }

    public function toArray()
    {
        return [
            'id' => $this->id,
            'customer_id' => $this->customerId,
            'entity_name' => $this->entityName,
            'dynamic_fields' => $this->dynamicFields,
            'schema_snapshot' => $this->schemaSnapshot,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
