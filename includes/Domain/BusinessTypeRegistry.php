<?php

namespace BusinessApp\Domain;

class BusinessTypeRegistry
{
    private $types = [];

    public function __construct()
    {
        $this->registerBuiltInTypes();
    }

    private function registerBuiltInTypes()
    {
        // Panel Beater Schema
        $this->register(new BusinessType(
            'panel_beater',
            'Panel Beater',
            [ // Quote Fields
                new FieldDefinition('paint_area', 'Paint Area', 'number', false, [], 'square_meters'),
            ],
            [ // Customer Fields
                new FieldDefinition('insurance_provider', 'Insurance Provider', 'text', false),
                new FieldDefinition('policy_number', 'Policy Number', 'text', false),
            ],
            'Vehicle',
            [ // Associated Entity Fields (Vehicle)
                new FieldDefinition('vehicle_make', 'Vehicle Make', 'text', true),
                new FieldDefinition('vehicle_model', 'Vehicle Model', 'text', true),
                new FieldDefinition('vehicle_year', 'Year', 'number', true),
                new FieldDefinition('vehicle_vin', 'VIN', 'text', false),
                new FieldDefinition('vehicle_color', 'Color', 'text', true),
            ]
        ));

        // Gardening Schema
        $this->register(new BusinessType(
            'gardening',
            'Gardening',
            [ // Quote Fields
                new FieldDefinition('service_frequency', 'Frequency', 'select', true, ['weekly', 'bi-weekly', 'monthly']),
                new FieldDefinition('green_waste_removal', 'Remove Green Waste?', 'checkbox', false),
            ],
            [ // Customer Fields
                new FieldDefinition('gate_code', 'Gate Access Code', 'text', false),
                new FieldDefinition('dogs_on_site', 'Dogs on Site?', 'checkbox', false),
            ],
            'Property',
            [ // Associated Entity Fields (Property)
                new FieldDefinition('property_type', 'Property Type', 'select', true, ['residential', 'commercial']),
                new FieldDefinition('lawn_size', 'Lawn Size', 'number', true, [], 'square_meters'),
                new FieldDefinition('address', 'Property Address', 'text', true),
            ]
        ));

        // Plumbing Schema
        $this->register(new BusinessType(
            'plumbing',
            'Plumbing',
            [ // Quote Fields
                new FieldDefinition('job_type', 'Job Type', 'select', true, ['repair', 'installation', 'maintenance']),
                new FieldDefinition('urgency', 'Urgency', 'select', true, ['standard', 'emergency']),
            ],
            [ // Customer Fields
                new FieldDefinition('account_type', 'Account Type', 'select', true, ['residential', 'commercial', 'strata']),
            ],
            'Site',
            [ // Associated Entity Fields (Site)
                new FieldDefinition('access_instructions', 'Access Instructions', 'textarea', false),
                new FieldDefinition('site_contact', 'Site Contact Name', 'text', false),
            ]
        ));

        // Electrical Schema
        $this->register(new BusinessType(
            'electrical',
            'Electrical',
            [ // Quote Fields
                new FieldDefinition('job_type', 'Job Type', 'select', true, ['wiring', 'repair', 'inspection']),
            ],
            [ // Customer Fields
                new FieldDefinition('customer_rating', 'Customer Rating', 'select', false, ['vip', 'standard', 'problematic']),
            ],
            'Premises',
            [ // Associated Entity Fields (Premises)
                new FieldDefinition('premises_age', 'Age of Premises (Years)', 'number', false),
                new FieldDefinition('safety_switch', 'Safety Switch Installed?', 'checkbox', false),
                new FieldDefinition('meter_location', 'Meter Box Location', 'text', false),
            ]
        ));
    }

    public function register(BusinessType $type)
    {
        $this->types[$type->getId()] = $type;
    }

    public function get($id)
    {
        return isset($this->types[$id]) ? $this->types[$id] : null;
    }

    public function getAll()
    {
        return $this->types;
    }
}
