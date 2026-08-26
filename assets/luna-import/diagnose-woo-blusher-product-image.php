<?php
/**
 * Read-only diagnostic: the user reports the WooCommerce "All Products"
 * archive (a different rendering path than the custom Elementor "Products"
 * page fixed earlier in PRs #222-226) shows the WRONG image for the
 * "LUNACI Blusher" product card - a screenshot shows a brush-set photo
 * under the "LUNACI Blusher" label. Find the actual WooCommerce product
 * post for Blusher, its featured image (_thumbnail_id) and gallery images
 * (_product_image_gallery), and what attachment(s) those IDs point to, so
 * we can see whether the featured image is simply wrong/mismatched.
 */

global $wpdb;

echo "=== searching wc products with 'blush' in title ===\n";
$products = $wpdb->get_results(
	"SELECT ID, post_title, post_status FROM {$wpdb->posts} WHERE post_type = 'product' AND post_title LIKE '%lush%'",
	ARRAY_A
);
foreach ( $products as $p ) {
	echo "product ID={$p['ID']} title='{$p['post_title']}' status={$p['post_status']}\n";
}

if ( empty( $products ) ) {
	echo "No products found matching 'blush' - trying broader search across all products.\n";
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
		} else {
			echo "  -> attachment {$thumb_id}: NOT FOUND (broken reference)\n";
		}
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

echo "\n=== searching attachments with 'blush' or 'brush' in title/guid ===\n";
$atts = $wpdb->get_results(
	"SELECT ID, post_title, guid FROM {$wpdb->posts} WHERE post_type = 'attachment' AND (post_title LIKE '%lush%' OR guid LIKE '%lush%' OR post_title LIKE '%brush%' OR guid LIKE '%brush%') ORDER BY ID",
	ARRAY_A
);
foreach ( $atts as $a ) {
	echo "attachment {$a['ID']}: title='{$a['post_title']}' guid={$a['guid']}\n";
}

echo "\nOK: read-only diagnostic complete, no writes performed\n";
