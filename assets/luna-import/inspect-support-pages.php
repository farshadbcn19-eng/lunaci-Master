<?php
global $wpdb;

$slugs = array( 'shipping', 'returns', 'privacy-policy', 'terms-of-service', 'terms-conditions', 'shipping-returns' );

echo "--- pages matching support-related slugs ---\n";
foreach ( $slugs as $slug ) {
	$post = get_page_by_path( $slug, OBJECT, 'page' );
	if ( $post ) {
		echo "slug: {$slug}\n";
		echo "  ID: {$post->ID}\n";
		echo "  title: {$post->post_title}\n";
		echo "  status: {$post->post_status}\n";
		echo '  content length: ' . strlen( $post->post_content ) . "\n";
		$is_elementor = get_post_meta( $post->ID, '_elementor_edit_mode', true );
		echo '  elementor edit mode: ' . ( $is_elementor ? $is_elementor : '(not elementor)' ) . "\n";
		echo '  permalink: ' . get_permalink( $post->ID ) . "\n";
		echo "  --- content first 500 chars ---\n";
		echo substr( $post->post_content, 0, 500 ) . "\n";
		echo "\n";
	} else {
		echo "slug: {$slug} -> NOT FOUND\n";
	}
}

echo "\n--- all pages (id/title/slug/status) for reference ---\n";
$pages = get_posts( array( 'post_type' => 'page', 'post_status' => 'any', 'numberposts' => -1 ) );
foreach ( $pages as $p ) {
	echo "id={$p->ID} slug={$p->post_name} title=\"{$p->post_title}\" status={$p->post_status}\n";
}

echo "\n--- Support menu items (look for a nav menu containing 'Support') ---\n";
$menus = wp_get_nav_menus();
foreach ( $menus as $menu ) {
	$items = wp_get_nav_menu_items( $menu->term_id );
	$has_support = false;
	if ( $items ) {
		foreach ( $items as $item ) {
			if ( false !== stripos( $item->title, 'support' ) || false !== stripos( $item->title, 'shipping' ) || false !== stripos( $item->title, 'return' ) || false !== stripos( $item->title, 'privacy' ) || false !== stripos( $item->title, 'terms' ) ) {
				$has_support = true;
				break;
			}
		}
	}
	if ( $has_support ) {
		echo "menu: {$menu->name} (id={$menu->term_id})\n";
		foreach ( $items as $item ) {
			echo "  - {$item->title} -> {$item->url} (object_id={$item->object_id}, parent={$item->menu_item_parent})\n";
		}
	}
}
