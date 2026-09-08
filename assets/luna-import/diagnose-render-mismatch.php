<?php
/**
 * READ-ONLY. The guarded fix (post 57) reported success with an exact
 * DB readback match, but a fresh cache-busted, cache-MISS live fetch
 * still shows the old, unfixed content. Something between the DB and
 * what gets served is stale. Check: does _elementor_data really hold
 * the fix right now? Does post_content (the WP core fallback Elementor
 * also maintains) still hold the old markup? Any relevant transients or
 * an Elementor-specific cache?
 */

global $wpdb;

echo "--- _elementor_data right now ---\n";
$data = $wpdb->get_var( $wpdb->prepare(
	"SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s", 57, '_elementor_data'
) );
echo 'length: ' . strlen( (string) $data ) . "\n";
echo "contains '<h1 class=\\\"ln-hero__wordmark\\\"': " . ( strpos( (string) $data, '<h1 class=\"ln-hero__wordmark\"' ) !== false ? 'YES' : 'no' ) . "\n";
echo "contains 'about-us': " . ( strpos( (string) $data, 'about-us' ) !== false ? 'YES' : 'no' ) . "\n";

echo "\n--- how many wp_postmeta rows for (post_id=57, meta_key=_elementor_data)? ---\n";
$count = $wpdb->get_var( $wpdb->prepare(
	"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s", 57, '_elementor_data'
) );
echo "row count: {$count}\n";
if ( (int) $count > 1 ) {
	$all = $wpdb->get_results( $wpdb->prepare(
		"SELECT meta_id, LENGTH(meta_value) as len FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s", 57, '_elementor_data'
	), ARRAY_A );
	foreach ( $all as $row ) {
		echo "  meta_id={$row['meta_id']} length={$row['len']}\n";
	}
}

echo "\n--- post_content for post 57 (WP core fallback content) ---\n";
$post = get_post( 57 );
echo 'post_content length: ' . strlen( $post->post_content ) . "\n";
echo "post_content contains 'ln-hero__wordmark': " . ( strpos( $post->post_content, 'ln-hero__wordmark' ) !== false ? 'yes' : 'no' ) . "\n";
echo 'post_modified: ' . $post->post_modified . "\n";
echo 'post_modified_gmt: ' . $post->post_modified_gmt . "\n";

echo "\n--- relevant transients ---\n";
$transients = $wpdb->get_results(
	"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '%elementor%' AND option_name LIKE '%transient%' LIMIT 20",
	ARRAY_A
);
foreach ( $transients as $t ) {
	echo '  ' . $t['option_name'] . "\n";
}
if ( empty( $transients ) ) {
	echo "  (none found)\n";
}

echo "\n--- direct fetch from PHP side (server-local, bypasses any external CDN) ---\n";
$response = wp_remote_get( home_url( '/?diag=' . time() ), array( 'timeout' => 15, 'sslverify' => false ) );
if ( is_wp_error( $response ) ) {
	echo 'wp_remote_get error: ' . $response->get_error_message() . "\n";
} else {
	$body = wp_remote_retrieve_body( $response );
	echo 'response length: ' . strlen( $body ) . "\n";
	echo "response contains '<h1 class=\\\"ln-hero__wordmark\\\"': " . ( strpos( $body, '<h1 class="ln-hero__wordmark"' ) !== false ? 'YES' : 'no' ) . "\n";
	echo "response contains 'about-us': " . ( strpos( $body, 'about-us' ) !== false ? 'YES' : 'no' ) . "\n";
}

echo "\nOK: read-only diagnostic complete\n";
