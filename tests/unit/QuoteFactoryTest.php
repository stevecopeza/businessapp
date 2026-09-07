<?php
/**
 * Converted from tests/unit_tests.php — Test 2.
 *
 * @package BusinessApp
 */

declare( strict_types=1 );

namespace BusinessApp\Tests\Unit;

use BusinessApp\Domain\BusinessTypeRegistry;
use BusinessApp\Domain\QuoteFactory;
use BusinessApp\Tests\Unit\Support\MockedWpdbTestCase;

/**
 * Pins the money arithmetic in QuoteFactory::createDraft().
 *
 * This is the one piece of the suite that tests a calculation rather than a query
 * shape, so it is the check most likely to catch a real regression — a wrong total
 * on a quote is money, and it is invisible in a query-shape assertion.
 */
final class QuoteFactoryTest extends MockedWpdbTestCase {

	/**
	 * Test 2, check 1: the draft total is the sum of qty x unit_price.
	 *
	 * @return void
	 */
	public function test_draft_total_is_the_sum_of_the_line_item_amounts(): void {
		$factory = new QuoteFactory( new BusinessTypeRegistry() );

		$quote = $factory->createDraft(
			1,
			'Test Quote',
			array(
				array( 'description' => 'Item 1', 'qty' => 2, 'unit_price' => 50, 'unit' => 'hr' ),
				array( 'description' => 'Item 2', 'qty' => 1, 'unit_price' => 200, 'unit' => 'fixed' ),
			)
		);

		$this->assertSame( 300.0, (float) $quote->getTotalAmount(), '2x50 + 1x200 must total 300.' );
	}

	/**
	 * Test 2, check 2: every supplied line item survives into the draft, with its
	 * own amount, description, quantity and unit.
	 *
	 * The printf original only counted the items. Counting proves none were dropped;
	 * it does not prove the right values landed on the right item, which is the
	 * failure a silently broken factory would actually produce.
	 *
	 * @return void
	 */
	public function test_each_line_item_carries_its_own_values_into_the_draft(): void {
		$factory = new QuoteFactory( new BusinessTypeRegistry() );

		$quote = $factory->createDraft(
			1,
			'Test Quote',
			array(
				array( 'description' => 'Item 1', 'qty' => 2, 'unit_price' => 50, 'unit' => 'hr' ),
				array( 'description' => 'Item 2', 'qty' => 1, 'unit_price' => 200, 'unit' => 'fixed' ),
			)
		);

		$items = $quote->getLineItems();

		$this->assertCount( 2, $items, 'Both supplied line items must survive into the draft.' );

		$this->assertSame( 'Item 1', $items[0]->getDescription(), 'First item description.' );
		$this->assertSame( 2.0, (float) $items[0]->getQty(), 'First item quantity.' );
		$this->assertSame( 'hr', $items[0]->getUnit(), 'First item unit.' );
		$this->assertSame( 50.0, (float) $items[0]->getUnitPrice(), 'First item unit price.' );
		$this->assertSame( 100.0, (float) $items[0]->getAmount(), 'First item amount is qty x unit price.' );

		$this->assertSame( 'Item 2', $items[1]->getDescription(), 'Second item description.' );
		$this->assertSame( 1.0, (float) $items[1]->getQty(), 'Second item quantity.' );
		$this->assertSame( 'fixed', $items[1]->getUnit(), 'Second item unit.' );
		$this->assertSame( 200.0, (float) $items[1]->getUnitPrice(), 'Second item unit price.' );
		$this->assertSame( 200.0, (float) $items[1]->getAmount(), 'Second item amount is qty x unit price.' );
	}
}
