<?php
/**
 * Read-only diagnostic: post 59's _elementor_data is 100% confirmed correct
 * in the database, and the live page keeps serving the OLD CSS text even on
 * (a) fresh LiteSpeed cache misses, (b) direct-origin requests bypassing any
 * CDN, (c) after a manual "Purge All" click through an authenticated
 * wp-admin session. This rules out every caching layer checked so far.
 *
 * New theory: since this site has NO persistent object cache (confirmed
 * earlier), WordPress transients fall back to being stored as literal rows
 * in wp_options - a storage mode `wp_cache_flush()` does NOT clear (it only
 * clears the object-cache layer, which isn't in play here). If Elementor (or
 * any plugin) caches a rendered/parsed copy of this widget's output into a
 * transient, that stale copy would silently survive everything we've tried.
 *
 * This does a brute-force search across wp_options for the OLD CSS fragment
 * text, to find exactly where a stale copy might be sitting - and also
 * checks wp_postmeta broadly (not just post 59) in case a duplicate/cached
 * copy lives under a different post ID (e.g. an Elementor "library" template
 * or a revision).
 */

global $wpdb;

$old_fragment = '#lna{width:100%;background:#0B0B0B;overflow:hidden;}';

echo "=== searching wp_options for the old CSS fragment ===\n";
$option_rows = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT option_id, option_name, LENGTH(option_value) AS len, autoload FROM {$wpdb->options} WHERE option_value LIKE %s",
		'%' . $wpdb->esc_like( $old_fragment ) . '%'
	),
	ARRAY_A
);
echo "matches: " . count( $option_rows ) . "\n";
foreach ( $option_rows as $row ) {
	echo "  option_id={$row['option_id']} name={$row['option_name']} length={$row['len']} autoload={$row['autoload']}\n";
}

echo "\n=== searching ALL wp_postmeta rows (any post) for the old CSS fragment ===\n";
$meta_rows = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT meta_id, post_id, meta_key, LENGTH(meta_value) AS len FROM {$wpdb->postmeta} WHERE meta_value LIKE %s",
		'%' . $wpdb->esc_like( $old_fragment ) . '%'
	),
	ARRAY_A
);
echo "matches: " . count( $meta_rows ) . "\n";
foreach ( $meta_rows as $row ) {
	$post = get_post( $row['post_id'] );
	echo "  meta_id={$row['meta_id']} post_id={$row['post_id']} meta_key={$row['meta_key']} length={$row['len']} post_title=" . ( $post ? $post->post_title : 'N/A' ) . " post_type=" . ( $post ? $post->post_type : 'N/A' ) . " post_status=" . ( $post ? $post->post_status : 'N/A' ) . "\n";
}

echo "\n=== searching wp_posts.post_content (any post) for the old CSS fragment ===\n";
$post_rows = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT ID, post_title, post_type, post_status, LENGTH(post_content) AS len FROM {$wpdb->posts} WHERE post_content LIKE %s",
		'%' . $wpdb->esc_like( $old_fragment ) . '%'
	),
	ARRAY_A
);
echo "matches: " . count( $post_rows ) . "\n";
foreach ( $post_rows as $row ) {
	echo "  ID={$row['ID']} title={$row['post_title']} type={$row['post_type']} status={$row['post_status']} length={$row['len']}\n";
}

echo "\n=== any transient options containing 'elementor' and '59' in the name ===\n";
$transient_rows = $wpdb->get_results(
	"SELECT option_name, LENGTH(option_value) AS len FROM {$wpdb->options} WHERE option_name LIKE '%transient%elementor%' OR (option_name LIKE '%elementor%' AND option_name LIKE '%59%')",
	ARRAY_A
);
echo "matches: " . count( $transient_rows ) . "\n";
foreach ( $transient_rows as $row ) {
	echo "  {$row['option_name']}  (length {$row['len']})\n";
}

echo "\nOK: read-only diagnostic complete, no writes performed\n";
