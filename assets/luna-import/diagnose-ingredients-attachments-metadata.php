<?php
/**
 * Read-only diagnostic: dump the full attachment metadata for both the
 * duplicate ingredients-image attachments (794 and 795) and list the
 * actual physical files on disk, to understand exactly which metadata
 * rows reference which real files before attempting any fix.
 */

global $wpdb;

foreach ( array( 794, 795 ) as $id ) {
	echo "=====================================================================\n";
	echo "Attachment ID={$id}\n";
	echo "=====================================================================\n";

	$row = $wpdb->get_row(
		$wpdb->prepare( "SELECT ID, post_status, guid FROM {$wpdb->posts} WHERE ID = %d", $id ),
		ARRAY_A
	);
	if ( ! $row ) {
		echo "no wp_posts row found\n\n";
		continue;
	}
	echo "post_status: {$row['post_status']}\n";
	echo "guid: {$row['guid']}\n";

	$attached_file = get_post_meta( $id, '_wp_attached_file', true );
	echo "_wp_attached_file: {$attached_file}\n";

	$metadata = wp_get_attachment_metadata( $id );
	echo "_wp_attachment_metadata (unserialized):\n";
	echo print_r( $metadata, true );

	$computed_url = wp_get_attachment_url( $id );
	echo "wp_get_attachment_url({$id}): {$computed_url}\n";
	echo "\n";
}

echo "=====================================================================\n";
echo "Physical files on disk under wp-content/uploads/2026/08/ matching 'ingredients'\n";
echo "=====================================================================\n";

$upload_dir = wp_upload_dir();
$dir        = $upload_dir['basedir'] . '/2026/08/';
$files      = glob( $dir . '*ingredients*' );
if ( ! $files ) {
	echo "no matching files found in {$dir}\n";
} else {
	foreach ( $files as $f ) {
		echo basename( $f ) . " (size=" . filesize( $f ) . " bytes)\n";
	}
}

echo "\nOK: read-only diagnostic complete, no writes performed\n";
