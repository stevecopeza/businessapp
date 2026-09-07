<?php
/**
 * Converted from tests/unit_tests.php — Test 5.
 *
 * @package BusinessApp
 */

declare( strict_types=1 );

namespace BusinessApp\Tests\Unit;

use BusinessApp\Infrastructure\QuoteItemRepository;
use BusinessApp\Tests\Unit\Support\MockedWpdbTestCase;
use RuntimeException;

/**
 * Pins the draft-only guard on QuoteItemRepository.
 *
 * A quote that has been sent to a customer is a document they have seen. Silently
 * adding a line to it after the fact is the bug this guard exists to prevent, so
 * both halves are pinned: it must refuse when sent, and it must NOT refuse when
 * draft — a guard that refuses everything is as broken as one that refuses nothing.
 */
final class QuoteItemRepositoryTest extends MockedWpdbTestCase {

	/**
	 * Test 5, check 1: adding an item to a non-draft quote throws, and writes nothing.
	 *
	 * @return void
	 */
	public function test_adding_an_item_to_a_sent_quote_is_refused(): void {
		$repository = new QuoteItemRepository( $this->wpdb, 'wp_businessapp_quote_items' );

		$this->wpdb->mock_status = 'sent';

		$thrown = null;
		try {
			$repository->createItem( 999, 'Test', 1, 'unit', 10 );
		} catch ( RuntimeException $e ) {
			$thrown = $e;
		}

		$this->assertInstanceOf(
			RuntimeException::class,
			$thrown,
			'createItem() must refuse a quote whose status is not draft.'
		);
		$this->assertSame(
			'Cannot add items to a non-draft quote (Status: sent).',
			$thrown->getMessage(),
			'The refusal must name the offending status so the caller can report it.'
		);

		foreach ( $this->wpdb->queries as $statement ) {
			$this->assertStringNotContainsString(
				'INSERT into wp_businessapp_quote_items',
				$statement,
				'A refused createItem() must not have written a row before throwing.'
			);
		}
	}

	/**
	 * Test 5, checks 2 and 3: a draft quote accepts the item, and the row carries
	 * the computed amount.
	 *
	 * @return void
	 */
	public function test_adding_an_item_to_a_draft_quote_is_allowed(): void {
		$repository = new QuoteItemRepository( $this->wpdb, 'wp_businessapp_quote_items' );

		$this->wpdb->mock_status = 'draft';

		$repository->createItem( 999, 'Test', 3, 'unit', 10 );

		$this->assertStatementIssued(
			array(
				'INSERT into wp_businessapp_quote_items',
				'"quote_id":999',
				'"description":"Test"',
				'"qty":3',
				'"unit_price":10',
				'"amount":30',
			),
			'A draft quote must accept a new item, with amount computed as qty x unit price.'
		);
	}
}
