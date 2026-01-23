<?php

namespace BusinessApp\Domain;

class Quote
{
    private $id;
    private $status;
    private $customerId;
    private $customerName;
    private $customerEmail;
    private $customerPhone;
    private $title;
    private $totalAmount;
    private $notes;
    private $publicToken;
    private $paymentStatus;
    private $dynamicFields;
    private $schemaSnapshot;
    private $associatedEntityIds;
    private $lineItems;
    private $createdAt;

    public function __construct(
        $id, 
        $status, 
        $customerId, 
        $title, 
        $totalAmount, 
        $customerName = '', 
        $customerEmail = '', 
        $customerPhone = '', 
        $notes = '', 
        $publicToken = '', 
        $paymentStatus = 'unpaid', 
        $dynamicFields = [], 
        $schemaSnapshot = [], 
        $associatedEntityIds = [],
        $lineItems = [],
        $createdAt = ''
    ) {
        $this->id = $id;
        $this->status = $status;
        $this->customerId = $customerId;
        $this->title = $title;
        $this->totalAmount = $totalAmount;
        $this->customerName = $customerName;
        $this->customerEmail = $customerEmail;
        $this->customerPhone = $customerPhone;
        $this->notes = $notes;
        $this->publicToken = $publicToken;
        $this->paymentStatus = $paymentStatus;
        $this->dynamicFields = $dynamicFields;
        $this->schemaSnapshot = $schemaSnapshot;
        $this->associatedEntityIds = $associatedEntityIds;
        $this->lineItems = $lineItems;
        $this->createdAt = $createdAt;
    }

    public function getId()
    {
        return $this->id;
    }

    public function getStatus()
    {
        return $this->status;
    }

    public function getCustomerId()
    {
        return $this->customerId;
    }

    public function getCustomerName()
    {
        return $this->customerName;
    }

    public function getCustomerEmail()
    {
        return $this->customerEmail;
    }

    public function getCustomerPhone()
    {
        return $this->customerPhone;
    }

    public function getTitle()
    {
        return $this->title;
    }

    public function getTotalAmount()
    {
        return $this->totalAmount;
    }

    public function getNotes()
    {
        return $this->notes;
    }

    public function getPublicToken()
    {
        return $this->publicToken;
    }

    public function getPaymentStatus()
    {
        return $this->paymentStatus;
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

    /**
     * @return QuoteItem[]
     */
    public function getLineItems()
    {
        return $this->lineItems;
    }

    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    public function setCustomerId($customerId)
    {
        $this->customerId = $customerId;
    }

    public function setCustomerName($customerName)
    {
        $this->customerName = $customerName;
    }

    public function setCustomerEmail($customerEmail)
    {
        $this->customerEmail = $customerEmail;
    }

    public function setCustomerPhone($customerPhone)
    {
        $this->customerPhone = $customerPhone;
    }

    public function setPublicToken($token)
    {
        $this->publicToken = $token;
    }

    public function updateDraftDetails($title, $totalAmount, $notes, $dynamicFields, $associatedEntityIds, $lineItems)
    {
        if ($this->status !== 'draft') {
            throw new \RuntimeException('Only draft quotes can be updated');
        }

        $this->title = $title;
        $this->totalAmount = $totalAmount;
        $this->notes = $notes;
        $this->dynamicFields = $dynamicFields;
        $this->associatedEntityIds = $associatedEntityIds;
        $this->lineItems = $lineItems;
    }

    public function markSent()
    {
        if ($this->status !== 'draft') {
            throw new \RuntimeException('Only draft quotes can be sent');
        }

        $this->status = 'sent';
    }

    public function markAccepted()
    {
        if ($this->status !== 'sent' && $this->status !== 'discussing') {
            throw new \RuntimeException('Only sent or discussing quotes can be accepted');
        }

        $this->status = 'accepted';
    }

    public function markRejected()
    {
        if ($this->status !== 'sent' && $this->status !== 'discussing') {
            throw new \RuntimeException('Only sent or discussing quotes can be rejected');
        }

        $this->status = 'rejected';
    }

    public function markExpired()
    {
        if ($this->status !== 'sent' && $this->status !== 'discussing') {
            throw new \RuntimeException('Only sent or discussing quotes can be expired');
        }

        $this->status = 'expired';
    }

    public function markPaid()
    {
        $this->paymentStatus = 'paid';
    }
}
