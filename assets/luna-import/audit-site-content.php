<?php
/**
 * Read-only pre-launch content audit: draft/pending/private posts that
 * might be forgotten leftovers, WPML EN<->ES translation pairing
 * completeness for pages and products, placeholder/lorem-ipsum text,
 * and WooCommerce product completeness (price, image, stock, description).
 */

global $wpdb;

echo "--- non-published posts/pages/products (drafts, pending, private) ---\n";
$non_published = $wpdb->get_results(
	"SELECT ID, post_type, post_status, post_title, post_date
	 FROM {$wpdb->posts}
	 WHERE post_type IN ('post', 'page', 'product')
	   AND post_status IN ('draft', 'pending', 'private', 'auto-draft', 'future')
	 ORDER BY post_type, post_status",
	ARRAY_A
);
if ( $non_published ) {
	foreach ( $non_published as $p ) {
		echo "{$p['post_type']} | {$p['post_status']} | ID={$p['ID']} | \"{$p['post_title']}\" | {$p['post_date']}\n";
	}
} else {
	echo "(none)\n";
}

echo "\n--- WPML translation pairing (pages) ---\n";
if ( function_exists( 'icl_object_id' ) || $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}icl_translations'" ) ) {
	$icl_table = $wpdb->prefix . 'icl_translations';
	$pages = $wpdb->get_results(
		"SELECT t.trid, t.language_code, t.element_id, p.post_title, p.post_status
		 FROM {$icl_table} t
		 JOIN {$wpdb->posts} p ON p.ID = t.element_id
		 WHERE t.element_type = 'post_page' AND p.post_status = 'publish'
		 ORDER BY t.trid, t.language_code",
		ARRAY_A
	);
	$by_trid = array();
	foreach ( $pages as $row ) {
		$by_trid[ $row['trid'] ][ $row['language_code'] ] = $row;
	}
	$langs_seen = array();
	foreach ( $by_trid as $trid => $langs ) {
		foreach ( $langs as $lc => $row ) {
			$langs_seen[ $lc ] = true;
		}
	}
	echo 'languages found: ' . implode( ', ', array_keys( $langs_seen ) ) . "\n";
	foreach ( $by_trid as $trid => $langs ) {
		$missing = array_diff( array_keys( $langs_seen ), array_keys( $langs ) );
		if ( ! empty( $missing ) ) {
			$any = reset( $langs );
			echo "INCOMPLETE trid={$trid}: has [" . implode( ',', array_keys( $langs ) ) . "], missing [" . implode( ',', $missing ) . "] - example title: \"{$any['post_title']}\"\n";
		}
	}
} else {
	echo "WPML translations table not found - skipping\n";
}

echo "\n--- WPML translation pairing (products) ---\n";
if ( $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}icl_translations'" ) ) {
	$icl_table = $wpdb->prefix . 'icl_translations';
	$products = $wpdb->get_results(
		"SELECT t.trid, t.language_code, t.element_id, p.post_title, p.post_status
		 FROM {$icl_table} t
		 JOIN {$wpdb->posts} p ON p.ID = t.element_id
		 WHERE t.element_type = 'post_product' AND p.post_status = 'publish'
		 ORDER BY t.trid, t.language_code",
		ARRAY_A
	);
	$by_trid = array();
	foreach ( $products as $row ) {
		$by_trid[ $row['trid'] ][ $row['language_code'] ] = $row;
	}
	$langs_seen = array();
	foreach ( $by_trid as $trid => $langs ) {
		foreach ( $langs as $lc => $row ) {
			$langs_seen[ $lc ] = true;
		}
	}
	echo 'languages found: ' . implode( ', ', array_keys( $langs_seen ) ) . "\n";
	echo 'total product translation groups: ' . count( $by_trid ) . "\n";
	foreach ( $by_trid as $trid => $langs ) {
		$missing = array_diff( array_keys( $langs_seen ), array_keys( $langs ) );
		if ( ! empty( $missing ) ) {
			$any = reset( $langs );
			echo "INCOMPLETE trid={$trid}: has [" . implode( ',', array_keys( $langs ) ) . "], missing [" . implode( ',', $missing ) . "] - example title: \"{$any['post_title']}\"\n";
		}
	}
} else {
	echo "WPML translations table not found - skipping\n";
}

echo "\n--- placeholder / lorem-ipsum text scan ---\n";
$needles = array( 'lorem ipsum', 'placeholder text', 'dolor sit amet', 'sample product', 'test product', 'coming soon', 'TODO', 'lorem', 'consectetur' );
foreach ( $needles as $needle ) {
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT ID, post_type, post_title FROM {$wpdb->posts}
			 WHERE post_status = 'publish' AND post_type IN ('post','page','product')
			   AND post_content LIKE %s",
			'%' . $wpdb->esc_like( $needle ) . '%'
		),
		ARRAY_A
	);
	foreach ( $rows as $r ) {
		echo "FOUND \"{$needle}\" in {$r['post_type']} ID={$r['ID']} \"{$r['post_title']}\"\n";
	}
}

echo "\n--- WooCommerce product completeness (published, simple+variable) ---\n";
$products = $wpdb->get_results(
	"SELECT ID, post_title FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status = 'publish' ORDER BY ID",
	ARRAY_A
);
echo 'total published products: ' . count( $products ) . "\n";
foreach ( $products as $p ) {
	$id = (int) $p['ID'];
	$issues = array();

	$thumb = get_post_thumbnail_id( $id );
	if ( ! $thumb ) {
		$issues[] = 'NO FEATURED IMAGE';
	}

	$price = get_post_meta( $id, '_price', true );
	$regular_price = get_post_meta( $id, '_regular_price', true );
	$type_terms = wp_get_post_terms( $id, 'product_type', array( 'fields' => 'slugs' ) );
	$is_variable = in_array( 'variable', $type_terms, true );
	if ( ! $is_variable && '' === (string) $price && '' === (string) $regular_price ) {
		$issues[] = 'NO PRICE SET';
	}

	$stock_status = get_post_meta( $id, '_stock_status', true );
	if ( 'outofstock' === $stock_status ) {
		$issues[] = 'OUT OF STOCK';
	}

	$content = get_post_field( 'post_content', $id );
	$excerpt = get_post_field( 'post_excerpt', $id );
	if ( strlen( trim( wp_strip_all_tags( $content ) ) ) < 10 && strlen( trim( $excerpt ) ) < 10 ) {
		$issues[] = 'NO DESCRIPTION';
	}

	if ( $is_variable ) {
		$variations = get_posts( array( 'post_type' => 'product_variation', 'post_parent' => $id, 'post_status' => 'publish', 'numberposts' => -1 ) );
		if ( empty( $variations ) ) {
			$issues[] = 'VARIABLE PRODUCT WITH NO VARIATIONS';
		}
	}

	if ( ! empty( $issues ) ) {
		echo "product {$id} \"{$p['post_title']}\": " . implode( '; ', $issues ) . "\n";
	}
}

echo "\n--- audit complete ---\n";
