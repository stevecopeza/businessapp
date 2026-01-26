<?php

namespace BusinessApp\Infrastructure;

use BusinessApp\Domain\Invoice;

class InvoiceRepository
{
    private $wpdb;
    private $tableName;
    private $invoiceItemRepository;

    public function __construct(\wpdb $wpdb, $tableName, InvoiceItemRepository $invoiceItemRepository)
    {
        $this->wpdb = $wpdb;
        $this->tableName = $tableName;
        $this->invoiceItemRepository = $invoiceItemRepository;
    }

    public function getAll($status = null, $jobId = null)
    {
        $sql = "SELECT * FROM {$this->tableName} WHERE 1=1";
        $params = [];

        if ($status) {
            $sql .= " AND status = %s";
            $params[] = $status;
        }

        if ($jobId) {
            $sql .= " AND job_id = %d";
            $params[] = $jobId;
        }

        $sql .= " ORDER BY created_at DESC";

        if (!empty($params)) {
            $sql = $this->wpdb->prepare($sql, $params);
        }

        $rows = $this->wpdb->get_results($sql, ARRAY_A);

        if (!is_array($rows)) {
            return [];
        }

        return array_map([$this, 'mapRowToInvoice'], $rows);
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

        return $this->mapRowToInvoice($row);
    }

    public function findByPublicToken($token)
    {
        $row = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$this->tableName} WHERE public_token = %s", $token),
            ARRAY_A
        );

        if (!$row) {
            return null;
        }

        return $this->mapRowToInvoice($row);
    }

    public function save(Invoice $invoice)
    {
        $now = current_time('mysql');

        $data = [
            'job_id'       => $invoice->getJobId(),
            'customer_id'  => $invoice->getCustomerId(),
            'status'       => $invoice->getStatus(),
            'title'        => $invoice->getTitle(),
            'notes'        => $invoice->getNotes(),
            'total_amount' => $invoice->getTotalAmount(),
            'public_token' => $invoice->getPublicToken(),
            'updated_at'   => $now,
        ];

        $format = ['%d', '%d', '%s', '%s', '%s', '%f', '%s', '%s'];

        if ($invoice->getId()) {
            $this->wpdb->update(
                $this->tableName,
                $data,
                ['id' => $invoice->getId()],
                $format,
                ['%d']
            );
            $id = $invoice->getId();
        } else {
            $data['created_at'] = $now;
            $format[] = '%s';
            
            $this->wpdb->insert($this->tableName, $data, $format);
            $id = $this->wpdb->insert_id;
        }
        
        // Save items
        // Note: For simplicity, we are deleting and recreating items on save
        // Ideally we should diff them, but this works for now given the immutable rule for sent invoices
        $this->invoiceItemRepository->deleteItemsForInvoice($id);
        
        $totalAmount = 0.0;
        foreach ($invoice->getLineItems() as $item) {
            $description = $item->getDescription();
            $qty = $item->getQty();
            $unit = $item->getUnit();
            $unitPrice = $item->getUnitPrice();
            $type = $item->getType();

            $this->invoiceItemRepository->createItem(
                $id,
                $description,
                $qty,
                $unit,
                $unitPrice,
                $type
            );
            
            $totalAmount += ((float)$qty * (float)$unitPrice);
        }
        
        // Update total if it changed during item saving (though it should be calculated in domain)
        if (abs($totalAmount - $invoice->getTotalAmount()) > 0.001) {
            $this->wpdb->update(
                $this->tableName,
                ['total_amount' => $totalAmount],
                ['id' => $id],
                ['%f'],
                ['%d']
            );
        }

        return $id;
    }

    public function create($jobId, $customerId, $title, $notes = '', $items = [])
    {
        $now = current_time('mysql');
        
        // Calculate total from items
        $totalAmount = 0.0;
        foreach ($items as $item) {
            $qty = isset($item['qty']) ? (float)$item['qty'] : 1;
            $price = isset($item['unit_price']) ? (float)$item['unit_price'] : 0;
            $totalAmount += $qty * $price;
        }

        $data = [
            'job_id' => $jobId,
            'customer_id' => $customerId,
            'status' => 'draft',
            'title' => $title,
            'notes' => $notes,
            'total_amount' => $totalAmount,
            'created_at' => $now,
            'updated_at' => $now
        ];

        $format = ['%d', '%d', '%s', '%s', '%s', '%f', '%s', '%s'];

        $this->wpdb->insert($this->tableName, $data, $format);
        $id = $this->wpdb->insert_id;

        if (!empty($items)) {
            foreach ($items as $item) {
                $description = is_array($item) ? $item['description'] : $item->getDescription();
                $qty = is_array($item) ? $item['qty'] : $item->getQty();
                $unit = is_array($item) ? $item['unit'] : $item->getUnit();
                $unitPrice = is_array($item) ? $item['unit_price'] : $item->getUnitPrice();
                $type = is_array($item) && isset($item['type']) ? $item['type'] : 'labor';

                $this->invoiceItemRepository->createItem(
                    $id,
                    $description,
                    $qty,
                    $unit,
                    $unitPrice,
                    $type
                );
            }
        }

        return $this->getById($id);
    }

    public function update($id, $data)
    {
        $data['updated_at'] = current_time('mysql');
        
        // Allowed fields to update on the invoice itself
        $allowed = ['status', 'title', 'notes', 'customer_id', 'total_amount', 'public_token'];
        $updateData = [];
        $format = [];

        foreach ($allowed as $field) {
            if (isset($data[$field])) {
                $updateData[$field] = $data[$field];
                $format[] = ($field === 'total_amount') ? '%f' : (($field === 'customer_id') ? '%d' : '%s');
            }
        }
        
        $format[] = '%s'; // for updated_at

        if (!empty($updateData)) {
            $this->wpdb->update(
                $this->tableName,
                $updateData,
                ['id' => $id],
                $format,
                ['%d']
            );
        }

        // Handle items update if present (Replace strategy)
        if (isset($data['items']) && is_array($data['items'])) {
            $this->invoiceItemRepository->deleteItemsForInvoice($id);
            
            $totalAmount = 0.0;
            foreach ($data['items'] as $item) {
                $description = $item['description'];
                $qty = $item['qty'];
                $unit = $item['unit'];
                $unitPrice = $item['unit_price'];
                $type = isset($item['type']) ? $item['type'] : 'labor';

                $this->invoiceItemRepository->createItem(
                    $id,
                    $description,
                    $qty,
                    $unit,
                    $unitPrice,
                    $type
                );
                
                $totalAmount += ((float)$qty * (float)$unitPrice);
            }
            
            // Update total amount on invoice
            $this->wpdb->update(
                $this->tableName,
                ['total_amount' => $totalAmount],
                ['id' => $id],
                ['%f'],
                ['%d']
            );
        }

        return $this->getById($id);
    }

    public function getRevenueStats()
    {
        $sql = "
            SELECT 
                SUM(CASE WHEN status = 'paid' THEN total_amount ELSE 0 END) as paid,
                SUM(CASE WHEN status IN ('sent', 'paid') THEN total_amount ELSE 0 END) as invoiced
            FROM {$this->tableName}
        ";
        
        $row = $this->wpdb->get_row($sql, ARRAY_A);
        
        return [
            'paid' => $row ? (float)$row['paid'] : 0.0,
            'invoiced' => $row ? (float)$row['invoiced'] : 0.0
        ];
    }

    private function mapRowToInvoice($row)
    {
        $items = $this->invoiceItemRepository->getItemsForInvoice($row['id']);

        return new Invoice(
            (int) $row['id'],
            (int) $row['job_id'],
            (int) $row['customer_id'],
            (string) $row['status'],
            (string) $row['title'],
            (string) $row['notes'],
            (float) $row['total_amount'],
            isset($row['public_token']) ? (string) $row['public_token'] : '',
            (string) $row['created_at'],
            (string) $row['updated_at'],
            $items
        );
    }
}
