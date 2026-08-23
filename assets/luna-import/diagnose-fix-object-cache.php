<?php
global $wpdb;

$page_id = 57;

echo "####################################################################\n";
echo "Diagnose: why does the live render still show old content for page {$page_id}?\n";
echo "####################################################################\n\n";

echo "-- Cache backend info --\n\n";
echo "wp_using_ext_object_cache(): " . ( wp_using_ext_object_cache() ? 'true (an external persistent object cache IS active)' : 'false (default in-memory-per-request cache only)' ) . "\n";
echo "object-cache.php drop-in exists: " . ( file_exists( WP_CONTENT_DIR . '/object-cache.php' ) ? 'YES' : 'no' ) . "\n";
if ( class_exists( 'Redis' ) ) {
	echo "PHP Redis extension: loaded\n";
}
global $wp_object_cache;
if ( isset( $wp_object_cache ) ) {
	echo "Object cache class: " . get_class( $wp_object_cache ) . "\n";
}
echo "\n";

echo "-- Compare raw SQL (uncached) vs get_post_meta() (goes through the object cache) --\n\n";

$raw_sql = $wpdb->get_var(
	$wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_elementor_data'", $page_id )
);
$raw_sql_len = null === $raw_sql ? 0 : strlen( $raw_sql );
$raw_sql_has_new = null !== $raw_sql && false !== strpos( $raw_sql, 'lunaimport-hero-luna.jpg' );

echo "Raw SQL: byte length = {$raw_sql_len}, contains new content = " . ( $raw_sql_has_new ? 'YES' : 'NO' ) . "\n";

// Force a cold read: clear this post's meta cache first, then read via the cached API.
wp_cache_delete( $page_id, 'post_meta' );
$cached_after_clear = get_post_meta( $page_id, '_elementor_data', true );
$cached_after_clear_len = strlen( (string) $cached_after_clear );
$cached_after_clear_has_new = false !== strpos( (string) $cached_after_clear, 'lunaimport-hero-luna.jpg' );
echo "get_post_meta() AFTER wp_cache_delete(): byte length = {$cached_after_clear_len}, contains new content = " . ( $cached_after_clear_has_new ? 'YES' : 'NO' ) . "\n";

// Now read via the cached API WITHOUT clearing first, to see what was actually being served.
$cached_before_clear = wp_cache_get( $page_id, 'post_meta' );
if ( is_array( $cached_before_clear ) && isset( $cached_before_clear['_elementor_data'][0] ) ) {
	$val = $cached_before_clear['_elementor_data'][0];
	$val_len = strlen( $val );
	$val_has_new = false !== strpos( $val, 'lunaimport-hero-luna.jpg' );
	echo "wp_cache_get() re-populated cache (post-clear, so this reflects the fresh read): byte length = {$val_len}, contains new content = " . ( $val_has_new ? 'YES' : 'NO' ) . "\n";
}
echo "\n";

echo "-- Render the actual widget HTML through Elementor's normal document API (what the page render path uses) --\n\n";
if ( did_action( 'elementor/loaded' ) || class_exists( '\\Elementor\\Plugin' ) ) {
	$document = \Elementor\Plugin::instance()->documents->get( $page_id );
	if ( $document ) {
		$elementor_data_via_api = $document->get_elements_data();
		$json_via_api = wp_json_encode( $elementor_data_via_api );
		$api_has_new = false !== strpos( $json_via_api, 'lunaimport-hero-luna.jpg' );
		echo "Elementor Document::get_elements_data() contains new content = " . ( $api_has_new ? 'YES' : 'NO' ) . " (byte length of JSON: " . strlen( $json_via_api ) . ")\n";
	} else {
		echo "Could not load Elementor document for page {$page_id}.\n";
	}
} else {
	echo "Elementor not loaded in this context - skipping document-level check.\n";
}
echo "\n";

echo "-- Aggressive cache clear (safe, no content writes) --\n\n";
wp_cache_flush();
echo "wp_cache_flush(): done\n";

if ( function_exists( 'wp_cache_flush_runtime' ) ) {
	wp_cache_flush_runtime();
	echo "wp_cache_flush_runtime(): done\n";
}

// Elementor-specific caches.
if ( class_exists( '\\Elementor\\Plugin' ) ) {
	try {
		\Elementor\Plugin::instance()->files_manager->clear_cache();
		echo "Elementor files_manager->clear_cache(): done\n";
	} catch ( \Throwable $e ) {
		echo "Elementor files_manager->clear_cache() failed: " . $e->getMessage() . "\n";
	}
	delete_post_meta( $page_id, '_elementor_css' );
	echo "Deleted _elementor_css postmeta for page {$page_id} (forces Elementor to regenerate on next view): done\n";
}

if ( function_exists( 'opcache_reset' ) ) {
	$ok = opcache_reset();
	echo "opcache_reset(): " . ( $ok ? 'done' : 'failed or not enabled' ) . "\n";
}
echo "\n";

echo "-- Re-check after aggressive clear --\n\n";
wp_cache_delete( $page_id, 'post_meta' );
$final_check = get_post_meta( $page_id, '_elementor_data', true );
$final_has_new = false !== strpos( (string) $final_check, 'lunaimport-hero-luna.jpg' );
echo "get_post_meta() after aggressive clear contains new content = " . ( $final_has_new ? 'YES' : 'NO' ) . "\n";

echo "\nOK: diagnose-fix-object-cache completed\n";
