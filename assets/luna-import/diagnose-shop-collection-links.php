<?php
/**
 * READ-ONLY. The live /products/ page ("Explore Collections — The LUNACI
 * Portfolio" section) has 4 "Discover Collection" links (Face, Eye, Lip,
 * Nail) that all point to the exact same generic https://.../shop/ URL
 * instead of their respective /product-category/{slug}/ archive pages.
 * The footer's "Collections" column (Face, Eye, Lip, Nail) has the same
 * problem. Clicking any of these 8 links just lands the visitor back on
 * the same overview page they're already on - a dead-end that reads as
 * "the shop doesn't work" even though every underlying category page
 * (verified separately) returns 200 and lists the right products.
 *
 * This script only locates the post and HTML widget holding this markup
 * and prints byte offsets / surrounding context, so the guarded fix
 * script can target an exact, unambiguous string replacement.
 */

global $wpdb;

function lunaci_find_widget_with( $needle ) {
	global $wpdb;
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT post_id, meta_id, LENGTH(meta_value) AS len
			 FROM {$wpdb->postmeta}
			 WHERE meta_key = '_elementor_data'
			 AND meta_value LIKE %s",
			'%' . $wpdb->esc_like( $needle ) . '%'
		),
		ARRAY_A
	);
	return $rows;
}

echo "--- url_to_postid('/products/') ---\n";
$post_id = url_to_postid( home_url( '/products/' ) );
echo var_export( $post_id, true ) . "\n";
if ( $post_id ) {
	$post = get_post( $post_id );
	echo "post_title: {$post->post_title}\n";
	echo "post_status: {$post->post_status}\n";
}

echo "\n--- searching _elementor_data for 'Discover Collection' ---\n";
$rows = lunaci_find_widget_with( 'Discover Collection' );
if ( ! $rows ) {
	echo "NONE FOUND in _elementor_data (content may live in an HTML widget's own postmeta, or a WPCode snippet, or a different meta key)\n";
} else {
	foreach ( $rows as $r ) {
		echo "post_id={$r['post_id']} meta_id={$r['meta_id']} elementor_data_len={$r['len']}\n";
	}
}

echo "\n--- searching ALL postmeta (any key) for 'cat-link' class ---\n";
$rows2 = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT post_id, meta_key, meta_id, LENGTH(meta_value) AS len
		 FROM {$wpdb->postmeta}
		 WHERE meta_value LIKE %s",
		'%' . $wpdb->esc_like( 'cat-link' ) . '%'
	),
	ARRAY_A
);
if ( ! $rows2 ) {
	echo "NONE FOUND\n";
} else {
	foreach ( $rows2 as $r ) {
		echo "post_id={$r['post_id']} meta_key={$r['meta_key']} meta_id={$r['meta_id']} len={$r['len']}\n";
	}
}

echo "\n--- how many literal occurrences of the wrong link vs total 'Discover Collection' count, per matching meta row ---\n";
foreach ( array_merge( $rows ?: array(), $rows2 ?: array() ) as $r ) {
	$meta_id = $r['meta_id'];
	$value   = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_id = %d", $meta_id ) );
	if ( null === $value ) {
		continue;
	}
	$discover_count = substr_count( $value, 'Discover Collection' );
	$shop_href_count = substr_count( $value, 'lunacibarcelona.com/shop/' );
	$cat_href_count  = substr_count( $value, 'lunacibarcelona.com/product-category/' );
	echo "meta_id={$meta_id}: 'Discover Collection' x{$discover_count}, literal '/shop/' href x{$shop_href_count}, literal '/product-category/' href x{$cat_href_count}\n";

	// Print a short window around each "Discover Collection" occurrence so the fix
	// script can target an exact, unambiguous substring per category.
	$offset = 0;
	while ( true ) {
		$pos = strpos( $value, 'cat-link', $offset );
		if ( false === $pos ) {
			break;
		}
		$window = substr( $value, max( 0, $pos - 250 ), 350 );
		echo "  ...context around byte {$pos}...\n";
		echo '  ' . str_replace( array( "\n", "\r" ), ' ', $window ) . "\n\n";
		$offset = $pos + 8;
	}
}

echo "OK: read-only diagnostic complete\n";
