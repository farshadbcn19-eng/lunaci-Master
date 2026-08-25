<?php
/**
 * Guarded fix: attachment 795 (the "Crafted with Intention" ingredients
 * image actually referenced live via its guid/CSS) has _wp_attached_file
 * and _wp_attachment_metadata that incorrectly point at attachment 794's
 * filenames (missing the "-1" collision suffix that WordPress actually
 * gave 795's own real files on disk). Both attachments have complete,
 * correctly-named physical files - this is purely a database metadata
 * pointer bug, confirmed via a read-only diagnostic (PR #181).
 *
 * This corrects 795's metadata to point at its own real "-1" files.
 * Every corrected filename is DERIVED from data already stored in the
 * metadata itself (width/height per size) rather than hand-transcribed,
 * and each corrected path is verified to actually exist on disk before
 * being written - so metadata can never end up pointing at a file that
 * isn't really there.
 *
 * STEP A: staleness gate (current _wp_attached_file / metadata['file'] match the known-wrong value exactly)
 * STEP B: derive corrected filenames, verify each exists on disk, write
 * STEP C: full read-back verification (both meta keys, wp_get_attachment_url, unrelated data intact)
 */

$attachment_id = 795;
$wrong_relative_path = '2026/08/lunaimport-products-ingredients-luna.jpg';
$correct_relative_path = '2026/08/lunaimport-products-ingredients-luna-1.jpg';

echo "=====================================================================\n";
echo "STEP A: PREPARE - fresh-read attachment {$attachment_id} metadata and validate\n";
echo "=====================================================================\n";

$current_attached_file = get_post_meta( $attachment_id, '_wp_attached_file', true );
echo "current _wp_attached_file: {$current_attached_file}\n";

if ( $current_attached_file !== $wrong_relative_path ) {
	echo "ERROR: _wp_attached_file does not match the expected wrong value.\n";
	echo "expected: {$wrong_relative_path}\n";
	echo "found:    {$current_attached_file}\n";
	echo "ABORT: refusing to write against unexpected content (may already be fixed, or something else changed)\n";
	exit( 1 );
}
echo "OK: _wp_attached_file matches the known-wrong value exactly\n";

$current_metadata = wp_get_attachment_metadata( $attachment_id );
if ( ! is_array( $current_metadata ) || ! isset( $current_metadata['file'] ) ) {
	echo "ERROR: could not read a valid _wp_attachment_metadata array for {$attachment_id}\n";
	exit( 1 );
}
echo "current metadata['file']: {$current_metadata['file']}\n";

if ( $current_metadata['file'] !== $wrong_relative_path ) {
	echo "ERROR: metadata['file'] does not match the expected wrong value.\n";
	echo "expected: {$wrong_relative_path}\n";
	echo "found:    {$current_metadata['file']}\n";
	echo "ABORT\n";
	exit( 1 );
}
echo "OK: metadata['file'] matches the known-wrong value exactly\n";

if ( ! isset( $current_metadata['sizes'] ) || ! is_array( $current_metadata['sizes'] ) || empty( $current_metadata['sizes'] ) ) {
	echo "ERROR: metadata['sizes'] is missing or empty - nothing to correct there, aborting out of caution\n";
	exit( 1 );
}
echo "OK: found " . count( $current_metadata['sizes'] ) . " size(s) to correct\n";

echo "\n=====================================================================\n";
echo "STEP B: COMMIT - derive corrected filenames, verify each exists on disk, then write\n";
echo "=====================================================================\n";

$upload_dir = wp_upload_dir();
$base_dir   = $upload_dir['basedir'] . '/2026/08/';

$new_main_path = $base_dir . 'lunaimport-products-ingredients-luna-1.jpg';
if ( ! file_exists( $new_main_path ) ) {
	echo "ERROR: expected corrected main file does not exist on disk: {$new_main_path}\n";
	echo "ABORT: refusing to point metadata at a non-existent file\n";
	exit( 1 );
}
echo "OK: verified corrected main file exists on disk: {$new_main_path}\n";

$new_metadata = $current_metadata;
$new_metadata['file'] = $correct_relative_path;

foreach ( $new_metadata['sizes'] as $size_key => $size_data ) {
	if ( ! isset( $size_data['width'], $size_data['height'], $size_data['file'] ) ) {
		echo "ERROR: size '{$size_key}' is missing width/height/file - aborting out of caution\n";
		exit( 1 );
	}
	$expected_old_filename = "lunaimport-products-ingredients-luna-{$size_data['width']}x{$size_data['height']}.jpg";
	if ( $size_data['file'] !== $expected_old_filename ) {
		echo "ERROR: size '{$size_key}' file ({$size_data['file']}) does not match expected derived old filename ({$expected_old_filename}) - aborting out of caution\n";
		exit( 1 );
	}
	$new_filename = "lunaimport-products-ingredients-luna-1-{$size_data['width']}x{$size_data['height']}.jpg";
	$new_size_path = $base_dir . $new_filename;
	if ( ! file_exists( $new_size_path ) ) {
		echo "ERROR: derived corrected file for size '{$size_key}' does not exist on disk: {$new_size_path}\n";
		echo "ABORT: refusing to point metadata at a non-existent file\n";
		exit( 1 );
	}
	echo "OK: size '{$size_key}' ({$size_data['width']}x{$size_data['height']}): {$size_data['file']} -> {$new_filename} (verified exists)\n";
	$new_metadata['sizes'][ $size_key ]['file'] = $new_filename;
}

$attached_file_updated = update_post_meta( $attachment_id, '_wp_attached_file', $correct_relative_path );
echo "update_post_meta(_wp_attached_file): " . ( $attached_file_updated ? 'updated' : 'no change reported (may already equal target - will verify below)' ) . "\n";

$metadata_updated = update_post_meta( $attachment_id, '_wp_attachment_metadata', $new_metadata );
echo "update_post_meta(_wp_attachment_metadata): " . ( $metadata_updated ? 'updated' : 'no change reported (may already equal target - will verify below)' ) . "\n";

clean_post_cache( $attachment_id );
if ( function_exists( 'clean_attachment_cache' ) ) {
	clean_attachment_cache( $attachment_id );
}
echo "OK: post cache cleared for {$attachment_id}\n";

echo "\n=====================================================================\n";
echo "STEP C: VERIFY - fresh read-back and confirm exact match\n";
echo "=====================================================================\n";

$any_error = false;

$verify_attached_file = get_post_meta( $attachment_id, '_wp_attached_file', true );
if ( $verify_attached_file !== $correct_relative_path ) {
	echo "ERROR: read-back _wp_attached_file does not match target.\n";
	echo "expected: {$correct_relative_path}\n";
	echo "found:    {$verify_attached_file}\n";
	$any_error = true;
} else {
	echo "OK: _wp_attached_file confirmed corrected\n";
}

$verify_metadata = wp_get_attachment_metadata( $attachment_id );
if ( ! is_array( $verify_metadata ) || $verify_metadata['file'] !== $correct_relative_path ) {
	echo "ERROR: read-back metadata['file'] does not match target\n";
	$any_error = true;
} else {
	echo "OK: metadata['file'] confirmed corrected\n";
}

$all_sizes_ok = true;
foreach ( $verify_metadata['sizes'] as $size_key => $size_data ) {
	$expected = "lunaimport-products-ingredients-luna-1-{$size_data['width']}x{$size_data['height']}.jpg";
	if ( $size_data['file'] !== $expected ) {
		echo "ERROR: size '{$size_key}' still incorrect: {$size_data['file']} (expected {$expected})\n";
		$all_sizes_ok = false;
		$any_error = true;
	}
}
if ( $all_sizes_ok ) {
	echo "OK: all " . count( $verify_metadata['sizes'] ) . " size(s) confirmed corrected\n";
}

$verify_url = wp_get_attachment_url( $attachment_id );
$expected_url = 'https://lunacibarcelona.com/wp-content/uploads/' . $correct_relative_path;
echo "wp_get_attachment_url({$attachment_id}): {$verify_url}\n";
if ( $verify_url !== $expected_url ) {
	echo "ERROR: wp_get_attachment_url does not match expected corrected URL\n";
	$any_error = true;
} else {
	echo "OK: wp_get_attachment_url confirmed corrected\n";
}

// sanity: attachment 794 untouched
$attachment_794_file = get_post_meta( 794, '_wp_attached_file', true );
if ( '2026/08/lunaimport-products-ingredients-luna.jpg' !== $attachment_794_file ) {
	echo "ERROR: attachment 794's own _wp_attached_file changed unexpectedly - possible unintended side effect\n";
	$any_error = true;
} else {
	echo "OK: attachment 794 unaffected (still points at its own correct file)\n";
}

if ( $any_error ) {
	echo "\nFINAL RESULT: FAILURE\n";
	exit( 1 );
}

echo "\nFINAL RESULT: SUCCESS\n";
