<?php
/**
 * Read-only diagnostic: check whether WPCode caches/mirrors snippet
 * content in the `wpcode_snippets` wp_options row (found in an earlier
 * search alongside post 483), and whether that cached copy still holds
 * the OLD (pre-!important) .prod-img CSS even though post 483's own
 * post_content was confirmed updated in the database. Also dumps
 * post 483's post_modified/post_modified_gmt, since a raw $wpdb->update()
 * does not bump those the way wp_update_post() would - if WPCode keys any
 * cache-busting logic off modified time, that would explain stale output.
 */

global $wpdb;

echo "=== post 483 post_content (fresh read) ===\n";
$row = $wpdb->get_row(
	$wpdb->prepare( "SELECT post_content, post_modified, post_modified_gmt, post_status FROM {$wpdb->posts} WHERE ID = %d", 483 ),
	ARRAY_A
);
if ( ! $row ) {
	echo "ERROR: post 483 not found\n";
} else {
	echo "post_modified: {$row['post_modified']}   post_modified_gmt: {$row['post_modified_gmt']}   status: {$row['post_status']}\n";
	$has_important = false !== strpos( $row['post_content'], 'height: 100% !important' );
	echo "post_content contains 'height: 100% !important': " . ( $has_important ? 'YES' : 'no' ) . "\n";
	$pos = strpos( $row['post_content'], '.prod-img {' );
	if ( false !== $pos ) {
		echo "fragment: " . substr( $row['post_content'], $pos, 200 ) . "\n";
	}
}

echo "\n=== wp_options rows with option_name LIKE '%wpcode%' ===\n";
$options = $wpdb->get_results(
	"SELECT option_name, LENGTH(option_value) AS len FROM {$wpdb->options} WHERE option_name LIKE '%wpcode%'",
	ARRAY_A
);
foreach ( $options as $opt ) {
	echo "{$opt['option_name']}  (length {$opt['len']})\n";
}

echo "\n=== searching each wpcode-related option's value for '.prod-img' ===\n";
foreach ( $options as $opt ) {
	$val = $wpdb->get_var(
		$wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $opt['option_name'] )
	);
	if ( null === $val ) continue;
	$count = substr_count( $val, '.prod-img' );
	if ( $count > 0 ) {
		echo "--- {$opt['option_name']}: {$count} occurrence(s) of '.prod-img' ---\n";
		$pos = strpos( $val, '.prod-img' );
		// dump a window, and specifically check for !important nearby
		$window = substr( $val, max( 0, $pos - 50 ), 500 );
		echo "window: " . $window . "\n";
		echo "contains 'height: 100% !important' in this option: " . ( false !== strpos( $val, 'height: 100% !important' ) ? 'YES' : 'no' ) . "\n\n";
	} else {
		echo "{$opt['option_name']}: no occurrence of '.prod-img'\n";
	}
}

echo "\n=== searching for ANY other wp_posts rows (post_type='wpcode') containing '.prod-img' besides 483 ===\n";
$other = $wpdb->get_results(
	"SELECT ID, post_title, post_status, post_modified FROM {$wpdb->posts} WHERE post_type = 'wpcode' AND post_content LIKE '%.prod-img%'",
	ARRAY_A
);
foreach ( $other as $o ) {
	echo "post {$o['ID']} '{$o['post_title']}' status={$o['post_status']} modified={$o['post_modified']}\n";
}

echo "\nOK: read-only diagnostic complete, no writes performed\n";
