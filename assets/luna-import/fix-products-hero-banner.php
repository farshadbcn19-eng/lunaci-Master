<?php
/**
 * Guarded fix: swap the Products page (post ID 61) hero banner image and
 * add a Ken-Burns style zoom effect (matching the Home page hero), by
 * editing the WPCode CSS snippet (custom post type 'wpcode', post ID 483)
 * that defines .page-hero. No Elementor content, no page text, is touched.
 *
 * The exact stored line-ending style (LF vs CRLF) is detected fresh from
 * the live row rather than assumed, since a prior dispatch found the live
 * byte length did not match an LF-only baseline captured via SSH/wp-cli
 * log output.
 *
 * STEP A: structural staleness gate (exact single occurrence of the old block)
 * STEP B: race-condition re-check immediately before write, then exact-match replace + write
 * STEP C: full read-back verification (content match, markers, unrelated CSS intact)
 * STEP D: rebuild WPCode's cached snippet option + clear general caches
 */

global $wpdb;

$post_id = 483;

$old_block_lf = <<<'OLDCSS'
/* ── PAGE HERO ── */
.page-hero {
  height: 72vh; min-height: 520px;
  display: flex; align-items: flex-end;
  padding: 0 8% 80px;
  position: relative; overflow: hidden;
  background:
    linear-gradient(to top, rgba(11,11,11,1) 0%, rgba(11,11,11,.3) 60%, rgba(11,11,11,.55) 100%),
    url('https://lunacibarcelona.com/wp-content/uploads/2026/05/lunaci_hero_retouched-1-scaled.jpg') center/cover no-repeat;
}
.hero-eyebrow { font-size: 11px; letter-spacing: 5px; text-transform: uppercase; color: var(--gold); margin-bottom: 20px; }
OLDCSS;

$new_block_lf = <<<'NEWCSS'
/* ── PAGE HERO ── */
.page-hero {
  height: 72vh; min-height: 520px;
  display: flex; align-items: flex-end;
  padding: 0 8% 80px;
  position: relative; overflow: hidden;
  background: var(--black);
}
.page-hero::before {
  content: '';
  position: absolute; inset: -10px;
  z-index: 0;
  background:
    linear-gradient(to top, rgba(11,11,11,1) 0%, rgba(11,11,11,.3) 60%, rgba(11,11,11,.55) 100%),
    url('https://lunacibarcelona.com/wp-content/uploads/2026/08/lunaimport-product-hero-luna.jpg?v=2') center/cover no-repeat;
  animation: lpHeroKB 20s ease-in-out infinite alternate;
}
.page-hero > div { position: relative; z-index: 1; }
@keyframes lpHeroKB {
  0%   { transform: scale(1); }
  100% { transform: scale(1.08); }
}
.hero-eyebrow { font-size: 11px; letter-spacing: 5px; text-transform: uppercase; color: var(--gold); margin-bottom: 20px; }
NEWCSS;

// trim trailing newline that heredoc adds, since it is not part of the exact in-DB substring
$old_block_lf = rtrim( $old_block_lf, "\n" );
$new_block_lf = rtrim( $new_block_lf, "\n" );

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

$eol       = $is_crlf ? "\r\n" : "\n";
$old_block = $is_crlf ? str_replace( "\n", "\r\n", $old_block_lf ) : $old_block_lf;
$new_block = $is_crlf ? str_replace( "\n", "\r\n", $new_block_lf ) : $new_block_lf;

$old_block_count = substr_count( $current, $old_block );
if ( 1 !== $old_block_count ) {
	echo "ERROR: expected exactly 1 occurrence of the old .page-hero CSS block, found {$old_block_count}\n";
	echo "ABORT: refusing to write against unexpected/changed content\n";
	exit( 1 );
}
echo "OK: found exactly 1 occurrence of the old .page-hero CSS block (using detected line-ending style)\n";

echo "\n=====================================================================\n";
echo "STEP B: COMMIT - race-condition re-check, then exact-match replace + write\n";
echo "=====================================================================\n";

$recheck = $wpdb->get_var(
	$wpdb->prepare( "SELECT post_content FROM {$wpdb->posts} WHERE ID = %d", $post_id )
);
if ( $recheck !== $current ) {
	echo "ERROR: content changed between STEP A and STEP B (race condition) - ABORT\n";
	exit( 1 );
}
echo "OK: race-condition re-check passed - content unchanged immediately before write\n";

$new_content = str_replace( $old_block, $new_block, $current );
$new_len     = strlen( $new_content );

$expected_new_len = $current_len + ( strlen( $new_block ) - strlen( $old_block ) );
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

if ( false === strpos( $verify_content, 'lunaimport-product-hero-luna.jpg' ) ) {
	echo "ERROR: new banner image filename NOT found in read-back content\n";
	$any_error = true;
} else {
	echo "OK: new banner image filename found\n";
}

if ( false !== strpos( $verify_content, 'lunaci_hero_retouched-1-scaled.jpg' ) ) {
	echo "ERROR: old banner image filename STILL present in read-back content\n";
	$any_error = true;
} else {
	echo "OK: old banner image filename confirmed gone\n";
}

if ( false === strpos( $verify_content, '@keyframes lpHeroKB' ) ) {
	echo "ERROR: lpHeroKB keyframes NOT found in read-back content\n";
	$any_error = true;
} else {
	echo "OK: lpHeroKB keyframes found\n";
}

// unrelated-content stability check: every other CSS rule name must still be present unchanged
$unrelated_markers = array(
	'.lp-header', '.lp-nav', '.hero-h1', '.hero-sub', '.lp-section', '.philosophy',
	'.cat-grid', '.cat-img', '.products-row', '.prod-img', '.ing-visual',
	'.quote-banner', '.cta-strip', '.lp-footer', 'body.page-id-61',
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
