<?php
/**
 * Read-only diagnostic: determine whether the Products page's WPCode CSS
 * snippet (post 483, which holds ALL of the recent visual fixes - hero
 * banner swap, Ken-Burns effect, full-bleed container, ingredients image
 * URL) is scoped to load on EVERY page/URL (in which case it already
 * applies equally to the ES translation, post 771, with no further fix
 * needed) or scoped only to the specific EN post ID 61 (in which case the
 * ES Products page would be missing all of these CSS fixes - a different
 * kind of staleness bug than the Home page one). Also live-fetches both
 * the EN and ES Products page URLs to see literally what each serves.
 * No writes are performed.
 */

global $wpdb;

echo "=====================================================================\n";
echo "PART 1: WPCode snippet post 483 - full metadata dump\n";
echo "=====================================================================\n";

$snippet = get_post( 483 );
if ( ! $snippet ) {
	echo "ERROR: post 483 not found.\n";
	exit( 1 );
}
echo "post_type: {$snippet->post_type}\n";
echo "post_status: {$snippet->post_status}\n";
echo "post_title: {$snippet->post_title}\n";
echo "post_content length: " . strlen( $snippet->post_content ) . "\n\n";

$all_meta = get_post_meta( 483 );
echo "All postmeta keys for post 483:\n";
foreach ( $all_meta as $key => $values ) {
	foreach ( $values as $v ) {
		$display = strlen( $v ) > 200 ? substr( $v, 0, 200 ) . '...(truncated)' : $v;
		echo "  {$key} = {$display}\n";
	}
}

echo "\n=====================================================================\n";
echo "PART 2: Products page (EN 61 / ES 771) - _elementor_data comparison\n";
echo "=====================================================================\n";
$en_data = get_post_meta( 61, '_elementor_data', true );
$es_data = get_post_meta( 771, '_elementor_data', true );
echo "EN post 61 _elementor_data length: " . strlen( is_string( $en_data ) ? $en_data : '' ) . "\n";
echo "ES post 771 _elementor_data length: " . strlen( is_string( $es_data ) ? $es_data : '' ) . "\n";
echo "Are they byte-identical? " . ( $en_data === $es_data ? 'yes' : 'no' ) . "\n";

echo "\n=====================================================================\n";
echo "PART 3: Live HTTP fetch - Products page EN vs ES (from the server itself)\n";
echo "=====================================================================\n";

$en_url = get_permalink( 61 );
$es_url = get_permalink( 771 );
echo "EN Products URL: {$en_url}\n";
echo "ES Products URL: {$es_url}\n";

foreach ( array( 'EN' => $en_url, 'ES' => $es_url ) as $label => $url ) {
	echo "\n--- Fetching {$label}: {$url} ---\n";
	$response = wp_remote_get( $url, array( 'timeout' => 15, 'redirection' => 5, 'headers' => array( 'Cache-Control' => 'no-cache' ) ) );
	if ( is_wp_error( $response ) ) {
		echo "ERROR: " . $response->get_error_message() . "\n";
		continue;
	}
	$code = wp_remote_retrieve_response_code( $response );
	$body = wp_remote_retrieve_body( $response );
	echo "HTTP status: {$code}\n";
	echo "body length: " . strlen( $body ) . "\n";

	foreach ( array(
		'e-con-boxed'                                    => 'full-bleed fix marker (elementor-element-0329089.e-con-boxed)',
		'lpHeroKB'                                        => 'Ken-Burns keyframe name',
		'lunaimport-product-hero-luna-1.jpg'              => 'current hero banner image filename',
		'lunaimport-products-ingredients-luna-1.jpg'      => 'current ingredients image filename',
	) as $marker => $desc ) {
		$present = ( false !== stripos( $body, $marker ) );
		echo "  contains \"{$marker}\" ({$desc}): " . ( $present ? 'yes' : 'no' ) . "\n";
	}

	if ( preg_match( '/<html[^>]*lang=["\']?([a-zA-Z-]+)/i', $body, $m ) ) {
		echo "  html lang attribute: {$m[1]}\n";
	}
}

echo "\nOK: read-only Products ES-scope diagnostic complete, no writes performed\n";
