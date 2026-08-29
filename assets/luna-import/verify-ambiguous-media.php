<?php
global $wpdb;

echo "--- WooCommerce sample-data posts (12,14,16,18,20,46,48,50,52,54) status ---\n";
$ids = array( 12, 14, 16, 18, 20, 46, 48, 50, 52, 54 );
foreach ( $ids as $id ) {
	$p = get_post( $id );
	if ( $p ) {
		echo "post {$id}: type={$p->post_type} status={$p->post_status} title=\"{$p->post_title}\"\n";
	} else {
		echo "post {$id}: DOES NOT EXIST\n";
	}
}

echo "\n--- woocommerce_placeholder_image option ---\n";
echo 'value: ' . get_option( 'woocommerce_placeholder_image', '(not set)' ) . "\n";

echo "\n--- attachment 534 (lunaci_hero_retouched) vs what homepage actually uses ---\n";
$att = get_post( 534 );
echo $att ? "534 title: {$att->post_title}, url: " . wp_get_attachment_url( 534 ) . "\n" : "534 not found\n";
$home_id = (int) get_option( 'page_on_front' );
echo "front page ID: {$home_id}\n";
if ( $home_id ) {
	$home_content = get_post_field( 'post_content', $home_id );
	echo 'home content length: ' . strlen( $home_content ) . "\n";
	echo 'home content mentions "hero": ' . ( false !== stripos( $home_content, 'hero' ) ? 'yes' : 'no' ) . "\n";
	echo 'home content mentions "lunaci_hero": ' . ( false !== stripos( $home_content, 'lunaci_hero' ) ? 'yes' : 'no' ) . "\n";
	preg_match_all( '/wp-content\/uploads\/[^"\'\s)]+/', $home_content, $m );
	echo "image paths referenced in home content (first 15):\n";
	foreach ( array_slice( array_unique( $m[0] ), 0, 15 ) as $path ) {
		echo "  {$path}\n";
	}
}

echo "\n--- attachment 758 (parent=731) and 762/763 (parents 326/687) context ---\n";
foreach ( array( 758, 762, 763 ) as $aid ) {
	$a = get_post( $aid );
	if ( ! $a ) {
		echo "{$aid}: not found\n";
		continue;
	}
	$parent = get_post( $a->post_parent );
	echo "{$aid}: parent={$a->post_parent} (" . ( $parent ? "{$parent->post_type}/{$parent->post_status}/\"{$parent->post_title}\"" : 'MISSING' ) . ")\n";
	$parent_thumb = $parent ? get_post_thumbnail_id( $parent->ID ) : null;
	echo "  parent's featured image ID: " . ( $parent_thumb ?: '(none)' ) . "\n";
}

echo "\n--- attachment 819/821/823 (real photo) - which products should they belong to? ---\n";
foreach ( array( 819, 821, 823 ) as $aid ) {
	$a = get_post( $aid );
	echo $a ? "{$aid}: title=\"{$a->post_title}\"\n" : "{$aid}: not found\n";
}
// try to find matching products by name fragments
foreach ( array( 'Eyeliner', 'Eye Liner', 'Compact Powder', 'Lip Gloss', 'Lipgloss' ) as $needle ) {
	$found = get_posts( array( 'post_type' => 'product', 'post_status' => 'any', 's' => $needle, 'numberposts' => 5 ) );
	foreach ( $found as $f ) {
		$thumb = get_post_thumbnail_id( $f->ID );
		echo "product match \"{$needle}\" -> ID={$f->ID} title=\"{$f->post_title}\" status={$f->post_status} featured_image={$thumb}\n";
	}
}

echo "\n--- shade-swatch image sets: any variable products or shade-selector content referencing them by name pattern? ---\n";
foreach ( array( 'LGVELVET', 'LUNACI-FOUND', 'LF-0', 'LV-01', 'FOU-0' ) as $needle ) {
	$count_products = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_content LIKE %s", '%' . $wpdb->esc_like( $needle ) . '%' ) );
	echo "pattern \"{$needle}\" found in post_content of {$count_products} posts\n";
}
