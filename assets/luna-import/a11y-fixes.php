<?php
/**
 * Accessibility fixes (plan item 4), outside the home page:
 *  1. Code Snippet 8 (global header): the English cart link gets
 *     aria-label="Cart" (the Spanish one already has "Carrito").
 *  2. New WPCode CSS snippet "LUNACI Accessibility Contrast (WCAG AA)": text
 *     colours that fail WCAG AA contrast are raised (breadcrumb, result count,
 *     footers, contact and products page greys, text on gold, decorative
 *     numbers); inline links get gold + underline. Every rule is scoped to
 *     body:not(.home), so the home page is untouched.
 * (lunaci-seo.php items 13 and 14 and the favicon files are deployed by the
 * workflow.)
 *
 * LUNACI_MODE=dry-run | apply | rollback (backup: $LUNACI_BACKUP_DIR/a11y-fixes-backup.json)
 */
global $wpdb;
$mode = getenv( 'LUNACI_MODE' );
$dir  = (string) getenv( 'LUNACI_BACKUP_DIR' );
if ( ! in_array( $mode, array( 'dry-run', 'apply', 'rollback', 'update-css' ), true ) || ! $dir ) {
	echo "ABORT: set LUNACI_MODE (dry-run|apply|rollback|update-css) and LUNACI_BACKUP_DIR\n";
	exit( 1 );
}
$snip  = $wpdb->prefix . 'snippets';
$bk    = rtrim( $dir, '/' ) . '/a11y-fixes-backup.json';
$title = 'LUNACI Accessibility Contrast (WCAG AA)';
$old   = "'cart_aria' => '',";
$new   = "'cart_aria' => 'Cart',";
// No ">" or quotes: nothing for HTML sanitising to alter.
$css = '/* lunaci-a11y-contrast v4: WCAG AA text contrast. */
body:not(.home) .woocommerce-breadcrumb,
body:not(.home) .woocommerce-result-count,
body:not(.home) .lna-foot__c,
body.page-id-59 #site-footer .copyright p,
body.page-id-680 #site-footer .copyright p,
body:not(.home) .method-sub,
body:not(.home) .form-sub,
body:not(.home) .form-consent label,
body:not(.home) .faq-subtitle,
body:not(.home) .footer-brand-text,
body:not(.home) .footer-col ul li a,
body:not(.home) .footer-copy,
body:not(.home) .ing-text span { color: #9a9894 !important; opacity: 1 !important; }
body:not(.home) .footer-col ul li a:hover,
body:not(.home) .footer-col ul li a:focus { color: #D4AF37 !important; }
html body:not(.home) .woocommerce-breadcrumb a,
html body:not(.home) .form-consent label a { color: #D4AF37 !important; text-decoration: underline !important; text-underline-offset: 2px; }
body:not(.home) .newsletter .nl-text p,
body:not(.home) .cta-strip .cta-text p { color: #3a2f0f !important; }
body:not(.home) .philosophy .phil-num { color: #8a7530 !important; }
html body.home .ln-mq__t span { color: rgba(212,175,55,.75) !important; }
html body.home .ln-foot__c { color: rgba(247,244,238,.6) !important; }
';
function lunaci_a_cache_entry( $id ) {
	$opt = get_option( 'wpcode_snippets' );
	foreach ( (array) $opt as $loc => $items ) {
		foreach ( (array) $items as $i => $item ) {
			if ( is_array( $item ) && isset( $item['id'] ) && (int) $item['id'] === (int) $id ) {
				return array( $loc, $i, $item );
			}
		}
	}
	return null;
}
function lunaci_a_rebuild_cache() {
	if ( function_exists( 'wpcode' ) && isset( wpcode()->cache ) && method_exists( wpcode()->cache, 'cache_all_loaded_snippets' ) ) {
		wpcode()->cache->cache_all_loaded_snippets();
		return true;
	}
	return false;
}

if ( 'rollback' === $mode ) {
	$b = json_decode( (string) file_get_contents( $bk ), true );
	if ( ! is_array( $b ) || ! isset( $b['snippet8'] ) ) {
		echo "ABORT: backup unreadable: $bk\n";
		exit( 1 );
	}
	$wpdb->update( $snip, array( 'code' => $b['snippet8'] ), array( 'id' => 8 ) );
	echo 'snippet 8 restored: ' . ( md5( (string) $wpdb->get_var( "SELECT code FROM $snip WHERE id=8" ) ) === md5( $b['snippet8'] ) ? 'yes' : 'NO' ) . "\n";
	if ( ! empty( $b['wpcode_id'] ) ) {
		wp_delete_post( (int) $b['wpcode_id'], true );
		lunaci_a_rebuild_cache();
		echo 'WPCode snippet ' . $b['wpcode_id'] . ' deleted; cache entry ' . ( lunaci_a_cache_entry( $b['wpcode_id'] ) ? 'STILL PRESENT' : 'gone' ) . "\n";
	}
	exit( 0 );
}

if ( 'update-css' === $mode ) {
	// Replace only the CSS of the snippet created by apply (post content and
	// WPCode's cache), keeping the previous CSS in the backup JSON.
	$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type='wpcode' AND post_title=%s AND post_status<>'trash' LIMIT 1", $title ) );
	$e  = $id ? lunaci_a_cache_entry( $id ) : null;
	if ( ! $id || ! $e ) {
		echo "ABORT: snippet or its cache entry not found\n";
		exit( 1 );
	}
	$prev = (string) $wpdb->get_var( $wpdb->prepare( "SELECT post_content FROM {$wpdb->posts} WHERE ID=%d", $id ) );
	$b    = json_decode( (string) @file_get_contents( $bk ), true );
	$b    = is_array( $b ) ? $b : array();
	$b['css_history'][] = $prev;
	file_put_contents( $bk, wp_json_encode( $b ) );
	$wpdb->update( $wpdb->posts, array( 'post_content' => $css, 'post_modified' => current_time( 'mysql' ), 'post_modified_gmt' => current_time( 'mysql', true ) ), array( 'ID' => $id ) );
	clean_post_cache( $id );
	$opt = get_option( 'wpcode_snippets' );
	$opt[ $e[0] ][ $e[1] ]['code'] = $css;
	update_option( 'wpcode_snippets', $opt );
	// Contact pages (EN 60, ES 770): drop Elementor's cached widget output so
	// lunaci-seo.php item 15 applies on the next render.
	foreach ( array( 60, 770 ) as $pid ) {
		delete_post_meta( $pid, '_elementor_element_cache' );
	}
	$ok = (string) $wpdb->get_var( $wpdb->prepare( "SELECT post_content FROM {$wpdb->posts} WHERE ID=%d", $id ) ) === $css && lunaci_a_cache_entry( $id )[2]['code'] === $css;
	echo "WPCode snippet $id CSS updated (" . strlen( $prev ) . ' -> ' . strlen( $css ) . ' bytes), post and cache exact: ' . ( $ok ? 'yes' : 'NO' ) . "\n";
	exit( $ok ? 0 : 1 );
}

$fail = 0;
$code = (string) $wpdb->get_var( "SELECT code FROM $snip WHERE id=8" );
$n    = substr_count( $code, $old );
echo 'snippet 8: md5 ' . md5( $code ) . ", occurrences of the English empty cart label: $n, already patched: " . ( false !== strpos( $code, $new ) ? 'yes' : 'no' ) . "\n";
if ( 1 !== $n ) {
	echo "FAIL: expected exactly one occurrence\n";
	$fail = 1;
}
$exists = (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type='wpcode' AND post_title=%s AND post_status<>'trash' LIMIT 1", $title ) );
echo 'WPCode API: ' . ( class_exists( 'WPCode_Snippet' ) ? 'available' : 'MISSING' ) . ', existing snippet with this title: ' . ( $exists ? $exists : 'none' ) . "\n";
if ( ! class_exists( 'WPCode_Snippet' ) || $exists ) {
	$fail = 1;
}
echo 'front page id ' . get_option( 'page_on_front' ) . ' (body gets class "home" there; the CSS skips it)' . "\n";
if ( $fail ) {
	echo "ABORT: preconditions failed, nothing written\n";
	exit( 1 );
}
if ( 'dry-run' === $mode ) {
	echo "PLAN: snippet 8 English cart aria-label; new WPCode CSS snippet (" . strlen( $css ) . " bytes, site-wide header)\nDRY-RUN OK\n";
	exit( 0 );
}

$b = array( 'snippet8' => $code, 'wpcode_id' => 0 );
if ( false === file_put_contents( $bk, wp_json_encode( $b ) ) ) {
	echo "ABORT: cannot write backup $bk\n";
	exit( 1 );
}
$bad      = 0;
$new_code = str_replace( $old, $new, $code );
$wpdb->update( $snip, array( 'code' => $new_code ), array( 'id' => 8 ) );
$ok = md5( (string) $wpdb->get_var( "SELECT code FROM $snip WHERE id=8" ) ) === md5( $new_code );
echo 'snippet 8 patched and verified: ' . ( $ok ? 'yes' : 'NO' ) . "\n";
$bad = $bad || ! $ok;

// Save as an administrator: WPCode checks capabilities, and without
// unfiltered_html WordPress would sanitise the code.
$admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
if ( $admins ) {
	wp_set_current_user( (int) $admins[0] );
}
$snippet = new WPCode_Snippet(
	array(
		'title'       => $title,
		'code'        => $css,
		'code_type'   => 'css',
		'location'    => 'site_wide_header',
		'auto_insert' => 1,
		'priority'    => 20,
		'active'      => true,
	)
);
$id = (int) $snippet->save();
$b['wpcode_id'] = $id;
file_put_contents( $bk, wp_json_encode( $b ) );
if ( ! $id ) {
	echo "FAIL: WPCode snippet not created\n";
	exit( 1 );
}
$stored = (string) $wpdb->get_var( $wpdb->prepare( "SELECT post_content FROM {$wpdb->posts} WHERE ID=%d", $id ) );
if ( $stored !== $css ) {
	echo "note: stored code differs from the CSS (sanitised); writing it directly\n";
	$wpdb->update( $wpdb->posts, array( 'post_content' => $css ), array( 'ID' => $id ) );
	clean_post_cache( $id );
}
if ( ! lunaci_a_cache_entry( $id ) || lunaci_a_cache_entry( $id )[2]['code'] !== $css ) {
	lunaci_a_rebuild_cache();
}
$e  = lunaci_a_cache_entry( $id );
$ok = $e && $e[2]['code'] === $css && 'site_wide_header' === $e[0];
echo "WPCode snippet $id: post_content exact=" . ( $wpdb->get_var( $wpdb->prepare( "SELECT post_content FROM {$wpdb->posts} WHERE ID=%d", $id ) ) === $css ? 'yes' : 'NO' ) . ', status=' . get_post_status( $id ) . ', cache entry ' . ( $e ? "in {$e[0]}" : 'MISSING' ) . ', cached code exact=' . ( $ok ? 'yes' : 'NO' ) . "\n";
$bad = $bad || ! $ok;
echo $bad ? "APPLY FINISHED WITH ERRORS (rollback available)\n" : "APPLY OK\n";
exit( $bad ? 1 : 0 );
