Feature: migrate-authors
  The one scenario PHPUnit can't safely cover for this command: whether it
  actually halts, with the right exit code, when the mapping is incomplete.
  WP_CLI::error() exits the process by default, which is fine here — Behat
  runs the real `wp` binary against a throwaway install, so exiting is
  expected and safe to assert on.

  Background:
    Given a WP install
    And I run `wp user create migrated-author migrated-author@example.com --porcelain`
    And save STDOUT as {MIGRATED_AUTHOR_ID}

  Scenario: Migrating every post with a complete mapping
    Given I run `wp post create --post_title="Legacy post" --post_status=publish --porcelain`
    And save STDOUT as {POST_ID}
    And I run `wp post meta update {POST_ID} _legacy_author_id 101`
    And a wp-content/mu-plugins/author-mapping.csv file:
      """
      101,{MIGRATED_AUTHOR_ID}
      """

    When I run `wp migrate-authors run --map=wp-content/mu-plugins/author-mapping.csv`
    Then STDOUT should contain:
      """
      1 posts migrated.
      """
    And the return code should be 0

  Scenario: Halting on a post whose legacy author ID isn't mapped
    Given I run `wp post create --post_title="Unmapped post" --post_status=publish --porcelain`
    And save STDOUT as {POST_ID}
    And I run `wp post meta update {POST_ID} _legacy_author_id 999`
    And a wp-content/mu-plugins/author-mapping.csv file:
      """
      101,{MIGRATED_AUTHOR_ID}
      """

    When I try `wp migrate-authors run --map=wp-content/mu-plugins/author-mapping.csv`
    Then STDERR should contain:
      """
      No mapping for legacy author ID 999
      """
    And the return code should be 1
