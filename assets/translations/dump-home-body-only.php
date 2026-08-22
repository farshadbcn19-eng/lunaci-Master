<?php
global $wpdb;
$post_id = 57;

$raw = $wpdb->get_var( $wpdb->prepare(
	"SELECT meta_value FROM {$wpdb->prefix}postmeta WHERE post_id = %d AND meta_key = '_elementor_data'",
	$post_id
) );
if ( $raw === null ) { echo "ABORT: not found\n"; exit(1); }

$data = json_decode( $raw, true );
$html = $data[0]['elements'][0]['settings']['html'];
echo "full length=" . strlen( $html ) . "\n";

// strip the <style>...</style> block - we only need the body markup for translation.
$body = preg_replace( '#<style>.*?</style>#s', '', $html, 1 );
echo "body-only length=" . strlen( $body ) . "\n";
echo "md5(full)=" . md5( $html ) . "\n";

echo "\n===BASE64_START===\n";
echo chunk_split( base64_encode( $body ), 120, "\n" );
echo "===BASE64_END===\n";
echo "\ndone\n";
