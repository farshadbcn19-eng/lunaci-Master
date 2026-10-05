<?php
/**
 * Spanish product category base: /es/categoria-producto/ instead of
 * /es/product-category/.
 *
 * WPML holds the complete Spanish translation "categoria-producto" of the
 * WCML base string, but it is not in WPML's compiled translation file, so
 * WCML keeps the English base. mu-plugins/lunaci-seo.php item 4 applies the
 * Spanish base itself when option lunaci_es_cat_base is "on".
 *
 * LUNACI_MODE=dry-run   report current links and the option
 * LUNACI_MODE=apply     option on, flush rewrite rules, verify
 * LUNACI_MODE=rollback  option off, flush rewrite rules, verify
 */

$mode = getenv( 'LUNACI_MODE' );
if ( ! in_array( $mode, array( 'dry-run', 'apply', 'rollback' ), true ) ) {
	echo "ABORT: set LUNACI_MODE (dry-run|apply|rollback)\n";
	exit( 1 );
}
if ( ! function_exists( 'lunaci_seo_es_cat_base_on' ) ) {
	echo "ABORT: lunaci-seo.php item 4 not loaded (deploy the mu-plugin first)\n";
	exit( 1 );
}

function lunaci_es_cat_links() {
	$out = array();
	do_action( 'wpml_switch_language', 'es' );
	foreach ( array( 'labios', 'rostro', 'ojos', 'unas' ) as $slug ) {
		$t            = get_term_by( 'slug', $slug, 'product_cat' );
		$out[ $slug ] = $t ? get_term_link( $t, 'product_cat' ) : '(term not found)';
	}
	do_action( 'wpml_switch_language', null );
	$en = get_term_link( 'lips', 'product_cat' );
	return array( $out, $en );
}

function lunaci_es_cat_report( $label ) {
	list( $es, $en ) = lunaci_es_cat_links();
	echo "--- $label: option lunaci_es_cat_base=" . get_option( 'lunaci_es_cat_base', '(unset)' ) . " ---\n";
	foreach ( $es as $s => $l ) {
		echo "ES $s: $l\n";
	}
	echo "EN lips: $en\n";
	$rules = (array) get_option( 'rewrite_rules' );
	echo 'categoria-producto rewrite rules: ' . count( array_filter( array_keys( $rules ), function ( $k ) { return 0 === strpos( $k, '^categoria-producto' ) || 0 === strpos( $k, 'categoria-producto' ); } ) ) . "\n";
	return array( $es, $en );
}

lunaci_es_cat_report( 'before' );
if ( 'dry-run' === $mode ) {
	echo "DRY-RUN: apply would set lunaci_es_cat_base=on and flush rewrite rules\n";
	exit( 0 );
}

update_option( 'lunaci_es_cat_base', 'apply' === $mode ? 'on' : 'off', true );
// The rules are registered on init; register them now for this flush too.
if ( 'apply' === $mode ) {
	add_rewrite_rule( '^categoria-producto/(.+?)/page/?([0-9]{1,})/?$', 'index.php?product_cat=$matches[1]&paged=$matches[2]', 'top' );
	add_rewrite_rule( '^categoria-producto/(.+?)/?$', 'index.php?product_cat=$matches[1]', 'top' );
}
flush_rewrite_rules( false );
wp_cache_flush();

list( $es, $en ) = lunaci_es_cat_report( 'after' );
$want = 'apply' === $mode ? '/es/categoria-producto/' : '/es/product-category/';
$bad  = array_filter( $es, function ( $l ) use ( $want ) { return false === strpos( $l, $want ); } );
if ( $bad || false === strpos( $en, '/product-category/lips/' ) || false !== strpos( $en, '/es/' ) ) {
	echo "FAIL: links not as expected\n";
	exit( 1 );
}
echo strtoupper( $mode ) . " DONE\n";
