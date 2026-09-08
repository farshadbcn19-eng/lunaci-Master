<?php
/**
 * Recovery: restore post 57's (homepage, EN) _elementor_data from the
 * user's Hostinger database backup dated 2026-09-03 11:31:56, after
 * diagnose-elementor-meta-write.php (merged in #380) corrupted the live
 * value - something strips backslash-escaping from any update_post_meta()
 * write to this specific meta key, which mangled every \n and \" in the
 * stored JSON and broke the hero section's rendering.
 *
 * The restore value (post57-elementor-data-restore-20260903.json, staged
 * to /tmp/ by the workflow) was extracted directly from the mysqldump
 * backup: located the wp_postmeta row (post_id=57, meta_key=
 * '_elementor_data'), parsed the SQL-quoted string with proper MySQL
 * escape handling, and unescaped it - NOT decoded+re-encoded, so this is
 * a byte-for-byte match of what was actually stored on 2026-09-03,
 * verified as valid JSON containing the hero wordmark, the nav, and
 * matching every occurrence count previously confirmed live (3x /about,
 * 1x hero wordmark div, etc).
 *
 * To avoid repeating the exact bug that caused this incident, this does
 * NOT call update_post_meta() (which appears to run the value through an
 * unwanted stripslashes-equivalent on this specific meta key). Instead it
 * writes directly via $wpdb->update(), which uses parameterized SQL and
 * no PHP-level slashing at all - the string given to it is exactly the
 * string that gets stored.
 */

global $wpdb;

$post_id = 57;
$meta_key = '_elementor_data';
$restore_path = '/tmp/post57-elementor-data-restore-20260903.json';

if ( ! file_exists( $restore_path ) ) {
	echo "ABORT: restore file not found at {$restore_path}\n";
	return;
}

$restore_value = file_get_contents( $restore_path );
echo 'restore file length: ' . strlen( $restore_value ) . "\n";

// Validate the restore payload before touching anything.
$decoded = json_decode( $restore_value, true );
if ( ! is_array( $decoded ) ) {
	echo 'ABORT: restore payload is not valid JSON: ' . json_last_error_msg() . "\n";
	return;
}
$required_markers = array( 'ln-hero__wordmark', 'ln-nav__links', 'Every woman is seen' );
foreach ( $required_markers as $marker ) {
	if ( strpos( $restore_value, $marker ) === false ) {
		echo "ABORT: restore payload is missing expected marker '{$marker}' - refusing to write.\n";
		return;
	}
}
echo "restore payload validated: valid JSON, contains all expected markers.\n";

// Guard: only restore if the live value currently looks like the known
// corrupted state (much shorter than the restore payload, or missing the
// hero wordmark entirely) - refuse to overwrite anything that might
// already be fine or might be a newer legitimate edit.
$current = $wpdb->get_var( $wpdb->prepare(
	"SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s",
	$post_id, $meta_key
) );
$current_len = strlen( (string) $current );
$looks_corrupted = ( $current === null )
	|| ( strpos( (string) $current, 'ln-hero__wordmark' ) === false )
	|| ( $current_len < strlen( $restore_value ) - 500 );

echo "current live value length: {$current_len}\n";
echo 'current looks corrupted: ' . ( $looks_corrupted ? 'yes' : 'no' ) . "\n";

if ( ! $looks_corrupted ) {
	echo "ABORT: current live value does not look corrupted (has hero wordmark, length close to restore payload) - refusing to overwrite, needs manual review.\n";
	return;
}

// Direct SQL update, bypassing update_post_meta()'s metadata API layer.
$result = $wpdb->update(
	$wpdb->postmeta,
	array( 'meta_value' => $restore_value ),
	array( 'post_id' => $post_id, 'meta_key' => $meta_key )
);

echo "\n\$wpdb->update() returned: " . var_export( $result, true ) . "\n";
if ( $wpdb->last_error ) {
	echo 'wpdb last_error: ' . $wpdb->last_error . "\n";
}

// Readback verification: re-fetch, json_decode, and compare structurally
// (not a naive string search - the earlier incident's false "did not
// persist" reports came from comparing against the wrong escaped form).
clean_post_cache( $post_id );
$readback = $wpdb->get_var( $wpdb->prepare(
	"SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s",
	$post_id, $meta_key
) );
$readback_len = strlen( (string) $readback );
$readback_decoded = json_decode( (string) $readback, true );

echo "\nreadback length: {$readback_len}\n";
echo 'readback is valid JSON: ' . ( is_array( $readback_decoded ) ? 'yes' : 'no' ) . "\n";
echo "readback contains 'ln-hero__wordmark': " . ( strpos( (string) $readback, 'ln-hero__wordmark' ) !== false ? 'yes' : 'no' ) . "\n";
echo 'readback exactly matches restore payload: ' . ( $readback === $restore_value ? 'YES' : 'NO' ) . "\n";

echo "\nOK: restore attempt complete\n";
