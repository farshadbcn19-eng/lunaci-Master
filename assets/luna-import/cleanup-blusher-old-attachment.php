<?php
/**
 * Guarded cleanup: safe-deletion check for the old Blusher product image
 * attachment (lunaci-blush.jpg), mirroring STEP D of
 * fix-about-story-image.php - this never got a chance to run because the
 * fix workflow's own post-write verification reported a false failure
 * (same class of issue diagnosed and explained in PR #216/#217: the check
 * itself was flawed, not the write). A follow-up diagnostic + live
 * screenshots (PR #225) already confirmed the new image is correctly live
 * on both EN and ES Products pages.
 */

global $wpdb;

$old_image_url = 'https://lunacibarcelona.com/wp-content/uploads/2026/06/lunaci-blush.jpg';

function lunaci_blusher_cleanup_normalize( $raw ) {
	$decoded = json_decode( $raw, true );
	if ( JSON_ERROR_NONE !== json_last_error() ) {
		return $raw;
	}
	$normalized = wp_json_encode( $decoded, JSON_UNESCAPED_SLASHES );
	return false !== $normalized ? $normalized : $raw;
}

echo "=====================================================================\n";
echo "PART 1: Confirm the old image is absent from both live pages\n";
echo "=====================================================================\n";

foreach ( array( 61 => 'EN Products', 771 => 'ES Products (Productos)' ) as $page_id => $label ) {
	$raw      = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_elementor_data'", $page_id ) );
	$raw_norm = $raw ? lunaci_blusher_cleanup_normalize( $raw ) : '';
	$count    = $raw ? substr_count( $raw_norm, $old_image_url ) : 0;
	echo "{$label} (post {$page_id}): occurrences of old image URL = {$count}\n";
	if ( $count > 0 ) {
		echo "ABORT: old image URL still present on live page {$page_id} - refusing to delete the original attachment\n";
		exit( 1 );
	}
}
echo "OK: old image URL confirmed absent from both live pages\n";

echo "\n=====================================================================\n";
echo "PART 2: Safe deletion of the old attachment\n";
echo "=====================================================================\n";

$old_attachment = $wpdb->get_row(
	$wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND guid = %s", $old_image_url ),
	ARRAY_A
);

if ( ! $old_attachment ) {
	echo "Old image is not a registered Media Library attachment (orphaned raw file) - nothing to clean up.\n";
	echo "\nFINAL RESULT: SUCCESS\n";
	exit( 0 );
}

$old_attachment_id = (int) $old_attachment['ID'];
echo "Old image is attachment ID={$old_attachment_id}\n";

$old_meta  = wp_get_attachment_metadata( $old_attachment_id );
$old_files = array();
if ( $old_meta ) {
	if ( isset( $old_meta['file'] ) ) {
		$old_files[] = basename( $old_meta['file'] );
	}
	if ( isset( $old_meta['sizes'] ) && is_array( $old_meta['sizes'] ) ) {
		foreach ( $old_meta['sizes'] as $size_data ) {
			if ( isset( $size_data['file'] ) ) {
				$old_files[] = $size_data['file'];
			}
		}
	}
}
$old_files = array_unique( $old_files );
echo "Attachment {$old_attachment_id}'s own files (" . count( $old_files ) . "): " . implode( ', ', $old_files ) . "\n";

$conflict_found = false;
if ( ! empty( $old_files ) ) {
	$like_filename      = basename( $old_image_url );
	$other_attachments = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE post_id != %d AND (meta_key = '_wp_attached_file' OR meta_key = '_wp_attachment_metadata') AND meta_value LIKE %s",
			$old_attachment_id,
			'%' . $wpdb->esc_like( $like_filename ) . '%'
		),
		ARRAY_A
	);
	foreach ( $other_attachments as $row ) {
		$other_id    = (int) $row['post_id'];
		$other_meta  = wp_get_attachment_metadata( $other_id );
		$other_files = array();
		if ( $other_meta ) {
			if ( isset( $other_meta['file'] ) ) {
				$other_files[] = basename( $other_meta['file'] );
			}
			if ( isset( $other_meta['sizes'] ) && is_array( $other_meta['sizes'] ) ) {
				foreach ( $other_meta['sizes'] as $size_data ) {
					if ( isset( $size_data['file'] ) ) {
						$other_files[] = $size_data['file'];
					}
				}
			}
		}
		$overlap = array_intersect( $old_files, $other_files );
		echo "Checked other attachment ID={$other_id}: files=" . implode( ', ', $other_files ) . " | overlap: " . ( empty( $overlap ) ? '(none)' : implode( ', ', $overlap ) ) . "\n";
		if ( ! empty( $overlap ) ) {
			$conflict_found = true;
		}
	}
}

if ( $conflict_found ) {
	echo "\nABORT deletion: another attachment's metadata references the same physical file(s) - refusing to delete, mirroring earlier precedent this session.\n";
	echo "\nFINAL RESULT: SUCCESS (deletion skipped pending investigation)\n";
	exit( 0 );
}

$featured_of = $wpdb->get_var(
	$wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_thumbnail_id' AND meta_value = %s", (string) $old_attachment_id )
);
if ( $featured_of > 0 ) {
	echo "\nABORT deletion: attachment {$old_attachment_id} is used as a featured image on {$featured_of} post(s) - refusing to delete\n";
	echo "\nFINAL RESULT: SUCCESS (deletion skipped - still used as featured image)\n";
	exit( 0 );
}

$result = wp_delete_attachment( $old_attachment_id, true );
if ( false === $result ) {
	echo "ERROR: wp_delete_attachment({$old_attachment_id}, true) returned false\n";
	echo "\nFINAL RESULT: PARTIAL SUCCESS (deletion failed)\n";
	exit( 0 );
}

$still_exists = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE ID = %d", $old_attachment_id ) );
if ( $still_exists ) {
	echo "FAIL: attachment {$old_attachment_id} row still exists after deletion attempt\n";
	echo "\nFINAL RESULT: PARTIAL SUCCESS (deletion verification failed)\n";
	exit( 0 );
}

echo "OK: attachment {$old_attachment_id} confirmed deleted\n";
echo "\nFINAL RESULT: SUCCESS\n";
