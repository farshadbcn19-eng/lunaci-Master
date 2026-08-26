<?php
/**
 * Guarded fix: found the true root cause of the About Us hero desktop-width
 * fix (PR #247) not appearing live, after exhausting every caching layer
 * (LiteSpeed page cache including a manual wp-admin "Purge All", direct-
 * origin requests, WP object cache, APCu, DNS/vhost routing - all ruled
 * out). A brute-force DB search found a SEPARATE postmeta key,
 * `_elementor_element_cache`, on both post 59 and 680 - Elementor's own
 * "Element Caching" performance feature, which stores a pre-rendered HTML
 * snapshot of the page completely independent from `_elementor_data`.
 *
 * Our earlier fix correctly updated `_elementor_data` (confirmed multiple
 * times via raw SQL), but a raw `update_post_meta()` call bypasses
 * Elementor's own save-hook logic that normally invalidates this cache
 * when editing through the Elementor editor - so the live page kept
 * rendering from this untouched, stale snapshot regardless of any
 * web-server-level cache purge.
 *
 * Fix: delete `_elementor_element_cache` for both pages. Elementor
 * regenerates this cache automatically and transparently on the next
 * render, this time correctly reflecting the current `_elementor_data`.
 */

$pages = array(
	59  => 'EN About Us',
	680 => 'ES About Us (Sobre Nosotros)',
);

echo "=====================================================================\n";
echo "STEP A: PREPARE - confirm the cache key exists on both pages\n";
echo "=====================================================================\n";

$to_fix = array();
foreach ( $pages as $page_id => $label ) {
	$cached = get_post_meta( $page_id, '_elementor_element_cache', true );
	$exists = ( '' !== $cached && false !== $cached );
	echo "page {$page_id} ({$label}): _elementor_element_cache exists=" . ( $exists ? 'yes' : 'no' ) . " length=" . ( $exists ? strlen( is_string( $cached ) ? $cached : serialize( $cached ) ) : 0 ) . "\n";
	if ( $exists ) {
		$to_fix[] = $page_id;
	}
}

if ( empty( $to_fix ) ) {
	echo "ABORT: no pages have this cache key - nothing to do\n";
	exit( 1 );
}
echo "OK: will delete _elementor_element_cache for: " . implode( ', ', $to_fix ) . "\n";

echo "\n=====================================================================\n";
echo "STEP B: COMMIT - delete the stale cache key\n";
echo "=====================================================================\n";

foreach ( $to_fix as $page_id ) {
	$deleted = delete_post_meta( $page_id, '_elementor_element_cache' );
	echo "page {$page_id}: delete_post_meta() returned: " . var_export( $deleted, true ) . "\n";
	clean_post_cache( $page_id );
}
wp_cache_flush();
echo "OK: caches cleared\n";

echo "\n=====================================================================\n";
echo "STEP C: VERIFY - confirm the cache key is gone\n";
echo "=====================================================================\n";

$all_ok = true;
foreach ( $to_fix as $page_id ) {
	$verify = get_post_meta( $page_id, '_elementor_element_cache', true );
	$still_exists = ( '' !== $verify && false !== $verify );
	echo "page {$page_id}: still exists=" . ( $still_exists ? 'yes (FAILURE)' : 'no (SUCCESS)' ) . "\n";
	if ( $still_exists ) {
		$all_ok = false;
	}
}

if ( $all_ok ) {
	echo "\nFINAL RESULT: SUCCESS\n";
} else {
	echo "\nFINAL RESULT: FAILURE - see lines above\n";
	exit( 1 );
}
