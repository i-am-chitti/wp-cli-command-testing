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
	 * Reads a legacy-author-id -> WordPress-user-id mapping from a CSV file,
	 * then walks every post that still carries a `_legacy_author_id` meta
	 * value and updates `post_author` to match. Stops the moment it finds a
	 * legacy ID that has no entry in the mapping — posts it has already
	 * touched stay touched.
	 *
	 * ## OPTIONS
	 *
	 * --map=<file>
	 * : Path to a two-column CSV file: legacy_author_id,wp_user_id.
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
	 * : Path to the mapping CSV file.
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

		foreach ( $mapping as $legacy_id => $user_id ) {
			if ( ! get_userdata( $user_id ) ) {
				WP_CLI::error( "Mapping references a WordPress user that doesn't exist: {$user_id} (legacy ID {$legacy_id})" );
				return;
			}
		}

		WP_CLI::success( count( $mapping ) . ' mappings verified.' );
	}

	/**
	 * Walk every migratable post and reassign its author.
	 *
	 * This is the one method in the class that never touches `WP_CLI::`.
	 * It throws a plain `Mapping_Error` instead of calling `WP_CLI::error()`,
	 * which is what lets a PHPUnit test call it directly and assert on the
	 * exception and on the database, with no WP-CLI runtime involved at all.
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
				// Deterministic order matters here: the whole point of the
				// "stops before touching the rest" test is knowing exactly
				// which posts came before the failure and which came after.
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
	 * Load a legacy-author-id -> WordPress-user-id mapping from a CSV file.
	 *
	 * @param string $path Path to the mapping CSV file.
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

		while ( false !== ( $row = fgetcsv( $handle ) ) ) {
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
