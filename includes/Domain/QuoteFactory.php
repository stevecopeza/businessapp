<?php

namespace BusinessApp\Domain;

class QuoteFactory
{
    private $registry;

    public function __construct(BusinessTypeRegistry $registry)
    {
        $this->registry = $registry;
    }

    public function createDraft(
        $customerId,
        $title,
        array $lineItems = [],
        $activeTypeId = 'panel_beater',
        array $associatedEntityIds = [],
        $customerName = '',
        $customerEmail = '',
        $customerPhone = '',
        $notes = '',
        $dynamicFields = []
    ) {
        $businessType = $this->registry->get($activeTypeId);
        $schemaSnapshot = [];

        if ($businessType) {
            $schemaSnapshot = $businessType->toArray();
        }

        $totalAmount = 0;
        $quoteItems = [];

        foreach ($lineItems as $itemData) {
            $qty = isset($itemData['qty']) ? (float)$itemData['qty'] : 0;
            $unitPrice = isset($itemData['unit_price']) ? (float)$itemData['unit_price'] : 0;
            $description = isset($itemData['description']) ? $itemData['description'] : '';
            $unit = isset($itemData['unit']) ? $itemData['unit'] : '';
            
            $itemTotal = $qty * $unitPrice;
            $totalAmount += $itemTotal;

            $quoteItems[] = new QuoteItem(
                null, // ID is null for new items
                $description,
                $qty,
                $unit,
                $unitPrice,
                $itemTotal
            );
        }

        return new Quote(
            null, // ID
            'draft', // Status
            $customerId,
            $title,
            $totalAmount,
            $customerName,
            $customerEmail,
            $customerPhone,
            $notes,
            '', // Public Token
            'unpaid', // Payment Status
            $dynamicFields,
            $schemaSnapshot,
            $associatedEntityIds,
            $quoteItems,
            current_time('mysql')
        );
    }

    public function updateDraft(Quote $quote, $title, array $lineItems, array $associatedEntityIds, $notes, $dynamicFields)
    {
        $totalAmount = 0;
        $quoteItems = [];

        foreach ($lineItems as $itemData) {
            $qty = isset($itemData['qty']) ? (float)$itemData['qty'] : 0;
            $unitPrice = isset($itemData['unit_price']) ? (float)$itemData['unit_price'] : 0;
            $description = isset($itemData['description']) ? $itemData['description'] : '';
            $unit = isset($itemData['unit']) ? $itemData['unit'] : '';
            
            $itemTotal = $qty * $unitPrice;
            $totalAmount += $itemTotal;

            $quoteItems[] = new QuoteItem(
                null, // ID is null for new items (will be replaced on save)
                $description,
                $qty,
                $unit,
                $unitPrice,
                $itemTotal
            );
        }

        $quote->updateDraftDetails(
            $title,
            $totalAmount,
            $notes,
            $dynamicFields,
            $associatedEntityIds,
            $quoteItems
        );

        return $quote;
    }
}

