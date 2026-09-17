<?php
/**
 * READ-ONLY. Both post 56 and post 836's _elementor_data fail
 * json_decode() with "Syntax error" via get_post_meta(), and Elementor's
 * own get_builder_content_for_display() returns empty for both. Dumps
 * the raw bytes straight from $wpdb (bypassing any meta-cache layer) so
 * the actual stored content can be inspected byte-for-byte.
 */

global $wpdb;

foreach ( array( 56, 836 ) as $id ) {
	echo "===== post {$id} raw wp_postmeta row =====\n";
	$row = $wpdb->get_row(
		$wpdb->prepare( "SELECT meta_id, LENGTH(meta_value) as len FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_elementor_data'", $id ),
		ARRAY_A
	);
	if ( ! $row ) {
		echo "no _elementor_data row found\n\n";
		continue;
	}
	$raw = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_id = %d", $row['meta_id'] ) );
	echo "meta_id={$row['meta_id']} len={$row['len']} (actual strlen=" . strlen( $raw ) . ")\n";
	echo 'first 80 bytes (var_export): ' . var_export( substr( $raw, 0, 80 ), true ) . "\n";
	echo 'last 80 bytes (var_export): ' . var_export( substr( $raw, -80 ), true ) . "\n";

	$decoded = json_decode( $raw, true );
	echo 'direct json_decode on raw $wpdb value - error: ' . json_last_error_msg() . "\n";
	echo 'decoded type: ' . gettype( $decoded ) . ( is_array( $decoded ) ? ', count=' . count( $decoded ) : '' ) . "\n";

	// Compare to what get_post_meta() returns for the same post/key.
	$via_api = get_post_meta( $id, '_elementor_data', true );
	echo 'get_post_meta() length: ' . strlen( (string) $via_api ) . "\n";
	echo 'raw === via_api: ' . var_export( $raw === $via_api, true ) . "\n";
	if ( $raw !== $via_api ) {
		echo 'first mismatch context (raw): ' . var_export( substr( $raw, 0, 120 ), true ) . "\n";
		echo 'first mismatch context (api): ' . var_export( substr( (string) $via_api, 0, 120 ), true ) . "\n";
	}
	echo "\n";
}

echo "OK: read-only diagnostic complete\n";
