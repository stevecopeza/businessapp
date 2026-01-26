<?php

namespace BusinessApp\Infrastructure;

use BusinessApp\Domain\InvoiceItem;

class InvoiceItemRepository
{
    private $wpdb;
    private $tableName;

    public function __construct(\wpdb $wpdb, $tableName)
    {
        $this->wpdb = $wpdb;
        $this->tableName = $tableName;
    }

    public function createItem($invoiceId, $description, $qty, $unit, $unitPrice, $type = 'labor')
    {
        $description = (string) $description;
        $qty = (int) $qty;
        $unit = (string) $unit;
        $unitPrice = (float) $unitPrice;
        $type = (string) $type;

        if ($qty <= 0) {
            $qty = 1;
        }

        $amount = $qty * $unitPrice;

        if ($description === '' && $amount === 0.0) {
            return;
        }

        $this->wpdb->insert(
            $this->tableName,
            [
                'invoice_id'   => $invoiceId,
                'description' => $description,
                'qty'        => $qty,
                'unit'       => $unit,
                'unit_price' => $unitPrice,
                'amount'     => $amount,
                'type'       => $type,
                'position'   => 1, // We might want to make this dynamic later
            ],
            [
                '%d',
                '%s',
                '%d',
                '%s',
                '%f',
                '%f',
                '%s',
                '%d',
            ]
        );
    }

    public function getItemsForInvoice($invoiceId)
    {
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT id, description, qty, unit, unit_price, amount, type, position FROM {$this->tableName} WHERE invoice_id = %d ORDER BY position ASC, id ASC",
                $invoiceId
            ),
            ARRAY_A
        );

        if (!is_array($rows)) {
            return [];
        }

        $items = [];
        foreach ($rows as $row) {
            $items[] = new InvoiceItem(
                (int) $row['id'],
                (string) $row['description'],
                (int) $row['qty'],
                isset($row['unit']) ? (string) $row['unit'] : '',
                (float) $row['unit_price'],
                (float) $row['amount'],
                isset($row['type']) ? (string) $row['type'] : 'labor'
            );
        }

        return $items;
    }

    public function deleteItemsForInvoice($invoiceId)
    {
        $this->wpdb->delete(
            $this->tableName,
            ['invoice_id' => $invoiceId],
            ['%d']
        );
    }
}
