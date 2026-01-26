<?php

namespace BusinessApp\Domain;

class Payment
{
    private $id;
    private $invoiceId;
    private $gateway;
    private $transactionId;
    private $amount;
    private $currency;
    private $status;
    private $meta;
    private $createdAt;

    public function __construct(
        $id,
        $invoiceId,
        $gateway,
        $transactionId,
        $amount,
        $currency = 'USD',
        $status = 'pending',
        $meta = [],
        $createdAt = ''
    ) {
        $this->id = $id;
        $this->invoiceId = $invoiceId;
        $this->gateway = $gateway;
        $this->transactionId = $transactionId;
        $this->amount = $amount;
        $this->currency = $currency;
        $this->status = $status;
        $this->meta = $meta;
        $this->createdAt = $createdAt;
    }

    public function getId()
    {
        return $this->id;
    }

    public function getInvoiceId()
    {
        return $this->invoiceId;
    }

    public function getGateway()
    {
        return $this->gateway;
    }

    public function getTransactionId()
    {
        return $this->transactionId;
    }

    public function getAmount()
    {
        return $this->amount;
    }

    public function getCurrency()
    {
        return $this->currency;
    }

    public function getStatus()
    {
        return $this->status;
    }

    public function getMeta()
    {
        return $this->meta;
    }

    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    public function setStatus($status)
    {
        $this->status = $status;
    }

    public function toArray()
    {
        return [
            'id' => $this->id,
            'invoice_id' => $this->invoiceId,
            'gateway' => $this->gateway,
            'transaction_id' => $this->transactionId,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'status' => $this->status,
            'meta' => $this->meta,
            'created_at' => $this->createdAt,
        ];
    }
}
