# WP-CLI Command Testing

Two example WP-CLI commands, tested with PHPUnit and Behat. Companion code for a blog post on testing WP-CLI commands.

## Commands

- **`wp migrate-authors run --map=<file>`** — reassigns `post_author` using a legacy-id → WordPress-user-id CSV mapping. Refuses to start if the mapping points at users that don't exist, then stops at the first post whose legacy ID isn't in the mapping; posts already migrated stay migrated.
- **`wp migrate-authors verify-mapping --map=<file>`** — checks that every WordPress user ID in a mapping file exists.
- **`wp stale-drafts list --days=<n> --format=<table|json|csv>`** — lists drafts last modified more than `n` days ago.

## Running the tests

```bash
composer install
cp .env.example .env

# PHPUnit needs a MySQL/MariaDB server — a throwaway container is easiest:
docker run -d --name wpcli_dev_db --restart unless-stopped -p 3308:3306 -e MYSQL_ROOT_PASSWORD=root mariadb:11
composer prepare-tests   # creates the test database

composer test            # PHPUnit
composer behat           # Behat, against the real `wp` binary
```

`.env` is gitignored and loaded automatically — `tests/bootstrap.php` loads it for PHPUnit, and the `behat` script sources it into a real shell environment (Behat spawns `wp` subprocesses, which inherit only real env vars).

For readable output instead of dots: `composer test -- --testdox` and `vendor/bin/behat --format=pretty`.

If Behat fails in `BeforeSuite` with `rename(...): Directory not empty`, its cache was half-written by an interrupted run — run `composer behat:clean` and try again.

## Notes on the setup

- **`prepare-tests` is `bin/prepare-db.php`**, using PHP's `mysqli` rather than shelling out to `mysql`. The bundled `install-package-tests` from `wp-cli/wp-cli-tests` requests `mysql_native_password` explicitly, which fails on clients that dropped that plugin (Homebrew's `mysql` 26.x, for one).
- **Behat runs on SQLite locally** via `WP_CLI_TEST_DBTYPE=sqlite`, so it needs no database server — `FeatureContext` skips its `mysql`/`mysqldump` shell-outs in that mode. CI leaves this unset and runs Behat against MySQL, so that path still gets coverage.
- **One WordPress version, pinned to 7.1** in two places that have to agree: `roots/wordpress` in `composer.json` (what PHPUnit tests against) and `WP_VERSION` in `.env` and CI (what Behat downloads).
- **`WP_CLI_PHP_ARGS` raises the memory limit.** WordPress 7.x archives don't extract under PHP CLI's default 128M — `wp core download` dies mid-extract and leaves a half-written Behat cache.
- **PHPUnit is pinned to `^9.6`.** The latest 10.5.x removed a `PHPUnit\Util\Test` method that `wp-phpunit`'s `abstract-testcase.php` still calls — an internal API, so `phpunit-polyfills` doesn't cover it.

## What each test covers

- **`Migrate_Authors_Command_Test`** — `test_migrate_stops_before_touching_posts_past_an_unmapped_id` asserts the post *before* a bad row is migrated and the post *after* it isn't. That's the property worth pinning down before this runs unattended.
- **`Stale_Drafts_Command_Test`** — `test_json_format_is_capturable_with_plain_output_buffering` covers the one output path `ob_start()` can see. `--format=json/csv` goes through `echo`; `WP_CLI::success()`/`log()`/`error()` go through a Logger writing straight to `php://stdout`.
- **`features/migrate-authors.feature`** — the thing PHPUnit can't safely cover: that `wp migrate-authors run` actually exits non-zero on a bad mapping. `WP_CLI::error()` exits the process, and `WP_CLI::$capture_exit` is private, so that behavior is only testable through the real binary.

## Structure

```
src/
  Commands/
    Migrate_Authors_Command.php   # migrate() throws a plain exception; run() is the WP-CLI wrapper
    Stale_Drafts_Command.php
  Migration/
    Mapping_Error.php
  Testing/
    Spy_Logger.php                # extends WP_CLI\Loggers\Base to capture output in tests
bin/
  prepare-db.php
tests/
  bootstrap.php
  wp-tests-config.php
  Unit/
features/
  migrate-authors.feature
plugin.php                        # registers both commands inside a real `wp` process
```
