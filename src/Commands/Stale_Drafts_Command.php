<?php
/**
 * Reports on drafts that have sat untouched past a given age.
 *
 * @package WPCLITesting
 */

declare( strict_types=1 );

namespace WPCLITesting\Commands;

use WP_CLI;
use WP_CLI_Command;
use WP_CLI\Utils;
use WP_Post;

/**
 * Class Stale_Drafts_Command.
 *
 * Exists to demonstrate the one output path that PHPUnit's `ob_start()`
 * *can* see: `--format=json/csv` goes through plain `echo`, unlike the
 * success/log/error messages `Migrate_Authors_Command` uses.
 */
class Stale_Drafts_Command extends WP_CLI_Command {

	/**
	 * List drafts that haven't been touched in a while.
	 *
	 * ## OPTIONS
	 *
	 * [--days=<days>]
	 * : Minimum age, in days, since the post was last modified. Default: 30.
	 *
	 * [--format=<format>]
	 * : table, json, or csv. Default: table.
	 *
	 * ## EXAMPLES
	 *
	 *     wp stale-drafts list --days=60 --format=json
	 *
	 * @subcommand list
	 *
	 * @param array{} $args Positional arguments (unused).
	 * @param array{days?: string, format?: string} $assoc_args Associative arguments.
	 *
	 * @return void
	 */
	public function report( array $args, array $assoc_args ): void {
		$days   = (int) ( $assoc_args['days'] ?? 30 );
		$drafts = $this->find_stale_drafts( $days );

		if ( [] === $drafts ) {
			WP_CLI::success( "No drafts older than {$days} days." );
			return;
		}

		Utils\format_items(
			(string) ( $assoc_args['format'] ?? 'table' ),
			$drafts,
			[ 'ID', 'post_title', 'days_stale' ]
		);
	}

	/**
	 * Find drafts whose last modification is older than the given number of days.
	 *
	 * @param int $days Minimum age, in days.
	 *
	 * @return array<int, array{ID: int, post_title: string, days_stale: int}>
	 */
	public function find_stale_drafts( int $days ): array {
		$post_ids = get_posts(
			[
				'post_type'      => 'post',
				'post_status'    => 'draft',
				'fields'         => 'ids',
				'posts_per_page' => -1,
				'date_query'     => [
					[
						'column' => 'post_modified_gmt',
						'before' => "{$days} days ago",
					],
				],
			]
		);

		return array_values(
			array_filter(
				array_map( [ $this, 'to_row' ], $post_ids )
			)
		);
	}

	/**
	 * Turn a post ID into a report row.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return array{ID: int, post_title: string, days_stale: int}|null
	 */
	private function to_row( int $post_id ): ?array {
		$post = get_post( $post_id );

		if ( ! $post instanceof WP_Post ) {
			return null;
		}

		$modified_timestamp = strtotime( $post->post_modified_gmt . ' GMT' );

		return [
			'ID'         => $post_id,
			'post_title' => $post->post_title,
			'days_stale' => false !== $modified_timestamp
				? (int) floor( ( time() - $modified_timestamp ) / DAY_IN_SECONDS )
				: 0,
		];
	}
}
