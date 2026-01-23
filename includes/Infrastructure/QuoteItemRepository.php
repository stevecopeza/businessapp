<?php

namespace BusinessApp\Infrastructure;

use BusinessApp\Domain\QuoteItem;

class QuoteItemRepository
{
    private $wpdb;
    private $tableName;

    public function __construct(\wpdb $wpdb, $tableName)
    {
        $this->wpdb = $wpdb;
        $this->tableName = $tableName;
    }

    public function createItem($quoteId, $description, $qty, $unit, $unitPrice)
    {
        $description = (string) $description;
        $qty = (int) $qty;
        $unit = (string) $unit;
        $unitPrice = (float) $unitPrice;

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
                'quote_id'   => $quoteId,
                'description' => $description,
                'qty'        => $qty,
                'unit'       => $unit,
                'unit_price' => $unitPrice,
                'amount'     => $amount,
                'position'   => 1, // We might want to make this dynamic later
            ],
            [
                '%d',
                '%s',
                '%d',
                '%s',
                '%f',
                '%f',
                '%d',
            ]
        );
    }

    public function getItemsForQuote($quoteId)
    {
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT id, description, qty, unit, unit_price, amount, position FROM {$this->tableName} WHERE quote_id = %d ORDER BY position ASC, id ASC",
                $quoteId
            ),
            ARRAY_A
        );

        if (!is_array($rows)) {
            return [];
        }

        $items = [];
        foreach ($rows as $row) {
            $items[] = new QuoteItem(
                (int) $row['id'],
                (string) $row['description'],
                (int) $row['qty'],
                isset($row['unit']) ? (string) $row['unit'] : '',
                (float) $row['unit_price'],
                (float) $row['amount']
            );
        }

        return $items;
    }

    public function deleteItemsForQuote($quoteId)
    {
        $this->wpdb->delete(
            $this->tableName,
            ['quote_id' => $quoteId],
            ['%d']
        );
    }
}
