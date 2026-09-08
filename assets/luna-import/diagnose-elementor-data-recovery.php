<?php
/**
 * READ-ONLY. Emergency triage: the previous diagnostic's "safe, reversible"
 * write+restore to post 57's _elementor_data was NOT actually reversible -
 * something strips content on write, and the live homepage's hero section
 * is now confirmed missing (verified via a fresh curl fetch: page dropped
 * from ~102KB to ~88.7KB, zero occurrences of "ln-hero__wordmark" left).
 * This looks for any recovery source WITHOUT writing anything.
 */

global $wpdb;

$post_id = 57;

echo "--- current _elementor_data state ---\n";
$current = get_post_meta( $post_id, '_elementor_data', true );
echo 'current length: ' . strlen( (string) $current ) . "\n";
echo "contains 'ln-hero__wordmark': " . ( strpos( (string) $current, 'ln-hero__wordmark' ) !== false ? 'yes' : 'NO - missing' ) . "\n";
echo "contains 'ln-nav': " . ( strpos( (string) $current, 'ln-nav' ) !== false ? 'yes' : 'NO - missing' ) . "\n";

echo "\n--- post revisions for post 57 ---\n";
$revisions = wp_get_post_revisions( $post_id );
echo 'revision count: ' . count( $revisions ) . "\n";
foreach ( $revisions as $rev ) {
	$rev_data = get_post_meta( $rev->ID, '_elementor_data', true );
	echo "  revision {$rev->ID} (modified {$rev->post_modified}): _elementor_data length = " . strlen( (string) $rev_data );
	echo $rev_data ? ( strpos( (string) $rev_data, 'ln-hero__wordmark' ) !== false ? ' [HAS hero wordmark - candidate]' : ' [no hero wordmark]' ) : ' [empty]';
	echo "\n";
}

echo "\n--- postmeta history table check (some backup/versioning plugins use one) ---\n";
$candidate_tables = array( 'wp_elementor_revisions', 'wp_actionscheduler_actions' );
foreach ( $candidate_tables as $t ) {
	$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $t ) ) );
	echo "table {$t}: " . ( $exists ? 'exists' : 'not found' ) . "\n";
}

echo "\n--- any autosave for post 57 ---\n";
$autosave = wp_get_post_autosave( $post_id );
if ( $autosave ) {
	$as_data = get_post_meta( $autosave->ID, '_elementor_data', true );
	echo 'autosave found, id=' . $autosave->ID . ', _elementor_data length=' . strlen( (string) $as_data ) . "\n";
} else {
	echo "no autosave found\n";
}

echo "\n--- raw current _elementor_data, first 3000 chars (to see exactly what remains) ---\n";
echo substr( (string) $current, 0, 3000 ) . "\n";

echo "\nOK: read-only recovery triage complete\n";
