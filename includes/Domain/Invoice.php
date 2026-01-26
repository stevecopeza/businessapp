<?php

namespace BusinessApp\Domain;

class Invoice
{
    private $id;
    private $jobId;
    private $customerId;
    private $status;
    private $title;
    private $notes;
    private $totalAmount;
    private $publicToken;
    private $createdAt;
    private $updatedAt;
    private $lineItems;

    public function __construct(
        $id,
        $jobId,
        $customerId,
        $status,
        $title,
        $notes = '',
        $totalAmount = 0.00,
        $publicToken = '',
        $createdAt = '',
        $updatedAt = '',
        $lineItems = []
    ) {
        $this->id = $id;
        $this->jobId = $jobId;
        $this->customerId = $customerId;
        $this->status = $status;
        $this->title = $title;
        $this->notes = $notes;
        $this->totalAmount = $totalAmount;
        $this->publicToken = $publicToken;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
        $this->lineItems = $lineItems;
    }

    public function getId()
    {
        return $this->id;
    }

    public function getJobId()
    {
        return $this->jobId;
    }

    public function getCustomerId()
    {
        return $this->customerId;
    }

    public function getStatus()
    {
        return $this->status;
    }

    public function setStatus($status)
    {
        $this->status = $status;
    }

    public function getTitle()
    {
        return $this->title;
    }

    public function getNotes()
    {
        return $this->notes;
    }

    public function getTotalAmount()
    {
        return $this->totalAmount;
    }

    public function getPublicToken()
    {
        return $this->publicToken;
    }

    public function setPublicToken($token)
    {
        $this->publicToken = $token;
    }

    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }

    public function getLineItems()
    {
        return $this->lineItems;
    }

    public function markSent()
    {
        if ($this->status !== 'draft') {
            throw new \RuntimeException("Invoice can only be sent from 'draft' status.");
        }
        $this->status = 'sent';
    }

    public function generateToken()
    {
        if (empty($this->publicToken)) {
            $this->publicToken = bin2hex(random_bytes(32));
        }
        return $this->publicToken;
    }

    public function toArray()
    {
        return [
            'id' => $this->id,
            'job_id' => $this->jobId,
            'customer_id' => $this->customerId,
            'status' => $this->status,
            'title' => $this->title,
            'notes' => $this->notes,
            'total_amount' => $this->totalAmount,
            'public_token' => $this->publicToken,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'items' => array_map(function ($item) {
                return $item->toArray();
            }, $this->lineItems),
        ];
    }
}
