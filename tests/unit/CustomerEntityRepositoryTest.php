<?php
/**
 * Converted from tests/unit_tests.php — Test 1 and Test 4.
 *
 * @package BusinessApp
 */

declare( strict_types=1 );

namespace BusinessApp\Tests\Unit;

use BusinessApp\Infrastructure\CustomerEntityRepository;
use BusinessApp\Tests\Unit\Support\MockedWpdbTestCase;

/**
 * Pins the write shape of CustomerEntityRepository.
 *
 * Inherited from the printf script: the three behaviours below were already judged
 * worth pinning, and are carried over unchanged in meaning.
 */
final class CustomerEntityRepositoryTest extends MockedWpdbTestCase {

	/**
	 * Test 1, check 1: create() writes to the customer entities table.
	 *
	 * @return void
	 */
	public function test_create_inserts_into_the_customer_entities_table(): void {
		$repository = new CustomerEntityRepository();

		$repository->create( 1, 'My Entity', array( 'foo' => 'bar' ), array( 'entity_name' => 'Test', 'fields' => array() ) );

		$this->assertStatementIssued(
			array( 'INSERT into wp_businessapp_customer_entities', '"entity_name":"My Entity"', '"customer_id":1' ),
			'create() must insert the entity into wp_businessapp_customer_entities.'
		);
	}

	/**
	 * Test 1, check 2: dynamic fields reach the write as JSON, not as an array cast.
	 *
	 * @return void
	 */
	public function test_create_json_encodes_the_dynamic_fields(): void {
		$repository = new CustomerEntityRepository();

		$repository->create( 1, 'My Entity', array( 'foo' => 'bar' ), array( 'entity_name' => 'Test', 'fields' => array() ) );

		$statement = $this->assertStatementIssued(
			array( 'INSERT into wp_businessapp_customer_entities' ),
			'create() must issue an insert before its payload can be checked.'
		);

		$payload = json_decode( substr( $statement, strpos( $statement, '{' ) ), true );

		$this->assertIsArray( $payload, 'The recorded insert payload must be decodable JSON.' );
		$this->assertSame(
			'{"foo":"bar"}',
			$payload['dynamic_fields'],
			'dynamic_fields must be JSON-encoded by the repository, not passed through as an array.'
		);
		$this->assertSame(
			'{"entity_name":"Test","fields":[]}',
			$payload['schema_snapshot'],
			'schema_snapshot must be JSON-encoded by the repository.'
		);
	}

	/**
	 * Test 4: delete() archives rather than removing.
	 *
	 * This is a data-loss guard. If delete() ever becomes a real DELETE, the
	 * customer's entity history disappears — so the assertion is on the verb as
	 * well as on the value written.
	 *
	 * @return void
	 */
	public function test_delete_archives_the_entity_instead_of_removing_it(): void {
		$repository = new CustomerEntityRepository();

		$repository->delete( 123 );

		$this->assertStatementIssued(
			array( 'UPDATE wp_businessapp_customer_entities', '"status":"archived"', '"id":123' ),
			'delete() must soft-delete by setting status=archived on the row.'
		);

		foreach ( $this->wpdb->queries as $statement ) {
			$this->assertStringNotContainsString(
				'DELETE FROM wp_businessapp_customer_entities',
				$statement,
				'delete() must never issue a hard DELETE against the entities table.'
			);
		}
	}
}
