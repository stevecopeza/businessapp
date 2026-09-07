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
	 * Loopback port the scratch site is served on. Chosen in the constructor rather
	 * than at start-up because wp-config.php has to name it, and that file is written
	 * during build_root().
	 *
	 * @var int
	 */
	private int $port;

	/**
	 * The `php -S` child serving the scratch site, or null while none is running.
	 *
	 * @var resource|null
	 */
	private $server;

	/**
	 * Absolute path of the web server's stderr log. A PHP fatal inside a page never
	 * reaches the response body — wp_debug_mode() turns display_errors off whenever
	 * WP_DEBUG is false — so this file is the only place a mid-page death is legible.
	 *
	 * @var string
	 */
	private string $server_log;

	/**
	 * Constructor. Reads configuration from the environment, defaulting to MAMP.
	 */
	public function __construct() {
		$this->root       = self::env( 'BUSINESSAPP_TEST_WP_ROOT', sys_get_temp_dir() . '/businessapp-integration-wp' );
		$this->db_name    = self::env( 'BUSINESSAPP_TEST_DB_NAME', 'businessapp_integration_test' );
		$this->port       = (int) self::env( 'BUSINESSAPP_TEST_PORT', (string) self::free_port() );
		$this->server_log = sys_get_temp_dir() . '/businessapp-integration-server.log';
	}

	/**
	 * Asks the operating system for a port nobody is listening on.
	 *
	 * @return int A port number.
	 * @throws RuntimeException When no loopback port can be obtained.
	 */
	private static function free_port(): int {
		$socket = @stream_socket_server( 'tcp://127.0.0.1:0', $errno, $errstr ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( false === $socket ) {
			throw new RuntimeException( "Cannot reserve a loopback port: {$errstr} ({$errno})" );
		}

		$name = stream_socket_get_name( $socket, false );
		fclose( $socket );

		return (int) substr( (string) $name, strrpos( (string) $name, ':' ) + 1 );
	}

	/**
	 * The base URL the scratch site answers on.
	 *
	 * @return string An absolute http:// URL with no trailing slash.
	 */
	public function base_url(): string {
		return 'http://127.0.0.1:' . $this->port;
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
				'--url=' . $this->base_url(),
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
	 * Serves the scratch WordPress over HTTP on the loopback port.
	 *
	 * WHY A REAL SERVER. A public invoice page is a document a signed-out customer
	 * receives over HTTP. Rendering it in-process — setting $_GET and calling the
	 * handler — proves the handler emits markup; it cannot produce a status line, and
	 * a PHP fatal would abort the PHPUnit process instead of truncating a response.
	 * The defect this suite exists to catch is precisely a page that starts, returns
	 * 200, and then dies. Only a request over a socket can observe that shape.
	 *
	 * @return void
	 * @throws RuntimeException When the server does not start answering.
	 */
	public function start_web_server(): void {
		if ( null !== $this->server ) {
			return;
		}

		file_put_contents( $this->server_log, '' );

		$descriptors = array(
			0 => array( 'file', '/dev/null', 'r' ),
			1 => array( 'file', $this->server_log, 'a' ),
			2 => array( 'file', $this->server_log, 'a' ),
		);

		$command = escapeshellarg( PHP_BINARY )
			. ' -S 127.0.0.1:' . $this->port
			. ' -t ' . escapeshellarg( $this->root );

		$pipes        = array();
		$this->server = proc_open( $command, $descriptors, $pipes );

		if ( ! is_resource( $this->server ) ) {
			$this->server = null;
			throw new RuntimeException( "Could not start a web server: {$command}" );
		}

		for ( $attempt = 0; $attempt < 100; $attempt++ ) {
			$probe = @fsockopen( '127.0.0.1', $this->port, $errno, $errstr, 0.2 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			if ( false !== $probe ) {
				fclose( $probe );
				return;
			}
			usleep( 100000 );
		}

		$this->stop_web_server();
		throw new RuntimeException(
			"The web server never answered on {$this->base_url()} after 10s.\n"
			. $this->server_output()
		);
	}

	/**
	 * Stops the web server if one is running.
	 *
	 * @return void
	 */
	public function stop_web_server(): void {
		if ( null === $this->server ) {
			return;
		}

		proc_terminate( $this->server );
		proc_close( $this->server );
		$this->server = null;
	}

	/**
	 * Fetches a path from the scratch site.
	 *
	 * Errors are NOT treated as failures of the fetch: a 404 or a 500 is an
	 * observation this suite needs to be able to assert on, so both the status and
	 * whatever body arrived are returned.
	 *
	 * @param string $path Path and query string, beginning with a slash.
	 * @return array{status:int, body:string} What the server answered.
	 * @throws RuntimeException When no server is running or the socket fails outright.
	 */
	public function get( string $path ): array {
		if ( null === $this->server ) {
			throw new RuntimeException( 'start_web_server() must be called before get().' );
		}

		$context = stream_context_create(
			array(
				'http' => array(
					'method'        => 'GET',
					'ignore_errors' => true,
					'timeout'       => 30,
					'header'        => "Accept: text/html\r\n",
				),
			)
		);

		$body = @file_get_contents( $this->base_url() . $path, false, $context ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		if ( false === $body ) {
			throw new RuntimeException(
				"GET {$path} did not complete.\n" . $this->server_output()
			);
		}

		$status = 0;
		foreach ( ( $http_response_header ?? array() ) as $line ) {
			if ( 1 === preg_match( '#^HTTP/\S+\s+(\d{3})#', $line, $matches ) ) {
				$status = (int) $matches[1];
			}
		}

		return array(
			'status' => $status,
			'body'   => (string) $body,
		);
	}

	/**
	 * Everything the web server has written to its log so far.
	 *
	 * This is where a mid-page PHP fatal is legible; the response body never carries
	 * one, because WordPress turns display_errors off when WP_DEBUG is false.
	 *
	 * @return string The log contents, labelled.
	 */
	public function server_output(): string {
		$text = is_file( $this->server_log ) ? (string) file_get_contents( $this->server_log ) : '';

		return "--- web server log ({$this->server_log}) ---\n" . $text;
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
		// Defined rather than left to the options table so redirect_canonical cannot
		// bounce a request off the loopback port before template_redirect runs.
		$lines[] = sprintf( "define( 'WP_HOME', '%s' );", $this->base_url() );
		$lines[] = sprintf( "define( 'WP_SITEURL', '%s' );", $this->base_url() );
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
