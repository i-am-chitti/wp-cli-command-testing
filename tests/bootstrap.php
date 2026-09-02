<?php
/**
 * PHPUnit bootstrap.
 *
 * Loads WP-CLI (as a plain Composer dependency) and WordPress's own test
 * suite, then boots the plugin exactly the way `muplugins_loaded` would on a
 * real site.
 *
 * @package WPCLITesting
 */

declare( strict_types=1 );

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// `vendor/autoload.php` only autoloads WP-CLI's *classes* (WP_CLI,
// WP_CLI_Command, WP_CLI\Loggers\Base, ...) via its classmap/PSR-0 config.
// WP_CLI\Utils\format_items() and friends are plain functions in this file,
// which Composer can't autoload — Stale_Drafts_Command needs it required
// directly. It's safe to load standalone: no side effects, no dependency on
// WP-CLI's Runner being started.
require_once dirname( __DIR__ ) . '/vendor/wp-cli/wp-cli/php/utils.php';

// WordPress's test bootstrap looks for this to bridge PHPUnit version differences.
putenv( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH=' . dirname( __DIR__ ) . '/vendor/yoast/phpunit-polyfills' );

$wp_tests_dir = getenv( 'WP_TESTS_DIR' ) ?: dirname( __DIR__ ) . '/vendor/wp-phpunit/wp-phpunit';

require $wp_tests_dir . '/includes/functions.php';

/**
 * Load the plugin under test.
 *
 * `WP_CLI_Command` and friends are already available at this point, because
 * `wp-cli/wp-cli` is loaded via `vendor/autoload.php` above, the same as any
 * other Composer dependency.
 *
 * @return void
 */
function _load_plugin_under_test(): void {
	require dirname( __DIR__ ) . '/plugin.php';
}
tests_add_filter( 'muplugins_loaded', '_load_plugin_under_test' );

require $wp_tests_dir . '/includes/bootstrap.php';
