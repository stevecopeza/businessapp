<?php
/**
 * Raises the records whose PUBLIC pages the render test then fetches over HTTP.
 *
 * Invoked as a child process by WordPressTestSite::run_probe(); argv[1] is the
 * scratch WordPress root. It makes NO assertions — it gathers facts and prints one
 * JSON document, so every assertion stays in the PHPUnit process.
 *
 * WHY EACH RECORD CARRIES A LINE ITEM. The public renderers only touch an item
 * inside `foreach ( $items as $item )`. A record with no items never enters that
 * loop, so a suite that raises one cannot see anything wrong inside it, however
 * many assertions it makes. The line item IS the test.
 *
 * It reaches the repositories through the plugin's OWN private properties by
 * reflection, deliberately: constructing them here with table names typed into the
 * test would assert against names of the test's own invention rather than the names
 * the plugin actually uses.
 *
 * @package BusinessApp
 */

declare( strict_types=1 );

// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped

use BusinessApp\Domain\Quote;

$businessapp_root = $argv[1] ?? '';

define( 'WP_USE_THEMES', false );
require_once rtrim( $businessapp_root, '/' ) . '/wp-load.php';

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

$businessapp_report = array(
	'invoice_token'       => null,
	'invoice_description' => 'Replace the nearside sill',
	'invoice_error'       => null,
	'quote_token'         => null,
	'quote_description'   => 'Respray the tailgate',
	'quote_error'         => null,
);

try {
	$businessapp_invoice = businessapp_plugin_property( 'invoiceRepository' )->create(
		1,
		1,
		'Public page integration invoice',
		'',
		array(
			array(
				'description' => $businessapp_report['invoice_description'],
				'qty'         => 3,
				'unit'        => 'hr',
				'unit_price'  => 150.0,
				'type'        => 'labor',
			),
		)
	);

	$businessapp_report['invoice_token'] = null === $businessapp_invoice
		? null
		: $businessapp_invoice->getPublicToken();
} catch ( Throwable $businessapp_error ) {
	$businessapp_report['invoice_error'] = get_class( $businessapp_error ) . ': ' . $businessapp_error->getMessage();
}

try {
	$businessapp_quote = businessapp_plugin_property( 'quoteFactory' )->createDraft(
		1,
		'Public page integration quote',
		array(
			array(
				'description' => $businessapp_report['quote_description'],
				'qty'         => 2,
				'unit'        => 'hr',
				'unit_price'  => 90.0,
			),
		),
		'panel_beater',
		array(),
		'Thandi Nkosi'
	);

	// Minted here because the plugin mints a quote's token when the quote is SENT
	// (businessapp.php:1056), and a quote saved as a draft carries an empty one —
	// which findByPublicToken('') would match against every other draft.
	$businessapp_quote->setPublicToken( wp_generate_password( 32, false, false ) );

	$businessapp_quote_repository = businessapp_plugin_property( 'quoteRepository' );
	$businessapp_quote_repository->save( $businessapp_quote );

	$businessapp_report['quote_token'] = $businessapp_quote->getPublicToken();
} catch ( Throwable $businessapp_error ) {
	$businessapp_report['quote_error'] = get_class( $businessapp_error ) . ': ' . $businessapp_error->getMessage();
}

echo wp_json_encode( $businessapp_report );
