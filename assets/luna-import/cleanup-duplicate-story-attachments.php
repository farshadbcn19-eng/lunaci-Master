<?php
/**
 * Guarded fix: delete the two now-unused duplicate About-story attachments
 * (306, 551), mirroring cleanup-duplicate-hero-attachments.php (PR #198).
 *
 * Background (PRs #212-#220): the "Our Story" section image on both About
 * Us pages (EN 59, ES 680) was replaced with a new photo, confirmed live
 * via real-browser screenshots. Attachment 306 (the old
 * lunaci-about-story.png) is confirmed unused in both pages' live content.
 * Attachment 551 is an exact duplicate database row of 306 - same title,
 * guid, date - pointing at the same physical files, and confirmed
 * referenced nowhere (0 post_content hits, 0 featured-image uses). The
 * only other references to the filename are 17 Elementor revision posts
 * (post_type 'revision', post_status 'inherit') of pages 59/680 - never
 * rendered on the live site. Both attachments are therefore safe to
 * delete.
 */

global $wpdb;

$old_image_filename = 'lunaci-about-story.png';
$ids_to_delete       = array( 551, 306 ); // delete the unused duplicate (551) first, then the original (306)

echo "=====================================================================\n";
echo "STEP A: PREPARE - fresh-read + validate preconditions\n";
echo "=====================================================================\n";

foreach ( $ids_to_delete as $id ) {
	$row = $wpdb->get_row(
		$wpdb->prepare( "SELECT ID, post_type, post_status, guid FROM {$wpdb->posts} WHERE ID = %d", $id ),
		ARRAY_A
	);
	if ( ! $row || 'attachment' !== $row['post_type'] ) {
		echo "ABORT: attachment {$id} not found or not an attachment (already deleted?)\n";
		exit( 1 );
	}
	echo "attachment {$id}: status={$row['post_status']} guid={$row['guid']}\n";

	$featured_of = $wpdb->get_var(
		$wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_thumbnail_id' AND meta_value = %s", (string) $id )
	);
	if ( $featured_of > 0 ) {
		echo "ABORT: attachment {$id} is used as a featured image on {$featured_of} post(s) - refusing to delete\n";
		exit( 1 );
	}
}

function lunaci_story_cleanup_normalize( $raw ) {
	$decoded = json_decode( $raw, true );
	if ( JSON_ERROR_NONE !== json_last_error() ) {
		return $raw;
	}
	$normalized = wp_json_encode( $decoded, JSON_UNESCAPED_SLASHES );
	return false !== $normalized ? $normalized : $raw;
}

foreach ( array( 59 => 'EN About Us', 680 => 'ES About Us (Sobre Nosotros)' ) as $page_id => $label ) {
	$raw      = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_elementor_data'", $page_id ) );
	$raw_norm = $raw ? lunaci_story_cleanup_normalize( $raw ) : '';
	$count    = $raw ? substr_count( $raw_norm, $old_image_filename ) : 0;
	echo "{$label} (post {$page_id}): occurrences of '{$old_image_filename}' in live _elementor_data = {$count}\n";
	if ( $count > 0 ) {
		echo "ABORT: old image filename still present in live content of post {$page_id} - refusing to delete underlying files\n";
		exit( 1 );
	}
}

echo "OK: preconditions satisfied - neither attachment is a featured image, neither page's live content references the old filename\n";

echo "\n=====================================================================\n";
echo "STEP B: COMMIT - delete both duplicate attachments\n";
echo "=====================================================================\n";

$deleted = array();
foreach ( $ids_to_delete as $id ) {
	$result = wp_delete_attachment( $id, true );
	if ( false === $result ) {
		echo "ERROR: wp_delete_attachment({$id}, true) returned false\n";
	} else {
		echo "OK: wp_delete_attachment({$id}, true) succeeded\n";
		$deleted[] = $id;
	}
}

clean_post_cache( 59 );
clean_post_cache( 680 );
wp_cache_flush();

echo "\n=====================================================================\n";
echo "STEP C: VERIFY - confirm both rows are gone\n";
echo "=====================================================================\n";

$all_gone = true;
foreach ( $ids_to_delete as $id ) {
	$still_exists = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE ID = %d", $id ) );
	if ( $still_exists ) {
		echo "FAIL: attachment {$id} row still exists\n";
		$all_gone = false;
	} else {
		echo "OK: attachment {$id} row confirmed gone\n";
	}
}

$upload_dir     = wp_upload_dir();
$physical_path  = trailingslashit( $upload_dir['basedir'] ) . '2026/06/' . $old_image_filename;
echo "Physical file {$physical_path} exists: " . ( file_exists( $physical_path ) ? 'yes (unexpected)' : 'no (expected)' ) . "\n";

if ( $all_gone ) {
	echo "\nFINAL RESULT: SUCCESS\n";
} else {
	echo "\nFINAL RESULT: PARTIAL - see FAIL lines above\n";
}
