<?php

namespace BusinessApp\Domain;

class FieldDefinition
{
    private $id;
    private $label;
    private $type;
    private $required;
    private $options;
    private $unit;

    public function __construct($id, $label, $type = 'text', $required = false, $options = [], $unit = null)
    {
        $this->id = $id;
        $this->label = $label;
        $this->type = $type;
        $this->required = $required;
        $this->options = $options;
        $this->unit = $unit;
    }

    public function getId()
    {
        return $this->id;
    }

    public function getLabel()
    {
        return $this->label;
    }

    public function getType()
    {
        return $this->type;
    }

    public function isRequired()
    {
        return $this->required;
    }

    public function getOptions()
    {
        return $this->options;
    }

    public function getUnit()
    {
        return $this->unit;
    }

    public function toArray()
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'type' => $this->type,
            'required' => $this->required,
            'options' => $this->options,
            'unit' => $this->unit,
        ];
    }
}
