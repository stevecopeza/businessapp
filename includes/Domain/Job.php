<?php

namespace BusinessApp\Domain;

class Job
{
    private $id;
    private $quoteId;
    private $customerId;
    private $status;
    private $title;
    private $notes;
    private $startDate;
    private $endDate;
    private $createdAt;
    private $updatedAt;
    private $dynamicFields;
    private $schemaSnapshot;
    private $associatedEntityIds;
    private $lineItems;

    public function __construct(
        $id,
        $quoteId,
        $customerId,
        $status,
        $title,
        $notes = '',
        $startDate = null,
        $endDate = null,
        $createdAt = '',
        $updatedAt = '',
        $dynamicFields = [],
        $schemaSnapshot = [],
        $associatedEntityIds = [],
        $lineItems = []
    ) {
        $this->id = $id;
        $this->quoteId = $quoteId;
        $this->customerId = $customerId;
        $this->status = $status;
        $this->title = $title;
        $this->notes = $notes;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
        $this->dynamicFields = $dynamicFields;
        $this->schemaSnapshot = $schemaSnapshot;
        $this->associatedEntityIds = $associatedEntityIds;
        $this->lineItems = $lineItems;
    }

    public function getId()
    {
        return $this->id;
    }

    public function getQuoteId()
    {
        return $this->quoteId;
    }

    public function getCustomerId()
    {
        return $this->customerId;
    }

    public function getStatus()
    {
        return $this->status;
    }

    public function getTitle()
    {
        return $this->title;
    }

    public function getNotes()
    {
        return $this->notes;
    }

    public function getStartDate()
    {
        return $this->startDate;
    }

    public function getEndDate()
    {
        return $this->endDate;
    }

    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }

    public function getDynamicFields()
    {
        return $this->dynamicFields;
    }

    public function getSchemaSnapshot()
    {
        return $this->schemaSnapshot;
    }

    public function getAssociatedEntityIds()
    {
        return $this->associatedEntityIds;
    }

    public function getLineItems()
    {
        return $this->lineItems;
    }

    public function toArray()
    {
        $items = array_map(function($item) {
            return $item->toArray();
        }, $this->lineItems);

        return [
            'id' => $this->id,
            'quote_id' => $this->quoteId,
            'customer_id' => $this->customerId,
            'status' => $this->status,
            'title' => $this->title,
            'notes' => $this->notes,
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'dynamic_fields' => $this->dynamicFields,
            'schema_snapshot' => $this->schemaSnapshot,
            'associated_entity_ids' => $this->associatedEntityIds,
            'items' => $items,
        ];
    }
}
