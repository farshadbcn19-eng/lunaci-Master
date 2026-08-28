<?php
/**
 * Read-only-then-guarded: the earlier fix-elementor-element-cache.php run
 * confirmed the key was deleted (STEP C: "still exists=no"), and a
 * litespeed purge was issued right after, yet a curl re-check ~20s later
 * still showed the OLD stale content with x-litespeed-cache: hit. This
 * checks the CURRENT state precisely - has _elementor_element_cache
 * regenerated already (and with what content), and does _elementor_data
 * still correctly contain the new media query - then re-deletes the
 * cache key if it has reappeared, guarded the same way as before.
 */

$pages = array(
	59  => 'EN About Us',
	680 => 'ES About Us (Sobre Nosotros)',
);

$marker = '@media(min-width:1025px){.lna-hero{align-items:center;}}';

echo "=== 1. Current _elementor_data state ===\n";
foreach ( $pages as $page_id => $label ) {
	$raw = get_post_meta( $page_id, '_elementor_data', true );
	$has_marker = is_string( $raw ) && false !== strpos( $raw, $marker );
	echo "page {$page_id} ({$label}): _elementor_data length=" . ( is_string( $raw ) ? strlen( $raw ) : 'N/A' ) . " contains_new_marker=" . ( $has_marker ? 'yes' : 'no' ) . "\n";
}

echo "\n=== 2. Current _elementor_element_cache state ===\n";
$to_delete = array();
foreach ( $pages as $page_id => $label ) {
	$cached = get_post_meta( $page_id, '_elementor_element_cache', true );
	$exists = ( '' !== $cached && false !== $cached );
	$cached_str = is_string( $cached ) ? $cached : ( is_array( $cached ) ? serialize( $cached ) : '' );
	$has_marker_in_cache = $exists && false !== strpos( $cached_str, $marker );
	echo "page {$page_id} ({$label}): exists=" . ( $exists ? 'yes' : 'no' ) . " length=" . ( $exists ? strlen( $cached_str ) : 0 ) . " contains_new_marker=" . ( $has_marker_in_cache ? 'yes' : 'no' ) . "\n";
	if ( $exists ) {
		$to_delete[] = $page_id;
	}
}

echo "\n=== 3. Delete again if present ===\n";
if ( empty( $to_delete ) ) {
	echo "OK: no pages currently have _elementor_element_cache - nothing to delete\n";
} else {
	foreach ( $to_delete as $page_id ) {
		$deleted = delete_post_meta( $page_id, '_elementor_element_cache' );
		echo "page {$page_id}: delete_post_meta() returned: " . var_export( $deleted, true ) . "\n";
		clean_post_cache( $page_id );
	}
	wp_cache_flush();
	echo "OK: deleted and caches cleared\n";
}

echo "\n=== 4. Verify deletion ===\n";
$all_gone = true;
foreach ( $pages as $page_id => $label ) {
	$verify = get_post_meta( $page_id, '_elementor_element_cache', true );
	$still_exists = ( '' !== $verify && false !== $verify );
	echo "page {$page_id}: still exists=" . ( $still_exists ? 'yes' : 'no' ) . "\n";
	if ( $still_exists ) {
		$all_gone = false;
	}
}

echo "\n=== 5. Check for _elementor_css / CSS file caching ===\n";
foreach ( $pages as $page_id => $label ) {
	$css_meta = get_post_meta( $page_id, '_elementor_css', true );
	echo "page {$page_id}: _elementor_css meta " . ( $css_meta ? 'EXISTS (length ' . ( is_string( $css_meta ) ? strlen( $css_meta ) : strlen( serialize( $css_meta ) ) ) . ')' : 'not present' ) . "\n";
}
$upload_dir = wp_upload_dir();
$elementor_css_dir = $upload_dir['basedir'] . '/elementor/css';
echo "elementor css dir: {$elementor_css_dir} exists=" . ( is_dir( $elementor_css_dir ) ? 'yes' : 'no' ) . "\n";
if ( is_dir( $elementor_css_dir ) ) {
	foreach ( array( 59, 680 ) as $page_id ) {
		$css_file = $elementor_css_dir . "/post-{$page_id}.css";
		if ( file_exists( $css_file ) ) {
			echo "  post-{$page_id}.css EXISTS, size=" . filesize( $css_file ) . " bytes, modified=" . date( 'Y-m-d H:i:s', filemtime( $css_file ) ) . "\n";
		} else {
			echo "  post-{$page_id}.css not present\n";
		}
	}
}

echo "\nOK: diagnostic complete\n";
if ( $all_gone ) {
	echo "FINAL RESULT: SUCCESS\n";
} else {
	echo "FINAL RESULT: FAILURE\n";
	exit( 1 );
}
