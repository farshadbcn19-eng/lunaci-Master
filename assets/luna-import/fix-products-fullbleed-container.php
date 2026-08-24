<?php
/**
 * Guarded fix: the Products page (post ID 61) is wrapped in an Elementor
 * container (auto-generated class .elementor-element-0329089) whose
 * Content Width is set to "Boxed", which caps its inner wrapper
 * (.e-con-inner) at max-width: min(100%, 1140px) and centers it - leaving
 * empty margins on both sides for the ENTIRE page (nav, hero, categories,
 * everything). The page's own <nav> is position:fixed and already spans
 * full width, so the boxed content below it visually breaks against it.
 *
 * Root-caused via a real headless-browser render (Playwright) of the live
 * page: getBoundingClientRect on .e-con-inner showed width=1140 centered
 * inside a 1920px viewport (390px empty margin each side) - exactly
 * matching the user's screenshot.
 *
 * Fix: override just this one container's boxed inner wrapper to go full
 * width, scoped by its unique Elementor element-ID class so no other
 * page/container on the site is affected. This is an additive CSS-only
 * change in the WPCode CSS snippet (post 483) - no Elementor JSON
 * settings and no page text/HTML are touched.
 *
 * STEP A: structural staleness gate (anchor line present exactly once, new rule not already present)
 * STEP B: race-condition re-check immediately before write, then insert + write
 * STEP C: full read-back verification (content match, markers, unrelated CSS intact)
 * STEP D: rebuild WPCode's cached snippet option + clear general caches
 */

global $wpdb;

$post_id = 483;

$anchor_lf = ".lunaci-products-page a { text-decoration: none; }";

$new_rule_lf = <<<'NEWCSS'
/* ── FULL-BLEED CONTAINER FIX ── */
/* This page's outer Elementor container has Content Width set to
   "Boxed", capping .e-con-inner at max-width: min(100%, 1140px) and
   centering it - leaving empty margins on both sides for the whole
   page. The fixed nav bar already spans full width, so boxed content
   below it looked visually cut off / not filling the frame. Override
   just this one container (scoped by its unique element-ID class) to
   go full width, matching the rest of the site's full-bleed sections. */
.elementor-element-0329089.e-con-boxed > .e-con-inner {
  max-width: none;
  width: 100%;
}
NEWCSS;

$new_rule_lf = rtrim( $new_rule_lf, "\n" );

echo "=====================================================================\n";
echo "STEP A: PREPARE - fresh-read wp_posts row ID={$post_id}, detect line-ending style, validate\n";
echo "=====================================================================\n";

$row = $wpdb->get_row(
	$wpdb->prepare( "SELECT ID, post_status, post_content FROM {$wpdb->posts} WHERE ID = %d", $post_id ),
	ARRAY_A
);

if ( ! $row ) {
	echo "ERROR: no wp_posts row found with ID={$post_id}\n";
	echo "ABORT\n";
	exit( 1 );
}

$current = $row['post_content'];
$current_len = strlen( $current );

echo "found row: ID={$row['ID']}  post_status={$row['post_status']}  byte_len={$current_len}\n";

if ( 'publish' !== $row['post_status'] ) {
	echo "ERROR: expected post_status=publish, found post_status={$row['post_status']}\n";
	echo "ABORT\n";
	exit( 1 );
}

$crlf_count = substr_count( $current, "\r\n" );
$lf_count   = substr_count( $current, "\n" );
$is_crlf    = ( $crlf_count > 0 && $crlf_count === $lf_count );
echo "detected line endings: crlf_count={$crlf_count} lf_count={$lf_count} -> using " . ( $is_crlf ? 'CRLF' : 'LF' ) . "\n";

$eol      = $is_crlf ? "\r\n" : "\n";
$anchor   = $is_crlf ? str_replace( "\n", "\r\n", $anchor_lf ) : $anchor_lf;
$new_rule = $is_crlf ? str_replace( "\n", "\r\n", $new_rule_lf ) : $new_rule_lf;

$anchor_count = substr_count( $current, $anchor );
if ( 1 !== $anchor_count ) {
	echo "ERROR: expected exactly 1 occurrence of the anchor line, found {$anchor_count}\n";
	echo "ABORT: refusing to write against unexpected/changed content\n";
	exit( 1 );
}
echo "OK: found exactly 1 occurrence of the anchor line (using detected line-ending style)\n";

if ( false !== strpos( $current, 'elementor-element-0329089.e-con-boxed' ) ) {
	echo "ERROR: the full-bleed fix rule already appears to be present - refusing to duplicate it\n";
	echo "ABORT\n";
	exit( 1 );
}
echo "OK: full-bleed fix rule not already present\n";

echo "\n=====================================================================\n";
echo "STEP B: COMMIT - race-condition re-check, then insert + write\n";
echo "=====================================================================\n";

$recheck = $wpdb->get_var(
	$wpdb->prepare( "SELECT post_content FROM {$wpdb->posts} WHERE ID = %d", $post_id )
);
if ( $recheck !== $current ) {
	echo "ERROR: content changed between STEP A and STEP B (race condition) - ABORT\n";
	exit( 1 );
}
echo "OK: race-condition re-check passed - content unchanged immediately before write\n";

$replacement = $anchor . $eol . $eol . $new_rule;
$new_content = str_replace( $anchor, $replacement, $current, $replace_count );

if ( 1 !== $replace_count ) {
	echo "ERROR: expected exactly 1 replacement, made {$replace_count} - ABORT\n";
	exit( 1 );
}

$new_len          = strlen( $new_content );
$expected_new_len = $current_len + strlen( $eol ) + strlen( $eol ) + strlen( $new_rule );
if ( $new_len !== $expected_new_len ) {
	echo "ERROR: computed new content length ({$new_len}) does not match expected ({$expected_new_len}) - refusing to write\n";
	exit( 1 );
}
echo "OK: computed new content, byte_len={$new_len} (was {$current_len})\n";

$update_ok = $wpdb->update(
	$wpdb->posts,
	array( 'post_content' => $new_content ),
	array( 'ID' => $post_id ),
	array( '%s' ),
	array( '%d' )
);

if ( false === $update_ok ) {
	echo "ERROR: \$wpdb->update() failed: {$wpdb->last_error}\n";
	echo "ABORT\n";
	exit( 1 );
}
echo "OK: \$wpdb->update() returned {$update_ok} (rows affected)\n";
clean_post_cache( $post_id );
echo "OK: clean_post_cache({$post_id}) called\n";

echo "\n=====================================================================\n";
echo "STEP C: VERIFY - fresh read-back from DB and confirm exact match\n";
echo "=====================================================================\n";

$verify_content = $wpdb->get_var(
	$wpdb->prepare( "SELECT post_content FROM {$wpdb->posts} WHERE ID = %d", $post_id )
);

$verify_len = strlen( $verify_content );
echo "read-back: byte_len={$verify_len}\n";

$any_error = false;

if ( $verify_content !== $new_content ) {
	echo "ERROR: read-back content does NOT match the intended new content byte-for-byte\n";
	$any_error = true;
} else {
	echo "OK: content_matches: PASS\n";
}

if ( false === strpos( $verify_content, 'elementor-element-0329089.e-con-boxed' ) ) {
	echo "ERROR: full-bleed fix rule NOT found in read-back content\n";
	$any_error = true;
} else {
	echo "OK: full-bleed fix rule found\n";
}

if ( false === strpos( $verify_content, 'lunaimport-product-hero-luna.jpg' ) ) {
	echo "ERROR: banner image reference missing after write - possible unintended corruption\n";
	$any_error = true;
} else {
	echo "OK: banner image reference still present\n";
}

// unrelated-content stability check: every other CSS rule name must still be present unchanged
$unrelated_markers = array(
	'.lp-header', '.lp-nav', '.page-hero', '.hero-h1', '.hero-sub', '.lp-section', '.philosophy',
	'.cat-grid', '.cat-img', '.products-row', '.prod-img', '.ing-visual',
	'.quote-banner', '.cta-strip', '.lp-footer', 'body.page-id-61', '@keyframes lpHeroKB',
);
foreach ( $unrelated_markers as $marker ) {
	if ( false === strpos( $verify_content, $marker ) ) {
		echo "ERROR: unrelated marker '{$marker}' missing after write - possible unintended corruption\n";
		$any_error = true;
	}
}
if ( ! $any_error ) {
	echo "OK: all unrelated CSS markers still present - no unintended corruption detected\n";
}

if ( $any_error ) {
	echo "\nFINAL RESULT: FAILURE\n";
	exit( 1 );
}

echo "\n=====================================================================\n";
echo "STEP D: cache cleanup (WPCode snippet cache + general caches)\n";
echo "=====================================================================\n";

if ( function_exists( 'wpcode' ) ) {
	try {
		wpcode()->cache->cache_all_loaded_snippets();
		echo "OK: wpcode()->cache->cache_all_loaded_snippets() executed\n";
	} catch ( \Throwable $e ) {
		echo "WARNING: wpcode cache rebuild threw: " . $e->getMessage() . "\n";
	}
} else {
	echo "WARNING: wpcode() function not available - could not rebuild WPCode snippet cache\n";
}

if ( class_exists( '\\Elementor\\Plugin' ) ) {
	try {
		\Elementor\Plugin::instance()->files_manager->clear_cache();
		echo "OK: Elementor files_manager->clear_cache() executed\n";
	} catch ( \Throwable $e ) {
		echo "WARNING: Elementor clear_cache() threw: " . $e->getMessage() . "\n";
	}
	delete_post_meta( 61, '_elementor_css' );
	echo "OK: deleted _elementor_css postmeta for page 61\n";
}

wp_cache_flush();
echo "OK: wp_cache_flush() executed\n";

echo "\nFINAL RESULT: SUCCESS\n";
