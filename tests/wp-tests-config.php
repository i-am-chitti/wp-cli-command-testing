<?php
/**
 * WordPress test-suite config, consumed via WP_PHPUNIT__TESTS_CONFIG.
 *
 * @package WPCLITesting
 */

define( 'DB_NAME', getenv( 'WP_CLI_TEST_DBNAME' ) ?: 'wp_cli_test' );
define( 'DB_USER', getenv( 'WP_CLI_TEST_DBUSER' ) ?: 'wp_cli_test' );
define( 'DB_PASSWORD', getenv( 'WP_CLI_TEST_DBPASS' ) ?: 'password1' );
define( 'DB_HOST', getenv( 'WP_CLI_TEST_DBHOST' ) ?: 'localhost' );
define( 'DB_CHARSET', 'utf8' );
define( 'DB_COLLATE', '' );

define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'Test Blog' );

define( 'WP_PHP_BINARY', 'php' );
define( 'WPLANG', '' );

define( 'ABSPATH', dirname( __DIR__ ) . '/wordpress/' );
