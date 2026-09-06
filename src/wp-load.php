<?php
/**
 * Bootstrap file for setting the ABSPATH constant
 * and loading the wp-config.php file. The wp-config.php
 * file will then load the wp-settings.php file, which
 * will then set up the WordPress environment.
 *
 * If the wp-config.php file is not found then an error
 * will be displayed asking the visitor to set up the
 * wp-config.php file.
 *
 * Will also search for wp-config.php in WordPress' parent
 * directory to allow the WordPress directory to remain
 * untouched.
 *
 * @package WordPress
 */

/** Define ABSPATH as this file's directory */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/*
 * Define WPINC early so the version-floor check below can load
 * `wp-includes/version.php` directly. Every entry point (web, WP-CLI,
 * cron) goes through this file, so a PHP floor failure here is the
 * only check downstream code needs to know about.
 */
if ( ! defined( 'WPINC' ) ) {
	define( 'WPINC', 'wp-includes' );
}

/*
 * Load version.php before anything else so the canonical floor
 * constants are available. `require_once` keeps the later load
 * from `wp-settings.php` idempotent.
 */
require_once ABSPATH . WPINC . '/version.php';

/*
 * The error_reporting() function can be disabled in php.ini. On systems where that is the case,
 * it's best to add a dummy function to the wp-config.php file, but as this call to the function
 * is run prior to wp-config.php loading, it is wrapped in a function_exists() check.
 */
if ( function_exists( 'error_reporting' ) ) {
	/*
	 * Initialize error reporting to a known set of levels.
	 *
	 * This will be adapted in wp_debug_mode() located in wp-includes/load.php based on WP_DEBUG.
	 * @see https://www.php.net/manual/en/errorfunc.constants.php List of known error levels.
	 */
	error_reporting( E_CORE_ERROR | E_CORE_WARNING | E_COMPILE_ERROR | E_ERROR | E_WARNING | E_PARSE | E_USER_ERROR | E_USER_WARNING | E_RECOVERABLE_ERROR );
}

/*
 * Phase 1 platform-floor enforcement. Runs before wp-config.php is loaded
 * so the failure cannot be suppressed by wp-config constants and surfaces
 * on every entry point that goes through wp-load.php (web requests, WP-CLI
 * commands, cron requests, multisite's wp-admin/network.php, and the
 * standalone scripts under wp-admin/).
 *
 * Per MODERNIZATION_PLAN.md Phase 1 task 1 and 'Decisions that sharpen or
 * override the Spec': PHP 8.5 is the hard minimum. No PHP 8.5 feature is
 * guarded behind a `version_compare()`; this is the single point of
 * enforcement. Floor value is read from `$required_php_version` defined in
 * `wp-includes/version.php`; that constant is the canonical source of
 * truth and `.version-support-php.json` mirrors it.
 */
if ( version_compare( PHP_VERSION, $required_php_version, '<' ) ) {
	$protocol = wp_get_server_protocol();
	header( sprintf( '%s 500 Internal Server Error', $protocol ), true, 500 );
	header( 'Content-Type: text/html; charset=utf-8' );
	printf(
		"WordPress requires PHP %1\$s or newer. Detected PHP %2\$s. Upgrade PHP on the server (or switch to a host that runs PHP %1\$s or newer) and retry the request.",
		$required_php_version,
		PHP_VERSION
	);
	exit( 1 );
}

/*
 * If wp-config.php exists in the WordPress root, or if it exists in the root and wp-settings.php
 * doesn't, load wp-config.php. The secondary check for wp-settings.php has the added benefit
 * of avoiding cases where the current directory is a nested installation, e.g. / is WordPress(a)
 * and /blog/ is WordPress(b).
 *
 * If neither set of conditions is true, initiate loading the setup process.
 */
if ( file_exists( ABSPATH . 'wp-config.php' ) ) {

	/** The config file resides in ABSPATH */
	require_once ABSPATH . 'wp-config.php';

} elseif ( @file_exists( dirname( ABSPATH ) . '/wp-config.php' ) && ! @file_exists( dirname( ABSPATH ) . '/wp-settings.php' ) ) {

	/** The config file resides one level above ABSPATH but is not part of another installation */
	require_once dirname( ABSPATH ) . '/wp-config.php';

} else {

	// A config file doesn't exist.

	// Check for the required PHP version and for the MySQL extension or a database drop-in.
	wp_check_php_mysql_versions();

	// Standardize $_SERVER variables across setups.
	wp_fix_server_vars();

	define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' );
	require_once ABSPATH . WPINC . '/functions.php';

	$path = wp_guess_url() . '/wp-admin/setup-config.php';

	// Redirect to setup-config.php.
	if ( ! str_contains( $_SERVER['REQUEST_URI'], 'setup-config' ) ) {
		header( 'Location: ' . $path );
		exit;
	}

	wp_load_translations_early();

	// Die with an error message.
	$die = '<p>' . sprintf(
		/* translators: %s: wp-config.php */
		__( "There doesn't seem to be a %s file. It is needed before the installation can continue." ),
		'<code>wp-config.php</code>'
	) . '</p>';
	$die .= '<p>' . sprintf(
		/* translators: 1: Documentation URL, 2: wp-config.php */
		__( 'Need more help? <a href="%1$s">Read the support article on %2$s</a>.' ),
		__( 'https://developer.wordpress.org/advanced-administration/wordpress/wp-config/' ),
		'<code>wp-config.php</code>'
	) . '</p>';
	$die .= '<p>' . sprintf(
		/* translators: %s: wp-config.php */
		__( "You can create a %s file through a web interface, but this doesn't work for all server setups. The safest way is to manually create the file." ),
		'<code>wp-config.php</code>'
	) . '</p>';
	$die .= '<p><a href="' . $path . '" class="button button-large">' . __( 'Create a Configuration File' ) . '</a></p>';

	wp_die( $die, __( 'WordPress &rsaquo; Error' ) );
}
