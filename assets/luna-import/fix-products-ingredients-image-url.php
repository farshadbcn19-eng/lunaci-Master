<?php
/**
 * Guarded fix: replace the Products page "Crafted with Intention"
 * (Ingredients) section background image. The old image
 * (lunaci_3462_retouched-1-scaled.jpg) was an orphaned file on disk with
 * no Media Library attachment record. This swaps it for the newly
 * imported replacement (properly registered in the Media Library via
 * `wp media import`), updating the .ing-visual background-image URL in
 * the WPCode CSS snippet (post 483). No other text/CSS is touched.
 *
 * NEW_IMAGE_URL is substituted by the workflow before this file is
 * uploaded, using the actual GUID reported by `wp media import` (not a
 * predicted filename), since WordPress may rename on a collision.
 *
 * STEP A: structural staleness gate (old URL present exactly once, new URL not already present)
 * STEP B: race-condition re-check immediately before write, then exact-match replace + write
 * STEP C: full read-back verification (content match, markers, unrelated CSS intact)
 * STEP D: rebuild WPCode's cached snippet option + clear general caches
 */

global $wpdb;

$post_id = 483;

$new_image_url = '__NEW_IMAGE_URL__';

$old_url = "background: url('https://lunacibarcelona.com/wp-content/uploads/2026/05/lunaci_3462_retouched-1-scaled.jpg') center/cover no-repeat;";
$new_url = "background: url('{$new_image_url}') center/cover no-repeat;";

echo "=====================================================================\n";
echo "STEP A: PREPARE - fresh-read wp_posts row ID={$post_id} and validate\n";
echo "=====================================================================\n";
echo "new image URL to write: {$new_image_url}\n";

if ( strpos( $new_image_url, '__NEW_IMAGE_URL__' ) !== false || strpos( $new_image_url, 'https://' ) !== 0 ) {
	echo "ERROR: new image URL placeholder was not substituted or is not a valid https URL: {$new_image_url}\n";
	echo "ABORT\n";
	exit( 1 );
}

$row = $wpdb->get_row(
	$wpdb->prepare( "SELECT ID, post_status, post_content FROM {$wpdb->posts} WHERE ID = %d", $post_id ),
	ARRAY_A
);

if ( ! $row ) {
	echo "ERROR: no wp_posts row found with ID={$post_id}\n";
	echo "ABORT\n";
	exit( 1 );
}

$current     = $row['post_content'];
$current_len = strlen( $current );

echo "found row: ID={$row['ID']}  post_status={$row['post_status']}  byte_len={$current_len}\n";

if ( 'publish' !== $row['post_status'] ) {
	echo "ERROR: expected post_status=publish, found post_status={$row['post_status']}\n";
	echo "ABORT\n";
	exit( 1 );
}

$old_count = substr_count( $current, $old_url );
if ( 1 !== $old_count ) {
	echo "ERROR: expected exactly 1 occurrence of the old .ing-visual image URL rule, found {$old_count}\n";
	echo "ABORT: refusing to write against unexpected/changed content\n";
	exit( 1 );
}
echo "OK: found exactly 1 occurrence of the old .ing-visual image URL rule\n";

if ( false !== strpos( $current, $new_image_url ) ) {
	echo "ERROR: the new image URL already appears in the content - refusing to duplicate/re-run\n";
	echo "ABORT\n";
	exit( 1 );
}
echo "OK: new image URL not already present\n";

echo "\n=====================================================================\n";
echo "STEP B: COMMIT - race-condition re-check, then exact-match replace + write\n";
echo "=====================================================================\n";

$recheck = $wpdb->get_var(
	$wpdb->prepare( "SELECT post_content FROM {$wpdb->posts} WHERE ID = %d", $post_id )
);
if ( $recheck !== $current ) {
	echo "ERROR: content changed between STEP A and STEP B (race condition) - ABORT\n";
	exit( 1 );
}
echo "OK: race-condition re-check passed - content unchanged immediately before write\n";

$new_content = str_replace( $old_url, $new_url, $current, $replace_count );

if ( 1 !== $replace_count ) {
	echo "ERROR: expected exactly 1 replacement, made {$replace_count} - ABORT\n";
	exit( 1 );
}

$new_len          = strlen( $new_content );
$expected_new_len = $current_len + ( strlen( $new_url ) - strlen( $old_url ) );
if ( $new_len !== $expected_new_len ) {
	echo "ERROR: computed new content length ({$new_len}) does not match expected ({$expected_new_len}) - refusing to write\n";
	exit( 1 );
}
echo "OK: computed new content, byte_len={$new_len} (was {$current_len})\n";

$update_ok = $wpdb->update(
	$wpdb->posts,
	array( 'post_content' => $new_content ),
	array( 'ID' => $post_id ),
	array( '%s' ),
	array( '%d' )
);

if ( false === $update_ok ) {
	echo "ERROR: \$wpdb->update() failed: {$wpdb->last_error}\n";
	echo "ABORT\n";
	exit( 1 );
}
echo "OK: \$wpdb->update() returned {$update_ok} (rows affected)\n";
clean_post_cache( $post_id );
echo "OK: clean_post_cache({$post_id}) called\n";

echo "\n=====================================================================\n";
echo "STEP C: VERIFY - fresh read-back from DB and confirm exact match\n";
echo "=====================================================================\n";

$verify_content = $wpdb->get_var(
	$wpdb->prepare( "SELECT post_content FROM {$wpdb->posts} WHERE ID = %d", $post_id )
);

$verify_len = strlen( $verify_content );
echo "read-back: byte_len={$verify_len}\n";

$any_error = false;

if ( $verify_content !== $new_content ) {
	echo "ERROR: read-back content does NOT match the intended new content byte-for-byte\n";
	$any_error = true;
} else {
	echo "OK: content_matches: PASS\n";
}

if ( false === strpos( $verify_content, $new_image_url ) ) {
	echo "ERROR: new image URL NOT found in read-back content\n";
	$any_error = true;
} else {
	echo "OK: new image URL found\n";
}

if ( false !== strpos( $verify_content, 'lunaci_3462_retouched-1-scaled.jpg' ) ) {
	echo "ERROR: old image URL STILL present in read-back content\n";
	$any_error = true;
} else {
	echo "OK: old image URL confirmed gone\n";
}

// unrelated-content stability check: every other CSS rule name must still be present unchanged
$unrelated_markers = array(
	'.lp-header', '.lp-nav', '.page-hero', '.hero-h1', '.hero-sub', '.lp-section', '.philosophy',
	'.cat-grid', '.cat-img', '.products-row', '.prod-img', '.ing-visual', '.ing-content', '.ing-list',
	'.quote-banner', '.cta-strip', '.lp-footer', 'body.page-id-61', '@keyframes lpHeroKB',
	'elementor-element-0329089.e-con-boxed',
);
foreach ( $unrelated_markers as $marker ) {
	if ( false === strpos( $verify_content, $marker ) ) {
		echo "ERROR: unrelated marker '{$marker}' missing after write - possible unintended corruption\n";
		$any_error = true;
	}
}
if ( ! $any_error ) {
	echo "OK: all unrelated CSS markers still present - no unintended corruption detected\n";
}

if ( $any_error ) {
	echo "\nFINAL RESULT: FAILURE\n";
	exit( 1 );
}

echo "\n=====================================================================\n";
echo "STEP D: cache cleanup (WPCode snippet cache + general caches)\n";
echo "=====================================================================\n";

if ( function_exists( 'wpcode' ) ) {
	try {
		wpcode()->cache->cache_all_loaded_snippets();
		echo "OK: wpcode()->cache->cache_all_loaded_snippets() executed\n";
	} catch ( \Throwable $e ) {
		echo "WARNING: wpcode cache rebuild threw: " . $e->getMessage() . "\n";
	}
} else {
	echo "WARNING: wpcode() function not available - could not rebuild WPCode snippet cache\n";
}

if ( class_exists( '\\Elementor\\Plugin' ) ) {
	try {
		\Elementor\Plugin::instance()->files_manager->clear_cache();
		echo "OK: Elementor files_manager->clear_cache() executed\n";
	} catch ( \Throwable $e ) {
		echo "WARNING: Elementor clear_cache() threw: " . $e->getMessage() . "\n";
	}
	delete_post_meta( 61, '_elementor_css' );
	echo "OK: deleted _elementor_css postmeta for page 61\n";
}

wp_cache_flush();
echo "OK: wp_cache_flush() executed\n";

echo "\nFINAL RESULT: SUCCESS\n";
