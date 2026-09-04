<?php
/**
 * Provisions the local test database — deliberately without shelling out to
 * the `mysql` CLI. Some newer client builds (Homebrew's `mysql` 26.x, for
 * one) dropped the `mysql_native_password` plugin that `wp-cli/wp-cli-tests`'
 * bundled setup script requests, which makes it fail outright on machines
 * where it should just work. PHP's own mysqli talks to the server directly
 * and doesn't care which client binary happens to be on PATH.
 *
 * @package WPCLITesting
 */

declare( strict_types=1 );

require __DIR__ . '/../vendor/autoload.php';

if ( file_exists( __DIR__ . '/../.env' ) ) {
	$loaded = Dotenv\Dotenv::createImmutable( dirname( __DIR__ ) )->load();
	foreach ( $loaded as $key => $value ) {
		putenv( "{$key}={$value}" );
	}
}

/**
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

[ $host, $port ] = array_pad( explode( ':', env_or( 'WP_CLI_TEST_DBHOST', '127.0.0.1' ) ), 2, '3306' );

$root_user = env_or( 'WP_CLI_TEST_DBROOTUSER', 'root' );
$root_pass = env_or( 'WP_CLI_TEST_DBROOTPASS', '' );
$db_name   = env_or( 'WP_CLI_TEST_DBNAME', 'wp_cli_test' );
$db_user   = env_or( 'WP_CLI_TEST_DBUSER', 'wp_cli_test' );
$db_pass   = env_or( 'WP_CLI_TEST_DBPASS', 'password1' );

$mysqli = @new mysqli( $host, $root_user, $root_pass, '', (int) $port );

if ( $mysqli->connect_errno ) {
	fwrite( STDERR, "Could not connect to {$host}:{$port} as {$root_user}: {$mysqli->connect_error}\n" );
	exit( 1 );
}

$mysqli->query( "CREATE DATABASE IF NOT EXISTS `{$db_name}`" );

if ( $db_user !== $root_user ) {
	$mysqli->query( "CREATE USER IF NOT EXISTS '{$db_user}'@'%' IDENTIFIED BY '{$db_pass}'" );
	$mysqli->query( "GRANT ALL PRIVILEGES ON `{$db_name}`.* TO '{$db_user}'@'%'" );
	$mysqli->query( 'FLUSH PRIVILEGES' );
}

echo "Database '{$db_name}' ready on {$host}:{$port}.\n";
