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
 * A plain PHP exception, on purpose — lets `migrate()`'s halting behaviour
 * be tested without touching WP-CLI's exit machinery at all.
 */
class Mapping_Error extends RuntimeException {

}
