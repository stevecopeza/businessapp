<?php

namespace BusinessApp\Domain;

class JobItem
{
    private $id;
    private $description;
    private $qty;
    private $unit;
    private $unitPrice;
    private $amount;

    public function __construct($id, $description, $qty, $unit, $unitPrice, $amount)
    {
        $this->id = $id;
        $this->description = $description;
        $this->qty = $qty;
        $this->unit = $unit;
        $this->unitPrice = $unitPrice;
        $this->amount = $amount;
    }

    public function getId()
    {
        return $this->id;
    }

    public function getDescription()
    {
        return $this->description;
    }

    public function getQty()
    {
        return $this->qty;
    }

    public function getUnit()
    {
        return $this->unit;
    }

    public function getUnitPrice()
    {
        return $this->unitPrice;
    }

    public function getAmount()
    {
        return $this->amount;
    }

    public function toArray()
    {
        return [
            'id' => $this->id,
            'description' => $this->description,
            'qty' => $this->qty,
            'unit' => $this->unit,
            'unit_price' => $this->unitPrice,
            'amount' => $this->amount,
        ];
    }
}
