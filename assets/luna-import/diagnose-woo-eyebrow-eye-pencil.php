<?php
/**
 * Read-only diagnostic: the Products landing page (post 61) has no
 * dedicated images for "Eyebrow Pencil" or "Eye Pencil" - they only appear
 * as text inside the shared "Eye Collection" category card. So the
 * reported broken/incorrect images must be on the actual WooCommerce
 * product pages (Shop archive / single product pages) for these two SKUs.
 * Find the WooCommerce product posts, their featured image
 * (_thumbnail_id) and gallery images (_product_image_gallery), and
 * whether those attachment IDs resolve to real, matching attachments -
 * same pattern as diagnose-woo-blusher-product-image.php which found a
 * similar mismatch for Blusher.
 */

global $wpdb;

echo "=== searching wc products with 'eyebrow' or 'eye pencil' in title ===\n";
$products = $wpdb->get_results(
	"SELECT ID, post_title, post_status FROM {$wpdb->posts} WHERE post_type = 'product' AND (post_title LIKE '%yebrow%' OR post_title LIKE '%ye Pencil%' OR post_title LIKE '%yeliner%')",
	ARRAY_A
);
foreach ( $products as $p ) {
	echo "product ID={$p['ID']} title='{$p['post_title']}' status={$p['post_status']}\n";
}

if ( empty( $products ) ) {
	echo "No products found matching those terms - listing ALL published products for reference.\n";
	$all = $wpdb->get_results(
		"SELECT ID, post_title FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status = 'publish' ORDER BY ID",
		ARRAY_A
	);
	foreach ( $all as $p ) {
		echo "  ID={$p['ID']} title='{$p['post_title']}'\n";
	}
	exit( 0 );
}

foreach ( $products as $p ) {
	$pid = (int) $p['ID'];
	echo "\n--- product {$pid} ('{$p['post_title']}') ---\n";

	$thumb_id = get_post_meta( $pid, '_thumbnail_id', true );
	echo "_thumbnail_id: " . var_export( $thumb_id, true ) . "\n";
	if ( $thumb_id ) {
		$att = get_post( $thumb_id );
		if ( $att ) {
			echo "  -> attachment {$thumb_id}: title='{$att->post_title}' guid={$att->guid}\n";
			$meta = wp_get_attachment_metadata( $thumb_id );
			if ( $meta && isset( $meta['width'], $meta['height'] ) ) {
				echo "     native dimensions: {$meta['width']}x{$meta['height']}\n";
			} else {
				echo "     native dimensions: (metadata missing or incomplete)\n";
			}
			$file_path = get_attached_file( $thumb_id );
			echo "     file path: {$file_path}  exists on disk: " . ( $file_path && file_exists( $file_path ) ? 'yes' : 'NO - MISSING' ) . "\n";
		} else {
			echo "  -> attachment {$thumb_id}: NOT FOUND (broken reference)\n";
		}
	} else {
		echo "  -> NO FEATURED IMAGE SET AT ALL\n";
	}

	$gallery = get_post_meta( $pid, '_product_image_gallery', true );
	echo "_product_image_gallery: " . var_export( $gallery, true ) . "\n";
	if ( $gallery ) {
		foreach ( explode( ',', $gallery ) as $gid ) {
			$gid = trim( $gid );
			if ( ! $gid ) continue;
			$gatt = get_post( $gid );
			if ( $gatt ) {
				echo "  -> gallery attachment {$gid}: title='{$gatt->post_title}' guid={$gatt->guid}\n";
			} else {
				echo "  -> gallery attachment {$gid}: NOT FOUND\n";
			}
		}
	}
}

echo "\n=== searching attachments with 'eyebrow' or 'eye-pencil' or 'eye_pencil' in title/guid ===\n";
$atts = $wpdb->get_results(
	"SELECT ID, post_title, guid FROM {$wpdb->posts} WHERE post_type = 'attachment' AND (post_title LIKE '%yebrow%' OR guid LIKE '%yebrow%' OR post_title LIKE '%ye-pencil%' OR guid LIKE '%ye-pencil%' OR post_title LIKE '%ye_pencil%' OR guid LIKE '%ye_pencil%' OR post_title LIKE '%ye pencil%') ORDER BY ID",
	ARRAY_A
);
foreach ( $atts as $a ) {
	echo "attachment {$a['ID']}: title='{$a['post_title']}' guid={$a['guid']}\n";
}

echo "\nOK: read-only diagnostic complete, no writes performed\n";
