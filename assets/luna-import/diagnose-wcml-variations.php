<?php
/**
 * Read-only. Everything needed to give the Spanish products their shade
 * selection through WCML: WCML version/settings, attribute translation
 * settings, every pa_color term with its translations, each EN/ES product
 * pair (type, attributes, variations with price/stock/SKU, existing ES
 * children and their WPML links), order counts, and the WCML sync methods.
 */
global $wpdb, $woocommerce_wpml, $sitepress;
echo 'WCML_VERSION=' . ( defined( 'WCML_VERSION' ) ? WCML_VERSION : '-' ) . ' WC=' . ( defined( 'WC_VERSION' ) ? WC_VERSION : '-' ) . "\n";
$s = get_option( '_wcml_settings' );
if ( is_array( $s ) ) {
	foreach ( $s as $k => $v ) {
		if ( is_scalar( $v ) || ( is_array( $v ) && strlen( wp_json_encode( $v ) ) < 300 ) ) {
			echo "wcml.$k = " . wp_json_encode( $v ) . "\n";
		}
	}
}
$ss = get_option( 'icl_sitepress_settings' );
echo 'wpml taxonomies_sync_option: ' . wp_json_encode( $ss['taxonomies_sync_option'] ?? null ) . "\n";
echo 'wpml custom_fields_translation (product keys): ';
foreach ( (array) ( $ss['translation-management']['custom_fields_translation'] ?? array() ) as $k => $v ) {
	if ( preg_match( '/^_(price|regular_price|sale_price|sku|stock|stock_status|manage_stock|product_attributes|variation|default_attributes)/', $k ) || 0 === strpos( $k, 'attribute_' ) ) {
		echo "$k=$v ";
	}
}
echo "\n=== pa_color terms\n";
foreach ( get_terms( array( 'taxonomy' => 'pa_color', 'hide_empty' => false, 'suppress_filters' => true, 'lang' => '' ) ) as $t ) {
	$l  = apply_filters( 'wpml_element_language_code', null, array( 'element_id' => $t->term_taxonomy_id, 'element_type' => 'tax_pa_color' ) );
	$es = apply_filters( 'wpml_object_id', $t->term_id, 'pa_color', false, 'es' );
	echo "  {$t->term_id} '{$t->name}' slug={$t->slug} count={$t->count} lang=" . var_export( $l, true ) . ' es=' . var_export( $es, true ) . "\n";
}
foreach ( wc_get_attribute_taxonomies() as $a ) {
	echo "attribute taxonomy: pa_{$a->attribute_name} label='{$a->attribute_label}' type={$a->attribute_type}\n";
}
echo "=== product pairs\n";
$en_ids = $wpdb->get_col( "SELECT p.ID FROM {$wpdb->posts} p JOIN {$wpdb->prefix}icl_translations t ON t.element_id = p.ID AND t.element_type = 'post_product' WHERE t.language_code = 'en' AND p.post_status = 'publish' ORDER BY p.ID" );
foreach ( $en_ids as $en ) {
	$es  = (int) apply_filters( 'wpml_object_id', (int) $en, 'product', false, 'es' );
	$pe  = wc_get_product( $en );
	$ps  = $es ? wc_get_product( $es ) : null;
	echo "--- EN $en '" . $pe->get_name() . "' type=" . $pe->get_type() . ' price=' . $pe->get_price() . ' sku=' . $pe->get_sku() . ' | ES ' . ( $ps ? "$es '" . $ps->get_name() . "' type=" . $ps->get_type() . ' price=' . $ps->get_price() . ' status=' . $ps->get_status() . ' stock=' . $ps->get_stock_status() : 'none' ) . "\n";
	echo '    EN _product_attributes: ' . wp_json_encode( get_post_meta( $en, '_product_attributes', true ) ) . "\n";
	if ( $ps ) {
		echo '    ES _product_attributes: ' . wp_json_encode( get_post_meta( $es, '_product_attributes', true ) ) . "\n";
		echo '    ES edit_mode=' . get_post_meta( $es, '_elementor_edit_mode', true ) . ' EN default_attributes=' . wp_json_encode( get_post_meta( $en, '_default_attributes', true ) ) . "\n";
	}
	if ( $pe->is_type( 'variable' ) ) {
		foreach ( $pe->get_children() as $vid ) {
			$v   = wc_get_product( $vid );
			$tv  = apply_filters( 'wpml_object_id', $vid, 'product_variation', false, 'es' );
			echo "    EN var $vid " . wp_json_encode( $v->get_attributes() ) . ' price=' . $v->get_price() . ' reg=' . $v->get_regular_price() . ' sale=' . $v->get_sale_price() . ' stock=' . $v->get_stock_status() . '/' . var_export( $v->get_stock_quantity(), true ) . ' sku=' . $v->get_sku() . ' img=' . $v->get_image_id() . ' status=' . $v->get_status() . ' es_tr=' . var_export( $tv, true ) . "\n";
		}
	}
	if ( $es ) {
		foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_status FROM {$wpdb->posts} WHERE post_parent = %d AND post_type = 'product_variation'", $es ) ) as $c ) {
			$l   = $wpdb->get_row( $wpdb->prepare( "SELECT trid, language_code, source_language_code FROM {$wpdb->prefix}icl_translations WHERE element_id = %d AND element_type = 'post_product_variation'", $c->ID ) );
			$v   = wc_get_product( $c->ID );
			echo "    ES child {$c->ID} {$c->post_status} " . ( $v ? wp_json_encode( $v->get_attributes() ) . ' price=' . $v->get_price() . ' sku=' . $v->get_sku() : '' ) . ' wpml=' . wp_json_encode( $l ) . "\n";
		}
	}
}
echo "=== orders\n";
echo 'orders total: ' . (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'shop_order'" ) . ' / hpos: ' . ( $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}wc_orders'" ) ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}wc_orders" ) : 'n/a' ) . "\n";
echo "=== WCML sync methods\n";
if ( is_object( $woocommerce_wpml ) ) {
	foreach ( array( 'sync_variations_data', 'sync_product_data', 'attributes', 'terms', 'products' ) as $prop ) {
		if ( isset( $woocommerce_wpml->$prop ) && is_object( $woocommerce_wpml->$prop ) ) {
			$o = $woocommerce_wpml->$prop;
			echo "$prop: " . get_class( $o ) . "\n";
			foreach ( get_class_methods( $o ) as $m ) {
				if ( preg_match( '/sync|duplicate|translat|variation|attribute/i', $m ) ) {
					$r = new ReflectionMethod( $o, $m );
					echo "   $m(" . implode( ', ', array_map( function ( $p ) { return '$' . $p->getName(); }, $r->getParameters() ) ) . ') @' . basename( $r->getFileName() ) . ':' . $r->getStartLine() . "\n";
				}
			}
		}
	}
} else {
	echo "woocommerce_wpml global not available\n";
}
