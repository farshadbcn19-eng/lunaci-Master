<?php
/**
 * post_modified bump did NOT resolve the Elementor render staleness
 * (confirmed via fix-bump-post-modified-elementor-cache.php: identical
 * output length and missing '100dvh' before and after). The remaining
 * candidate is the site's 'elementor_atomic_cache_validity__*' options
 * (base, global, global_related, local, component-styles-related-posts) -
 * an internal Elementor cache-validity system. These are explicitly
 * labeled as a cache (not primary content), so clearing them is a safe,
 * standard cache-invalidation operation: Elementor must be able to
 * regenerate its own cache from the real source data (_elementor_data)
 * without any data loss, by definition of what a cache is.
 *
 * Deletes those 5 options, then re-checks Elementor's own
 * get_builder_content_for_display() to confirm whether this resolves the
 * staleness.
 */

global $wpdb;

$cache_options = array(
	'elementor_atomic_cache_validity__base',
	'elementor_atomic_cache_validity__component-styles-related-posts',
	'elementor_atomic_cache_validity__global',
	'elementor_atomic_cache_validity__global_related',
	'elementor_atomic_cache_validity__local',
);

echo "--- BEFORE: get_builder_content_for_display() for post 60 ---\n";
if ( class_exists( '\Elementor\Plugin' ) ) {
	$before = \Elementor\Plugin::$instance->frontend->get_builder_content_for_display( 60 );
	echo "contains '100dvh': " . ( false !== strpos( $before, '100dvh' ) ? 'YES' : 'NO' ) . " (length=" . strlen( $before ) . ")\n";
}

echo "\n--- Deleting cache-validity options ---\n";
foreach ( $cache_options as $opt ) {
	$existed = ( false !== get_option( $opt, false ) );
	$deleted = delete_option( $opt );
	echo "delete_option('$opt'): existed=" . ( $existed ? 'YES' : 'NO' ) . " deleted=" . ( $deleted ? 'YES' : 'NO' ) . "\n";
}

// Also clear post cache and any transients for good measure (cheap, safe).
clean_post_cache( 60 );
clean_post_cache( 770 );
wp_cache_flush();

echo "\n--- AFTER: get_builder_content_for_display() for post 60 ---\n";
if ( class_exists( '\Elementor\Plugin' ) ) {
	$after = \Elementor\Plugin::$instance->frontend->get_builder_content_for_display( 60 );
	echo "contains '100dvh': " . ( false !== strpos( $after, '100dvh' ) ? 'YES' : 'NO' ) . " (length=" . strlen( $after ) . ")\n";

	$pos = strpos( $after, 'min-height: 100vh' );
	if ( false !== $pos ) {
		echo "\n--- 100 bytes from 'min-height: 100vh' in AFTER output ---\n";
		echo substr( $after, $pos, 100 ) . "\n";
	}
}

echo "\n--- AFTER: get_builder_content_for_display() for post 770 (ES) ---\n";
if ( class_exists( '\Elementor\Plugin' ) ) {
	$after_es = \Elementor\Plugin::$instance->frontend->get_builder_content_for_display( 770 );
	echo "contains '100dvh': " . ( false !== strpos( $after_es, '100dvh' ) ? 'YES' : 'NO' ) . " (length=" . strlen( $after_es ) . ")\n";
}

echo "\nOK: script complete\n";
