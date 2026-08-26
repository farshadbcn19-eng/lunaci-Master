<?php
/**
 * Read-only diagnostic: the WooCommerce shop archive serves every product
 * image at the registered "woocommerce_thumbnail" size, confirmed via
 * Playwright to be exactly 300x300px (square-cropped) for all 14 products,
 * then stretched via object-fit:cover into ~340x455px cards - a ~1.5x
 * upscale of an already-small JPEG, causing the blur/haze the user
 * reported. Before proposing a fix (raising the registered thumbnail size
 * + regenerating thumbnails), check each product's ORIGINAL uploaded file
 * dimensions, since a fix can only be as sharp as the source material.
 *
 * Also dumps the current WooCommerce image-size options so we know the
 * exact current width/height/crop settings to change.
 */

global $wpdb;

echo "=== current WooCommerce image size options ===\n";
$opts = array(
	'woocommerce_thumbnail_image_width',
	'woocommerce_thumbnail_cropping',
	'woocommerce_thumbnail_cropping_custom_width',
	'woocommerce_thumbnail_cropping_custom_height',
	'woocommerce_single_image_width',
);
foreach ( $opts as $o ) {
	echo "{$o}: " . var_export( get_option( $o ), true ) . "\n";
}

echo "\n=== product original (full-size) image dimensions ===\n";
$products = $wpdb->get_results(
	"SELECT ID, post_title FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status = 'publish' ORDER BY ID",
	ARRAY_A
);

foreach ( $products as $p ) {
	$pid = (int) $p['ID'];
	$thumb_id = get_post_meta( $pid, '_thumbnail_id', true );
	if ( ! $thumb_id ) {
		echo "product {$pid} '{$p['post_title']}': NO featured image set\n";
		continue;
	}
	$file = get_attached_file( $thumb_id );
	$meta = wp_get_attachment_metadata( $thumb_id );
	$orig_w = $meta['width'] ?? null;
	$orig_h = $meta['height'] ?? null;
	$exists = $file && file_exists( $file );
	echo "product {$pid} '{$p['post_title']}': attachment={$thumb_id} original={$orig_w}x{$orig_h} file_exists=" . ( $exists ? 'yes' : 'NO' ) . " path=" . basename( (string) $file ) . "\n";
	if ( is_array( $meta['sizes'] ?? null ) ) {
		foreach ( $meta['sizes'] as $size_name => $size_info ) {
			if ( 'woocommerce_thumbnail' === $size_name || 'thumbnail' === $size_name ) {
				echo "    size '{$size_name}': {$size_info['width']}x{$size_info['height']}\n";
			}
		}
	}
}

echo "\nOK: read-only diagnostic complete, no writes performed\n";
