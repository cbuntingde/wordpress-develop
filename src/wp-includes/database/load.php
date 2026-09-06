<?php
/**
 * Load the DatabaseDialect module — the value-object types, the
 * feature-constant catalog, the interface, and the two vendor
 * implementations. Procedural autoloader because the WP runtime
 * does not run the Composer autoloader before wpdb constructs.
 *
 * @package WordPress
 */

declare(strict_types=1);

require_once ABSPATH . WPINC . '/database/class-query.php';
require_once ABSPATH . WPINC . '/database/class-expression.php';
require_once ABSPATH . WPINC . '/database/class-database-features.php';
require_once ABSPATH . WPINC . '/database/class-database-dialect.php';
require_once ABSPATH . WPINC . '/database/class-mysql-dialect.php';
require_once ABSPATH . WPINC . '/database/class-mariadb-dialect.php';
require_once ABSPATH . WPINC . '/database/class-query-builder.php';
