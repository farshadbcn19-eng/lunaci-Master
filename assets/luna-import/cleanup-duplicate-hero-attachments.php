<?php
/**
 * Guarded fix: delete the two now-unused duplicate About-hero attachments.
 *
 * Background (see PRs #195, #196, #197): fix-about-hero-banner.php already
 * replaced the hero image on both EN About Us (post 59) and ES About Us
 * (post 680) with the new photo, and confirmed via read-back that the old
 * image URL is gone from both. Its STEP D refused to delete attachment 307
 * because attachment 552 is an exact duplicate database row - same title,
 * guid, _wp_attached_file, _wp_attachment_metadata, created the same
 * second - pointing at the same physical file. Follow-up diagnostics
 * confirmed:
 *  - attachment 552 is referenced nowhere (0 post_content hits, 0 uses as
 *    a featured image)
 *  - the only other referencing rows are 17 Elementor revisions (post_type
 *    'revision', post_status 'inherit') of pages 59/680 - never rendered
 *    on the live site
 * Both attachments are therefore safe to delete, mirroring the
 * attachment-794/795 precedent from earlier this session.
 */

global $wpdb;

$old_image_filename = 'lunaci-about-hero.png';
$ids_to_delete       = array( 552, 307 ); // delete the unused duplicate (552) first, then the original (307)

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

foreach ( array( 59 => 'EN About Us', 680 => 'ES About Us (Sobre Nosotros)' ) as $page_id => $label ) {
	$raw = $wpdb->get_var(
		$wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_elementor_data'", $page_id )
	);
	$count = $raw ? substr_count( $raw, $old_image_filename ) : 0;
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

$upload_dir = wp_upload_dir();
$physical_path = trailingslashit( $upload_dir['basedir'] ) . '2026/06/' . $old_image_filename;
echo "Physical file {$physical_path} exists: " . ( file_exists( $physical_path ) ? 'yes (unexpected)' : 'no (expected)' ) . "\n";

if ( $all_gone ) {
	echo "\nFINAL RESULT: SUCCESS\n";
} else {
	echo "\nFINAL RESULT: PARTIAL - see FAIL lines above\n";
}
