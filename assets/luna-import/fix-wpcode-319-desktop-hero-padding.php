<?php
/**
 * Guarded fix: WPCode snippet post 319 (site-wide/everywhere auto-insert
 * CSS, no conditional logic - applies to every viewport) forces
 * `.lna-hero__content{padding:0 5% 8vh !important;...}` unconditionally.
 * This beats the About Us hero fix's intended `12vh` bottom padding
 * (Elementor's own rule has no !important), so the "Our Story" heading
 * on desktop never actually moved up as intended.
 *
 * The user confirmed mobile already looked correct before this fix, so
 * the base (mobile) 8vh rule must stay untouched - only desktop should
 * get the extra breathing room. Fix: append a desktop-only
 * (min-width:1025px) media query overriding just the padding to 12vh,
 * leaving every other rule in the snippet (margins, text-align, overlay
 * gradient, mobile padding) exactly as-is.
 */

$post_id = 319;

echo "=====================================================================\n";
echo "STEP A: PREPARE - confirm current content matches expected baseline\n";
echo "=====================================================================\n";

$post = get_post( $post_id );
if ( ! $post ) {
	echo "ABORT: post {$post_id} not found\n";
	exit( 1 );
}

$needle       = 'padding:0 5% 8vh !important;';
$occurrences  = substr_count( $post->post_content, $needle );
$already_has  = false !== strpos( $post->post_content, '@media (min-width:1025px){.lna-hero__content{padding:0 5% 12vh !important;}}' );

echo "post {$post_id} post_content length: " . strlen( $post->post_content ) . "\n";
echo "occurrences of base padding rule: {$occurrences}\n";
echo "desktop override already present: " . ( $already_has ? 'yes' : 'no' ) . "\n";

if ( $already_has ) {
	echo "OK: desktop override already applied - nothing to do\n";
	echo "\nFINAL RESULT: SUCCESS (no-op, already fixed)\n";
	exit( 0 );
}

if ( 1 !== $occurrences ) {
	echo "ABORT: expected exactly 1 occurrence of base padding rule, found {$occurrences} - content may have changed since diagnosis\n";
	exit( 1 );
}

echo "OK: baseline confirmed, proceeding\n";

echo "\n=====================================================================\n";
echo "STEP B: COMMIT - append desktop-only padding override\n";
echo "=====================================================================\n";

$fresh_post = get_post( $post_id );
$fresh_occurrences = substr_count( $fresh_post->post_content, $needle );
if ( 1 !== $fresh_occurrences ) {
	echo "ABORT: race check failed, content changed - occurrences now {$fresh_occurrences}\n";
	exit( 1 );
}

$addition    = "\n@media (min-width:1025px){.lna-hero__content{padding:0 5% 12vh !important;}}\n";
$new_content = $fresh_post->post_content . $addition;

$result = wp_update_post(
	array(
		'ID'           => $post_id,
		'post_content' => $new_content,
	),
	true
);

if ( is_wp_error( $result ) ) {
	echo "ABORT: wp_update_post failed: " . $result->get_error_message() . "\n";
	exit( 1 );
}

echo "wp_update_post() returned post ID: {$result}\n";
clean_post_cache( $post_id );
wp_cache_flush();
echo "OK: caches cleared\n";

echo "\n=====================================================================\n";
echo "STEP C: VERIFY - re-read from DB and confirm the override is present\n";
echo "=====================================================================\n";

$verify_post = get_post( $post_id );
$verify_has_override = false !== strpos( $verify_post->post_content, '@media (min-width:1025px){.lna-hero__content{padding:0 5% 12vh !important;}}' );
$verify_base_intact  = 1 === substr_count( $verify_post->post_content, $needle );

echo "desktop override present: " . ( $verify_has_override ? 'yes' : 'no' ) . "\n";
echo "base (mobile) rule still intact (exactly 1 occurrence): " . ( $verify_base_intact ? 'yes' : 'no' ) . "\n";

if ( $verify_has_override && $verify_base_intact ) {
	echo "\nFINAL RESULT: SUCCESS\n";
} else {
	echo "\nFINAL RESULT: FAILURE - see lines above\n";
	exit( 1 );
}
