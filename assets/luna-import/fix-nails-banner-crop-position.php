<?php
/**
 * Guarded fix: the Nails category banner photo (1527x1030, less wide
 * than the 21:9 CSS crop box used for all 4 category banners) has its
 * actual subject (the hand with red-painted nails) in the LOWER half of
 * the frame, but the shared CSS rule
 *   .lunaci-category-banner__img { object-position: center top; }
 * keeps the TOP of the source image visible and crops the bottom -
 * cutting off exactly the nails. Add a small, narrowly-scoped override
 * (body.term-nails only, so Face/Eyes/Lips - already confirmed correctly
 * framed - are untouched) inside the existing "Lunaci Category Banners"
 * wp_snippets entry's inline <style> block, right before its closing
 * </style> tag.
 */

global $wpdb;

$table      = $wpdb->prefix . 'snippets';
$snippet_id = 7;
$override   = "\n    body.term-nails .lunaci-category-banner__img {\n        object-position: center bottom;\n    }\n    ";

echo "--- STEP A: PREPARE ---\n";
$current_code = $wpdb->get_var( $wpdb->prepare( "SELECT code FROM {$table} WHERE id = %d", $snippet_id ) );
if ( null === $current_code ) {
	echo "ABORT: snippet id={$snippet_id} not found in {$table}\n";
	exit( 1 );
}
if ( false !== strpos( $current_code, 'term-nails' ) ) {
	echo "ABORT: a 'term-nails' override already exists in this snippet - refusing to proceed (already fixed?)\n";
	exit( 1 );
}
$style_close_count = substr_count( $current_code, '</style>' );
if ( 1 !== $style_close_count ) {
	echo "ABORT: expected exactly 1 occurrence of </style>, found {$style_close_count}\n";
	exit( 1 );
}
echo "OK: preconditions satisfied (no existing term-nails override, exactly 1 </style> tag)\n";

echo "\n--- STEP B: COMMIT ---\n";
$fresh_code = $wpdb->get_var( $wpdb->prepare( "SELECT code FROM {$table} WHERE id = %d", $snippet_id ) );
if ( $fresh_code !== $current_code ) {
	echo "ABORT: snippet code changed since STEP A (concurrent edit) - refusing to write\n";
	exit( 1 );
}

$replace_count = 0;
$new_code      = preg_replace( '/<\/style>/', $override . '</style>', $fresh_code, 1, $replace_count );
if ( 1 !== $replace_count ) {
	echo "ABORT: expected exactly 1 replacement, got {$replace_count}\n";
	exit( 1 );
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
if ( false === strpos( $verify_code, 'body.term-nails .lunaci-category-banner__img' ) ||
     false === strpos( $verify_code, 'object-position: center bottom;' ) ) {
	echo "FAIL: override not found in snippet code after write\n";
	exit( 1 );
}
echo "verify: term-nails override present\n";

// sanity: make sure the other three category classes are untouched (no accidental broad match)
foreach ( array( 'term-face', 'term-eyes', 'term-lips' ) as $other_term ) {
	if ( false !== strpos( $verify_code, "body.{$other_term} .lunaci-category-banner__img" ) ) {
		echo "FAIL: unexpected override also found for {$other_term} - aborting\n";
		exit( 1 );
	}
}
echo "verify: face/eyes/lips remain untouched\n";

echo "\n=====================================================================\n";
echo "FINAL RESULT: SUCCESS\n";
