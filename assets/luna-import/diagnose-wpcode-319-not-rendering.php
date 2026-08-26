<?php
/**
 * Read-only: the direct $wpdb->update() write to post 319's post_content
 * (adding a desktop-only media query for .lna-hero__content) was verified
 * correct in the DB (STEP C passed), but a live fetch of /about-us/ and
 * /es/about-us-es/ minutes later + after wp litespeed-purge all shows NO
 * trace of the new media query - the exact same WPCode-caching pattern
 * seen with the earlier Blusher CSS fix (PR #237), where WPCode mirrors
 * snippet code into a SEPARATE cache independent of post_content.
 *
 * Since bypassing wp_update_post() (forced, because WPCode blocks it for
 * its own CPT) may have also skipped whatever hook normally regenerates
 * WPCode's cached/compiled output on save, check every place that output
 * could be cached: transients, any generated static CSS files on disk,
 * and re-confirm the DB write is actually still there (rule out a revert).
 */

global $wpdb;

echo "=== 1. Re-confirm post 319 post_content currently in DB ===\n";
$post = get_post( 319 );
if ( $post ) {
	$has_media_query = false !== strpos( $post->post_content, '@media (min-width:1025px)' );
	echo "post_content length: " . strlen( $post->post_content ) . "\n";
	echo "contains new media query: " . ( $has_media_query ? 'yes' : 'no' ) . "\n";
	echo "full content:\n---BEGIN---\n{$post->post_content}\n---END---\n";
} else {
	echo "post 319 not found\n";
}

echo "\n=== 2. wp_options: any transients mentioning 'wpcode' ===\n";
$transients = $wpdb->get_results(
	"SELECT option_name, LENGTH(option_value) AS len, autoload FROM {$wpdb->options} WHERE option_name LIKE '%wpcode%' ORDER BY option_name",
	ARRAY_A
);
echo "matches: " . count( $transients ) . "\n";
foreach ( $transients as $row ) {
	echo "  {$row['option_name']} (length {$row['len']}, autoload {$row['autoload']})\n";
}

echo "\n=== 3. wp_options: search for the OLD css fragment (8vh, no media query) anywhere ===\n";
$old_frag = '.lna-hero__ov2{';
$old_rows = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT option_id, option_name, LENGTH(option_value) AS len FROM {$wpdb->options} WHERE option_value LIKE %s",
		'%' . $wpdb->esc_like( $old_frag ) . '%'
	),
	ARRAY_A
);
echo "matches: " . count( $old_rows ) . "\n";
foreach ( $old_rows as $row ) {
	echo "  option_id={$row['option_id']} name={$row['option_name']} length={$row['len']}\n";
}

echo "\n=== 4. wp_postmeta: search for the same fragment (any post) ===\n";
$meta_rows = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT meta_id, post_id, meta_key, LENGTH(meta_value) AS len FROM {$wpdb->postmeta} WHERE meta_value LIKE %s",
		'%' . $wpdb->esc_like( $old_frag ) . '%'
	),
	ARRAY_A
);
echo "matches: " . count( $meta_rows ) . "\n";
foreach ( $meta_rows as $row ) {
	echo "  meta_id={$row['meta_id']} post_id={$row['post_id']} meta_key={$row['meta_key']} length={$row['len']}\n";
}

echo "\n=== 5. Direct-origin fetch of /about-us/ from the server itself (bypass any external CDN) ===\n";
$response = wp_remote_get(
	home_url( '/about-us/?directfetchcheck=' . time() ),
	array(
		'timeout'   => 20,
		'sslverify' => false,
	)
);
if ( is_wp_error( $response ) ) {
	echo "wp_remote_get failed: " . $response->get_error_message() . "\n";
} else {
	$body = wp_remote_retrieve_body( $response );
	echo "response code: " . wp_remote_retrieve_response_code( $response ) . "\n";
	echo "body length: " . strlen( $body ) . "\n";
	echo "contains new media query: " . ( false !== strpos( $body, '@media (min-width:1025px)' ) ? 'yes' : 'no' ) . "\n";
	echo "contains old 8vh rule: " . ( false !== strpos( $body, 'padding:0 5% 8vh !important' ) ? 'yes' : 'no' ) . "\n";
	$headers = wp_remote_retrieve_headers( $response );
	echo "x-litespeed-cache header: " . ( isset( $headers['x-litespeed-cache'] ) ? $headers['x-litespeed-cache'] : 'not set' ) . "\n";
}

echo "\nOK: read-only diagnostic complete, no writes performed\n";
