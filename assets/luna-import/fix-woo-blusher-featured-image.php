<?php
/**
 * Guarded fix: the WooCommerce "LUNACI Blusher" product (post 326) has its
 * featured image (_thumbnail_id) set to attachment 762, titled
 * "lunaci-blusher-brushes.png" - a mismatched file (a brush-set photo, not
 * the Blusher compact) that renders under the "LUNACI Blusher" label on
 * the WooCommerce "All Products" archive. This is a separate rendering
 * path from the custom Elementor "Products" page fixed earlier (PRs
 * #222-226), which pulls from _elementor_data, not this product's own
 * featured image.
 *
 * The correct photo is already uploaded and in use on the Elementor page:
 * attachment 810, "lunaimport-blusher-product.jpg". This fix simply points
 * the product's featured image at that existing, already-verified
 * attachment.
 */

global $wpdb;

$product_id      = 326;
$wrong_thumb_id  = '762';
$correct_thumb_id = 810;

echo "=====================================================================\n";
echo "STEP A: PREPARE - fresh-read + validate preconditions\n";
echo "=====================================================================\n";

$current = get_post_meta( $product_id, '_thumbnail_id', true );
echo "current _thumbnail_id for product {$product_id}: " . var_export( $current, true ) . "\n";

if ( (string) $current !== $wrong_thumb_id ) {
	echo "ABORT: expected current _thumbnail_id to be '{$wrong_thumb_id}', found " . var_export( $current, true ) . " - refusing to proceed\n";
	exit( 1 );
}

$correct_att = get_post( $correct_thumb_id );
if ( ! $correct_att || 'attachment' !== $correct_att->post_type ) {
	echo "ABORT: attachment {$correct_thumb_id} not found or not an attachment\n";
	exit( 1 );
}
echo "correct attachment {$correct_thumb_id}: title='{$correct_att->post_title}' guid={$correct_att->guid}\n";

$product_post = get_post( $product_id );
if ( ! $product_post || 'product' !== $product_post->post_type ) {
	echo "ABORT: post {$product_id} not found or not a product\n";
	exit( 1 );
}
echo "OK: preconditions satisfied - product='{$product_post->post_title}'\n";

echo "\n=====================================================================\n";
echo "STEP B: COMMIT - race-check, write\n";
echo "=====================================================================\n";

$fresh_current = get_post_meta( $product_id, '_thumbnail_id', true );
if ( (string) $fresh_current !== $wrong_thumb_id ) {
	echo "ABORT: _thumbnail_id changed since STEP A (concurrent edit detected) - refusing to write\n";
	exit( 1 );
}
echo "PASS: race-condition guard confirms value unchanged\n";

$updated = update_post_meta( $product_id, '_thumbnail_id', $correct_thumb_id );
echo "update_post_meta() returned: " . var_export( $updated, true ) . "\n";

clean_post_cache( $product_id );
if ( function_exists( 'wc_delete_product_transients' ) ) {
	wc_delete_product_transients( $product_id );
	echo "OK: wc_delete_product_transients() called\n";
}
wp_cache_flush();
echo "OK: caches cleared\n";

echo "\n=====================================================================\n";
echo "STEP C: VERIFY - fresh read-back\n";
echo "=====================================================================\n";

$verify = get_post_meta( $product_id, '_thumbnail_id', true );
echo "verify _thumbnail_id: " . var_export( $verify, true ) . "\n";

if ( (string) $verify === (string) $correct_thumb_id ) {
	echo "\nFINAL RESULT: SUCCESS\n";
} else {
	echo "\nFINAL RESULT: FAILURE - see lines above\n";
	exit( 1 );
}
