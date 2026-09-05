<?php
/**
 * Reassigns post authorship from a legacy numeric author ID to a WordPress user.
 *
 * @package WPCLITesting
 */

declare( strict_types=1 );

namespace WPCLITesting\Commands;

use WP_CLI;
use WP_CLI_Command;
use WPCLITesting\Migration\Mapping_Error;

/**
 * Class Migrate_Authors_Command.
 */
class Migrate_Authors_Command extends WP_CLI_Command {

	/**
	 * Reassign posts to their mapped WordPress author.
	 *
	 * Stops at the first post with no mapping — posts already touched stay touched.
	 *
	 * ## OPTIONS
	 *
	 * --map=<file>
	 * : Path to a CSV file: legacy_author_id,wp_user_id.
	 *
	 * ## EXAMPLES
	 *
	 *     wp migrate-authors run --map=author-mapping.csv
	 *
	 * @subcommand run
	 *
	 * @param array{} $args Positional arguments (unused).
	 * @param array{map?: string} $assoc_args Associative arguments.
	 *
	 * @return void
	 */
	public function run( array $args, array $assoc_args ): void {
		$mapping = $this->load_mapping( (string) ( $assoc_args['map'] ?? '' ) );

		// Plain method call, not runcommand() — same package, so the
		// indirection would buy nothing and cost testability.
		$invalid = $this->find_invalid_mapping( $mapping );

		if ( null !== $invalid ) {
			WP_CLI::error( $invalid );
			return;
		}

		try {
			$migrated = $this->migrate( $mapping );
		} catch ( Mapping_Error $error ) {
			WP_CLI::error( $error->getMessage() );
			return;
		}

		WP_CLI::success( "{$migrated} posts migrated." );
	}

	/**
	 * Check a mapping file for WordPress user IDs that don't exist.
	 *
	 * ## OPTIONS
	 *
	 * --map=<file>
	 * : Path to the mapping file.
	 *
	 * ## EXAMPLES
	 *
	 *     wp migrate-authors verify-mapping --map=author-mapping.csv
	 *
	 * @subcommand verify-mapping
	 *
	 * @param array{} $args Positional arguments (unused).
	 * @param array{map?: string} $assoc_args Associative arguments.
	 *
	 * @return void
	 */
	public function verify_mapping( array $args, array $assoc_args ): void {
		$mapping = $this->load_mapping( (string) ( $assoc_args['map'] ?? '' ) );

		$invalid = $this->find_invalid_mapping( $mapping );

		if ( null !== $invalid ) {
			WP_CLI::error( $invalid );
			return;
		}

		WP_CLI::success( count( $mapping ) . ' mappings verified.' );
	}

	/**
	 * Find the first mapping entry pointing at a WordPress user that doesn't exist.
	 *
	 * Returns a message rather than calling WP_CLI::error(), so `verify-mapping`
	 * and `run`'s pre-flight check can each report it their own way.
	 *
	 * @param array<string, int> $mapping Legacy author ID => WordPress user ID.
	 *
	 * @return string|null Error message, or null when every mapped user exists.
	 */
	private function find_invalid_mapping( array $mapping ): ?string {
		foreach ( $mapping as $legacy_id => $user_id ) {
			if ( ! get_userdata( $user_id ) ) {
				return "Mapping references a WordPress user that doesn't exist: {$user_id} (legacy ID {$legacy_id})";
			}
		}

		return null;
	}

	/**
	 * Walk every migratable post and reassign its author.
	 *
	 * Never touches `WP_CLI::` — throws a plain exception instead, so it's
	 * testable directly with no WP-CLI runtime involved.
	 *
	 * @param array<string, int> $mapping Legacy author ID => WordPress user ID.
	 *
	 * @throws Mapping_Error When a post's legacy author ID has no mapping.
	 *
	 * @return int Number of posts migrated.
	 */
	public function migrate( array $mapping ): int {
		$post_ids = get_posts(
			[
				'post_type'      => 'post',
				'fields'         => 'ids',
				'posts_per_page' => -1,
				// Deterministic order — the "stops before touching the rest" test depends on it.
				'orderby'        => 'ID',
				'order'          => 'ASC',
			]
		);

		$migrated = 0;

		foreach ( $post_ids as $post_id ) {
			$legacy_id = get_post_meta( $post_id, '_legacy_author_id', true );

			if ( '' === $legacy_id ) {
				continue;
			}

			if ( ! isset( $mapping[ $legacy_id ] ) ) {
				throw new Mapping_Error( "No mapping for legacy author ID {$legacy_id} (post {$post_id})" );
			}

			wp_update_post(
				[
					'ID'          => $post_id,
					'post_author' => $mapping[ $legacy_id ],
				]
			);
			update_post_meta( $post_id, '_migrated', '1' );

			++$migrated;
		}

		return $migrated;
	}

	/**
	 * Load a legacy-author-id -> WordPress-user-id mapping.
	 *
	 * @param string $path Path to the mapping file.
	 *
	 * @return array<string, int>
	 */
	private function load_mapping( string $path ): array {
		if ( '' === $path || ! is_readable( $path ) ) {
			WP_CLI::error( "Mapping file not found: {$path}" );
			return [];
		}

		$handle = fopen( $path, 'r' );

		if ( false === $handle ) {
			WP_CLI::error( "Could not open mapping file: {$path}" );
			return [];
		}

		$mapping = [];

		// $escape is passed explicitly: its default is deprecated as of PHP 8.4
		// and changes in PHP 9. '' is the RFC 4180 behaviour — no escape
		// character — which is what a mapping file should be parsed with.
		while ( false !== ( $row = fgetcsv( $handle, null, ',', '"', '' ) ) ) {
			if ( 2 !== count( $row ) ) {
				continue;
			}

			[ $legacy_id, $user_id ] = $row;

			$mapping[ trim( (string) $legacy_id ) ] = (int) trim( (string) $user_id );
		}

		fclose( $handle );

		return $mapping;
	}
}
