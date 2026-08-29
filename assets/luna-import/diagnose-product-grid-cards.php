<?php
/**
 * Read-only diagnostic: user reports that on Face/Eyes/Lips category
 * pages, the FIRST 2 product cards render correctly (image + gradient +
 * title + price + button), but the remaining cards below them show only
 * a raw image with no title/price/button overlay. Since the grid CSS
 * ("LUNACI Shop Design" style) positions the image, gradient, title,
 * price and button all as position:absolute relative to li.product, the
 * li itself must have an explicit height/aspect-ratio for children to be
 * visible - dump the full CSS from the relevant wp_snippets/wpcode
 * entries, focusing on any rule for "li.product" itself (position,
 * height, aspect-ratio, grid-template).
 */

global $wpdb;

$table = $wpdb->prefix . 'snippets';
$rows = $wpdb->get_results( "SELECT id, name FROM {$table} ORDER BY id", ARRAY_A );

foreach ( $rows as $row ) {
	$code = $wpdb->get_var( $wpdb->prepare( "SELECT code FROM {$table} WHERE id = %d", $row['id'] ) );
	if ( false !== stripos( $code, 'li.product' ) || false !== stripos( $code, 'ul.products' ) ) {
		echo "\n===== snippet id={$row['id']} name=\"{$row['name']}\" =====\n";
		// print only lines/rules mentioning li.product, ul.products, aspect-ratio, grid
		if ( preg_match_all( '/[^\{\};]*\b(li\.product|ul\.products)\b[^\{]*\{[^\}]*\}/i', $code, $m ) ) {
			foreach ( $m[0] as $rule ) {
				echo trim( $rule ) . "\n\n";
			}
		} else {
			echo "(no li.product/ul.products rule found via regex - dumping first 3000 chars)\n";
			echo substr( $code, 0, 3000 ) . "\n";
		}
	}
}

echo "\nOK: grid CSS scan complete\n";
