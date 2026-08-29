<?php
/**
 * Guarded fix: the Spanish product "Delineador de Labios LUNACI" (ID 731)
 * has no featured image at all, even though a photo (attachment 758,
 * "lunaci-lip-pencil.jpg") was uploaded and attached to this exact product
 * (post_parent=731) - it was just never actually set as the featured image.
 * Confirmed via a read-only audit: get_post_thumbnail_id(731) returns none.
 */

$product_id    = 731;
$attachment_id = 758;

echo "--- STEP A: PREPARE ---\n";
$product = get_post( $product_id );
if ( ! $product || 'product' !== $product->post_type ) {
	echo "ABORT: product {$product_id} not found or not a product\n";
	exit( 1 );
}
echo "product: ID={$product->ID} title=\"{$product->post_title}\" status={$product->post_status}\n";

$attachment = get_post( $attachment_id );
if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
	echo "ABORT: attachment {$attachment_id} not found\n";
	exit( 1 );
}
if ( (int) $attachment->post_parent !== $product_id ) {
	echo "ABORT: attachment {$attachment_id} post_parent is {$attachment->post_parent}, expected {$product_id}\n";
	exit( 1 );
}

$current_thumb = get_post_thumbnail_id( $product_id );
if ( $current_thumb ) {
	echo "ABORT: product {$product_id} already has a featured image (ID={$current_thumb}) - refusing to overwrite\n";
	exit( 1 );
}
echo "OK: product has no featured image, attachment belongs to this product - safe to set\n";

echo "\n--- STEP B: COMMIT ---\n";
$recheck_thumb = get_post_thumbnail_id( $product_id );
if ( $recheck_thumb ) {
	echo "ABORT: featured image appeared since STEP A (concurrent edit) - refusing to write\n";
	exit( 1 );
}
$result = set_post_thumbnail( $product_id, $attachment_id );
if ( ! $result ) {
	echo "ABORT: set_post_thumbnail() failed\n";
	exit( 1 );
}
echo "OK: set_post_thumbnail() succeeded\n";

echo "\n--- STEP C: VERIFY ---\n";
$verify_thumb = get_post_thumbnail_id( $product_id );
echo "verify: product {$product_id} featured image is now: {$verify_thumb}\n";
if ( (int) $verify_thumb !== $attachment_id ) {
	echo "FAIL: featured image mismatch\n";
	exit( 1 );
}

echo "\n=====================================================================\n";
echo "FINAL RESULT: SUCCESS\n";
