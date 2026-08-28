<?php
/**
 * Read-only diagnostic for two pending tasks on the WooCommerce Shop
 * ("All Products") grid:
 *
 * 1. User reports 14 published products render 3-per-row, so the last row
 *    has only 2 cards - but those 2 "leftover" cards are currently shown
 *    FIRST on the page instead of last. Need to find: (a) each product's
 *    menu_order (the field WooCommerce's default "Default sorting" uses,
 *    combined with title as tiebreaker), and (b) the ACTUAL rendered order
 *    on the live page, to identify which 2 products are currently first
 *    and how to move them to last.
 *
 * 2. Confirm product 327 (EN "LUNACI Lipgloss Velvet") and its ES
 *    counterpart 723 ("Brillo de Labios Velvet LUNACI") exist and dump
 *    their current featured image, to prepare the guarded photo swap.
 */

global $wpdb;

echo "=== EN published products: ID, title, menu_order ===\n";
$en_products = $wpdb->get_results(
	"SELECT ID, post_title, menu_order FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status = 'publish' ORDER BY menu_order ASC, post_title ASC",
	ARRAY_A
);
foreach ( $en_products as $p ) {
	echo "ID={$p['ID']} menu_order={$p['menu_order']} title='{$p['post_title']}'\n";
}
echo "\ntotal EN published products: " . count( $en_products ) . "\n";

echo "\n=== ES published products (ID >= 680): ID, title, menu_order, status ===\n";
$all_products = $wpdb->get_results(
	"SELECT ID, post_title, menu_order, post_status FROM {$wpdb->posts} WHERE post_type = 'product' ORDER BY ID ASC",
	ARRAY_A
);
foreach ( $all_products as $p ) {
	if ( (int) $p['ID'] >= 680 ) {
		echo "ID={$p['ID']} menu_order={$p['menu_order']} title='{$p['post_title']}' status={$p['post_status']}\n";
	}
}

echo "\n=== product 327 (EN Lipgloss Velvet) and 723 (ES Brillo de Labios Velvet) ===\n";
foreach ( array( 327, 723 ) as $pid ) {
	$p = get_post( $pid );
	if ( ! $p ) {
		echo "product {$pid}: NOT FOUND\n";
		continue;
	}
	echo "product {$pid}: title='{$p->post_title}' status={$p->post_status}\n";
	$thumb = get_post_meta( $pid, '_thumbnail_id', true );
	echo "  _thumbnail_id: " . var_export( $thumb, true ) . "\n";
	if ( $thumb ) {
		$url = wp_get_attachment_url( $thumb );
		echo "  current image url: {$url}\n";
	}
}

echo "\nOK: read-only diagnostic complete\n";
