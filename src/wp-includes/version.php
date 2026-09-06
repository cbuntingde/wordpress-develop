<?php
/**
 * WordPress Version
 *
 * Contains version information for the current WordPress release.
 *
 * @package WordPress
 * @since 1.2.0
 */

/**
 * The WordPress version string.
 *
 * Holds the current version number for WordPress core. Used to bust caches
 * and to enable development mode for scripts when running from the /src directory.
 *
 * @global string $wp_version
 */
$wp_version = '7.2-alpha-63166-src';

/**
 * Holds the WordPress DB revision, increments when changes are made to the WordPress DB schema.
 *
 * @global int $wp_db_version
 */
$wp_db_version = 61833;

/**
 * Holds the TinyMCE version.
 *
 * @global string $tinymce_version
 */
$tinymce_version = '49110-20250317';

/**
 * Holds the minimum required PHP version.
 *
 * Enforced at bootstrap by `wp_check_php_mysql_versions()` in
 * `src/wp-includes/load.php` and at the very top of `src/wp-load.php`.
 * Single source of truth for the floor lives in `.version-support-php.json`
 * at the repository root. Per MODERNIZATION_PLAN.md Phase 1 task 1.
 *
 * @global string $required_php_version
 */
$required_php_version = '8.5';

/**
 * Holds the names of required PHP extensions.
 *
 * @global string[] $required_php_extensions
 */
$required_php_extensions = array(
	'json',
	'hash',
	'mysqli',
);

/**
 * Holds the minimum required MySQL version.
 *
 * Enforced at connection time by `wpdb::check_database_version()`.
 * Per MODERNIZATION_PLAN.md Phase 1 task 2 and the MySQL floor decision
 * in 'Decisions that sharpen or override the Spec': MySQL 8.4 is the
 * hard minimum. MySQL 8.0–8.3 do NOT pass.
 *
 * @global string $required_mysql_version
 */
$required_mysql_version = '8.4';

/**
 * Holds the minimum required MariaDB version.
 *
 * Enforced at connection time by `wpdb::check_database_version()`.
 * Per MODERNIZATION_PLAN.md Phase 1 task 2.
 *
 * @global string $required_mariadb_version
 */
$required_mariadb_version = '10.11';

/**
 * Holds the minimum required database table character set.
 *
 * Enforced at connection time by `wpdb::check_table_storage()`.
 * Per MODERNIZATION_PLAN.md Phase 1 task 4.
 *
 * @global string $required_db_charset
 */
$required_db_charset = 'utf8mb4';

/**
 * Holds the minimum required database table engine.
 *
 * Enforced at connection time by `wpdb::check_table_storage()`.
 * Per MODERNIZATION_PLAN.md Phase 1 task 4.
 *
 * @global string $required_db_engine
 */
$required_db_engine = 'InnoDB';
