<?php
/**
 * Guarded fix: replace the "Our Story" section image on the About Us page
 * (EN post 59 + ES post 680) with a new user-supplied photo, then safely
 * clean up the old attachment if nothing else references its physical
 * files - same pattern as fix-about-hero-banner.php (PR #195).
 */

global $wpdb;

$old_image_url = 'https://lunacibarcelona.com/wp-content/uploads/2026/06/lunaci-about-story.png';
$new_image_url  = '__NEW_IMAGE_URL__';

$pages = array(
	59  => 'EN About Us',
	680 => 'ES About Us (Sobre Nosotros)',
);

echo "=====================================================================\n";
echo "STEP A: PREPARE - fresh-read + validate preconditions for both pages\n";
echo "=====================================================================\n";

$page_data = array();

foreach ( $pages as $page_id => $label ) {
	echo "\n--- {$label} (post {$page_id}) ---\n";
	$raw = $wpdb->get_var(
		$wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_elementor_data'", $page_id )
	);
	if ( null === $raw ) {
		echo "ABORT: no _elementor_data found for post {$page_id}\n";
		exit( 1 );
	}

	$old_count = substr_count( $raw, $old_image_url );
	$new_count = substr_count( $raw, $new_image_url );
	echo "old image URL occurs {$old_count}x  new image URL already present {$new_count}x\n";

	if ( 1 !== $old_count ) {
		echo "ABORT: expected exactly 1 occurrence of the old image URL, found {$old_count} - refusing to proceed\n";
		exit( 1 );
	}
	if ( 0 !== $new_count ) {
		echo "ABORT: the new image URL is already present - refusing to proceed (already fixed?)\n";
		exit( 1 );
	}

	echo "OK: preconditions satisfied for page {$page_id}\n";

	$page_data[ $page_id ] = array(
		'label' => $label,
		'raw'   => $raw,
	);
}

echo "\n=====================================================================\n";
echo "STEP B: COMMIT - race-check, replace, write for both pages\n";
echo "=====================================================================\n";

foreach ( $page_data as $page_id => $data ) {
	echo "\n--- {$data['label']} (post {$page_id}) ---\n";

	$fresh_raw = $wpdb->get_var(
		$wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_elementor_data'", $page_id )
	);
	if ( $fresh_raw !== $data['raw'] ) {
		echo "ABORT: content changed since STEP A (concurrent edit detected) - refusing to write to page {$page_id}\n";
		exit( 1 );
	}
	echo "PASS: race-condition guard confirms content unchanged\n";

	$new_raw = str_replace( $old_image_url, $new_image_url, $fresh_raw );
	if ( substr_count( $new_raw, $new_image_url ) !== 1 || false !== strpos( $new_raw, $old_image_url ) ) {
		echo "ABORT: replacement verification failed for page {$page_id}\n";
		exit( 1 );
	}

	update_post_meta( $page_id, '_elementor_data', wp_slash( $new_raw ) );
	echo "OK: update_post_meta() succeeded for page {$page_id}\n";

	clean_post_cache( $page_id );
	delete_post_meta( $page_id, '_elementor_css' );
	if ( class_exists( '\Elementor\Plugin' ) ) {
		\Elementor\Plugin::instance()->files_manager->clear_cache();
	}
}

wp_cache_flush();
echo "\nOK: caches cleared for both pages, object cache flushed\n";

echo "\n=====================================================================\n";
echo "STEP C: VERIFY - fresh read-back for both pages\n";
echo "=====================================================================\n";

$all_ok = true;
foreach ( $page_data as $page_id => $data ) {
	echo "\n--- {$data['label']} (post {$page_id}) ---\n";
	$verify_raw = $wpdb->get_var(
		$wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_elementor_data'", $page_id )
	);
	$has_new = $verify_raw && substr_count( $verify_raw, $new_image_url ) === 1;
	$has_old = $verify_raw && false !== strpos( $verify_raw, $old_image_url );
	echo "old image gone: " . ( ! $has_old ? 'yes' : 'no' ) . "   new image present(x1): " . ( $has_new ? 'yes' : 'no' ) . "\n";
	if ( ! $has_new || $has_old ) {
		$all_ok = false;
	}
}

if ( ! $all_ok ) {
	echo "\nFINAL RESULT: FAILURE - see lines above (STEP D skipped)\n";
	exit( 1 );
}

echo "\nOK: image replace confirmed successful on both pages\n";

echo "\n=====================================================================\n";
echo "STEP D: Safe deletion of old attachment (lunaci-about-story.png)\n";
echo "=====================================================================\n";

$old_attachment = $wpdb->get_row(
	$wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND guid = %s", $old_image_url ),
	ARRAY_A
);

if ( ! $old_attachment ) {
	echo "Old image is not a registered Media Library attachment (orphaned raw file) - nothing to clean up in the database.\n";
	echo "\nFINAL RESULT: SUCCESS (replace only, no attachment row to delete)\n";
	exit( 0 );
}

$old_attachment_id = (int) $old_attachment['ID'];
echo "Old image is attachment ID={$old_attachment_id}\n";

$old_meta = wp_get_attachment_metadata( $old_attachment_id );
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
	$like_filename = basename( $old_image_url );
	$other_attachments = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE post_id != %d AND (meta_key = '_wp_attached_file' OR meta_key = '_wp_attachment_metadata') AND meta_value LIKE %s",
			$old_attachment_id,
			'%' . $wpdb->esc_like( $like_filename ) . '%'
		),
		ARRAY_A
	);
	foreach ( $other_attachments as $row ) {
		$other_id   = (int) $row['post_id'];
		$other_meta = wp_get_attachment_metadata( $other_id );
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
		echo "Checked other attachment ID={$other_id}: files=" . implode( ', ', $other_files ) . " | overlap with {$old_attachment_id}: " . ( empty( $overlap ) ? '(none)' : implode( ', ', $overlap ) ) . "\n";
		if ( ! empty( $overlap ) ) {
			$conflict_found = true;
		}
	}
}

if ( $conflict_found ) {
	echo "\nABORT deletion: another attachment's metadata references the same physical file(s) as attachment {$old_attachment_id}.\n";
	echo "This is the same metadata-pointer bug pattern found earlier this session (attachments 794/795, and 307/552) - refusing to delete\n";
	echo "until that is investigated and fixed first, to avoid breaking the other attachment.\n";
	echo "\nFINAL RESULT: SUCCESS (replace only, deletion skipped pending metadata investigation)\n";
	exit( 0 );
}

$featured_of = $wpdb->get_var(
	$wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_thumbnail_id' AND meta_value = %s", (string) $old_attachment_id )
);
if ( $featured_of > 0 ) {
	echo "\nABORT deletion: attachment {$old_attachment_id} is used as a featured image on {$featured_of} post(s) - refusing to delete\n";
	echo "\nFINAL RESULT: SUCCESS (replace only, deletion skipped - still used as featured image)\n";
	exit( 0 );
}

$result = wp_delete_attachment( $old_attachment_id, true );
if ( false === $result ) {
	echo "ERROR: wp_delete_attachment({$old_attachment_id}, true) returned false\n";
	echo "\nFINAL RESULT: PARTIAL SUCCESS (replace succeeded, deletion failed)\n";
	exit( 0 );
}

$still_exists = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE ID = %d", $old_attachment_id ) );
if ( $still_exists ) {
	echo "FAIL: attachment {$old_attachment_id} row still exists after deletion attempt\n";
	echo "\nFINAL RESULT: PARTIAL SUCCESS (replace succeeded, deletion verification failed)\n";
	exit( 0 );
}

echo "OK: attachment {$old_attachment_id} confirmed deleted\n";
echo "\nFINAL RESULT: SUCCESS\n";
