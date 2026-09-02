<?php
/**
 * Tests for Stale_Drafts_Command.
 *
 * @package WPCLITesting
 */

declare( strict_types=1 );

namespace WPCLITesting\Tests\Unit;

use WP_UnitTestCase;
use WPCLITesting\Commands\Stale_Drafts_Command;

/**
 * @covers \WPCLITesting\Commands\Stale_Drafts_Command
 */
class Stale_Drafts_Command_Test extends WP_UnitTestCase {

	/**
	 * Back-date a draft's last-modified timestamp, the same way a post
	 * that's genuinely been sitting untouched would look.
	 *
	 * @param int $days_ago How many days in the past to set post_modified to.
	 *
	 * @return int Post ID.
	 */
	private function create_draft_last_modified_days_ago( int $days_ago ): int {
		global $wpdb;

		$post_id = self::factory()->post->create(
			[
				'post_status' => 'draft',
				'post_title'  => "Draft from {$days_ago} days ago",
			]
		);

		// wp_insert_post() ignores an explicit post_modified on creation and
		// stamps the current time instead, so backdating has to go straight
		// to the database — the same column find_stale_drafts() queries.
		$timestamp = gmdate( 'Y-m-d H:i:s', time() - $days_ago * DAY_IN_SECONDS );
		$wpdb->update(
			$wpdb->posts,
			[
				'post_modified'     => $timestamp,
				'post_modified_gmt' => $timestamp,
			],
			[ 'ID' => $post_id ]
		);
		clean_post_cache( $post_id );

		return $post_id;
	}

	public function test_find_stale_drafts_only_returns_drafts_past_the_cutoff(): void {
		$old_draft    = $this->create_draft_last_modified_days_ago( 90 );
		$recent_draft = $this->create_draft_last_modified_days_ago( 5 );
		$published    = self::factory()->post->create( [ 'post_status' => 'publish' ] );

		$stale_ids = array_column(
			( new Stale_Drafts_Command() )->find_stale_drafts( 30 ),
			'ID'
		);

		$this->assertContains( $old_draft, $stale_ids );
		$this->assertNotContains( $recent_draft, $stale_ids, "A draft modified 5 days ago isn't stale at a 30-day cutoff." );
		$this->assertNotContains( $published, $stale_ids, 'A published post is never a "stale draft", no matter its age.' );
	}

	/**
	 * The lesson this command exists to demonstrate: --format=json goes
	 * through plain echo, so a normal ob_start() does capture it — unlike
	 * the success/log/error messages Migrate_Authors_Command relies on a
	 * Spy_Logger for.
	 */
	public function test_json_format_is_capturable_with_plain_output_buffering(): void {
		$this->create_draft_last_modified_days_ago( 90 );

		ob_start();
		( new Stale_Drafts_Command() )->report( [], [ 'days' => '30', 'format' => 'json' ] );
		$output = ob_get_clean();

		$decoded = json_decode( (string) $output, true );

		$this->assertIsArray( $decoded );
		$this->assertCount( 1, $decoded );
		$this->assertSame( 'Draft from 90 days ago', $decoded[0]['post_title'] );
	}
}
