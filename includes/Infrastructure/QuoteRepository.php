<?php

namespace BusinessApp\Infrastructure;

use BusinessApp\Domain\Quote;

class QuoteRepository
{
    private $wpdb;
    private $tableName;
    private $quoteItemRepository;

    public function __construct(\wpdb $wpdb, $tableName, QuoteItemRepository $quoteItemRepository)
    {
        $this->wpdb = $wpdb;
        $this->tableName = $tableName;
        $this->quoteItemRepository = $quoteItemRepository;
    }



    public function getAll()
    {
        $rows = $this->wpdb->get_results(
            "SELECT id, customer_id, customer_name, customer_email, customer_phone, title, notes, total_amount, status, payment_status, public_token, dynamic_fields, schema_snapshot, associated_entity_ids, created_at FROM {$this->tableName} ORDER BY id DESC",
            ARRAY_A
        );

        if (!is_array($rows)) {
            return [];
        }

        $quotes = [];

        foreach ($rows as $row) {
            $quotes[] = $this->mapRowToQuote($row);
        }

        return $quotes;
    }

    public function findById($id)
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT id, customer_id, customer_name, customer_email, customer_phone, title, notes, total_amount, status, payment_status, public_token, dynamic_fields, schema_snapshot, associated_entity_ids, created_at FROM {$this->tableName} WHERE id = %d",
                $id
            ),
            ARRAY_A
        );

        if (!$row) {
            return null;
        }

        return $this->mapRowToQuote($row);
    }

    public function save(Quote $quote)
    {
        $now = current_time('mysql');

        $data = [
            'customer_id' => $quote->getCustomerId(),
            'customer_name' => $quote->getCustomerName(),
            'customer_email' => $quote->getCustomerEmail(),
            'customer_phone' => $quote->getCustomerPhone(),
            'title'        => $quote->getTitle(),
            'notes'        => $quote->getNotes(),
            'total_amount' => $quote->getTotalAmount(),
            'status'       => $quote->getStatus(),
            'payment_status' => $quote->getPaymentStatus(),
            'public_token' => $quote->getPublicToken(),
            'dynamic_fields' => json_encode($quote->getDynamicFields()),
            'schema_snapshot' => json_encode($quote->getSchemaSnapshot()),
            'associated_entity_ids' => json_encode($quote->getAssociatedEntityIds()),
            'updated_at'   => $now,
        ];

        $format = [
            '%d', '%s', '%s', '%s', '%s', '%s', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%s'
        ];

        if ($quote->getId()) {
            $this->wpdb->update(
                $this->tableName,
                $data,
                ['id' => $quote->getId()],
                $format,
                ['%d']
            );
            $quoteId = $quote->getId();
        } else {
            $data['created_at'] = $now;
            $format[] = '%s';
            $this->wpdb->insert(
                $this->tableName,
                $data,
                $format
            );
            $quoteId = $this->wpdb->insert_id;
        }

        // Update items only if draft (lifecycle rule) or if we just want to ensure consistency
        // For now, always replace items on save to keep it simple and robust
        $this->quoteItemRepository->deleteItemsForQuote($quoteId);
        
        foreach ($quote->getLineItems() as $item) {
            $this->quoteItemRepository->createItem(
                $quoteId,
                $item->getDescription(),
                $item->getQty(),
                $item->getUnit(),
                $item->getUnitPrice()
            );
        }

        return $quoteId;
    }

    public function findByPublicToken($token)
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT id, customer_id, customer_name, customer_email, customer_phone, title, notes, total_amount, status, payment_status, public_token, dynamic_fields, schema_snapshot, associated_entity_ids, created_at FROM {$this->tableName} WHERE public_token = %s",
                $token
            ),
            ARRAY_A
        );

        if (!$row) {
            return null;
        }

        return $this->mapRowToQuote($row);
    }

    private function mapRowToQuote($row)
    {
        $dynamicFields = !empty($row['dynamic_fields']) ? json_decode($row['dynamic_fields'], true) : [];
        $schemaSnapshot = !empty($row['schema_snapshot']) ? json_decode($row['schema_snapshot'], true) : [];
        $associatedEntityIds = !empty($row['associated_entity_ids']) ? json_decode($row['associated_entity_ids'], true) : [];

        // Load line items
        $lineItems = $this->quoteItemRepository->getItemsForQuote((int) $row['id']);

        return new Quote(
            (int) $row['id'],
            (string) $row['status'],
            (int) $row['customer_id'],
            (string) $row['title'],
            (float) $row['total_amount'],
            (string) $row['customer_name'],
            (string) $row['customer_email'],
            (string) $row['customer_phone'],
            (string) $row['notes'],
            (string) $row['public_token'],
            isset($row['payment_status']) ? (string) $row['payment_status'] : 'unpaid',
            is_array($dynamicFields) ? $dynamicFields : [],
            is_array($schemaSnapshot) ? $schemaSnapshot : [],
            is_array($associatedEntityIds) ? $associatedEntityIds : [],
            $lineItems,
            isset($row['created_at']) ? $row['created_at'] : ''
        );
    }
}
