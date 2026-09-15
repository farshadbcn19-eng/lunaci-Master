<?php
/**
 * Read-only: dump full content of the two unidentified PUBLISHED WPCode
 * snippets (319 "Untitled Snippet", 407 "LUNACI Shop Design") to find which
 * one (if either) injects the global unified nav (#lunaciGlobalNav / .ln-nav)
 * seen on every live page, since the prior assumption of "row id=8" was
 * confirmed wrong (no such row exists) and neither wpcode row is literally
 * titled "Global Header".
 */

global $wpdb;

foreach ( array( 319, 407 ) as $post_id ) {
	echo "=====================================================================\n";
	echo "post {$post_id}\n";
	echo "=====================================================================\n";
	$row = $wpdb->get_row(
		$wpdb->prepare( "SELECT ID, post_title, post_status, post_content FROM {$wpdb->posts} WHERE ID = %d", $post_id ),
		ARRAY_A
	);
	if ( ! $row ) {
		echo "no row found\n\n";
		continue;
	}
	echo "title: {$row['post_title']}\n";
	echo "status: {$row['post_status']}\n";
	echo "length: " . strlen( $row['post_content'] ) . "\n";

	// WPCode-specific meta: where it's injected (e.g. wp_footer, everywhere, etc.)
	$meta = get_post_meta( $post_id );
	foreach ( $meta as $key => $vals ) {
		if ( false !== stripos( $key, 'wpcode' ) || false !== stripos( $key, 'location' ) || false !== stripos( $key, 'auto_insert' ) || false !== stripos( $key, 'active' ) ) {
			echo "meta[{$key}] = " . implode( ' | ', $vals ) . "\n";
		}
	}

	echo "\n--- FULL CONTENT ---\n";
	echo $row['post_content'] . "\n";
	echo "--- END CONTENT ---\n\n";
}

echo "OK: read-only diagnostic complete, no writes performed\n";
