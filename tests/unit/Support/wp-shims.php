<?php
/**
 * The minimum WordPress surface the unit suite needs, and nothing more.
 *
 * tests/bootstrap.php deliberately loads only vendor/autoload.php, so nothing here
 * is defined for us. Every declaration is guarded, so requiring this file inside a
 * process that HAS loaded WordPress (the integration suite) is a no-op rather than
 * a fatal redeclaration.
 *
 * Carried over verbatim in behaviour from the three converted scripts
 * (tests/unit_tests.php, tests/test_job_updates.php, tests/test_workflow_propagation.php),
 * which each declared their own private copy of this.
 *
 * @package BusinessApp
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/' );
}
if ( ! defined( 'ARRAY_A' ) ) {
	define( 'ARRAY_A', 'ARRAY_A' );
}
if ( ! defined( 'ARRAY_N' ) ) {
	define( 'ARRAY_N', 'ARRAY_N' );
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	/**
	 * Stand-in for WordPress's sanitize_text_field().
	 *
	 * @param string $str Raw value.
	 * @return string Trimmed value.
	 */
	function sanitize_text_field( $str ) {
		return trim( (string) $str );
	}
}

if ( ! function_exists( 'current_time' ) ) {
	/**
	 * Stand-in for WordPress's current_time().
	 *
	 * @param string $type Requested format; ignored, always MySQL datetime.
	 * @return string Datetime string.
	 */
	function current_time( $type ) {
		unset( $type );
		return gmdate( 'Y-m-d H:i:s' );
	}
}

if ( ! function_exists( 'get_option' ) ) {
	/**
	 * Stand-in for WordPress's get_option().
	 *
	 * @param string $name          Option name.
	 * @param mixed  $default_value Returned unconditionally.
	 * @return mixed The default.
	 */
	function get_option( $name, $default_value = false ) {
		unset( $name );
		return $default_value;
	}
}

if ( ! class_exists( 'wpdb', false ) ) {
	/**
	 * Minimal stand-in for the global wpdb class.
	 *
	 * Exists only so the repositories' `\wpdb` type hints resolve under the unit
	 * suite. Every method is a no-op; MockWpdb is what tests actually use.
	 */
	class wpdb { // phpcs:ignore Generic.Classes.OpeningBraceSameLine.ContentAfterBrace

		/**
		 * Table prefix.
		 *
		 * @var string
		 */
		public $prefix = 'wp_';

		/**
		 * Id assigned to the last insert.
		 *
		 * @var int
		 */
		public $insert_id = 0;
	}
}
