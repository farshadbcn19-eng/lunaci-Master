<?php
/**
 * Read-only diagnostic: post 59's _elementor_data is 100% confirmed
 * correct in the DB, and /about-us/ is confirmed to resolve to post 59.
 * A direct curl to 127.0.0.1 with a Host header (bypassing DNS/any CDN
 * entirely) STILL returns the OLD content, while WP-CLI (this exact
 * process) reads the NEW content correctly. This strongly suggests a
 * persistent object cache backend (e.g. APCu) that is scoped per-SAPI/
 * per-worker-pool, so wp_cache_flush() run from the WP-CLI process never
 * touches the actual web-serving LiteSpeed/PHP workers' cache pool.
 *
 * This checks: is an external object cache active, which drop-in file is
 * in use, and what backend it reports.
 */

echo "wp_using_ext_object_cache(): " . ( wp_using_ext_object_cache() ? 'YES' : 'no' ) . "\n";

global $wp_object_cache;
if ( isset( $wp_object_cache ) ) {
	echo "wp_object_cache class: " . get_class( $wp_object_cache ) . "\n";
}

$dropin_path = WP_CONTENT_DIR . '/object-cache.php';
echo "object-cache.php drop-in exists: " . ( file_exists( $dropin_path ) ? 'YES' : 'no' ) . "\n";
if ( file_exists( $dropin_path ) ) {
	$head = file_get_contents( $dropin_path, false, null, 0, 2000 );
	echo "--- first 2000 chars of object-cache.php ---\n";
	echo $head . "\n";
}

echo "\nfunction_exists('apcu_fetch'): " . ( function_exists( 'apcu_fetch' ) ? 'YES' : 'no' ) . "\n";
echo "extension_loaded('apcu'): " . ( extension_loaded( 'apcu' ) ? 'YES' : 'no' ) . "\n";
if ( function_exists( 'apcu_enabled' ) ) {
	echo "apcu_enabled(): " . ( apcu_enabled() ? 'YES' : 'no' ) . "\n";
}
echo "ini apc.enable_cli: " . ini_get( 'apc.enable_cli' ) . "\n";

echo "\nclass_exists('Redis'): " . ( class_exists( 'Redis' ) ? 'YES' : 'no' ) . "\n";
echo "class_exists('Memcached'): " . ( class_exists( 'Memcached' ) ? 'YES' : 'no' ) . "\n";

// Check for LiteSpeed Cache plugin's own object cache module
if ( defined( 'LSCWP_V' ) ) {
	echo "\nLiteSpeed Cache plugin version: " . LSCWP_V . "\n";
}
echo "\nOK: read-only diagnostic complete, no writes performed\n";
