<?php
/**
 * Runs inside a real WordPress and reports what a real database did.
 *
 * Invoked as a child process by WordPressTestSite::run_probe(); argv[1] is the
 * scratch WordPress root. It makes NO assertions — it gathers facts and prints one
 * JSON document, so every assertion stays in the PHPUnit process where a missing
 * one is caught by beStrictAboutTestsThatDoNotTestAnything.
 *
 * It reaches the repositories through the plugin's OWN private properties by
 * reflection, deliberately. Constructing them here with table names typed into the
 * test is the defect this whole card is about: tests/unit_tests.php:318-319 passed
 * the literals 'wp_businessapp_invoices' and 'wp_businessapp_invoice_items', so it
 * asserted against names of its own invention rather than the names the plugin uses.
 *
 * @package BusinessApp
 */

declare( strict_types=1 );

// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped

$businessapp_root = $argv[1] ?? '';

define( 'WP_USE_THEMES', false );
require_once rtrim( $businessapp_root, '/' ) . '/wp-load.php';

global $wpdb;

$businessapp_report = array(
	'prefix'          => $wpdb->prefix,
	'plugin_tables'   => array(),
	'db_version'      => get_option( 'businessapp_db_version' ),
	// What the plugin DECLARES, so the control can assert that what was persisted
	// equals it rather than a literal typed into the test. A literal here would go
	// red on every legitimate schema bump, which is version-coupling, not a guard.
	'declared_db_version' => defined( 'BUSINESSAPP_DB_VERSION' ) ? BUSINESSAPP_DB_VERSION : null,
	'invoice_created' => null,
	'invoice_error'   => null,
	'invoice_rows'    => null,
	'item_rows'       => null,
);

foreach ( (array) $wpdb->get_col( "SHOW TABLES LIKE '{$wpdb->prefix}businessapp%'" ) as $businessapp_table ) {
	$businessapp_report['plugin_tables'][] = $businessapp_table;
}
sort( $businessapp_report['plugin_tables'] );

/**
 * Reads a private property off the live plugin instance.
 *
 * @param string $name Property name.
 * @return mixed The value.
 */
function businessapp_plugin_property( string $name ) {
	$plugin   = BusinessApp_Plugin::instance();
	$property = ( new ReflectionClass( $plugin ) )->getProperty( $name );
	$property->setAccessible( true );

	return $property->getValue( $plugin );
}

try {
	$businessapp_invoice_repository = businessapp_plugin_property( 'invoiceRepository' );

	$businessapp_invoice = $businessapp_invoice_repository->create(
		1,
		1,
		'Integration invoice',
		'Raised by the integration suite',
		array(
			array(
				'description' => 'Callout',
				'qty'         => 2,
				'unit'        => 'hr',
				'unit_price'  => 125.0,
				'type'        => 'labor',
			),
		)
	);

	$businessapp_report['invoice_created'] = null === $businessapp_invoice
		? null
		: array(
			'id'           => $businessapp_invoice->getId(),
			// Rendered to 2dp as a STRING on purpose: JSON collapses a whole float
			// to an integer, which would soften an exact money assertion into a
			// loose one on the far side of the process boundary.
			'total_amount' => sprintf( '%.2f', (float) $businessapp_invoice->getTotalAmount() ),
			'status'       => $businessapp_invoice->getStatus(),
			'items'        => count( $businessapp_invoice->getLineItems() ),
		);
} catch ( Throwable $businessapp_error ) {
	$businessapp_report['invoice_error'] = get_class( $businessapp_error ) . ': ' . $businessapp_error->getMessage();
}

$businessapp_report['invoice_rows'] = businessapp_count_rows( $wpdb, $wpdb->prefix . 'businessapp_invoices' );
$businessapp_report['item_rows']    = businessapp_count_rows( $wpdb, $wpdb->prefix . 'businessapp_invoice_items' );

/**
 * Counts rows in a table, or reports null when the table is not there.
 *
 * @param wpdb   $db    The live database handle.
 * @param string $table Fully prefixed table name.
 * @return int|null Row count, or null when the table does not exist.
 */
function businessapp_count_rows( $db, string $table ): ?int {
	$suppressed = $db->suppress_errors( true );
	$count      = $db->get_var( "SELECT COUNT(*) FROM {$table}" );
	$db->suppress_errors( $suppressed );

	return null === $count ? null : (int) $count;
}

echo wp_json_encode( $businessapp_report );
