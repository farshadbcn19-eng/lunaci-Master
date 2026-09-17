<?php
/**
 * READ-ONLY. Every prior search (mu-plugins, active+parent theme,
 * regular plugins, wp_posts, wp_postmeta, wp_options, elementor_library
 * templates, and a raw filesystem grep across the whole webroot) found
 * nothing containing "lunaci-filter-btn", even though it is confirmed
 * present in the live, non-cached (x-litespeed-cache: miss) HTML of
 * /product-category/face/. The one place never checked: a WooCommerce
 * product_cat TERM's own "description" field (wp_term_taxonomy /
 * wp_termmeta) - WooCommerce archive pages render that field via
 * woocommerce_archive_description(), with shortcode support, and it
 * lives in a completely different table than posts/postmeta, so none
 * of the earlier searches would have touched it.
 */

global $wpdb;

echo "--- product_cat terms: face, eyes, lips, nails (and any others) ---\n";
$terms = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) );
foreach ( $terms as $term ) {
	echo "term_id={$term->term_id} slug={$term->slug} name={$term->name}\n";
	echo '  description length: ' . strlen( $term->description ) . "\n";
	if ( false !== strpos( $term->description, 'lunaci-filter-btn' ) ) {
		echo "  *** CONTAINS 'lunaci-filter-btn' ***\n";
	}
	if ( strlen( $term->description ) > 0 && strlen( $term->description ) < 4000 ) {
		echo '  description: ' . $term->description . "\n";
	} elseif ( strlen( $term->description ) >= 4000 ) {
		echo '  description (first 1000 chars): ' . substr( $term->description, 0, 1000 ) . "...(truncated, total " . strlen( $term->description ) . " chars)\n";
	}

	$termmeta = get_term_meta( $term->term_id );
	foreach ( $termmeta as $mk => $mv ) {
		$val = is_array( $mv ) ? implode( ' | ', $mv ) : $mv;
		if ( false !== strpos( (string) $val, 'lunaci-filter-btn' ) ) {
			echo "  *** termmeta '{$mk}' CONTAINS 'lunaci-filter-btn' ***\n";
			echo '  ' . substr( (string) $val, 0, 2000 ) . "\n";
		}
	}
	echo "\n";
}

echo "--- broad DB-wide search: any wp_termmeta row anywhere containing 'lunaci-filter-btn' ---\n";
$hits = $wpdb->get_results( "SELECT term_id, meta_key, LENGTH(meta_value) as len FROM {$wpdb->termmeta} WHERE meta_value LIKE '%lunaci-filter-btn%'", ARRAY_A );
foreach ( (array) $hits as $h ) {
	echo "term_id={$h['term_id']} meta_key={$h['meta_key']} len={$h['len']}\n";
}
if ( ! $hits ) {
	echo "(none)\n";
}

echo "\n--- broad DB-wide search: wp_term_taxonomy.description anywhere containing 'lunaci-filter-btn' ---\n";
$hits2 = $wpdb->get_results( "SELECT term_taxonomy_id, taxonomy, LENGTH(description) as len FROM {$wpdb->term_taxonomy} WHERE description LIKE '%lunaci-filter-btn%'", ARRAY_A );
foreach ( (array) $hits2 as $h ) {
	echo "term_taxonomy_id={$h['term_taxonomy_id']} taxonomy={$h['taxonomy']} len={$h['len']}\n";
}
if ( ! $hits2 ) {
	echo "(none)\n";
}

echo "\nOK: read-only diagnostic complete\n";
