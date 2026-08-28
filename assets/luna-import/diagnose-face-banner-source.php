<?php
/**
 * Read-only diagnostic: the Face category (product_cat term_id=30) has no
 * WooCommerce category thumbnail set (confirmed empty in a prior diagnostic),
 * yet the user sees a banner on https://lunacibarcelona.com/product-category/face/.
 * That banner must come from a WPCode snippet (PHP hook or CSS) rather than
 * Elementor page content, since WooCommerce archive templates aren't
 * Elementor-editable without Elementor Pro's Theme Builder. Scan all
 * published WPCode snippets for anything referencing "face", the category
 * archive, or generic archive/category banner hooks, and dump full content
 * of any match so the exact banner source can be located.
 */

global $wpdb;

$keywords = array( 'face', 'product-category', 'product_cat', 'category-banner', 'archive-header', 'woocommerce_before_main_content', 'term-30', 'tax-product_cat' );

$wpcode_posts = $wpdb->get_results( "SELECT ID, post_title FROM {$wpdb->posts} WHERE post_type = 'wpcode' AND post_status = 'publish' ORDER BY ID", ARRAY_A );

echo "=== All published WPCode snippets ===\n";
foreach ( $wpcode_posts as $row ) {
	$content = get_post_field( 'post_content', $row['ID'] );
	$hits = array();
	foreach ( $keywords as $kw ) {
		if ( false !== stripos( $content, $kw ) ) {
			$hits[] = $kw;
		}
	}
	echo "ID={$row['ID']} title=\"{$row['post_title']}\" len=" . strlen( $content ) . " keyword_hits=" . ( empty( $hits ) ? '(none)' : implode( ',', $hits ) ) . "\n";
}

echo "\n=== Full content of snippets with keyword hits ===\n";
foreach ( $wpcode_posts as $row ) {
	$content = get_post_field( 'post_content', $row['ID'] );
	$has_hit = false;
	foreach ( $keywords as $kw ) {
		if ( false !== stripos( $content, $kw ) ) {
			$has_hit = true;
			break;
		}
	}
	if ( $has_hit ) {
		echo "\n----- snippet ID={$row['ID']} title=\"{$row['post_title']}\" -----\n";
		echo $content . "\n";
		echo "----- end snippet ID={$row['ID']} -----\n";
	}
}

echo "\nOK: read-only WPCode scan complete\n";
