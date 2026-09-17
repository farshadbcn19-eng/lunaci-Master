<?php
/**
 * Guarded fix: the /all-products/ page (post 836) renders an empty
 * .page-content div. Root cause found via
 * diagnose-elementor-data-base64.php: update_post_meta() runs
 * wp_unslash() on the value before storing, which silently strips the
 * backslashes out of the \" escapes that wp_json_encode() put around
 * the shortcode's own quoted attributes (limit=\"-1\" etc.) - so the
 * stored _elementor_data is invalid JSON (confirmed locally: PHP's
 * json_decode() reports "Syntax error" on the live bytes). The
 * earlier fix-shop-collection-links.php script avoided this because
 * it wrote via $wpdb->update() directly, bypassing wp_unslash()
 * entirely - this script does the same for post 836.
 *
 * (Note: post 56, the original WooCommerce "Shop" page, has the exact
 * same corruption in its own pre-existing _elementor_data - that's
 * unrelated to this session's changes and out of scope here.)
 */

global $wpdb;

$post_id = 836;

echo "--- STEP A: PREPARE ---\n";

$post = get_post( $post_id );
if ( ! $post || 'all-products' !== $post->post_name || 'page' !== $post->post_type ) {
	echo "ABORT: post {$post_id} is not the expected all-products page\n";
	exit( 1 );
}
echo "OK: post {$post_id} is the all-products page (status={$post->post_status})\n";

$current_raw = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_elementor_data'", $post_id ) );
if ( null === $current_raw ) {
	echo "ABORT: no _elementor_data row for post {$post_id}\n";
	exit( 1 );
}
json_decode( $current_raw );
$current_is_valid = ( JSON_ERROR_NONE === json_last_error() );
echo 'current _elementor_data is valid JSON: ' . var_export( $current_is_valid, true ) . ' (' . json_last_error_msg() . ")\n";

if ( $current_is_valid ) {
	echo "OK: nothing to repair - current data already parses as valid JSON\n";
	echo "FINAL RESULT: SUCCESS (no-op)\n";
	exit( 0 );
}

$original_hash = md5( $current_raw );

// Rebuild the exact same structure fix-shop-all-button-real-grid.php intended,
// then write it with $wpdb->update() directly - bypassing update_post_meta()'s
// wp_unslash() so the \" escapes survive.
$elementor_data = array(
	array(
		'id'       => 'a11f001',
		'elType'   => 'container',
		'settings' => new stdClass(),
		'elements' => array(
			array(
				'id'       => 'a11f002',
				'elType'   => 'container',
				'settings' => array(
					'flex_direction' => 'column',
					'content_width'  => 'full',
				),
				'elements' => array(
					array(
						'id'         => 'a11f003',
						'elType'     => 'widget',
						'settings'   => array(
							'shortcode' => '[products limit="-1" columns="4"]',
						),
						'elements'   => array(),
						'widgetType' => 'shortcode',
					),
				),
				'isInner'  => true,
			),
		),
		'isInner'  => false,
	),
);

$new_raw = wp_json_encode( $elementor_data );
json_decode( $new_raw );
if ( JSON_ERROR_NONE !== json_last_error() ) {
	echo 'ABORT: freshly built replacement JSON does not parse either (' . json_last_error_msg() . ") - refusing to write\n";
	exit( 1 );
}
echo 'OK: replacement _elementor_data is valid JSON, length=' . strlen( $new_raw ) . "\n";

echo "\n--- STEP B: COMMIT ---\n";

$fresh_raw = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_elementor_data'", $post_id ) );
if ( md5( (string) $fresh_raw ) !== $original_hash ) {
	echo "ABORT: _elementor_data changed since STEP A (concurrent edit) - refusing to write\n";
	exit( 1 );
}

$updated = $wpdb->update(
	$wpdb->postmeta,
	array( 'meta_value' => $new_raw ),
	array( 'post_id' => $post_id, 'meta_key' => '_elementor_data' ),
	array( '%s' ),
	array( '%d', '%s' )
);
echo '$wpdb->update() rows affected: ' . var_export( $updated, true ) . "\n";

wp_cache_flush();
clean_post_cache( $post_id );

echo "\n--- STEP C: VERIFY ---\n";
$overall_success = true;

$verify_raw = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_elementor_data'", $post_id ) );
$verify_decoded = json_decode( $verify_raw, true );
if ( JSON_ERROR_NONE === json_last_error() && is_array( $verify_decoded ) ) {
	echo "OK: stored _elementor_data now parses as valid JSON\n";
} else {
	echo 'MISMATCH: stored _elementor_data still invalid (' . json_last_error_msg() . ")\n";
	$overall_success = false;
}

// Live smoke test with automatic rollback, same pattern as the parent fix.
$smoke_urls = array( home_url( '/all-products/' ), home_url( '/' ) );
$smoke_ok = true;
foreach ( $smoke_urls as $smoke_url ) {
	$resp = wp_remote_get( $smoke_url, array( 'timeout' => 20 ) );
	if ( is_wp_error( $resp ) ) {
		echo "SMOKE TEST FAIL: {$smoke_url} -> " . $resp->get_error_message() . "\n";
		$smoke_ok = false;
		continue;
	}
	$code = wp_remote_retrieve_response_code( $resp );
	$body = wp_remote_retrieve_body( $resp );
	$has_fatal = ( false !== stripos( $body, 'Fatal error' ) || false !== stripos( $body, 'Parse error' ) || false !== stripos( $body, 'syntax error' ) );
	$has_products = ( false !== strpos( $body, 'type-product' ) || false !== strpos( $body, 'lunaci-filter-btn' ) );
	echo "SMOKE TEST {$smoke_url}: HTTP {$code}, fatal/parse error: " . ( $has_fatal ? 'YES' : 'no' ) . ", product markup present: " . ( $has_products ? 'yes' : 'no' ) . "\n";
	if ( 200 !== (int) $code || $has_fatal ) {
		$smoke_ok = false;
	}
}

if ( ! $smoke_ok ) {
	echo "SMOKE TEST FAILED - rolling back to original _elementor_data\n";
	$rollback = $wpdb->update(
		$wpdb->postmeta,
		array( 'meta_value' => $current_raw ),
		array( 'post_id' => $post_id, 'meta_key' => '_elementor_data' ),
		array( '%s' ),
		array( '%d', '%s' )
	);
	echo 'rollback rows affected: ' . var_export( $rollback, true ) . "\n";
	wp_cache_flush();
	echo "ABORT: rolled back - see smoke test results above\n";
	exit( 1 );
}
echo "OK: smoke test passed\n";

echo "\n=====================================================================\n";
if ( $overall_success ) {
	echo "FINAL RESULT: SUCCESS\n";
} else {
	echo "FINAL RESULT: FAILURE - see per-check results above\n";
	exit( 1 );
}
