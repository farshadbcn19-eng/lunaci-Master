<?php
/**
 * Read-only diagnostic: PR #247's fix reported SUCCESS (STEP C read-back
 * confirmed new CSS present, old gone) for post 59, and a LiteSpeed
 * cache-miss (freshly-generated, non-cached) HTML response minutes later
 * STILL shows the OLD CSS text. This checks the CURRENT state of post
 * 59's _elementor_data directly, right now, to see whether our change was
 * somehow reverted after the fix ran, or whether something else entirely
 * is going on (e.g. Elementor reading from a different data source than
 * raw postmeta for rendering).
 */

global $wpdb;

$page_id = 59;

$raw = $wpdb->get_var(
	$wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_elementor_data'", $page_id )
);
if ( null === $raw ) {
	echo "ERROR: _elementor_data not found for post {$page_id}\n";
	exit( 1 );
}

echo "raw _elementor_data length: " . strlen( $raw ) . "\n";
echo "contains new '#lna{width:100vw': " . ( false !== strpos( $raw, '#lna{width:100vw' ) ? 'YES' : 'no' ) . "\n";
echo "contains old '#lna{width:100%;background:#0B0B0B;overflow:hidden;}': " . ( false !== strpos( $raw, '#lna{width:100%;background:#0B0B0B;overflow:hidden;}' ) ? 'YES' : 'no' ) . "\n";
echo "contains new 'padding:0 5% 12vh': " . ( false !== strpos( $raw, 'padding:0 5% 12vh' ) ? 'YES' : 'no' ) . "\n";
echo "contains old 'padding:0 5% 4vh': " . ( false !== strpos( $raw, 'padding:0 5% 4vh' ) ? 'YES' : 'no' ) . "\n";

// Also check via get_post_meta() (goes through WP's meta cache API) to see if it differs from raw DB
$via_api = get_post_meta( $page_id, '_elementor_data', true );
echo "\nvia get_post_meta() length: " . strlen( $via_api ) . "\n";
echo "via API contains new '#lna{width:100vw': " . ( false !== strpos( $via_api, '#lna{width:100vw' ) ? 'YES' : 'no' ) . "\n";
echo "raw === via_api: " . ( $raw === $via_api ? 'YES (identical)' : 'NO (differ!)' ) . "\n";

// Check post_modified to see if anything touched this post since our fix ran
$post = get_post( $page_id );
echo "\npost_modified: {$post->post_modified}   post_modified_gmt: {$post->post_modified_gmt}   post_status: {$post->post_status}\n";

// Check if there's a more recent revision that might be involved
$revisions = wp_get_post_revisions( $page_id, array( 'numberposts' => 3 ) );
echo "\nrecent revisions:\n";
foreach ( $revisions as $rev ) {
	echo "  revision {$rev->ID}  modified={$rev->post_modified}\n";
}

// Double-check: is there possibly a SEPARATE postmeta row (duplicate key) for the same post?
$all_rows = $wpdb->get_results(
	$wpdb->prepare( "SELECT meta_id, LENGTH(meta_value) AS len FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_elementor_data'", $page_id ),
	ARRAY_A
);
echo "\nnumber of _elementor_data rows for post {$page_id}: " . count( $all_rows ) . "\n";
foreach ( $all_rows as $row ) {
	echo "  meta_id={$row['meta_id']} length={$row['len']}\n";
}

echo "\nOK: read-only diagnostic complete, no writes performed\n";
