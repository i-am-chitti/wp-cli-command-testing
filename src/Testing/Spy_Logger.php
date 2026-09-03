<?php
/**
 * A WP-CLI logger that records messages instead of writing them to STDOUT,
 * so tests can make assertions on them.
 *
 * @package WPCLITesting
 */

declare( strict_types=1 );

namespace WPCLITesting\Testing;

use WP_CLI\Loggers\Base;

/**
 * Class Spy_Logger.
 *
 * WP-CLI's real loggers write with `fwrite()` straight to `php://stdout`,
 * which is why `expectOutputString()`/`ob_start()` never see them. Install
 * this one with `WP_CLI::set_logger()` before calling a command, and reset
 * it in `tearDown()`.
 */
class Spy_Logger extends Base {

	/**
	 * Captured messages, grouped by level.
	 *
	 * @var array<string, string[]>
	 */
	public array $messages = [
		'info'    => [],
		'success' => [],
		'warning' => [],
		'error'   => [],
	];

	public function info( $message, $newline = true ) {
		$this->messages['info'][] = $message;
	}

	public function success( $message ) {
		$this->messages['success'][] = $message;
	}

	public function warning( $message ) {
		$this->messages['warning'][] = $message;
	}

	public function error( $message ) {
		$this->messages['error'][] = $message;
	}
}
