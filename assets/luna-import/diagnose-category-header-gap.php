<?php
/**
 * Read-only diagnostic: on mobile, there's a large empty gap between the
 * category banner image and the "Home / Face" breadcrumb + title below it.
 * The banner is injected via a separate hook (woocommerce_before_main_content,
 * priority 5) ABOVE the theme's native ".woocommerce-products-header"
 * element, which likely still carries its own padding/margin meant for a
 * layout where IT was the hero - now stacking with the new banner's own
 * margin-bottom to create a large gap. Search WPCode + wp_snippets for any
 * CSS rule touching .woocommerce-products-header, .page-title, or related
 * spacing, so a precise fix can target only the extra spacing.
 */

global $wpdb;

$keywords = array( 'woocommerce-products-header', 'page-title', 'woocommerce-breadcrumb', 'products-header' );

echo "=== WPCode snippets (post_type=wpcode) matching header/title spacing keywords ===\n";
$wpcode_posts = $wpdb->get_results( "SELECT ID, post_title FROM {$wpdb->posts} WHERE post_type = 'wpcode' AND post_status = 'publish' ORDER BY ID", ARRAY_A );
foreach ( $wpcode_posts as $row ) {
	$content = get_post_field( 'post_content', $row['ID'] );
	$hits = array();
	foreach ( $keywords as $kw ) {
		if ( false !== stripos( $content, $kw ) ) {
			$hits[] = $kw;
		}
	}
	if ( ! empty( $hits ) ) {
		echo "\n----- wpcode ID={$row['ID']} title=\"{$row['post_title']}\" keyword_hits=" . implode( ',', $hits ) . " -----\n";
		echo $content . "\n";
		echo "----- end wpcode ID={$row['ID']} -----\n";
	} else {
		echo "wpcode ID={$row['ID']} title=\"{$row['post_title']}\": no hits\n";
	}
}

echo "\n=== wp_snippets table (Code Snippets plugin) matching header/title spacing keywords ===\n";
$table = $wpdb->prefix . 'snippets';
$snippet_rows = $wpdb->get_results( "SELECT id, name, active FROM {$table} ORDER BY id", ARRAY_A );
foreach ( $snippet_rows as $row ) {
	$code = $wpdb->get_var( $wpdb->prepare( "SELECT code FROM {$table} WHERE id = %d", $row['id'] ) );
	$hits = array();
	foreach ( $keywords as $kw ) {
		if ( false !== stripos( $code, $kw ) ) {
			$hits[] = $kw;
		}
	}
	if ( ! empty( $hits ) ) {
		echo "\n----- snippet id={$row['id']} name=\"{$row['name']}\" active={$row['active']} keyword_hits=" . implode( ',', $hits ) . " -----\n";
		echo $code . "\n";
		echo "----- end snippet id={$row['id']} -----\n";
	} else {
		echo "snippet id={$row['id']} name=\"{$row['name']}\": no hits\n";
	}
}

echo "\nOK: read-only header/title spacing scan complete\n";
