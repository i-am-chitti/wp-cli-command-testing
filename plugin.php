<?php
/**
 * Plugin Name: WP-CLI Command Testing Examples
 * Description: Example WP-CLI commands, written to accompany a blog post on testing WP-CLI commands. Not for production use.
 * Version: 1.0.0
 * License: MIT
 *
 * @package WPCLITesting
 */

declare( strict_types=1 );

namespace WPCLITesting;

use WP_CLI;
use WPCLITesting\Commands\Migrate_Authors_Command;
use WPCLITesting\Commands\Stale_Drafts_Command;

require_once __DIR__ . '/vendor/autoload.php';

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command( 'migrate-authors', Migrate_Authors_Command::class );
	WP_CLI::add_command( 'stale-drafts', Stale_Drafts_Command::class );
}
