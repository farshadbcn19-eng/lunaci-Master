<?php
/**
 * EMERGENCY REPAIR: the mobile-viewport fix (fix-contact-hero-mobile-viewport.php)
 * used a PHP double-quoted string "min-height: 100vh;\n  min-height: 100dvh;"
 * as its replacement text. The "\n" there is an ACTUAL raw newline byte
 * (0x0A), not the two-character JSON escape sequence "\n" (backslash + n).
 * Inserting a raw, unescaped control character into the middle of a JSON
 * string value produces syntactically invalid JSON - confirmed via this
 * session's own verification step, which reported
 * "_elementor_data still parses as valid JSON: NO" for both post 60 and 770
 * immediately after that fix ran.
 *
 * This finds the exact raw-newline sequence that fix introduced and
 * replaces it with the correct two-character JSON escape "\n" (backslash
 * followed by the letter n), restoring valid JSON while keeping the new
 * CSS line intact. Does not touch anything else.
 */

global $wpdb;

// The exact bytes as inserted by the buggy fix: a real newline character
// between the two min-height declarations.
$broken_needle = "min-height: 100vh;\n  min-height: 100dvh;";
// The corrected form: a literal two-character backslash-n escape, valid
// inside a JSON string.
$fixed_replacement = 'min-height: 100vh;\n  min-height: 100dvh;';

foreach ( array( 'EN Contact' => 60, 'ES Contacto' => 770 ) as $label => $post_id ) {
	echo "==========================================================================\n";
	echo "{$label} (post {$post_id})\n";
	echo "==========================================================================\n";

	echo "--- STEP A: PREPARE ---\n";
	$row = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT meta_id, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_elementor_data'",
			$post_id
		),
		ARRAY_A
	);

	if ( ! $row ) {
		echo "ERROR: no _elementor_data postmeta row found for post $post_id\n\n";
		continue;
	}

	$live = $row['meta_value'];

	$decoded_before = json_decode( $live, true );
	$json_ok_before  = ( null !== $decoded_before && JSON_ERROR_NONE === json_last_error() );
	echo "currently parses as valid JSON: " . ( $json_ok_before ? 'YES (nothing to repair)' : 'NO' ) . "\n";

	if ( $json_ok_before ) {
		echo "SKIP: already valid, no repair needed\n\n";
		continue;
	}

	$count_broken = substr_count( $live, $broken_needle );
	echo "count of the exact broken (raw-newline) sequence: $count_broken\n";

	if ( 1 !== $count_broken ) {
		echo "ERROR: expected exactly 1 occurrence of the broken sequence, found $count_broken - refusing to modify (manual inspection needed)\n\n";
		continue;
	}
	echo "OK: found exactly 1 occurrence of the broken sequence\n";

	echo "\n--- STEP B: COMMIT ---\n";
	$updated = str_replace( $broken_needle, $fixed_replacement, $live, $replace_count );
	echo "replacements made: $replace_count\n";

	if ( 1 !== $replace_count ) {
		echo "ERROR: expected exactly 1 replacement, made $replace_count - refusing to modify\n\n";
		continue;
	}

	$decoded_after = json_decode( $updated, true );
	$json_ok_after  = ( null !== $decoded_after && JSON_ERROR_NONE === json_last_error() );
	echo "computed repaired content parses as valid JSON: " . ( $json_ok_after ? 'YES' : 'NO' ) . "\n";

	if ( ! $json_ok_after ) {
		echo "ERROR: repaired content still does not parse as valid JSON (json error: " . json_last_error_msg() . ") - refusing to write\n\n";
		continue;
	}

	echo "OK: computed repaired content is valid JSON, new length=" . strlen( $updated ) . " (was " . strlen( $live ) . ")\n";

	$update_ok = $wpdb->update(
		$wpdb->postmeta,
		array( 'meta_value' => $updated ),
		array( 'meta_id' => $row['meta_id'] ),
		array( '%s' ),
		array( '%d' )
	);

	if ( false === $update_ok ) {
		echo "ERROR: \$wpdb->update() failed: {$wpdb->last_error}\n\n";
		continue;
	}
	echo "OK: update() returned $update_ok (rows affected)\n";

	echo "\n--- STEP C: VERIFY ---\n";
	$verify_row = $wpdb->get_row(
		$wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_id = %d", $row['meta_id'] ),
		ARRAY_A
	);

	if ( ! $verify_row ) {
		echo "ERROR: could not re-read meta_id {$row['meta_id']} after update\n\n";
		continue;
	}

	$exact_match = ( $verify_row['meta_value'] === $updated );
	$decoded_verify = json_decode( $verify_row['meta_value'], true );
	$json_ok_verify = ( null !== $decoded_verify && JSON_ERROR_NONE === json_last_error() );
	$has_dvh = ( false !== strpos( $verify_row['meta_value'], 'min-height: 100dvh;' ) );

	echo "stored meta_value matches computed repaired content byte-for-byte: " . ( $exact_match ? 'YES' : 'NO' ) . "\n";
	echo "stored meta_value parses as valid JSON: " . ( $json_ok_verify ? 'YES' : 'NO' ) . "\n";
	echo "stored meta_value still contains 'min-height: 100dvh;': " . ( $has_dvh ? 'YES' : 'NO' ) . "\n";

	if ( $exact_match && $json_ok_verify && $has_dvh ) {
		echo "OK: verification passed for post $post_id\n";
	} else {
		echo "ERROR: verification FAILED for post $post_id\n";
	}
	echo "\n";
}

echo "OK: script complete\n";
