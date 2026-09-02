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
 * WP-CLI's real loggers extend `WP_CLI\Loggers\Base` and write with
 * `fwrite()` straight to `php://stdout` / `php://stderr`, which is why
 * `expectOutputString()` and `ob_start()` never see them. This one keeps
 * the messages in memory instead.
 *
 * Install it with `WP_CLI::set_logger( new Spy_Logger() )` before calling a
 * command, and reset it in `tearDown()` so it doesn't leak into the next
 * test.
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

	/**
	 * @param string $message Message to record.
	 * @param bool   $newline Unused; kept for interface compatibility.
	 *
	 * @return void
	 */
	public function info( $message, $newline = true ) {
		$this->messages['info'][] = $message;
	}

	/**
	 * @param string $message Message to record.
	 *
	 * @return void
	 */
	public function success( $message ) {
		$this->messages['success'][] = $message;
	}

	/**
	 * @param string $message Message to record.
	 *
	 * @return void
	 */
	public function warning( $message ) {
		$this->messages['warning'][] = $message;
	}

	/**
	 * @param string $message Message to record.
	 *
	 * @return void
	 */
	public function error( $message ) {
		$this->messages['error'][] = $message;
	}
}
