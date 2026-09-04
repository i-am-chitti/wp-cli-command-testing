# WP-CLI Command Testing

Two small, original WP-CLI commands, tested end to end with PHPUnit and Behat. Built as the companion code for a blog post on testing WP-CLI commands — every technique in the post is real code here, not a snippet.

Nothing in this repo is tied to any client project. `migrate-authors` (reassigns posts from a legacy numeric author ID to a WordPress user) and `stale-drafts` (reports on drafts that haven't been touched in a while) are both invented for this repo.

## Commands

- **`wp migrate-authors run --map=<file>`** — reassigns `post_author` using a legacy-id → WordPress-user-id CSV mapping. Stops the moment it hits a post whose legacy ID isn't in the mapping; posts it already touched stay touched.
- **`wp migrate-authors verify-mapping --map=<file>`** — checks that every WordPress user ID in a mapping file actually exists.
- **`wp stale-drafts list --days=<n> --format=<table|json|csv>`** — lists drafts last modified more than `n` days ago.

## Running the tests

You need a MySQL/MariaDB server reachable from your machine. The easiest way is a throwaway container:

```bash
docker run -d --name wpcli_dev_db --restart unless-stopped -p 3308:3306 -e MYSQL_ROOT_PASSWORD=root mariadb:11
php -r '(new mysqli("127.0.0.1","root","root","",3308))->query("CREATE DATABASE IF NOT EXISTS wp_cli_test");'
```

Then copy `.env.example` to `.env` and adjust the port/credentials if yours differ:

```bash
cp .env.example .env
```

`.env` is gitignored and read automatically by `tests/bootstrap.php` — no exporting env vars by hand, and `composer test` just works from here on:

```bash
composer install
composer prepare-tests   # provisions a throwaway WP install + test DB
composer test            # PHPUnit — reads .env automatically
composer behat           # Behat, against the real `wp` binary — still needs the WP_CLI_TEST_DB* vars exported, since it's a separate script that doesn't load .env
```

For readable output instead of dots: `composer test -- --testdox` and `vendor/bin/behat --format=pretty`.

## What each test actually proves

- **`Migrate_Authors_Command_Test`** — the interesting one is `test_migrate_stops_before_touching_posts_past_an_unmapped_id`. It asserts the post *before* a bad row is migrated and the post *after* it isn't. That's the one property worth pinning down before this kind of command runs unattended.
- **`Stale_Drafts_Command_Test`** — `test_json_format_is_capturable_with_plain_output_buffering` shows the one output path that a plain `ob_start()` *can* see. `--format=json/csv` goes through `echo`; `WP_CLI::success()`/`log()`/`error()` don't.
- **`features/migrate-authors.feature`** — covers the one thing PHPUnit can't safely cover here: that `wp migrate-authors run` actually exits non-zero on a bad mapping. More on why below.

## Things I learned building this that the blog post doesn't have room for

**`WP_CLI\Loggers\Base` is an abstract class, not an interface.** `Spy_Logger` (`src/Testing/Spy_Logger.php`) extends it and implements `info()`, `success()`, `warning()`, `error()`. `debug()` already has a working default in `Base`, so it's left alone.

**`WP_CLI::$capture_exit` is a private static property.** You cannot set it from outside the `WP_CLI` class — `\WP_CLI::$capture_exit = true;` fails. It's flipped internally, only by `WP_CLI::runcommand()`.

**`WP_CLI::runcommand()` needs a fully started `Runner`**, which normally only exists inside a real `wp` invocation. Calling it from a bare PHPUnit bootstrap (even with `wp-cli/wp-cli` loaded via Composer) fails on missing runner config — `Trying to access array offset on null` — well before it gets anywhere near your command. I confirmed this by hand: loading just the classes gets you `WP_CLI::set_logger()`, `WP_CLI::success()`, `WP_CLI::add_command()` for free, but `WP_CLI::runcommand()`'s in-process dispatch (`launch => false`) additionally needs `php/utils.php` and `php/dispatcher.php` required manually, the `WP_CLI` and `WP_CLI_ROOT` constants defined, and even then it dies on an uninitialized `Runner::$config` — which is only ever populated by the real bootstrap sequence in `wp-cli.php`.

That's why `Migrate_Authors_Command::migrate()` exists as a separate method that never touches `WP_CLI::` at all — it throws a plain `Mapping_Error` instead. `run()` is a thin wrapper that catches it and calls `WP_CLI::error()`. PHPUnit tests `migrate()` directly, with zero WP-CLI runtime involved. The one thing that *does* need `WP_CLI::error()`'s real exit behavior — proving `run()` halts with the right exit code — lives in the Behat feature instead, where a real `wp` process makes that safe to trigger.

A few smaller things that only showed up once I actually ran this against a real database, not just read about it:

- **`WP_Post::post_author` (and `->ID`, `->post_parent`, ...) come back as numeric strings, not ints.** They're raw `$wpdb` row values that WordPress never casts. `assertSame( 2, get_post( $id )->post_author )` fails — cast the actual side: `assertSame( 2, (int) get_post( $id )->post_author )`.
- **`wp_insert_post()` ignores an explicit `post_modified` on creation.** Backdating a post for a date-based query test (`Stale_Drafts_Command_Test`) has to update the `posts` table directly after creation, then call `clean_post_cache()` — see `create_draft_last_modified_days_ago()`.
- **`wp-cli/wp-cli` alone doesn't give you `wp post`, `wp user`, etc.** Those ship in the separate `wp-cli/entity-command` package. The Behat feature needed it as a dev dependency before `wp post create` / `wp user create` would resolve.
- **PHPUnit version matters more than the `^9.6 || ^10.5` range suggests.** The very latest 10.5.x point release removed a `PHPUnit\Util\Test` method that `wp-phpunit/wp-phpunit`'s own `abstract-testcase.php` still calls directly — not something `yoast/phpunit-polyfills` covers, since it's an internal API, not a public assertion. Pinned to `^9.6` here, which is fully green.
- **`vlucas/phpdotenv` doesn't call `putenv()` by default** — it only populates `$_ENV`/`$_SERVER`. That's invisible until something forks a child process, like WordPress's own test installer does: the child only inherits real OS env vars, so `DB_HOST` silently fell back to `localhost` even though `.env` loaded correctly in the parent process. Fixed by explicitly `putenv()`-ing each value phpdotenv loads (`tests/bootstrap.php`), and by having `tests/wp-tests-config.php` check `getenv()`, then `$_ENV`, then `$_SERVER` rather than trusting just one of them.

Every command in this README has actually been run: `composer test` is 5/5 green against a real MySQL 8 + WordPress 6.7 install, and `composer behat` is 2/2 green against the real `wp` binary.

## Structure

```
src/
  Commands/
    Migrate_Authors_Command.php
    Stale_Drafts_Command.php
  Migration/
    Mapping_Error.php
  Testing/
    Spy_Logger.php
tests/
  bootstrap.php
  Unit/
    Migrate_Authors_Command_Test.php
    Stale_Drafts_Command_Test.php
features/
  migrate-authors.feature
plugin.php   # registers both commands when loaded inside a real `wp` process
```
