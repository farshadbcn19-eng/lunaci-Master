<?php
/**
 * READ-ONLY. The picture-webp fix reported "wrapped 7 <img> tags" for
 * both posts 57 and 772 (meaning the in-memory str_replace worked and
 * $wpdb->update() did not return false), but the readback via
 * get_post_meta() immediately after showed no "<picture>" substring.
 * Bypass WordPress's object/meta cache entirely and query
 * wp_postmeta.meta_value directly to determine whether the write
 * actually persisted to the database.
 */

global $wpdb;

foreach ( array( 57, 772 ) as $post_id ) {
	$row = $wpdb->get_row( $wpdb->prepare(
		"SELECT meta_id, LENGTH(meta_value) AS len FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_elementor_data'",
		$post_id
	) );
	echo "Post $post_id: ";
	if ( ! $row ) {
		echo "NO ROW FOUND for _elementor_data\n";
		continue;
	}
	echo "meta_id={$row->meta_id} length={$row->len}\n";

	$raw_direct = $wpdb->get_var( $wpdb->prepare(
		"SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_elementor_data'",
		$post_id
	) );
	echo '  direct DB query contains "<picture>": ' . ( strpos( $raw_direct, '<picture>' ) !== false ? 'YES' : 'NO' ) . "\n";
	echo '  direct DB query contains "lunaimport-hero-luna.webp": ' . ( strpos( $raw_direct, 'lunaimport-hero-luna.webp' ) !== false ? 'YES' : 'NO' ) . "\n";

	// Also check via get_post_meta() (goes through WP's cache layer).
	$via_api = get_post_meta( $post_id, '_elementor_data', true );
	echo '  get_post_meta() contains "<picture>": ' . ( strpos( $via_api, '<picture>' ) !== false ? 'YES' : 'NO' ) . "\n";
	echo '  direct === via_api: ' . ( $raw_direct === $via_api ? 'YES (same)' : 'NO (differ, length ' . strlen( $raw_direct ) . ' vs ' . strlen( $via_api ) . ')' ) . "\n";
}

echo "\nOK: read-only diagnostic complete\n";
