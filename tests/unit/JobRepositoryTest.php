<?php
/**
 * Converted from tests/unit_tests.php Test 3, tests/test_job_updates.php (whole file)
 * and tests/test_workflow_propagation.php (whole file).
 *
 * @package BusinessApp
 */

declare( strict_types=1 );

namespace BusinessApp\Tests\Unit;

use BusinessApp\Domain\Quote;
use BusinessApp\Domain\QuoteItem;
use BusinessApp\Infrastructure\JobItemRepository;
use BusinessApp\Infrastructure\JobRepository;
use BusinessApp\Tests\Unit\Support\MockedWpdbTestCase;

/**
 * Pins how a job is created from a quote, updated, and looked up by quote id.
 *
 * These are the quote-to-job conversion behaviours: the point at which an accepted
 * quote becomes work. A line item lost here is work nobody gets paid for.
 */
final class JobRepositoryTest extends MockedWpdbTestCase {

	/**
	 * A job row shaped like the one the jobs table returns.
	 *
	 * @param array<string, mixed> $overrides Fields to replace.
	 * @return array<string, mixed> The row.
	 */
	private function job_row( array $overrides = array() ): array {
		return array_merge(
			array(
				'id'                    => 123,
				'quote_id'              => 100,
				'customer_id'           => 1,
				'status'                => 'planned',
				'title'                 => 'New Job',
				'notes'                 => '',
				'start_date'            => null,
				'end_date'              => null,
				'dynamic_fields'        => '{}',
				'schema_snapshot'       => '{}',
				'associated_entity_ids' => '[]',
				'created_at'            => '2023-01-01 00:00:00',
				'updated_at'            => '2023-01-01 00:00:00',
			),
			$overrides
		);
	}

	/**
	 * unit_tests.php Test 3, check 1: converting a quote writes the job row.
	 *
	 * @return void
	 */
	public function test_converting_a_quote_inserts_the_job(): void {
		$item_repository = new JobItemRepository( $this->wpdb, 'wp_businessapp_job_items' );
		$repository      = new JobRepository( $this->wpdb, 'wp_businessapp_jobs', $item_repository );

		$this->wpdb->mock_rows[] = $this->job_row( array( 'title' => 'Converted Quote' ) );

		$quote = $this->converted_quote();

		$repository->create(
			$quote->getId(),
			$quote->getCustomerId(),
			$quote->getTitle(),
			$quote->getNotes(),
			null,
			null,
			$quote->getDynamicFields(),
			$quote->getSchemaSnapshot(),
			$quote->getAssociatedEntityIds(),
			$quote->getLineItems()
		);

		$this->assertStatementIssued(
			array(
				'INSERT into wp_businessapp_jobs',
				'"quote_id":100',
				'"customer_id":1',
				'"title":"Converted Quote"',
				'"status":"planned"',
			),
			'Converting a quote must insert a job carrying the quote id, customer and title.'
		);
	}

	/**
	 * unit_tests.php Test 3, check 2: every quote line item becomes a job line item.
	 *
	 * ⚠ THIS CHECK WAS NOT A CHECK. In tests/unit_tests.php:264-269 both branches of
	 * the if printed "[PASS]", so it reported success whether or not a single item
	 * was written. It is inherited here as a real assertion — one that names both
	 * items, since a conversion that silently drops a line is exactly the bug the
	 * original was reaching for.
	 *
	 * @return void
	 */
	public function test_converting_a_quote_carries_every_line_item_onto_the_job(): void {
		$item_repository = new JobItemRepository( $this->wpdb, 'wp_businessapp_job_items' );
		$repository      = new JobRepository( $this->wpdb, 'wp_businessapp_jobs', $item_repository );

		$this->wpdb->mock_rows[] = $this->job_row( array( 'title' => 'Converted Quote' ) );

		$quote = $this->converted_quote();

		$repository->create(
			$quote->getId(),
			$quote->getCustomerId(),
			$quote->getTitle(),
			$quote->getNotes(),
			null,
			null,
			$quote->getDynamicFields(),
			$quote->getSchemaSnapshot(),
			$quote->getAssociatedEntityIds(),
			$quote->getLineItems()
		);

		$item_inserts = array_values(
			array_filter(
				$this->wpdb->queries,
				static function ( $statement ) {
					return false !== strpos( $statement, 'INSERT into wp_businessapp_job_items' );
				}
			)
		);

		$this->assertCount(
			2,
			$item_inserts,
			'Both quote line items must be written to wp_businessapp_job_items.'
		);
		$this->assertStringContainsString( '"description":"Paint"', $item_inserts[0], 'First item description.' );
		$this->assertStringContainsString( '"qty":10', $item_inserts[0], 'First item quantity.' );
		$this->assertStringContainsString( '"amount":50', $item_inserts[0], 'First item amount.' );
		$this->assertStringContainsString( '"description":"Labor"', $item_inserts[1], 'Second item description.' );
		$this->assertStringContainsString( '"qty":5', $item_inserts[1], 'Second item quantity.' );
		$this->assertStringContainsString( '"amount":250', $item_inserts[1], 'Second item amount.' );
	}

	/**
	 * test_job_updates.php check 1: update() writes the new title to the job row.
	 *
	 * @return void
	 */
	public function test_update_writes_the_new_title_to_the_job(): void {
		$repository = $this->repository_with_items();

		$this->wpdb->mock_rows[] = $this->job_row( array( 'id' => 1, 'title' => 'Updated Job Title' ) );

		$repository->update( 1, $this->update_payload() );

		$this->assertStatementIssued(
			array( 'UPDATE wp_businessapp_jobs', '"title":"Updated Job Title"', '"id":1' ),
			'update() must write the new title against the job id it was given.'
		);
	}

	/**
	 * test_job_updates.php check 2: update() clears the previous line items first.
	 *
	 * The repository replaces rather than diffs items, so a missing delete leaves
	 * the old lines behind and doubles the job's cost.
	 *
	 * @return void
	 */
	public function test_update_deletes_the_previous_line_items(): void {
		$repository = $this->repository_with_items();

		$this->wpdb->mock_rows[] = $this->job_row( array( 'id' => 1, 'title' => 'Updated Job Title' ) );

		$repository->update( 1, $this->update_payload() );

		$this->assertStatementIssued(
			array( 'DELETE FROM wp_businessapp_job_items', '"job_id":1' ),
			'update() must clear the job\'s existing items before writing the new ones.'
		);
	}

	/**
	 * test_job_updates.php check 3: both replacement items are written.
	 *
	 * @return void
	 */
	public function test_update_writes_every_replacement_line_item(): void {
		$repository = $this->repository_with_items();

		$this->wpdb->mock_rows[] = $this->job_row( array( 'id' => 1, 'title' => 'Updated Job Title' ) );

		$repository->update( 1, $this->update_payload() );

		$item_inserts = array_values(
			array_filter(
				$this->wpdb->queries,
				static function ( $statement ) {
					return false !== strpos( $statement, 'INSERT into wp_businessapp_job_items' );
				}
			)
		);

		$this->assertCount( 2, $item_inserts, 'Both replacement items must be written.' );
		$this->assertStringContainsString( '"description":"New Item 1"', $item_inserts[0], 'First replacement description.' );
		$this->assertStringContainsString( '"amount":50', $item_inserts[0], '5 x 10 must be recorded as 50.' );
		$this->assertStringContainsString( '"description":"New Item 2"', $item_inserts[1], 'Second replacement description.' );
		$this->assertStringContainsString( '"amount":100', $item_inserts[1], '2 x 50 must be recorded as 100.' );
	}

	/**
	 * test_workflow_propagation.php check 1: no job for the quote means null, not a
	 * hollow Job object.
	 *
	 * @return void
	 */
	public function test_get_by_quote_id_returns_null_when_no_job_exists(): void {
		$repository = new JobRepository( $this->wpdb, 'wp_businessapp_jobs' );

		$this->wpdb->set_row_resolver(
			static function ( $query ) {
				return false !== strpos( $query, "quote_id = '999'" )
					? array( 'id' => 999 )
					: null;
			}
		);

		$this->assertNull(
			$repository->getByQuoteId( 100 ),
			'A quote with no job must produce null, so callers can tell "not converted" from "converted".'
		);
	}

	/**
	 * test_workflow_propagation.php check 2: an existing job is found and mapped.
	 *
	 * @return void
	 */
	public function test_get_by_quote_id_returns_the_existing_job(): void {
		$repository = new JobRepository( $this->wpdb, 'wp_businessapp_jobs' );

		$row = $this->job_row(
			array(
				'id'       => 999,
				'quote_id' => 999,
				'title'    => 'Existing Job',
			)
		);

		$this->wpdb->set_row_resolver(
			static function ( $query ) use ( $row ) {
				return false !== strpos( $query, "quote_id = '999'" ) ? $row : null;
			}
		);

		$job = $repository->getByQuoteId( 999 );

		$this->assertNotNull( $job, 'An existing job must be found by its quote id.' );
		$this->assertSame( 999, $job->getId(), 'The mapped job must carry the row id.' );
		$this->assertSame( 999, $job->getQuoteId(), 'The mapped job must carry the quote id.' );
		$this->assertSame( 'Existing Job', $job->getTitle(), 'The mapped job must carry the row title.' );
		$this->assertSame( 'planned', $job->getStatus(), 'The mapped job must carry the row status.' );
	}

	/**
	 * test_workflow_propagation.php check 3: an explicit status reaches the insert.
	 *
	 * @return void
	 */
	public function test_create_writes_the_status_it_was_given(): void {
		$repository = new JobRepository( $this->wpdb, 'wp_businessapp_jobs' );

		$this->wpdb->mock_rows[] = $this->job_row();

		$repository->create( 100, 1, 'New Job', 'Notes', null, null, array(), array(), array(), array(), 'planned' );

		$this->assertStatementIssued(
			array( 'INSERT into wp_businessapp_jobs', '"status":"planned"' ),
			'create() must write the status it was passed, not a hardcoded default.'
		);
	}

	/**
	 * The quote used by the conversion tests: two items totalling 300.
	 *
	 * @return Quote The quote being converted.
	 */
	private function converted_quote(): Quote {
		return new Quote(
			100,
			'draft',
			1,
			'Converted Quote',
			300.0,
			'John',
			'john@example.com',
			'555',
			'Notes',
			'token123',
			'unpaid',
			array( 'vin' => '123' ),
			array( 'entity_name' => 'Vehicle' ),
			array(),
			array(
				new QuoteItem( 1, 'Paint', 10, 'ltr', 5, 50 ),
				new QuoteItem( 2, 'Labor', 5, 'hr', 50, 250 ),
			),
			'2023-01-01'
		);
	}

	/**
	 * A JobRepository wired to a real JobItemRepository over the same double.
	 *
	 * @return JobRepository The repository under test.
	 */
	private function repository_with_items(): JobRepository {
		return new JobRepository(
			$this->wpdb,
			'wp_businessapp_jobs',
			new JobItemRepository( $this->wpdb, 'wp_businessapp_job_items' )
		);
	}

	/**
	 * The update payload used by the three update tests.
	 *
	 * @return array<string, mixed> The payload.
	 */
	private function update_payload(): array {
		return array(
			'title' => 'Updated Job Title',
			'items' => array(
				array( 'description' => 'New Item 1', 'qty' => 5, 'unit' => 'pcs', 'unit_price' => 10 ),
				array( 'description' => 'New Item 2', 'qty' => 2, 'unit' => 'hrs', 'unit_price' => 50 ),
			),
		);
	}
}
