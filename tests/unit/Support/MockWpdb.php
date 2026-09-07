<?php
/**
 * Recording test double for wpdb.
 *
 * @package BusinessApp
 */

declare( strict_types=1 );

namespace BusinessApp\Tests\Unit\Support;

require_once __DIR__ . '/wp-shims.php';

/**
 * Records the writes a repository issues, and answers reads from a queue.
 *
 * ⚠ WHAT THIS DOUBLE CANNOT DO, AND WHY IT MATTERS. It answers success to every
 * write, so a test built on it proves that a repository SPEAKS — never that a
 * database LISTENS. It cannot see a missing table, a wrong column, a type
 * mismatch or a constraint. Those live only in tests/integration/, which runs
 * against a real MySQL server. Do not grow this class to compensate; grow the
 * integration suite instead.
 */
final class MockWpdb extends \wpdb {

	/**
	 * Every statement issued, in order. Reads are prefixed so they can be told apart.
	 *
	 * @var array<int, string>
	 */
	public $queries = array();

	/**
	 * The last WRITE issued. Reads deliberately do not touch it.
	 *
	 * @var string|null
	 */
	public $last_query = null;

	/**
	 * Status returned for the `SELECT status` guard in QuoteItemRepository.
	 *
	 * @var string
	 */
	public $mock_status = 'draft';

	/**
	 * Rows handed to the next get_row() calls, oldest first.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	public $mock_rows = array();

	/**
	 * Consulted by get_row() when the queue is empty. Receives the query text and
	 * returns a row array, or null for "no such record".
	 *
	 * @var callable|null
	 */
	private $row_resolver = null;

	/**
	 * Constructor.
	 *
	 * @param string $prefix Table prefix the repositories will read off this object.
	 */
	public function __construct( string $prefix = 'wp_' ) {
		$this->prefix    = $prefix;
		$this->insert_id = 123;
	}

	/**
	 * Installs the fallback used when the row queue is empty.
	 *
	 * @param callable $resolver Receives the query string, returns array|null.
	 * @return void
	 */
	public function set_row_resolver( callable $resolver ): void {
		$this->row_resolver = $resolver;
	}

	/**
	 * Records an INSERT.
	 *
	 * @param string     $table  Table name.
	 * @param array      $data   Column/value pairs.
	 * @param array|null $format Placeholder formats; recorded but unused.
	 * @return int Rows affected.
	 */
	public function insert( $table, $data, $format = null ) {
		unset( $format );
		$this->record( 'INSERT into ' . $table . ' ' . (string) json_encode( $data ) );
		return 1;
	}

	/**
	 * Records an UPDATE.
	 *
	 * @param string     $table         Table name.
	 * @param array      $data          Column/value pairs.
	 * @param array      $where         Where clause pairs.
	 * @param array|null $format        Placeholder formats; recorded but unused.
	 * @param array|null $where_format  Where placeholder formats; recorded but unused.
	 * @return int Rows affected.
	 */
	public function update( $table, $data, $where, $format = null, $where_format = null ) {
		unset( $format, $where_format );
		$this->record(
			'UPDATE ' . $table . ' ' . (string) json_encode( $data )
			. ' WHERE ' . (string) json_encode( $where )
		);
		return 1;
	}

	/**
	 * Records a DELETE.
	 *
	 * @param string     $table  Table name.
	 * @param array      $where  Where clause pairs.
	 * @param array|null $format Placeholder formats; recorded but unused.
	 * @return int Rows affected.
	 */
	public function delete( $table, $where, $format = null ) {
		unset( $format );
		$this->record( 'DELETE FROM ' . $table . ' WHERE ' . (string) json_encode( $where ) );
		return 1;
	}

	/**
	 * Answers a single-row read from the queue, then the resolver, then null.
	 *
	 * @param string $query       SQL, already through prepare().
	 * @param string $output_type ARRAY_A for an array, anything else for an object.
	 * @param int    $row_offset  Unused; present for signature compatibility.
	 * @return array|object|null The row.
	 */
	public function get_row( $query, $output_type = 'OBJECT', $row_offset = 0 ) {
		unset( $row_offset );
		$this->queries[] = 'GET_ROW: ' . $query;

		$row = null;
		if ( ! empty( $this->mock_rows ) ) {
			$row = array_shift( $this->mock_rows );
		} elseif ( null !== $this->row_resolver ) {
			$row = call_user_func( $this->row_resolver, $query );
		}

		if ( null === $row ) {
			return null;
		}

		return ARRAY_A === $output_type ? $row : (object) $row;
	}

	/**
	 * Answers a multi-row read. Always empty; a repository that needs rows back
	 * belongs in the integration suite.
	 *
	 * @param string $query       SQL, already through prepare().
	 * @param string $output_type Unused; present for signature compatibility.
	 * @return array Always empty.
	 */
	public function get_results( $query, $output_type = 'OBJECT' ) {
		unset( $output_type );
		$this->queries[] = 'GET_RESULTS: ' . $query;
		return array();
	}

	/**
	 * Answers a scalar read. Only the QuoteItemRepository draft guard is modelled.
	 *
	 * @param string $query SQL, already through prepare().
	 * @param int    $x     Unused; present for signature compatibility.
	 * @param int    $y     Unused; present for signature compatibility.
	 * @return string|null The mocked status, or null.
	 */
	public function get_var( $query, $x = 0, $y = 0 ) {
		unset( $x, $y );
		$this->queries[] = 'GET_VAR: ' . $query;

		if ( false !== strpos( $query, 'SELECT status' ) ) {
			return $this->mock_status;
		}

		return null;
	}

	/**
	 * Substitutes %s/%d placeholders so query-shape assertions can read the values.
	 *
	 * @param string $query SQL with placeholders.
	 * @param mixed  $args  A single value or a list of values.
	 * @return string The substituted SQL.
	 */
	public function prepare( $query, $args ) {
		foreach ( is_array( $args ) ? $args : array( $args ) as $arg ) {
			$query = preg_replace( '/%[sdf]/', "'" . (string) $arg . "'", (string) $query, 1 );
		}

		return (string) $query;
	}

	/**
	 * Stores a write against both the ordered log and last_query.
	 *
	 * @param string $statement Rendered statement.
	 * @return void
	 */
	private function record( string $statement ): void {
		$this->last_query = $statement;
		$this->queries[]  = $statement;
	}
}
