<?php

namespace BusinessApp\Domain;

class BusinessType
{
    private $id;
    private $name;
    private $fields;
    private $customerFields;
    private $associatedEntityName;
    private $associatedEntityFields;

    /**
     * @param string $id
     * @param string $name
     * @param FieldDefinition[] $fields
     * @param FieldDefinition[] $customerFields
     * @param string|null $associatedEntityName
     * @param FieldDefinition[] $associatedEntityFields
     */
    public function __construct($id, $name, array $fields = [], array $customerFields = [], $associatedEntityName = null, array $associatedEntityFields = [])
    {
        $this->id = $id;
        $this->name = $name;
        $this->fields = $fields;
        $this->customerFields = $customerFields;
        $this->associatedEntityName = $associatedEntityName;
        $this->associatedEntityFields = $associatedEntityFields;
    }

    public function getId()
    {
        return $this->id;
    }

    public function getName()
    {
        return $this->name;
    }

    /**
     * @return FieldDefinition[]
     */
    public function getFields()
    {
        return $this->fields;
    }

    /**
     * @return FieldDefinition[]
     */
    public function getCustomerFields()
    {
        return $this->customerFields;
    }

    public function getAssociatedEntityName()
    {
        return $this->associatedEntityName;
    }

    /**
     * @return FieldDefinition[]
     */
    public function getAssociatedEntityFields()
    {
        return $this->associatedEntityFields;
    }

    public function getField($fieldId)
    {
        foreach ($this->fields as $field) {
            if ($field->getId() === $fieldId) {
                return $field;
            }
        }
        return null;
    }

    public function toArray()
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'fields' => array_map(function ($field) {
                return $field->toArray();
            }, $this->fields),
            'customer_fields' => array_map(function ($field) {
                return $field->toArray();
            }, $this->customerFields),
            'associated_entity_name' => $this->associatedEntityName,
            'associated_entity_fields' => array_map(function ($field) {
                return $field->toArray();
            }, $this->associatedEntityFields),
        ];
    }
}
