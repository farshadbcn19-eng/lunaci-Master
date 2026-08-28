<?php
/**
 * Read-only diagnostic: find the "Face" product category page (reached
 * from the Shop page) and its current banner, so a guarded fix can
 * target the right location. Checks (a) WooCommerce product_cat taxonomy
 * term "face" and its category thumbnail/description, (b) any Elementor
 * page specifically built for a Face category landing page, and (c) the
 * raw category archive template in case the banner is set via a
 * WooCommerce category image rather than a custom page.
 */

global $wpdb;

echo "=== WooCommerce product_cat terms (all) ===\n";
$terms = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) );
if ( is_wp_error( $terms ) ) {
	echo "ERROR: " . $terms->get_error_message() . "\n";
} else {
	foreach ( $terms as $term ) {
		echo "term_id={$term->term_id} slug={$term->slug} name='{$term->name}' count={$term->count}\n";
	}
}

echo "\n=== Face category detail ===\n";
$face_term = get_term_by( 'slug', 'face', 'product_cat' );
if ( ! $face_term ) {
	// try 'cara' (Spanish) or partial name match as fallback
	$face_term = get_term_by( 'name', 'Face', 'product_cat' );
}
if ( ! $face_term ) {
	echo "No product_cat term found with slug/name 'face'.\n";
} else {
	echo "term_id={$face_term->term_id} slug={$face_term->slug} name='{$face_term->name}'\n";
	$thumb_id = get_term_meta( $face_term->term_id, 'thumbnail_id', true );
	echo "category thumbnail_id: " . var_export( $thumb_id, true ) . "\n";
	if ( $thumb_id ) {
		echo "category thumbnail url: " . wp_get_attachment_url( $thumb_id ) . "\n";
	}
	echo "category archive URL: " . get_term_link( $face_term ) . "\n";
	echo "category description (first 500 chars): " . substr( $face_term->description, 0, 500 ) . "\n";
}

echo "\n=== searching pages/posts with 'face' in title or slug (any post_type) ===\n";
$candidates = $wpdb->get_results(
	"SELECT ID, post_title, post_type, post_status, post_name FROM {$wpdb->posts}
	 WHERE (post_title LIKE '%Face%' OR post_name LIKE '%face%')
	 AND post_status IN ('publish','draft')
	 ORDER BY ID",
	ARRAY_A
);
foreach ( $candidates as $c ) {
	echo "ID={$c['ID']} type={$c['post_type']} status={$c['post_status']} title='{$c['post_title']}' slug={$c['post_name']}\n";
}

echo "\nOK: read-only diagnostic complete\n";
