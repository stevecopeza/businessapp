<?php
/**
 * Base case for unit tests that drive a repository through a wpdb double.
 *
 * @package BusinessApp
 */

declare( strict_types=1 );

namespace BusinessApp\Tests\Unit\Support;

use PHPUnit\Framework\TestCase;

/**
 * Installs a fresh MockWpdb as the WordPress global before every test.
 *
 * Several repositories (CustomerEntityRepository, CustomerRepository) take no
 * constructor argument and read `global $wpdb` instead, so the double has to be
 * reachable there and not only injected.
 */
abstract class MockedWpdbTestCase extends TestCase {

	/**
	 * The double under the repository being tested.
	 *
	 * @var MockWpdb
	 */
	protected MockWpdb $wpdb;

	/**
	 * Installs a clean double and publishes it as $GLOBALS['wpdb'].
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->wpdb        = new MockWpdb();
		$GLOBALS['wpdb'] = $this->wpdb;
	}

	/**
	 * Removes the double so one test cannot leak into the next.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		unset( $GLOBALS['wpdb'] );
		parent::tearDown();
	}

	/**
	 * Asserts that the repository issued a statement matching every fragment.
	 *
	 * The first fragment selects the candidate statement (it names the verb and
	 * table); the rest are asserted against it individually, so a failure says
	 * WHICH part of the statement was wrong rather than only that nothing matched.
	 * Every fragment produces a real assertion, so a test built only from this
	 * helper is never counted as assertion-free.
	 *
	 * @param array<int, string> $fragments Substrings; the first selects the statement.
	 * @param string             $message   Failure message.
	 * @return string The matching statement.
	 */
	protected function assertStatementIssued( array $fragments, string $message ): string {
		$selector = array_shift( $fragments );
		$candidate = null;

		foreach ( $this->wpdb->queries as $statement ) {
			if ( false !== strpos( $statement, $selector ) ) {
				$candidate = $statement;
				break;
			}
		}

		if ( null === $candidate ) {
			$this->fail(
				$message . "\nNo statement contained: " . $selector
				. "\nStatements issued:\n  " . implode( "\n  ", $this->wpdb->queries )
			);
		}

		$this->assertStringContainsString( $selector, $candidate, $message );

		foreach ( $fragments as $fragment ) {
			$this->assertStringContainsString( $fragment, $candidate, $message );
		}

		return $candidate;
	}
}
