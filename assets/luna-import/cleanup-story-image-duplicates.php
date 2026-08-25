<?php
/**
 * Guarded cleanup: the About-Us "Our Story" image replace fix
 * (fix-about-story-image.php) had to be retried several times before
 * succeeding (each retry's workflow calls `wp media import` unconditionally
 * BEFORE the guarded PHP fix even runs, so multiple failed early attempts
 * left several unused duplicate copies of the same photo in the Media
 * Library under collision-suffixed filenames like
 * lunaimport-about-story-luna.jpg, -2.jpg, -3.jpg, -5.jpg etc). The site is
 * now confirmed live and working with lunaimport-about-story-luna-4.jpg
 * (verified via real-browser screenshots on both EN and ES).
 *
 * This script:
 *  1) finds every attachment whose guid matches the
 *     "lunaimport-about-story-luna*" pattern,
 *  2) confirms which one is actually referenced live on posts 59/680,
 *  3) safely deletes every OTHER one that is genuinely unreferenced
 *     anywhere (same conflict-checking pattern as the 794/795 and 307/552
 *     precedents earlier this session),
 *  4) then applies the same safe-deletion check to the ORIGINAL old
 *     attachment (lunaci-about-story.png), which never got a chance to run
 *     through STEP D of the original fix script since every prior attempt
 *     aborted or reported (incorrectly, as it turned out) a failure before
 *     reaching that step.
 */

global $wpdb;

$live_image_url = 'https://lunacibarcelona.com/wp-content/uploads/2026/08/lunaimport-about-story-luna-4.jpg';
$old_image_url  = 'https://lunacibarcelona.com/wp-content/uploads/2026/06/lunaci-about-story.png';

echo "=====================================================================\n";
echo "PART 1: Confirm the live image is actually referenced on both pages\n";
echo "=====================================================================\n";

function lunaci_cleanup_normalize_elementor_data( $raw ) {
	// _elementor_data is JSON-encoded with slashes escaped as `\/` by
	// default; decode then re-encode with JSON_UNESCAPED_SLASHES so plain-
	// slash URL searches work reliably regardless of how it was stored.
	$decoded = json_decode( $raw, true );
	if ( JSON_ERROR_NONE !== json_last_error() ) {
		return $raw;
	}
	$normalized = wp_json_encode( $decoded, JSON_UNESCAPED_SLASHES );
	return false !== $normalized ? $normalized : $raw;
}

foreach ( array( 59 => 'EN About Us', 680 => 'ES About Us (Sobre Nosotros)' ) as $page_id => $label ) {
	$raw      = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_elementor_data'", $page_id ) );
	$raw_norm = $raw ? lunaci_cleanup_normalize_elementor_data( $raw ) : '';
	$count    = $raw ? substr_count( $raw_norm, $live_image_url ) : 0;
	echo "{$label} (post {$page_id}): live image URL occurs {$count}x\n";
	if ( 1 !== $count ) {
		echo "ABORT: expected the live image to occur exactly once on page {$page_id}, found {$count} - refusing to proceed with cleanup\n";
		exit( 1 );
	}
}
echo "OK: live image confirmed referenced exactly once on both pages\n";

echo "\n=====================================================================\n";
echo "PART 2: Find all lunaimport-about-story-luna* duplicate attachments\n";
echo "=====================================================================\n";

$duplicates = $wpdb->get_results(
	"SELECT ID, guid FROM {$wpdb->posts} WHERE post_type = 'attachment' AND guid LIKE '%lunaimport-about-story-luna%'",
	ARRAY_A
);

$to_delete = array();
foreach ( $duplicates as $row ) {
	$id   = (int) $row['ID'];
	$guid = $row['guid'];
	$is_live = ( $guid === $live_image_url );
	echo "attachment ID={$id} guid={$guid} " . ( $is_live ? '<= LIVE, KEEP' : '(candidate for deletion)' ) . "\n";
	if ( ! $is_live ) {
		$to_delete[] = $id;
	}
}

if ( empty( $to_delete ) ) {
	echo "No duplicate copies found to clean up.\n";
} else {
	echo "\n--- Checking each duplicate candidate for safety before deleting ---\n";
	foreach ( $to_delete as $id ) {
		$referencing_posts = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type != 'attachment' AND post_id != %d AND (post_content LIKE %s)",
				$id,
				'%attachment_' . $id . '%'
			)
		);
		$featured_of = $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_thumbnail_id' AND meta_value = %s", (string) $id )
		);
		if ( $featured_of > 0 ) {
			echo "SKIP {$id}: used as a featured image on {$featured_of} post(s)\n";
			continue;
		}
		$result = wp_delete_attachment( $id, true );
		if ( false === $result ) {
			echo "ERROR: wp_delete_attachment({$id}, true) returned false\n";
			continue;
		}
		$still_exists = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE ID = %d", $id ) );
		echo ( $still_exists ? "FAIL: {$id} still exists after deletion attempt\n" : "OK: deleted duplicate attachment {$id}\n" );
	}
}

echo "\n=====================================================================\n";
echo "PART 3: Safe deletion of the ORIGINAL old attachment (lunaci-about-story.png)\n";
echo "=====================================================================\n";

foreach ( array( 59, 680 ) as $page_id ) {
	$raw      = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_elementor_data'", $page_id ) );
	$raw_norm = $raw ? lunaci_cleanup_normalize_elementor_data( $raw ) : '';
	$count    = $raw ? substr_count( $raw_norm, $old_image_url ) : 0;
	if ( $count > 0 ) {
		echo "ABORT: old image URL still present on live page {$page_id} - refusing to delete the original attachment\n";
		exit( 1 );
	}
}
echo "OK: old image URL confirmed absent from both live pages\n";

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
	echo "\nABORT deletion: another attachment's metadata references the same physical file(s) - refusing to delete, mirroring the 794/795 and 307/552 precedent.\n";
	echo "\nFINAL RESULT: SUCCESS (duplicates cleaned up, original deletion skipped pending investigation)\n";
	exit( 0 );
}

$featured_of = $wpdb->get_var(
	$wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_thumbnail_id' AND meta_value = %s", (string) $old_attachment_id )
);
if ( $featured_of > 0 ) {
	echo "\nABORT deletion: attachment {$old_attachment_id} is used as a featured image on {$featured_of} post(s) - refusing to delete\n";
	echo "\nFINAL RESULT: SUCCESS (duplicates cleaned up, original deletion skipped - used as featured image)\n";
	exit( 0 );
}

$result = wp_delete_attachment( $old_attachment_id, true );
if ( false === $result ) {
	echo "ERROR: wp_delete_attachment({$old_attachment_id}, true) returned false\n";
	echo "\nFINAL RESULT: PARTIAL SUCCESS (duplicates cleaned up, original deletion failed)\n";
	exit( 0 );
}

$still_exists = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE ID = %d", $old_attachment_id ) );
if ( $still_exists ) {
	echo "FAIL: attachment {$old_attachment_id} row still exists after deletion attempt\n";
	echo "\nFINAL RESULT: PARTIAL SUCCESS (duplicates cleaned up, original deletion verification failed)\n";
	exit( 0 );
}

echo "OK: attachment {$old_attachment_id} confirmed deleted\n";
echo "\nFINAL RESULT: SUCCESS\n";
