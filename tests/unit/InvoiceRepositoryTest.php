<?php
/**
 * Converted from tests/unit_tests.php — Test 6.
 *
 * @package BusinessApp
 */

declare( strict_types=1 );

namespace BusinessApp\Tests\Unit;

use BusinessApp\Infrastructure\InvoiceItemRepository;
use BusinessApp\Infrastructure\InvoiceRepository;
use BusinessApp\Tests\Unit\Support\MockedWpdbTestCase;

/**
 * Pins the invoice write shapes and the revenue arithmetic.
 *
 * ⚠ READ THIS BEFORE TRUSTING A GREEN HERE. Every test in this class runs against
 * a MockWpdb that answers success to any write. They prove the InvoiceRepository
 * composes the right statement; they say NOTHING about whether the invoice tables
 * exist. wp_businessapp_invoices has never had a CREATE TABLE in any commit, and
 * this class was fully green throughout. The test that can see that lives in
 * tests/integration/InvoiceLifecycleTest.php.
 */
final class InvoiceRepositoryTest extends MockedWpdbTestCase {

	/**
	 * An invoice row shaped like the one the invoices table returns.
	 *
	 * @param array<string, mixed> $overrides Fields to replace.
	 * @return array<string, mixed> The row.
	 */
	private function invoice_row( array $overrides = array() ): array {
		return array_merge(
			array(
				'id'           => 1,
				'job_id'       => 10,
				'customer_id'  => 5,
				'status'       => 'draft',
				'title'        => 'Test Invoice',
				'notes'        => 'Payment due in 7 days',
				'total_amount' => 200.0,
				'public_token' => 'token',
				'created_at'   => '2023-01-01 00:00:00',
				'updated_at'   => '2023-01-01 00:00:00',
			),
			$overrides
		);
	}

	/**
	 * Builds the repository pair over the shared double.
	 *
	 * @return InvoiceRepository The repository under test.
	 */
	private function repository(): InvoiceRepository {
		return new InvoiceRepository(
			$this->wpdb,
			'wp_businessapp_invoices',
			new InvoiceItemRepository( $this->wpdb, 'wp_businessapp_invoice_items' )
		);
	}

	/**
	 * Test 6.1, check 1: create() writes the invoice row.
	 *
	 * @return void
	 */
	public function test_create_inserts_the_invoice(): void {
		$repository = $this->repository();

		$this->wpdb->mock_rows[] = $this->invoice_row();

		$repository->create(
			10,
			5,
			'Test Invoice',
			'Payment due in 7 days',
			array( array( 'description' => 'Service', 'qty' => 2, 'unit_price' => 100, 'unit' => 'hr', 'type' => 'labor' ) )
		);

		$this->assertStatementIssued(
			array(
				'INSERT into wp_businessapp_invoices',
				'"job_id":10',
				'"customer_id":5',
				'"title":"Test Invoice"',
				'"status":"draft"',
				'"total_amount":200',
			),
			'create() must insert the invoice with its job, customer, title and computed total.'
		);
	}

	/**
	 * Test 6.1, extra: the line items reach the invoice items table.
	 *
	 * Inherited in spirit from the original Test 6.1, which only looked at the
	 * invoice row. An invoice whose lines were dropped still totals correctly on
	 * the header and shows the customer nothing to pay for.
	 *
	 * @return void
	 */
	public function test_create_writes_the_invoice_line_items(): void {
		$repository = $this->repository();

		$this->wpdb->mock_rows[] = $this->invoice_row();

		$repository->create(
			10,
			5,
			'Test Invoice',
			'Payment due in 7 days',
			array( array( 'description' => 'Service', 'qty' => 2, 'unit_price' => 100, 'unit' => 'hr', 'type' => 'labor' ) )
		);

		$this->assertStatementIssued(
			array(
				'INSERT into wp_businessapp_invoice_items',
				'"description":"Service"',
				'"qty":2',
				'"unit_price":100',
				'"amount":200',
				'"type":"labor"',
			),
			'create() must write each supplied line to wp_businessapp_invoice_items.'
		);
	}

	/**
	 * Test 6.1, check 2: the returned invoice carries the total.
	 *
	 * @return void
	 */
	public function test_create_returns_an_invoice_carrying_the_total(): void {
		$repository = $this->repository();

		$this->wpdb->mock_rows[] = $this->invoice_row();

		$invoice = $repository->create(
			10,
			5,
			'Test Invoice',
			'Payment due in 7 days',
			array( array( 'description' => 'Service', 'qty' => 2, 'unit_price' => 100, 'unit' => 'hr', 'type' => 'labor' ) )
		);

		$this->assertNotNull( $invoice, 'create() must return the stored invoice.' );
		$this->assertSame( 200.0, (float) $invoice->getTotalAmount(), '2 x 100 must total 200.' );
		$this->assertSame( 10, $invoice->getJobId(), 'The returned invoice must carry its job id.' );
		$this->assertSame( 5, $invoice->getCustomerId(), 'The returned invoice must carry its customer id.' );
		$this->assertSame( 'draft', $invoice->getStatus(), 'A new invoice starts as a draft.' );
	}

	/**
	 * Test 6.2: revenue stats split paid from invoiced.
	 *
	 * These two numbers are what the dashboard reports as money. A silent swap
	 * between them is invisible to any query-shape assertion, so both are pinned
	 * to distinct values.
	 *
	 * @return void
	 */
	public function test_revenue_stats_report_paid_and_invoiced_separately(): void {
		$repository = $this->repository();

		$this->wpdb->mock_rows[] = array(
			'paid'     => 500.00,
			'invoiced' => 1200.00,
		);

		$stats = $repository->getRevenueStats();

		$this->assertSame( 500.0, $stats['paid'], 'Paid revenue must come back as a float, unchanged.' );
		$this->assertSame( 1200.0, $stats['invoiced'], 'Invoiced revenue must come back as a float, unchanged.' );
	}

	/**
	 * Test 6.2, extra: an empty invoices table reports zero, not null.
	 *
	 * @return void
	 */
	public function test_revenue_stats_report_zero_when_there_are_no_invoices(): void {
		$repository = $this->repository();

		$stats = $repository->getRevenueStats();

		$this->assertSame( 0.0, $stats['paid'], 'No invoices means zero paid, not null.' );
		$this->assertSame( 0.0, $stats['invoiced'], 'No invoices means zero invoiced, not null.' );
	}

	/**
	 * Test 6.3: update() writes the status and returns the updated invoice.
	 *
	 * @return void
	 */
	public function test_update_writes_the_new_status(): void {
		$repository = $this->repository();

		$this->wpdb->mock_rows[] = $this->invoice_row( array( 'status' => 'sent' ) );

		$updated = $repository->update( 1, array( 'status' => 'sent' ) );

		$this->assertStatementIssued(
			array( 'UPDATE wp_businessapp_invoices', '"status":"sent"', '"id":1' ),
			'update() must write the new status against the invoice id it was given.'
		);
		$this->assertSame( 'sent', $updated->getStatus(), 'The returned invoice must show the new status.' );
	}
}
