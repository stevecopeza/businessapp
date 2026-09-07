<?php
/**
 * Provisions a real WordPress on a real, empty MySQL database.
 *
 * @package BusinessApp
 */

declare( strict_types=1 );

namespace BusinessApp\Tests\Integration;

use RuntimeException;

/**
 * Builds a throwaway WordPress install and activates the plugin against it.
 *
 * WHY THIS EXISTS. The unit suite drives every repository through a wpdb double
 * that answers success to any write, so it proves the repositories SPEAK and never
 * that a database LISTENS. wp_businessapp_invoices has never had a CREATE TABLE in
 * any commit, and the mocked suite was green over that for seven months. Only a
 * real server can see it.
 *
 * WHY IT SHELLS OUT INSTEAD OF LOADING WORDPRESS IN-PROCESS. `composer test:all`
 * runs the Unit and Integration suites in ONE PHP process. The unit suite declares
 * a stand-in `wpdb` class and an ABSPATH of /tmp/ (tests/unit/Support/wp-shims.php),
 * which real WordPress cannot coexist with. Booting WordPress in a child process
 * keeps the two suites independent whichever order they run in.
 *
 * WHY IT FAILS RATHER THAN SKIPS WHEN THE SERVER IS MISSING. `composer test:integration`
 * used to exit 0 with zero tests — a gate that reports success without having looked.
 * A skip would restore exactly that. If the environment is not there, this suite is
 * red and the message says what to set.
 */
final class WordPressTestSite {

	/**
	 * Absolute path of the scratch WordPress root.
	 *
	 * @var string
	 */
	private string $root;

	/**
	 * Name of the throwaway database, dropped and recreated on every run.
	 *
	 * @var string
	 */
	private string $db_name;

	/**
	 * Constructor. Reads configuration from the environment, defaulting to MAMP.
	 */
	public function __construct() {
		$this->root    = self::env( 'BUSINESSAPP_TEST_WP_ROOT', sys_get_temp_dir() . '/businessapp-integration-wp' );
		$this->db_name = self::env( 'BUSINESSAPP_TEST_DB_NAME', 'businessapp_integration_test' );
	}

	/**
	 * Reads an environment variable, falling back to a default.
	 *
	 * @param string $name          Variable name.
	 * @param string $default_value Value when unset or empty.
	 * @return string The value.
	 */
	private static function env( string $name, string $default_value ): string {
		$value = getenv( $name );
		return ( false === $value || '' === $value ) ? $default_value : $value;
	}

	/**
	 * Drops the database, recreates it empty, installs WordPress and activates
	 * the plugin — which is what runs the plugin's schema code.
	 *
	 * @return void
	 * @throws RuntimeException When the database, wp-cli or WordPress core is unusable.
	 */
	public function provision(): void {
		$this->recreate_empty_database();
		$this->build_root();
		$this->wp_cli(
			array(
				'core',
				'install',
				'--url=http://businessapp.test',
				'--title=BusinessApp integration',
				'--admin_user=admin',
				'--admin_password=admin',
				'--admin_email=integration@businessapp.test',
				'--skip-email',
			)
		);
		$this->wp_cli( array( 'plugin', 'activate', 'businessapp' ) );
	}

	/**
	 * Runs a PHP script with the scratch WordPress loaded and returns its JSON output.
	 *
	 * @param string $script Absolute path of the script to run.
	 * @return array<string, mixed> The decoded document the script printed.
	 * @throws RuntimeException When the child fails or prints something undecodable.
	 */
	public function run_probe( string $script ): array {
		$command = escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( $script ) . ' ' . escapeshellarg( $this->root ) . ' 2>&1';

		$output = array();
		$status = 0;
		exec( $command, $output, $status );
		$text = implode( "\n", $output );

		if ( 0 !== $status ) {
			throw new RuntimeException( "Probe exited {$status}:\n{$text}" );
		}

		$start = strpos( $text, '{"' );
		if ( false === $start ) {
			throw new RuntimeException( "Probe printed no JSON document:\n{$text}" );
		}

		$decoded = json_decode( substr( $text, $start ), true );
		if ( ! is_array( $decoded ) ) {
			throw new RuntimeException( "Probe printed undecodable JSON:\n{$text}" );
		}

		return $decoded;
	}

	/**
	 * Drops and recreates the throwaway database so activation meets an EMPTY server.
	 *
	 * @return void
	 * @throws RuntimeException When the server is unreachable or refuses the statements.
	 */
	private function recreate_empty_database(): void {
		$host   = self::env( 'BUSINESSAPP_TEST_DB_HOST', 'localhost' );
		$user   = self::env( 'BUSINESSAPP_TEST_DB_USER', 'root' );
		$pass   = self::env( 'BUSINESSAPP_TEST_DB_PASSWORD', 'root' );
		$socket = self::env( 'BUSINESSAPP_TEST_DB_SOCKET', '/Applications/MAMP/tmp/mysql/mysql.sock' );

		$mysqli = @new \mysqli( $host, $user, $pass, '', 0, $socket ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		if ( $mysqli->connect_errno ) {
			throw new RuntimeException(
				"Cannot reach the test MySQL server ({$user}@{$host}, socket {$socket}): {$mysqli->connect_error}\n"
				. 'The integration suite needs a real server. Start MAMP, or set BUSINESSAPP_TEST_DB_HOST / '
				. '_USER / _PASSWORD / _SOCKET / _NAME. This suite fails rather than skips, because a skip '
				. 'is a green nobody earned.'
			);
		}

		foreach ( array( "DROP DATABASE IF EXISTS `{$this->db_name}`", "CREATE DATABASE `{$this->db_name}`" ) as $statement ) {
			if ( ! $mysqli->query( $statement ) ) {
				throw new RuntimeException( "Failed: {$statement} — {$mysqli->error}" );
			}
		}

		$mysqli->close();
	}

	/**
	 * Assembles the scratch WordPress root: core by symlink, plugin by symlink,
	 * and a wp-config.php pointing at the throwaway database.
	 *
	 * @return void
	 * @throws RuntimeException When WordPress core cannot be found.
	 */
	private function build_root(): void {
		$plugin_dir = dirname( __DIR__, 2 );
		$core       = self::env( 'BUSINESSAPP_TEST_WP_CORE', dirname( $plugin_dir, 3 ) );

		if ( ! is_file( $core . '/wp-includes/version.php' ) ) {
			throw new RuntimeException(
				"No WordPress core at {$core} (looked for wp-includes/version.php). "
				. 'Set BUSINESSAPP_TEST_WP_CORE to a WordPress root.'
			);
		}

		self::remove_tree( $this->root );
		mkdir( $this->root . '/wp-content/plugins', 0777, true );

		foreach ( glob( $core . '/*.php' ) as $file ) {
			if ( ! in_array( basename( $file ), array( 'wp-config.php', 'wp-config-sample.php' ), true ) ) {
				copy( $file, $this->root . '/' . basename( $file ) );
			}
		}

		symlink( $core . '/wp-admin', $this->root . '/wp-admin' );
		symlink( $core . '/wp-includes', $this->root . '/wp-includes' );
		symlink( $plugin_dir, $this->root . '/wp-content/plugins/' . basename( $plugin_dir ) );

		file_put_contents( $this->root . '/wp-config.php', $this->wp_config() );
	}

	/**
	 * Renders the scratch wp-config.php.
	 *
	 * @return string The file contents.
	 */
	private function wp_config(): string {
		$host   = self::env( 'BUSINESSAPP_TEST_DB_HOST', 'localhost' );
		$socket = self::env( 'BUSINESSAPP_TEST_DB_SOCKET', '/Applications/MAMP/tmp/mysql/mysql.sock' );
		$values = array(
			'DB_NAME'     => $this->db_name,
			'DB_USER'     => self::env( 'BUSINESSAPP_TEST_DB_USER', 'root' ),
			'DB_PASSWORD' => self::env( 'BUSINESSAPP_TEST_DB_PASSWORD', 'root' ),
			'DB_HOST'     => '' === $socket ? $host : $host . ':' . $socket,
			'DB_CHARSET'  => 'utf8mb4',
			'DB_COLLATE'  => '',
		);

		$lines = array( '<?php' );
		foreach ( $values as $name => $value ) {
			$lines[] = sprintf( "define( '%s', '%s' );", $name, addslashes( (string) $value ) );
		}
		foreach ( array( 'AUTH', 'SECURE_AUTH', 'LOGGED_IN', 'NONCE' ) as $salt ) {
			$lines[] = sprintf( "define( '%s_KEY', 'businessapp-integration' );", $salt );
			$lines[] = sprintf( "define( '%s_SALT', 'businessapp-integration' );", $salt );
		}
		$lines[] = "define( 'WP_DEBUG', false );";
		$lines[] = "define( 'WP_DEBUG_DISPLAY', false );";
		$lines[] = "define( 'AUTOMATIC_UPDATER_DISABLED', true );";
		$lines[] = "define( 'WP_HTTP_BLOCK_EXTERNAL', true );";
		$lines[] = "\$table_prefix = 'wp_';";
		$lines[] = "if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', __DIR__ . '/' ); }";
		$lines[] = "require_once ABSPATH . 'wp-settings.php';";

		return implode( "\n", $lines ) . "\n";
	}

	/**
	 * Runs wp-cli against the scratch root.
	 *
	 * @param array<int, string> $args Sub-command and options.
	 * @return void
	 * @throws RuntimeException When wp-cli is missing or the command fails.
	 */
	private function wp_cli( array $args ): void {
		$binary = self::env( 'BUSINESSAPP_TEST_WP_CLI', '/usr/local/bin/wp' );

		if ( ! is_file( $binary ) ) {
			throw new RuntimeException(
				"wp-cli not found at {$binary}. Install it, or set BUSINESSAPP_TEST_WP_CLI to its path."
			);
		}

		$command = escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( $binary )
			. ' --path=' . escapeshellarg( $this->root );
		foreach ( $args as $arg ) {
			$command .= ' ' . escapeshellarg( $arg );
		}
		$command .= ' 2>&1';

		$output = array();
		$status = 0;
		exec( $command, $output, $status );

		if ( 0 !== $status ) {
			throw new RuntimeException(
				'wp ' . implode( ' ', $args ) . " failed (exit {$status}):\n" . implode( "\n", $output )
			);
		}
	}

	/**
	 * Removes a directory tree, following no symlinks.
	 *
	 * @param string $path Directory to remove.
	 * @return void
	 */
	private static function remove_tree( string $path ): void {
		if ( ! is_dir( $path ) ) {
			return;
		}

		foreach ( scandir( $path ) as $entry ) {
			if ( '.' === $entry || '..' === $entry ) {
				continue;
			}
			$child = $path . '/' . $entry;
			if ( is_link( $child ) || is_file( $child ) ) {
				unlink( $child );
			} else {
				self::remove_tree( $child );
			}
		}

		rmdir( $path );
	}
}
