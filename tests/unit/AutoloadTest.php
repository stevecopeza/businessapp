<?php

declare( strict_types=1 );

namespace BusinessApp\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Guards the PSR-4 map in composer.json.
 *
 * This test exists because the autoloader can be completely dead and every other
 * test still passes: businessapp.php carries 23 require_once lines, so a wrong map
 * ("src/" instead of "includes/") produces a green suite and no error anywhere.
 *
 * tests/bootstrap.php therefore loads ONLY vendor/autoload.php — never the plugin —
 * so the only way a BusinessApp class can resolve here is through PSR-4. Break the
 * map and this file goes red.
 */
final class AutoloadTest extends TestCase {

	/**
	 * Every class under includes/, as of the scaffold. Mirrors the 23 require_once
	 * lines at the top of businessapp.php.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function classProvider(): array {
		$classes = array(
			'BusinessApp\\Domain\\BusinessType',
			'BusinessApp\\Domain\\BusinessTypeRegistry',
			'BusinessApp\\Domain\\Customer',
			'BusinessApp\\Domain\\CustomerEntity',
			'BusinessApp\\Domain\\FieldDefinition',
			'BusinessApp\\Domain\\Invoice',
			'BusinessApp\\Domain\\InvoiceItem',
			'BusinessApp\\Domain\\Job',
			'BusinessApp\\Domain\\JobFactory',
			'BusinessApp\\Domain\\JobItem',
			'BusinessApp\\Domain\\Payment',
			'BusinessApp\\Domain\\Quote',
			'BusinessApp\\Domain\\QuoteFactory',
			'BusinessApp\\Domain\\QuoteItem',
			'BusinessApp\\Infrastructure\\CustomerEntityRepository',
			'BusinessApp\\Infrastructure\\CustomerRepository',
			'BusinessApp\\Infrastructure\\InvoiceItemRepository',
			'BusinessApp\\Infrastructure\\InvoiceRepository',
			'BusinessApp\\Infrastructure\\JobItemRepository',
			'BusinessApp\\Infrastructure\\JobRepository',
			'BusinessApp\\Infrastructure\\PaymentRepository',
			'BusinessApp\\Infrastructure\\QuoteItemRepository',
			'BusinessApp\\Infrastructure\\QuoteRepository',
		);

		$cases = array();
		foreach ( $classes as $class ) {
			$cases[ $class ] = array( $class );
		}

		return $cases;
	}

	/**
	 * @dataProvider classProvider
	 *
	 * @param string $class Fully-qualified class name.
	 */
	public function test_class_resolves_through_the_psr4_map( string $class ): void {
		$this->assertTrue(
			class_exists( $class ),
			sprintf( '%s did not resolve. The PSR-4 map in composer.json is wrong.', $class )
		);

		$file = ( new ReflectionClass( $class ) )->getFileName();

		$this->assertIsString( $file );
		$this->assertStringEndsWith(
			'/includes/' . str_replace( '\\', '/', substr( $class, strlen( 'BusinessApp\\' ) ) ) . '.php',
			$file,
			sprintf( '%s resolved to an unexpected file: %s', $class, (string) $file )
		);
	}

	/**
	 * The plugin bootstrap must not be what makes these classes available. If this
	 * test ever passes only because businessapp.php was loaded, the guard above is
	 * decoration.
	 */
	public function test_the_plugin_bootstrap_is_not_loaded(): void {
		$this->assertNotContains(
			realpath( __DIR__ . '/../../businessapp.php' ),
			array_map( 'realpath', get_included_files() ),
			'tests/bootstrap.php must load only the Composer autoloader.'
		);
	}
}
