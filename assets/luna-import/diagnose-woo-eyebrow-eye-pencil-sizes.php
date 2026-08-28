<?php
/**
 * Read-only, deeper diagnostic: attachments 251 (Eyebrow Pencil) and 240
 * (Eye Pencil) both resolve and their FULL-size file exists on disk, but
 * attachment 240's guid ('lunaci-eye-pencil.jpg') doesn't match its actual
 * attached file ('lunaci-eye-pencil-e1781429622606.jpg' - a WordPress
 * "edited image" filename), which is the classic pattern behind broken
 * CROPPED sub-sizes (thumbnail/medium/woocommerce_thumbnail) even when the
 * full-size original is fine. Dump the full attachment metadata 'sizes'
 * array for both 240 and 251, and check disk existence for EVERY size, to
 * find exactly which size (if any) is actually broken - since the Shop
 * grid / product archive likely renders a specific cropped size, not the
 * full original.
 */

$attachment_ids = array(
	251 => 'Eyebrow Pencil (product 497)',
	240 => 'Eye Pencil (product 501)',
);

foreach ( $attachment_ids as $att_id => $label ) {
	echo "\n=====================================================================\n";
	echo "ATTACHMENT {$att_id} ({$label})\n";
	echo "=====================================================================\n";

	$att = get_post( $att_id );
	if ( ! $att ) {
		echo "ABORT: attachment not found\n";
		continue;
	}
	echo "guid: {$att->guid}\n";

	$attached_file = get_attached_file( $att_id );
	echo "_wp_attached_file resolved path: {$attached_file}\n";
	echo "  full-size exists on disk: " . ( $attached_file && file_exists( $attached_file ) ? 'yes' : 'NO - MISSING' ) . "\n";

	$meta = wp_get_attachment_metadata( $att_id );
	if ( ! $meta ) {
		echo "No attachment metadata found at all.\n";
		continue;
	}
	echo "metadata 'file': " . ( $meta['file'] ?? '(not set)' ) . "\n";
	echo "metadata 'width'x'height': " . ( $meta['width'] ?? '?' ) . "x" . ( $meta['height'] ?? '?' ) . "\n";

	$upload_dir = wp_upload_dir();
	$base_dir   = trailingslashit( $upload_dir['basedir'] );
	$file_dir   = dirname( $attached_file ) . '/';

	if ( empty( $meta['sizes'] ) || ! is_array( $meta['sizes'] ) ) {
		echo "No 'sizes' sub-array in metadata (no cropped variants registered at all).\n";
	} else {
		echo "\n--- registered sizes (" . count( $meta['sizes'] ) . ") ---\n";
		foreach ( $meta['sizes'] as $size_name => $size_data ) {
			$size_file = $file_dir . $size_data['file'];
			$exists    = file_exists( $size_file );
			echo "  {$size_name}: file='{$size_data['file']}' {$size_data['width']}x{$size_data['height']}  exists: " . ( $exists ? 'yes' : 'NO - MISSING' ) . "\n";
		}
	}

	// what URL does wp_get_attachment_image_src() actually return for the
	// size WooCommerce typically uses on archive/shop grids?
	foreach ( array( 'woocommerce_thumbnail', 'medium', 'thumbnail', 'full' ) as $size ) {
		$src = wp_get_attachment_image_src( $att_id, $size );
		echo "wp_get_attachment_image_src(..., '{$size}'): " . ( $src ? $src[0] . " ({$src[1]}x{$src[2]})" : 'FALSE (no result)' ) . "\n";
	}
}

echo "\nOK: read-only diagnostic complete, no writes performed\n";
