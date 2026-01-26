<?php

namespace BusinessApp\Infrastructure;

use BusinessApp\Domain\Payment;

class PaymentRepository
{
    private $wpdb;
    private $tableName;

    public function __construct(\wpdb $wpdb, $tableName)
    {
        $this->wpdb = $wpdb;
        $this->tableName = $tableName;
    }

    public function getByInvoiceId($invoiceId)
    {
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare("SELECT * FROM {$this->tableName} WHERE invoice_id = %d ORDER BY created_at DESC", $invoiceId),
            ARRAY_A
        );

        if (!is_array($rows)) {
            return [];
        }

        return array_map([$this, 'mapRowToPayment'], $rows);
    }

    public function getById($id)
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$this->tableName} WHERE id = %d", $id),
            ARRAY_A
        );

        if (!$row) {
            return null;
        }

        return $this->mapRowToPayment($row);
    }

    public function save(Payment $payment)
    {
        $now = current_time('mysql');

        $data = [
            'invoice_id' => $payment->getInvoiceId(),
            'gateway' => $payment->getGateway(),
            'transaction_id' => $payment->getTransactionId(),
            'amount' => $payment->getAmount(),
            'currency' => $payment->getCurrency(),
            'status' => $payment->getStatus(),
            'meta' => json_encode($payment->getMeta()),
        ];

        $format = ['%d', '%s', '%s', '%f', '%s', '%s', '%s'];

        if ($payment->getId()) {
            $this->wpdb->update(
                $this->tableName,
                $data,
                ['id' => $payment->getId()],
                $format,
                ['%d']
            );
            $id = $payment->getId();
        } else {
            $data['created_at'] = $now;
            $format[] = '%s';
            
            $this->wpdb->insert($this->tableName, $data, $format);
            $id = $this->wpdb->insert_id;
        }

        return $id;
    }

    private function mapRowToPayment($row)
    {
        return new Payment(
            (int) $row['id'],
            (int) $row['invoice_id'],
            $row['gateway'],
            $row['transaction_id'],
            (float) $row['amount'],
            $row['currency'],
            $row['status'],
            json_decode($row['meta'], true) ?: [],
            $row['created_at']
        );
    }
}
