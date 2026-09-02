<?php
/**
 * Tests for Migrate_Authors_Command.
 *
 * @package WPCLITesting
 */

declare( strict_types=1 );

namespace WPCLITesting\Tests\Unit;

use WP_CLI;
use WP_UnitTestCase;
use WPCLITesting\Commands\Migrate_Authors_Command;
use WPCLITesting\Migration\Mapping_Error;
use WPCLITesting\Testing\Spy_Logger;

/**
 * @covers \WPCLITesting\Commands\Migrate_Authors_Command
 */
class Migrate_Authors_Command_Test extends WP_UnitTestCase {

	/**
	 * @var Spy_Logger
	 */
	private $logger;

	public function setUp(): void {
		parent::setUp();

		$this->logger = new Spy_Logger();
		WP_CLI::set_logger( $this->logger );
	}

	/**
	 * Create one post per legacy author ID, in the given order.
	 *
	 * @param int[] $legacy_ids Legacy author IDs, one per post.
	 *
	 * @return int[] Post IDs, in creation order.
	 */
	private function create_posts_with_legacy_authors( array $legacy_ids ): array {
		return array_map(
			function ( $legacy_id ) {
				$post_id = self::factory()->post->create();
				update_post_meta( $post_id, '_legacy_author_id', (string) $legacy_id );

				return $post_id;
			},
			$legacy_ids
		);
	}

	/**
	 * The one thing this command exists to do: every post with a mapped
	 * legacy ID ends up with the right WordPress author.
	 */
	public function test_migrate_reassigns_every_post_with_a_valid_mapping(): void {
		[ $post_a, $post_b ] = $this->create_posts_with_legacy_authors( [ 101, 102 ] );
		$new_author = self::factory()->user->create();

		$migrated = ( new Migrate_Authors_Command() )->migrate(
			[
				'101' => $new_author,
				'102' => $new_author,
			]
		);

		$this->assertSame( 2, $migrated );
		$this->assertSame( $new_author, (int) get_post( $post_a )->post_author );
		$this->assertSame( $new_author, (int) get_post( $post_b )->post_author );
	}

	/**
	 * The test that actually matters: an unmapped legacy ID stops the run,
	 * and posts already touched stay touched. This is the guarantee that a
	 * "helpful" refactor (log a warning and continue, instead of stopping)
	 * would silently break.
	 */
	public function test_migrate_stops_before_touching_posts_past_an_unmapped_id(): void {
		$mapped_author   = self::factory()->user->create();
		$original_author = self::factory()->user->create();

		[ $post_a, $post_b, $post_c ] = $this->create_posts_with_legacy_authors( [ 101, 999, 102 ] );

		foreach ( [ $post_a, $post_b, $post_c ] as $post_id ) {
			wp_update_post(
				[
					'ID'          => $post_id,
					'post_author' => $original_author,
				]
			);
		}

		$command = new Migrate_Authors_Command();

		$this->expectException( Mapping_Error::class );
		$this->expectExceptionMessage( 'No mapping for legacy author ID 999' );

		try {
			$command->migrate(
				[
					'101' => $mapped_author, // Post A: mapped.
					// 999 (Post B): not mapped.
					'102' => $mapped_author, // Post C: mapped, but should never be reached.
				]
			);
		} finally {
			$this->assertSame(
				$mapped_author,
				(int) get_post( $post_a )->post_author,
				'Post before the failure should be migrated.'
			);
			$this->assertSame(
				$original_author,
				(int) get_post( $post_c )->post_author,
				'Post after the failure should be untouched.'
			);
		}
	}

	/**
	 * `run()` is the thin, WP-CLI-facing wrapper. This confirms it reads a
	 * real mapping file, delegates to migrate(), and reports through the
	 * logger — not that the message string looks right in isolation.
	 */
	public function test_run_reports_success_through_the_logger(): void {
		[ $post_a ] = $this->create_posts_with_legacy_authors( [ 101 ] );
		$new_author  = self::factory()->user->create();

		$mapping_file = tempnam( sys_get_temp_dir(), 'map' );
		self::assertNotFalse( $mapping_file );
		file_put_contents( $mapping_file, "101,{$new_author}\n" );

		( new Migrate_Authors_Command() )->run( [], [ 'map' => $mapping_file ] );

		unlink( $mapping_file );

		$this->assertSame( $new_author, (int) get_post( $post_a )->post_author );
		$this->assertStringContainsString( '1 posts migrated', $this->logger->messages['success'][0] ?? '' );
	}

	// Deliberately no test here that calls run() with a bad mapping and
	// expects it to "just log an error". WP_CLI::error() exits the process
	// by default, and there's no supported way to stop that from a plain
	// PHPUnit test — WP_CLI::$capture_exit is private, and the trick that
	// flips it, WP_CLI::runcommand(), needs a fully started Runner that
	// only exists inside a real `wp` invocation. That's exactly what the
	// Behat scenario in features/migrate-authors.feature is for: it runs
	// the real binary, so exiting is expected and safe to assert on.
}
