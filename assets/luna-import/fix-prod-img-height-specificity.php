<?php
/**
 * Guarded fix: Products page product images (e.g. Blusher) not filling
 * their card frame.
 *
 * Root cause (confirmed via live browser CSS-rule matching): the WPCode
 * global snippet (post 483) declares `.prod-img{width:100%;height:100%;
 * object-fit:cover;...}`, but Elementor's own frontend stylesheet also
 * ships `.elementor img{height:auto;max-width:100%;...}`. CSS specificity
 * for `.elementor img` (one class + one element = 0,1,1) beats `.prod-img`
 * (one class = 0,1,0), so Elementor's `height:auto` wins regardless of
 * source order - the image renders at its own natural aspect ratio
 * instead of stretching to fill the `.prod-img-wrap` container (which has
 * `aspect-ratio:3/4`), leaving a visible gap whenever a product photo's
 * natural aspect ratio differs from 3/4. This affects every product photo
 * to some degree, but is only clearly visible when the mismatch is large
 * (as with the new Blusher photo, ~0.80 natural ratio vs the container's
 * 0.75).
 *
 * Fix: add `!important` to just the `height` declaration in `.prod-img`,
 * the standard, minimal way to win against a same-or-higher-specificity
 * third-party framework rule without touching anything else. This is a
 * global WPCode snippet, so the fix applies to every product card, not
 * just Blusher - the correct root-cause fix rather than a band-aid for
 * one product.
 */

global $wpdb;

$old_fragment = '.prod-img { width: 100%; height: 100%; object-fit: cover; transition: transform 1s cubic-bezier(.25,.46,.45,.94); }';
$new_fragment = '.prod-img { width: 100%; height: 100% !important; object-fit: cover; transition: transform 1s cubic-bezier(.25,.46,.45,.94); }';

$snippet_id = 483;

echo "=====================================================================\n";
echo "STEP A: PREPARE - fresh-read + validate preconditions\n";
echo "=====================================================================\n";

$raw = $wpdb->get_var(
	$wpdb->prepare( "SELECT post_content FROM {$wpdb->posts} WHERE ID = %d", $snippet_id )
);
if ( null === $raw ) {
	echo "ABORT: snippet post {$snippet_id} not found\n";
	exit( 1 );
}

$old_count = substr_count( $raw, $old_fragment );
$new_count = substr_count( $raw, $new_fragment );
echo "old_fragment occurs {$old_count}x  new_fragment already present {$new_count}x\n";

if ( 1 !== $old_count ) {
	echo "ABORT: expected exactly 1 occurrence of the old fragment, found {$old_count} - refusing to proceed\n";
	exit( 1 );
}
if ( 0 !== $new_count ) {
	echo "ABORT: the new fragment is already present - refusing to proceed (already fixed?)\n";
	exit( 1 );
}
echo "OK: preconditions satisfied\n";

echo "\n=====================================================================\n";
echo "STEP B: COMMIT - race-check, replace, write\n";
echo "=====================================================================\n";

$fresh_raw = $wpdb->get_var(
	$wpdb->prepare( "SELECT post_content FROM {$wpdb->posts} WHERE ID = %d", $snippet_id )
);
if ( $fresh_raw !== $raw ) {
	echo "ABORT: content changed since STEP A (concurrent edit detected) - refusing to write\n";
	exit( 1 );
}
echo "PASS: race-condition guard confirms content unchanged\n";

$new_raw = str_replace( $old_fragment, $new_fragment, $fresh_raw );
if ( substr_count( $new_raw, $new_fragment ) !== 1 || false !== strpos( $new_raw, $old_fragment ) ) {
	echo "ABORT: replacement verification failed\n";
	exit( 1 );
}

$updated = $wpdb->update(
	$wpdb->posts,
	array( 'post_content' => $new_raw ),
	array( 'ID' => $snippet_id )
);
echo "wpdb->update() rows affected: " . var_export( $updated, true ) . "\n";

clean_post_cache( $snippet_id );
wp_cache_flush();
echo "OK: caches cleared\n";

echo "\n=====================================================================\n";
echo "STEP C: VERIFY - fresh read-back\n";
echo "=====================================================================\n";

$verify_raw = $wpdb->get_var(
	$wpdb->prepare( "SELECT post_content FROM {$wpdb->posts} WHERE ID = %d", $snippet_id )
);
$has_new = $verify_raw && substr_count( $verify_raw, $new_fragment ) === 1;
$has_old = $verify_raw && false !== strpos( $verify_raw, $old_fragment );
echo "old fragment gone: " . ( ! $has_old ? 'yes' : 'no' ) . "   new fragment present(x1): " . ( $has_new ? 'yes' : 'no' ) . "\n";

if ( $has_new && ! $has_old ) {
	echo "\nFINAL RESULT: SUCCESS\n";
} else {
	echo "\nFINAL RESULT: FAILURE - see lines above\n";
	exit( 1 );
}
