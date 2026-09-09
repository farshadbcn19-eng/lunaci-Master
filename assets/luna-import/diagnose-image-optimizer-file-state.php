<?php
/**
 * READ-ONLY. get_attached_file() now returns .webp paths for all 7
 * target attachments even though image_optimizer_metadata.status is
 * "optimization-failed" — the plugin appears to have renamed the
 * attachment's file reference before failing. Check the real on-disk
 * state: does the .webp file exist and have a valid size/type, does
 * the original .jpg still exist, and is the postmeta consistent.
 */

$attachment_ids = array( 776, 778, 780, 782, 784, 786, 788 );

foreach ( $attachment_ids as $id ) {
	echo "Attachment $id\n";
	$attached_file_meta = get_post_meta( $id, '_wp_attached_file', true );
	echo "  _wp_attached_file meta: $attached_file_meta\n";

	$current = get_attached_file( $id );
	echo "  get_attached_file(): $current\n";
	echo '  current file exists: ' . ( $current && file_exists( $current ) ? 'YES (' . filesize( $current ) . ' bytes)' : 'NO' ) . "\n";

	if ( $current ) {
		$jpg_guess = preg_replace( '/\.webp$/i', '.jpg', $current );
		if ( $jpg_guess !== $current ) {
			echo '  sibling .jpg exists: ' . ( file_exists( $jpg_guess ) ? 'YES (' . filesize( $jpg_guess ) . ' bytes)' : 'no' ) . " ($jpg_guess)\n";
		}
		if ( $current && file_exists( $current ) ) {
			$info = @getimagesize( $current );
			echo '  getimagesize() on current file: ' . ( $info ? "{$info[0]}x{$info[1]} mime={$info['mime']}" : 'FAILED (not a valid image / corrupt)' ) . "\n";
		}
	}

	$mime = get_post_mime_type( $id );
	echo "  post_mime_type: $mime\n";
	echo "\n";
}

echo "OK: read-only diagnostic complete\n";
