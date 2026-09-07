<?php
/**
 * The test a mocked wpdb is structurally incapable of expressing.
 *
 * @package BusinessApp
 */

declare( strict_types=1 );

namespace BusinessApp\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Activates the plugin against a real, empty MySQL database and raises an invoice.
 *
 * ⛔ THIS FILE IS EXPECTED TO BE RED AS OF 2026-09-07, AND THAT IS THE POINT.
 * businessapp.php's create_or_update_tables() issues eight dbDelta() calls and none
 * of them creates wp_businessapp_invoices or wp_businessapp_invoice_items. A fresh
 * install therefore cannot raise a single invoice, and has not been able to since
 * the feature was written — while the roadmap called invoicing Complete, because
 * every test that touched invoicing ran against a double that answered success to
 * everything.
 *
 * When BA-ACTIVATION-NEVER-CREATES-THE-INVOICE-TABLES-1 is fixed, this file goes
 * green on its own. Until then, DO NOT make it pass by relaxing an assertion,
 * skipping a case, or teaching the test to create the tables itself — creating them
 * here would restore exactly the blindness this file exists to remove.
 */
final class InvoiceLifecycleTest extends TestCase {

	/**
	 * What the probe observed on the real database. Gathered once for the class,
	 * because provisioning a WordPress install per test would take minutes.
	 *
	 * @var array<string, mixed>
	 */
	private static array $report = array();

	/**
	 * Provisions the throwaway site and runs the probe inside it.
	 *
	 * @return void
	 */
	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();

		$site = new WordPressTestSite();
		$site->provision();
		self::$report = $site->run_probe( __DIR__ . '/probe-invoice-lifecycle.php' );
	}

	/**
	 * The control. If this fails, the harness is broken and every other failure in
	 * this file is uninterpretable — an empty result is not evidence until the
	 * instrument is known to work.
	 *
	 * @return void
	 */
	public function test_activation_created_the_tables_it_does_create(): void {
		$prefix = self::$report['prefix'];

		$this->assertSame(
			'10',
			(string) self::$report['db_version'],
			'The plugin must have recorded its schema version, or activation did not run at all.'
		);

		foreach ( array( 'quotes', 'quote_items', 'customers', 'jobs', 'job_items', 'payments' ) as $table ) {
			$this->assertContains(
				$prefix . 'businessapp_' . $table,
				self::$report['plugin_tables'],
				"Activation must create {$prefix}businessapp_{$table}; if it did not, this harness is not "
				. 'exercising the plugin and nothing else in this file can be believed.'
			);
		}
	}

	/**
	 * RED TODAY. Activation must create the tables the invoice feature writes to.
	 *
	 * @return void
	 */
	public function test_activation_creates_the_invoice_tables(): void {
		$prefix = self::$report['prefix'];

		$this->assertContains(
			$prefix . 'businessapp_invoices',
			self::$report['plugin_tables'],
			'A fresh activation must create the invoices table. It does not: businessapp.php '
			. 'create_or_update_tables() has no CREATE TABLE for it, so every invoice write on a '
			. 'fresh install hits a table that is not there.'
		);

		$this->assertContains(
			$prefix . 'businessapp_invoice_items',
			self::$report['plugin_tables'],
			'A fresh activation must create the invoice items table.'
		);
	}

	/**
	 * RED TODAY. A fresh install must be able to raise an invoice and read it back.
	 *
	 * This is the behaviour a customer pays for, asserted at the layer that decides
	 * whether it happened: rows in a real database.
	 *
	 * @return void
	 */
	public function test_a_fresh_install_can_raise_an_invoice(): void {
		$this->assertNull(
			self::$report['invoice_error'],
			'Raising an invoice on a fresh install must not error.'
		);

		$this->assertIsArray(
			self::$report['invoice_created'],
			'InvoiceRepository::create() must return the stored invoice, not null.'
		);

		$this->assertSame(
			'250.00',
			self::$report['invoice_created']['total_amount'],
			'2 hours at 125.00 must be invoiced as 250.00.'
		);
		$this->assertSame(
			'draft',
			self::$report['invoice_created']['status'],
			'A newly raised invoice starts as a draft.'
		);
		$this->assertSame(
			1,
			self::$report['invoice_created']['items'],
			'The invoice must carry the line it was raised with, read back from the database.'
		);
	}

	/**
	 * RED TODAY. The invoice and its line must actually be on disk afterwards.
	 *
	 * Separate from the test above on purpose: a repository can return an object
	 * assembled in memory while having persisted nothing, which is the failure a
	 * mocked wpdb can never distinguish from success.
	 *
	 * @return void
	 */
	public function test_the_raised_invoice_is_stored_in_the_database(): void {
		$this->assertSame(
			1,
			self::$report['invoice_rows'],
			'Exactly one row must exist in the invoices table after raising one invoice. '
			. 'null here means the table does not exist at all.'
		);

		$this->assertSame(
			1,
			self::$report['item_rows'],
			'The invoice line must be stored in the invoice items table. '
			. 'null here means the table does not exist at all.'
		);
	}
}
