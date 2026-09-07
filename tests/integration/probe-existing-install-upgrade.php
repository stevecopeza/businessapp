<?php
/**
 * Proves the schema change reaches an install that already ran an older version.
 *
 * WHY THIS EXISTS SEPARATELY FROM THE LIFECYCLE PROBE. There is no
 * register_activation_hook in this plugin — `grep -c register_activation_hook
 * businessapp.php` is 0, against 14 add_action calls as a positive control. The schema
 * runs from the CONSTRUCTOR, via maybe_upgrade_schema(), which returns EARLY when the
 * stored businessapp_db_version already equals BUSINESSAPP_DB_VERSION. So on every
 * install that has ever run this plugin — Steve's included — adding a CREATE TABLE
 * changes nothing at all unless the constant is also bumped.
 *
 * The lifecycle probe cannot see that: it meets an EMPTY database, where the option is
 * absent and create_or_update_tables() runs whatever the constant says. Only an install
 * already stamped with an older version can tell the two halves of the fix apart.
 *
 * So this probe manufactures exactly that install: it drops the invoice tables, winds
 * businessapp_db_version back to the previous value, and boots WordPress again in a
 * FRESH process so the constructor re-runs. If anyone ever removes the version bump,
 * this goes red and the lifecycle probe stays green.
 *
 * Invoked as a child process by WordPressTestSite::run_probe(); argv[1] is the scratch
 * WordPress root. It makes no assertions — it prints one JSON document.
 *
 * @package BusinessApp
 */

declare( strict_types=1 );

// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped

$businessapp_root = rtrim( $argv[1] ?? '', '/' );

/**
 * Boots WordPress in a child process and returns that child's JSON document.
 *
 * A fresh process is the point: maybe_upgrade_schema() runs once, from the plugin
 * constructor, so the upgrade cannot be re-observed inside a process that already
 * loaded the plugin.
 *
 * @param string $root  Scratch WordPress root.
 * @param string $php   PHP fragment to run after wp-load.php, which must echo JSON.
 * @return array<string, mixed> The decoded document.
 * @throws RuntimeException When the child fails or prints something undecodable.
 */
function businessapp_in_fresh_wordpress( string $root, string $php ): array {
	$script = sys_get_temp_dir() . '/businessapp-probe-' . bin2hex( random_bytes( 8 ) ) . '.php';

	file_put_contents(
		$script,
		"<?php\ndefine( 'WP_USE_THEMES', false );\nrequire_once " . var_export( $root . '/wp-load.php', true ) . ";\n" . $php
	);

	$output = array();
	$status = 0;
	exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( $script ) . ' 2>&1', $output, $status );
	unlink( $script );

	$text = implode( "\n", $output );

	if ( 0 !== $status ) {
		throw new RuntimeException( "Child WordPress exited {$status}:\n{$text}" );
	}

	$start = strpos( $text, '{"' );
	if ( false === $start ) {
		throw new RuntimeException( "Child WordPress printed no JSON document:\n{$text}" );
	}

	$decoded = json_decode( substr( $text, $start ), true );
	if ( ! is_array( $decoded ) ) {
		throw new RuntimeException( "Child WordPress printed undecodable JSON:\n{$text}" );
	}

	return $decoded;
}

$businessapp_read_state = <<<'PHP'
global $wpdb;
$businessapp_tables = array();
foreach ( (array) $wpdb->get_col( "SHOW TABLES LIKE '{$wpdb->prefix}businessapp%'" ) as $businessapp_table ) {
	$businessapp_tables[] = $businessapp_table;
}
sort( $businessapp_tables );
echo wp_json_encode(
	array(
		'tables'       => $businessapp_tables,
		'db_version'   => get_option( 'businessapp_db_version' ),
		'declared'     => defined( 'BUSINESSAPP_DB_VERSION' ) ? BUSINESSAPP_DB_VERSION : null,
	)
);
PHP;

// 1. What a current install looks like, after the ordinary activation the harness did.
$businessapp_before = businessapp_in_fresh_wordpress( $businessapp_root, $businessapp_read_state );

// 2. Wind it back into an install that last ran the PREVIOUS schema version: drop the two
//    tables this card adds and re-stamp the option. Nothing else is touched, so the other
//    eight tables stay exactly as they were — this is an upgrade, not a reinstall.
$businessapp_wind_back = <<<'PHP'
global $wpdb;
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}businessapp_invoice_items" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}businessapp_invoices" );
update_option( 'businessapp_db_version', '10' );
$businessapp_tables = array();
foreach ( (array) $wpdb->get_col( "SHOW TABLES LIKE '{$wpdb->prefix}businessapp%'" ) as $businessapp_table ) {
	$businessapp_tables[] = $businessapp_table;
}
sort( $businessapp_tables );
echo wp_json_encode(
	array(
		'tables'     => $businessapp_tables,
		'db_version' => get_option( 'businessapp_db_version' ),
	)
);
PHP;

$businessapp_wound_back = businessapp_in_fresh_wordpress( $businessapp_root, $businessapp_wind_back );

// 3. Load WordPress once more. Nothing is asked of the plugin beyond existing: the
//    constructor alone must notice the stale version and put the tables back.
$businessapp_after = businessapp_in_fresh_wordpress( $businessapp_root, $businessapp_read_state );

echo json_encode(
	array(
		'declared_version'    => $businessapp_before['declared'],
		'tables_before'       => $businessapp_before['tables'],
		'version_before'      => $businessapp_before['db_version'],
		'tables_wound_back'   => $businessapp_wound_back['tables'],
		'version_wound_back'  => $businessapp_wound_back['db_version'],
		'tables_after'        => $businessapp_after['tables'],
		'version_after'       => $businessapp_after['db_version'],
	)
);
