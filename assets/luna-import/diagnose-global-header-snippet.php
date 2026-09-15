<?php
/**
 * Read-only: locate the WPCode "LUNACI Global Header (unified nav)" snippet
 * by content/title search instead of assuming a specific wp_posts ID (the
 * prior assumption of ID=8 was wrong - no such wp_posts row exists).
 * WPCode Lite stores snippets as post_type 'wpcode' in wp_posts.
 */

global $wpdb;

echo "--- All wp_posts rows with post_type='wpcode' ---\n";
$rows = $wpdb->get_results(
	"SELECT ID, post_title, post_status, LENGTH(post_content) AS content_len FROM {$wpdb->posts} WHERE post_type = 'wpcode' ORDER BY ID",
	ARRAY_A
);
if ( ! $rows ) {
	echo "no rows with post_type='wpcode' found\n";
} else {
	foreach ( $rows as $r ) {
		echo "ID={$r['ID']}  status={$r['post_status']}  len={$r['content_len']}  title=\"{$r['post_title']}\"\n";
	}
}

echo "\n--- Search ALL post types for '.site-header' AND 'nav-cta'/'lp-header' markers ---\n";
$candidates = $wpdb->get_results(
	"SELECT ID, post_type, post_title, post_status, LENGTH(post_content) AS content_len
	 FROM {$wpdb->posts}
	 WHERE post_content LIKE '%.site-header%'
	    OR post_content LIKE '%lunaciGlobalNav%'
	    OR post_content LIKE '%ln-nav%'
	 ORDER BY ID",
	ARRAY_A
);
if ( ! $candidates ) {
	echo "no candidates found\n";
} else {
	foreach ( $candidates as $r ) {
		echo "ID={$r['ID']}  type={$r['post_type']}  status={$r['post_status']}  len={$r['content_len']}  title=\"{$r['post_title']}\"\n";
	}
}

echo "\nOK: read-only diagnostic complete, no writes performed\n";
