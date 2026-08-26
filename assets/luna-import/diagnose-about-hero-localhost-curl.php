<?php
/**
 * Read-only diagnostic: post 59's _elementor_data is 100% confirmed
 * correct in the DB, url_to_postid() confirms /about-us/ maps to post 59,
 * yet every external fetch (from GitHub Actions, via Playwright) of the
 * live URL keeps returning the OLD CSS text, even on fresh
 * (x-litespeed-cache: miss) responses, minutes and multiple purges later.
 *
 * This tests the ONE remaining explanation: an external caching/CDN layer
 * sitting in front of this origin server, which our server-side
 * `wp litespeed-purge all` command cannot reach. By using wp_remote_get()
 * to fetch the SAME URL from the server itself, and separately rendering
 * the page's content programmatically via WordPress's own query/template
 * system (bypassing HTTP entirely), we can determine whether the origin
 * itself is fine (confirming an external layer is the culprit) or whether
 * something is wrong even at the origin.
 */

// Test 1: fetch the live URL from the server itself via HTTP
$response = wp_remote_get( 'https://lunacibarcelona.com/about-us/?localtest=' . time(), array(
	'timeout'   => 20,
	'sslverify' => false,
) );

if ( is_wp_error( $response ) ) {
	echo "wp_remote_get ERROR: " . $response->get_error_message() . "\n";
} else {
	$body = wp_remote_retrieve_body( $response );
	$headers = wp_remote_retrieve_headers( $response );
	echo "=== Test 1: wp_remote_get() from server itself ===\n";
	echo "status: " . wp_remote_retrieve_response_code( $response ) . "\n";
	echo "x-litespeed-cache header: " . ( isset( $headers['x-litespeed-cache'] ) ? $headers['x-litespeed-cache'] : 'N/A' ) . "\n";
	echo "body length: " . strlen( $body ) . "\n";
	echo "contains new '#lna{width:100vw': " . ( false !== strpos( $body, '#lna{width:100vw' ) ? 'YES' : 'no' ) . "\n";
	echo "contains old '#lna{width:100%;background:#0B0B0B;overflow:hidden;}': " . ( false !== strpos( $body, '#lna{width:100%;background:#0B0B0B;overflow:hidden;}' ) ? 'YES' : 'no' ) . "\n";
}

// Test 2: render post 59's content directly via WordPress's own content filter, no HTTP at all
echo "\n=== Test 2: direct in-process render via apply_filters('the_content', ...) ===\n";
$post = get_post( 59 );
if ( $post ) {
	global $post;
	$GLOBALS['post'] = get_post( 59 );
	setup_postdata( $GLOBALS['post'] );
	$content = apply_filters( 'the_content', $GLOBALS['post']->post_content );
	wp_reset_postdata();

	echo "rendered content length: " . strlen( $content ) . "\n";
	echo "contains new '#lna{width:100vw': " . ( false !== strpos( $content, '#lna{width:100vw' ) ? 'YES' : 'no' ) . "\n";
	echo "contains old '#lna{width:100%;background:#0B0B0B;overflow:hidden;}': " . ( false !== strpos( $content, '#lna{width:100%;background:#0B0B0B;overflow:hidden;}' ) ? 'YES' : 'no' ) . "\n";
} else {
	echo "post 59 not found\n";
}

echo "\nOK: read-only diagnostic complete, no writes performed\n";
