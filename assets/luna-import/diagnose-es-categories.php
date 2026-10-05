<?php
/**
 * READ-ONLY. State needed before translating the WooCommerce product
 * categories to Spanish: WPML/WCML settings for product_cat, existing
 * terms and translations, ES product category assignments, and the Code
 * Snippets that render the category banner and the filter bar.
 */

global $wpdb, $sitepress;

echo "--- WPML: product_cat translation mode ---\n";
$tax_sync = apply_filters( 'wpml_setting', array(), 'taxonomies_sync_option' );
echo 'taxonomies_sync_option[product_cat] = ' . var_export( $tax_sync['product_cat'] ?? null, true ) . " (1 = translatable, 0 = not, 2 = translatable with fallback)\n";
echo 'active languages: ' . implode( ',', array_keys( (array) apply_filters( 'wpml_active_languages', array(), array( 'skip_missing' => 0 ) ) ) ) . "\n";
echo 'default language: ' . apply_filters( 'wpml_default_language', null ) . "\n";

echo "\n--- WCML / WPML slug translation for product_cat base ---\n";
$wcml = get_option( '_wcml_settings', array() );
echo 'wcml url_translation / slugs: ' . wp_json_encode( array_intersect_key( (array) $wcml, array_flip( array( 'url_translation', 'attributes_settings', 'products_sync_date', 'trnsl_interface' ) ) ) ) . "\n";
$permalinks = get_option( 'woocommerce_permalinks' );
echo 'woocommerce_permalinks: ' . wp_json_encode( $permalinks ) . "\n";
$st = $wpdb->prefix . 'icl_strings';
if ( $wpdb->get_var( "SHOW TABLES LIKE '$st'" ) === $st ) {
	$rows = $wpdb->get_results( "SELECT s.id, s.name, s.value, s.context, t.language, t.value AS tr FROM $st s LEFT JOIN {$wpdb->prefix}icl_string_translations t ON t.string_id = s.id WHERE s.context LIKE '%slug%' OR s.name LIKE '%product_cat%' OR s.value IN ('product-category','product') LIMIT 30", ARRAY_A );
	foreach ( $rows as $r ) {
		echo "string {$r['id']} [{$r['context']}] {$r['name']} = {$r['value']} -> {$r['language']}: {$r['tr']}\n";
	}
}

echo "\n--- product_cat terms with WPML language and translations ---\n";
$terms = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false, 'suppress_filters' => true ) );
foreach ( $terms as $t ) {
	$det = apply_filters( 'wpml_element_language_details', null, array( 'element_id' => $t->term_taxonomy_id, 'element_type' => 'tax_product_cat' ) );
	$tr  = array();
	foreach ( array( 'en', 'es' ) as $l ) {
		$tr[] = $l . '=' . var_export( apply_filters( 'wpml_object_id', $t->term_id, 'product_cat', false, $l ), true );
	}
	echo "term {$t->term_id} tt={$t->term_taxonomy_id} slug={$t->slug} name={$t->name} count={$t->count} lang=" . ( $det->language_code ?? '?' ) . ' trid=' . ( $det->trid ?? '?' ) . ' ' . implode( ' ', $tr ) . ' desc_len=' . strlen( $t->description ) . ' thumb=' . (int) get_term_meta( $t->term_id, 'thumbnail_id', true ) . "\n";
}

echo "\n--- ES products and their product_cat terms ---\n";
$es_products = $wpdb->get_col( "SELECT element_id FROM {$wpdb->prefix}icl_translations WHERE element_type = 'post_product' AND language_code = 'es'" );
foreach ( $es_products as $pid ) {
	$p = get_post( $pid );
	if ( ! $p || 'publish' !== $p->post_status ) {
		continue;
	}
	$cats = wp_get_object_terms( $pid, 'product_cat', array( 'fields' => 'all' ) );
	$en   = apply_filters( 'wpml_object_id', $pid, 'product', false, 'en' );
	echo "ES {$pid} \"{$p->post_title}\" (EN {$en}) cats: " . implode( ', ', array_map( function ( $c ) { return $c->term_id . ':' . $c->slug; }, $cats ) ) . "\n";
}

echo "\n--- Code Snippets mentioning categories / filter bar / banners ---\n";
$table = $wpdb->prefix . 'snippets';
if ( $wpdb->get_var( "SHOW TABLES LIKE '$table'" ) === $table ) {
	$rows = $wpdb->get_results( "SELECT id, name, active, scope, code FROM $table WHERE code LIKE '%product_cat%' OR code LIKE '%lunaci-filter-btn%' OR code LIKE '%product-category%' OR code LIKE '%is_product_category%' OR name LIKE '%Categor%'", ARRAY_A );
	foreach ( $rows as $r ) {
		echo "\n=== snippet {$r['id']} \"{$r['name']}\" active={$r['active']} scope={$r['scope']} len=" . strlen( $r['code'] ) . " ===\n";
		echo $r['code'] . "\n";
	}
}

echo "\n--- live ES URLs for categories (if any) ---\n";
foreach ( $terms as $t ) {
	$es_id = apply_filters( 'wpml_object_id', $t->term_id, 'product_cat', false, 'es' );
	if ( $es_id && (int) $es_id !== (int) $t->term_id ) {
		do_action( 'wpml_switch_language', 'es' );
		echo "{$t->slug} -> ES term {$es_id}: " . get_term_link( (int) $es_id, 'product_cat' ) . "\n";
		do_action( 'wpml_switch_language', null );
	}
}
do_action( 'wpml_switch_language', 'es' );
echo 'ES link for EN lips term (no translation => ?): ' . get_term_link( 'lips', 'product_cat' ) . "\n";
do_action( 'wpml_switch_language', null );
