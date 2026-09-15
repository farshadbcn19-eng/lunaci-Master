<?php
/**
 * Fix the Contact page hero ".contact-hero { min-height: 100vh; }" mobile
 * layout issue reported by the client (banner "broken"/misaligned on real
 * mobile Safari, not reproduced by headless Chromium screenshots).
 *
 * Root cause: on iOS Safari, `100vh` is calculated against the LARGEST
 * possible viewport (browser chrome collapsed), not the viewport actually
 * visible when the page loads (chrome expanded). This makes a
 * `min-height: 100vh` element taller than the visible screen, causing
 * clipped/misaligned content and an inconsistent look between page loads
 * as Safari's chrome expands/collapses on scroll.
 *
 * Fix: add `min-height: 100dvh;` (dynamic viewport height - correctly
 * tracks the actual visible viewport, well supported on modern mobile
 * browsers) right after the existing `min-height: 100vh;` line. Older
 * browsers that don't understand `dvh` simply ignore that declaration and
 * keep using the `vh` fallback already in place - a safe, additive,
 * non-destructive CSS-only change.
 *
 * Applies to both Contact EN (post 60) and ES (post 770), each with its
 * own independent copy of _elementor_data (WPML). Uses the same
 * read-verify-write-verify pattern via direct $wpdb->update() on
 * wp_postmeta proven safe earlier this session (never update_post_meta(),
 * which triggered a KSES pipeline that corrupted <style> tags).
 */

global $wpdb;

$needle      = 'min-height: 100vh;';
$replacement = "min-height: 100vh;\n  min-height: 100dvh;";

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
		echo "ERROR: no _elementor_data postmeta row found for post $post_id\n";
		echo "ABORT for this post\n\n";
		continue;
	}

	$live = $row['meta_value'];
	$count_hero_marker = substr_count( $live, '.contact-hero {' );
	$count_needle       = substr_count( $live, $needle );
	$count_already      = substr_count( $live, 'min-height: 100dvh;' );

	echo "count '.contact-hero {' marker: $count_hero_marker\n";
	echo "count '$needle': $count_needle\n";
	echo "count 'min-height: 100dvh;' (already applied?): $count_already\n";

	if ( 1 !== $count_hero_marker ) {
		echo "ERROR: expected exactly 1 '.contact-hero {' marker, found $count_hero_marker - refusing to modify\n";
		echo "ABORT for this post\n\n";
		continue;
	}
	if ( 1 !== $count_needle ) {
		echo "ERROR: expected exactly 1 occurrence of '$needle', found $count_needle - refusing to modify\n";
		echo "ABORT for this post\n\n";
		continue;
	}
	if ( 0 !== $count_already ) {
		echo "SKIP: fix already applied (100dvh present) - nothing to do\n\n";
		continue;
	}
	echo "OK: preconditions match exactly\n";

	echo "\n--- STEP B: COMMIT ---\n";
	$updated = str_replace( $needle, $replacement, $live, $replace_count );
	echo "replacements made: $replace_count\n";

	if ( 1 !== $replace_count ) {
		echo "ERROR: expected exactly 1 replacement, made $replace_count - refusing to modify\n";
		echo "ABORT for this post\n\n";
		continue;
	}
	echo "OK: computed updated content locally, new length=" . strlen( $updated ) . " (was " . strlen( $live ) . ")\n";

	$update_ok = $wpdb->update(
		$wpdb->postmeta,
		array( 'meta_value' => $updated ),
		array( 'meta_id' => $row['meta_id'] ),
		array( '%s' ),
		array( '%d' )
	);

	if ( false === $update_ok ) {
		echo "ERROR: \$wpdb->update() failed: {$wpdb->last_error}\n";
		echo "ABORT for this post\n\n";
		continue;
	}
	echo "OK: update() returned $update_ok (rows affected)\n";

	echo "\n--- STEP C: VERIFY ---\n";
	$verify_row = $wpdb->get_row(
		$wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_id = %d", $row['meta_id'] ),
		ARRAY_A
	);

	if ( ! $verify_row ) {
		echo "ERROR: could not re-read meta_id {$row['meta_id']} after update\n";
		echo "ABORT for this post\n\n";
		continue;
	}

	$has_dvh    = ( false !== strpos( $verify_row['meta_value'], 'min-height: 100dvh;' ) );
	$exact_match = ( $verify_row['meta_value'] === $updated );
	echo "new 'min-height: 100dvh;' present: " . ( $has_dvh ? 'YES' : 'NO' ) . "\n";
	echo "stored meta_value matches computed updated content byte-for-byte: " . ( $exact_match ? 'YES' : 'NO' ) . "\n";

	// Confirm the JSON is still valid (structural safety check, given the
	// earlier <style>-tag corruption incident this session).
	$decoded = json_decode( $verify_row['meta_value'], true );
	$json_ok = ( null !== $decoded && JSON_ERROR_NONE === json_last_error() );
	echo "_elementor_data still parses as valid JSON: " . ( $json_ok ? 'YES' : 'NO' ) . "\n";

	if ( ! $has_dvh || ! $exact_match || ! $json_ok ) {
		echo "ERROR: verification FAILED for post $post_id\n";
	} else {
		echo "OK: verification passed for post $post_id\n";
	}
	echo "\n";
}

echo "OK: script complete\n";
