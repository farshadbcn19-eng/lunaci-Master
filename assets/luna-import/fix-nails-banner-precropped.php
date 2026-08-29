<?php
/**
 * Guarded fix: found the real root cause of the Nails banner cropping
 * bug via a live Playwright screenshot - the .lunaci-category-banner__img
 * element's height:100% is not resolving against its aspect-ratio(21/9)
 * parent, so object-fit/object-position never gets to crop the image at
 * all; the visible area ends up being whatever the ancestor's
 * overflow:hidden clips from the TOP of the image's natural render size,
 * regardless of the object-position value. This is invisible for
 * Face/Eyes/Lips because those source photos are already close to 21:9,
 * but very visible for Nails (a much less-wide photo) since it crops out
 * the entire lower portion where the painted nails are.
 *
 * Rather than keep chasing the CSS mechanism, sidestep it entirely:
 * upload a PRE-CROPPED version of the Nails photo (already ~21:9,
 * composed to keep the hand/nails prominent - matches the framing of
 * the other three banners) and point the 'nails' entry at it. Also
 * remove the now-unnecessary term-nails object-position override added
 * in the previous fix, since the image no longer needs any CSS-level
 * cropping.
 */

global $wpdb;

$new_attachment = getenv( 'LUNACI_NAILS_BANNER_CROPPED_MEDIA_ID' );
if ( false === $new_attachment || '' === $new_attachment || ! is_numeric( $new_attachment ) ) {
	echo "ABORT: expected LUNACI_NAILS_BANNER_CROPPED_MEDIA_ID env var\n";
	exit( 1 );
}
$new_attachment = (int) $new_attachment;

echo "--- verify new attachment ---\n";
$new_att_post = get_post( $new_attachment );
if ( ! $new_att_post || 'attachment' !== $new_att_post->post_type ) {
	echo "ABORT: new attachment {$new_attachment} not found or not an attachment\n";
	exit( 1 );
}
$new_url = wp_get_attachment_url( $new_attachment );
echo "new attachment {$new_attachment} resolved url: {$new_url}\n";

$table         = $wpdb->prefix . 'snippets';
$snippet_id    = 7;
$nails_pattern = "/('nails'\\s*=>\\s*)(\\d+)(\\s*,)/";

echo "\n--- STEP A: PREPARE ---\n";
$current_code = $wpdb->get_var( $wpdb->prepare( "SELECT code FROM {$table} WHERE id = %d", $snippet_id ) );
if ( null === $current_code ) {
	echo "ABORT: snippet id={$snippet_id} not found in {$table}\n";
	exit( 1 );
}
if ( ! preg_match( $nails_pattern, $current_code, $m ) ) {
	echo "ABORT: could not find a 'nails' => <id>, entry in snippet code - format changed?\n";
	exit( 1 );
}
$old_id = (int) $m[2];
echo "current 'nails' banner attachment ID: {$old_id}\n";

if ( $old_id === $new_attachment ) {
	echo "ABORT: 'nails' banner is already the new attachment - refusing to proceed (already fixed?)\n";
	exit( 1 );
}

$has_term_nails_override = ( false !== strpos( $current_code, 'body.term-nails .lunaci-category-banner__img' ) );
echo 'term-nails object-position override currently present: ' . ( $has_term_nails_override ? 'yes' : 'no' ) . "\n";
echo "OK: preconditions satisfied\n";

echo "\n--- STEP B: COMMIT ---\n";
$fresh_code = $wpdb->get_var( $wpdb->prepare( "SELECT code FROM {$table} WHERE id = %d", $snippet_id ) );
if ( $fresh_code !== $current_code ) {
	echo "ABORT: snippet code changed since STEP A (concurrent edit) - refusing to write\n";
	exit( 1 );
}

$replace_count = 0;
$new_code      = preg_replace( $nails_pattern, '${1}' . $new_attachment . '${3}', $fresh_code, -1, $replace_count );
if ( 1 !== $replace_count ) {
	echo "ABORT: expected exactly 1 replacement of the 'nails' entry, got {$replace_count}\n";
	exit( 1 );
}

// also remove the now-unnecessary term-nails object-position override, if present
if ( $has_term_nails_override ) {
	$override_pattern = '/\s*body\.term-nails \.lunaci-category-banner__img \{\s*object-position: center bottom;\s*\}\s*/';
	$removed_count     = 0;
	$new_code2         = preg_replace( $override_pattern, "\n    ", $new_code, 1, $removed_count );
	if ( 1 === $removed_count ) {
		$new_code = $new_code2;
		echo "removed the now-unnecessary term-nails object-position override\n";
	} else {
		echo "NOTE: term-nails override text present but did not match removal pattern exactly - leaving it in place (harmless no-op on a pre-cropped image)\n";
	}
}

$updated = $wpdb->update( $table, array( 'code' => $new_code ), array( 'id' => $snippet_id ), array( '%s' ), array( '%d' ) );
if ( false === $updated ) {
	echo "ABORT: \$wpdb->update() failed: {$wpdb->last_error}\n";
	exit( 1 );
}
echo "OK: \$wpdb->update() rows affected: {$updated}\n";

wp_cache_flush();

echo "\n--- STEP C: VERIFY ---\n";
$verify_code = $wpdb->get_var( $wpdb->prepare( "SELECT code FROM {$table} WHERE id = %d", $snippet_id ) );
if ( ! preg_match( $nails_pattern, $verify_code, $vm ) ) {
	echo "FAIL: could not find a 'nails' => <id>, entry after write\n";
	exit( 1 );
}
$verify_id = (int) $vm[2];
echo "verify 'nails' banner attachment ID: {$verify_id}\n";

if ( $verify_id !== $new_attachment ) {
	echo "FAIL: 'nails' banner attachment ID not updated to expected new attachment\n";
	exit( 1 );
}

echo "verify: 'face', 'eyes', 'lips' entries unchanged: " .
	( false !== strpos( $verify_code, "'lips'  => 828," ) &&
	  false !== strpos( $verify_code, "'eyes'  => 826," ) ? 'yes' : 'CHECK MANUALLY' ) . "\n";

echo "\n=====================================================================\n";
echo "FINAL RESULT: SUCCESS\n";
