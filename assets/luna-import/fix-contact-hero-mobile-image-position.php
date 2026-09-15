<?php
/**
 * Fixes the Contact hero banner image "stretched / LUNACI branding not
 * visible" issue reported by the client on mobile.
 *
 * Root cause (confirmed by downloading and inspecting the actual image,
 * not guessing): the banner photo is 1942x809px (wide, ~2.4:1, shot for
 * desktop), used with `background: ... center/cover no-repeat`. On a
 * narrow, tall mobile viewport (.contact-hero has min-height:100vh),
 * `background-position: center` + `cover` crops almost the entire width,
 * showing only a thin vertical slice around the exact horizontal middle
 * of the photo - which is empty desk/window space. The model (positioned
 * ~55-80% across) and the LUNACI-branded notebook cover and box (left and
 * right edges) fall outside that crop entirely on mobile.
 *
 * Fix: add a mobile-only background-position override that shifts the
 * visible crop window toward the model/branding (matches the client's
 * expectation that "the banner should adapt for mobile like other
 * pages" - the underlying full-bleed cover technique already used
 * elsewhere on the site, just needs an art-directed position for this
 * specific photo's off-center composition).
 *
 * Applies to both Contact EN (post 60) and ES (post 770).
 */

global $wpdb;

$needle      = '.contact-hero { padding-left: 6%; padding-right: 6%; }';
$replacement = $needle . ' .contact-hero { background-position: 78% center; }';

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
		continue;
	}

	$live = $row['meta_value'];
	$count_needle  = substr_count( $live, $needle );
	$count_already = substr_count( $live, 'background-position: 78% center' );

	echo "count anchor line: $count_needle\n";
	echo "count 'already applied' marker: $count_already\n";

	if ( 1 !== $count_needle ) {
		echo "ERROR: expected exactly 1 occurrence of the anchor line, found $count_needle - refusing to modify\n\n";
		continue;
	}
	if ( 0 !== $count_already ) {
		echo "SKIP: fix already applied\n\n";
		continue;
	}
	echo "OK: preconditions match exactly\n";

	echo "\n--- STEP B: COMMIT ---\n";
	$updated = str_replace( $needle, $replacement, $live, $replace_count );
	echo "replacements made: $replace_count\n";

	if ( 1 !== $replace_count ) {
		echo "ERROR: expected exactly 1 replacement, made $replace_count - refusing to modify\n\n";
		continue;
	}
	echo "OK: computed updated content, new length=" . strlen( $updated ) . " (was " . strlen( $live ) . ")\n";

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

	$has_fix     = ( false !== strpos( $verify_row['meta_value'], 'background-position: 78% center' ) );
	$exact_match = ( $verify_row['meta_value'] === $updated );
	$decoded     = json_decode( $verify_row['meta_value'], true );
	$json_ok     = ( null !== $decoded && JSON_ERROR_NONE === json_last_error() );

	echo "new rule present: " . ( $has_fix ? 'YES' : 'NO' ) . "\n";
	echo "byte-for-byte match: " . ( $exact_match ? 'YES' : 'NO' ) . "\n";
	echo "still valid JSON: " . ( $json_ok ? 'YES' : 'NO' ) . "\n";

	if ( $has_fix && $exact_match && $json_ok ) {
		echo "OK: verification passed for post $post_id\n";

		// Clear Elementor's internal element-render cache (root cause found
		// and fixed earlier this session: _elementor_element_cache holds a
		// stale rendered copy that raw postmeta writes never invalidate).
		delete_post_meta( $post_id, '_elementor_element_cache' );
		clean_post_cache( $post_id );
		echo "cleared _elementor_element_cache and post cache for post $post_id\n";
	} else {
		echo "ERROR: verification FAILED for post $post_id\n";
	}
	echo "\n";
}

echo "OK: script complete\n";
