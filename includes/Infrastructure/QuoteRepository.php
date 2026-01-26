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
            // Check current DB status to decide on item updates
            $currentDbStatus = $this->wpdb->get_var(
                $this->wpdb->prepare("SELECT status FROM {$this->tableName} WHERE id = %d", $quote->getId())
            );

            $this->wpdb->update(
                $this->tableName,
                $data,
                ['id' => $quote->getId()],
                $format,
                ['%d']
            );
            $quoteId = $quote->getId();
        } else {
            $currentDbStatus = 'draft'; // New quotes are treated as draft for item insertion (or whatever the quote status is, but usually draft)
            // Actually if we create a quote as 'sent' immediately (import?), we might want to allow items.
            // But standard flow is create draft.
            // Let's assume 'draft' for logic below or checking $quote->getStatus() if needed.
            // But simpler: New quotes always allow item insertion in this block logic, 
            // because we haven't inserted the Quote row yet? 
            // Wait, we insert Quote row first below.
            
            $data['created_at'] = $now;
            $format[] = '%s';
            $this->wpdb->insert(
                $this->tableName,
                $data,
                $format
            );
            $quoteId = $this->wpdb->insert_id;
            $currentDbStatus = 'draft'; // Effectively, since we just inserted, and if we inserted 'sent', the ItemRepo check might fail if we don't be careful.
            // If we insert as 'sent', ItemRepo check (which queries DB) will see 'sent'.
            // So we must insert items BEFORE Quote if we want to allow "Create as Sent".
            // But we need ID for items.
            // So "Create as Sent" is impossible with "ItemRepo Check".
            // We must "Create as Draft", insert items, then "Update to Sent".
            // Or ItemRepo must allow "If Quote doesn't exist yet?" No, it needs FK.
            // So "Create as Sent" is blocked. That's fine.
        }

        // Update items only if currently draft in DB (allows transitioning Draft -> Sent)
        // If it's already Sent in DB, we skip item updates to protect immutability (and avoid ItemRepo error)
        if ($currentDbStatus === 'draft' || !$quote->getId()) {
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
