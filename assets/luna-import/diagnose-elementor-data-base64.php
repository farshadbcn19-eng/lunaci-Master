<?php
/**
 * READ-ONLY. Dumps the complete raw _elementor_data for post 56 and
 * post 836 as base64, so the exact bytes can be decoded and tested
 * locally (json_decode is reporting "Syntax error" on both, which
 * needs byte-exact inspection to pin down - log transport escaping
 * has been misleading twice already this session).
 */

global $wpdb;

foreach ( array( 56, 836 ) as $id ) {
	$raw = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_elementor_data'", $id ) );
	echo "===== post {$id} base64 (len=" . strlen( (string) $raw ) . ") =====\n";
	echo base64_encode( (string) $raw ) . "\n";
	echo "===== end post {$id} =====\n\n";
}

echo "OK: read-only diagnostic complete\n";
