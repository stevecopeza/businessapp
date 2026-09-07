<?php
/**
 * PHPUnit bootstrap for the BusinessApp unit suite.
 *
 * Deliberately loads NOTHING but the Composer autoloader. businessapp.php carries
 * 23 require_once lines that would resolve every class regardless of whether the
 * PSR-4 map is correct — so a suite that loaded the plugin could not tell a working
 * autoloader from a dead one. Loading only the autoloader makes tests/unit/AutoloadTest.php
 * a real check of the map rather than decoration.
 *
 * @package BusinessApp
 */

declare( strict_types=1 );

$businessapp_autoload = __DIR__ . '/../vendor/autoload.php';

if ( ! is_file( $businessapp_autoload ) ) {
	fwrite( STDERR, "Composer dependencies are not installed. Run: composer install\n" );
	exit( 1 );
}

require_once $businessapp_autoload;
