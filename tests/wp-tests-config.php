<?php
/**
 * WordPress test-suite config, consumed via WP_PHPUNIT__TESTS_CONFIG.
 *
 * @package WPCLITesting
 */

/**
 * Read an env var from wherever it actually landed.
 *
 * `getenv()` only sees real process env vars (shell `export`, or CI setting
 * them on the job). `vlucas/phpdotenv` (loaded in tests/bootstrap.php from
 * .env) populates $_ENV instead of calling putenv() by default — so without
 * this, a value that's genuinely set in .env still reads back as missing.
 *
 * @param string $name    Env var name.
 * @param string $default Fallback if it's not set anywhere.
 *
 * @return string
 */
function env_or( string $name, string $default ): string {
	$value = getenv( $name );
	if ( false !== $value ) {
		return $value;
	}

	return (string) ( $_ENV[ $name ] ?? $_SERVER[ $name ] ?? $default );
}

define( 'DB_NAME', env_or( 'WP_CLI_TEST_DBNAME', 'wp_cli_test' ) );
define( 'DB_USER', env_or( 'WP_CLI_TEST_DBUSER', 'wp_cli_test' ) );
define( 'DB_PASSWORD', env_or( 'WP_CLI_TEST_DBPASS', 'password1' ) );
define( 'DB_HOST', env_or( 'WP_CLI_TEST_DBHOST', 'localhost' ) );
define( 'DB_CHARSET', 'utf8' );
define( 'DB_COLLATE', '' );

define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'Test Blog' );

define( 'WP_PHP_BINARY', 'php' );
define( 'WPLANG', '' );

define( 'ABSPATH', dirname( __DIR__ ) . '/wordpress/' );
