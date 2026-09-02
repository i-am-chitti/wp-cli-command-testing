<?php
/**
 * Thrown when a post's legacy author ID has no entry in the mapping file.
 *
 * @package WPCLITesting
 */

declare( strict_types=1 );

namespace WPCLITesting\Migration;

use RuntimeException;

/**
 * A plain PHP exception, on purpose.
 *
 * `Migrate_Authors_Command::migrate()` throws this instead of calling
 * `WP_CLI::error()` directly, so the halting behaviour can be asserted on
 * from a plain PHPUnit test without touching WP-CLI's exit machinery at all.
 */
class Mapping_Error extends RuntimeException {

}
