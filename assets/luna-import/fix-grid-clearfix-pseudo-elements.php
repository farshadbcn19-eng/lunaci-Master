<?php
/**
 * Guarded fix: confirmed via a live Playwright check that ul.products'
 * ::before/::after both resolve to content:" "; display:table - WooCommerce
 * core's own default clearfix on .products (content/display come from
 * woocommerce.css, not this snippet). Once this snippet switches ul.products
 * to display:grid, those generated-content pseudo-elements become real
 * (invisible) grid items and get auto-placed FIRST, in column 1 of row 1 -
 * pushing every real product over by one slot. That's why the live Eyes
 * category page showed row 1 with only 2 cards (columns 2-3, column 1 empty)
 * while every later row correctly filled all 3 columns.
 *
 * Fix: neutralize the pseudo-elements. CSS Grid containers never need float
 * clearing, so content:none + display:none is a safe no-op for anything grid
 * layout actually needs, and removes the phantom grid items entirely.
 */

global $wpdb;

$table      = $wpdb->prefix . 'snippets';
$snippet_id = 6;
$marker     = 'LUNACI grid clearfix fix';

$new_css = "\n/* {$marker} — WooCommerce's default ul.products:before/:after\n   clearfix (content:\" \"; display:table;) becomes a real (invisible) grid\n   item once ul.products is switched to display:grid above, silently\n   occupying column 1 of row 1 and pushing every real product over by one\n   slot. Neutralize it - CSS Grid containers never need float clearing. */\n.woocommerce ul.products::before,\n.woocommerce ul.products::after,\nul.products::before,\nul.products::after {\n  content: none !important;\n  display: none !important;\n}\n";

echo "--- STEP A: PREPARE ---\n";
$current_code = $wpdb->get_var( $wpdb->prepare( "SELECT code FROM {$table} WHERE id = %d", $snippet_id ) );
if ( null === $current_code ) {
	echo "ABORT: snippet id={$snippet_id} not found in {$table}\n";
	exit( 1 );
}

if ( false !== strpos( $current_code, $marker ) ) {
	echo "ABORT: marker '{$marker}' already present - refusing to proceed (already fixed?)\n";
	exit( 1 );
}

$style_close_count = substr_count( $current_code, '</style>' );
echo "occurrences of '</style>' in code: {$style_close_count}\n";
if ( 1 !== $style_close_count ) {
	echo "ABORT: expected exactly 1 occurrence of '</style>', found {$style_close_count} - refusing to guess insertion point\n";
	exit( 1 );
}
echo "OK: preconditions satisfied\n";

echo "\n--- STEP B: COMMIT ---\n";
$fresh_code = $wpdb->get_var( $wpdb->prepare( "SELECT code FROM {$table} WHERE id = %d", $snippet_id ) );
if ( $fresh_code !== $current_code ) {
	echo "ABORT: snippet code changed since STEP A (concurrent edit) - refusing to write\n";
	exit( 1 );
}

$replace_count = 0;
$new_code      = preg_replace( '/<\/style>/', $new_css . '</style>', $fresh_code, 1, $replace_count );
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
if ( false === strpos( $verify_code, $marker ) ) {
	echo "FAIL: marker not found in code after write\n";
	exit( 1 );
}
echo "verify: marker '{$marker}' present in updated code\n";

$new_style_close_count = substr_count( $verify_code, '</style>' );
echo "verify: '</style>' occurrences after write: {$new_style_close_count} (still 1 = we didn't duplicate the tag)\n";
if ( 1 !== $new_style_close_count ) {
	echo "FAIL: unexpected number of '</style>' occurrences after write\n";
	exit( 1 );
}

echo "\n=====================================================================\n";
echo "FINAL RESULT: SUCCESS\n";
