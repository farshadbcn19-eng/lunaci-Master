<?php
/**
 * Read-only. For every URL in seo/phase-c-meta.json: the post/term it
 * resolves to, its wp_aioseo_posts row (title, description) and what the
 * page renders now. Also AIOSEO title/description templates for products,
 * pages and product_cat, and whether wp_aioseo_terms exists.
 */
global $wpdb;
$json = getenv( 'LUNACI_META_JSON' );
$rows = json_decode( (string) file_get_contents( $json ), true );
if ( ! is_array( $rows ) ) {
	echo "ABORT: cannot read $json\n";
	exit( 1 );
}
echo 'AIOSEO version: ' . ( defined( 'AIOSEO_VERSION' ) ? AIOSEO_VERSION : '?' ) . "\n";
$opt = json_decode( (string) get_option( 'aioseo_options_dynamic' ), true );
foreach ( array( 'postTypes' => array( 'product', 'page' ), 'taxonomies' => array( 'product_cat' ) ) as $group => $keys ) {
	foreach ( $keys as $k ) {
		$o = $opt['searchAppearance'][ $group ][ $k ] ?? null;
		echo "template $group/$k: title=" . wp_json_encode( $o['title'] ?? null ) . ' desc=' . wp_json_encode( $o['metaDescription'] ?? null ) . "\n";
	}
}
$opt2 = json_decode( (string) get_option( 'aioseo_options' ), true );
echo 'separator: ' . wp_json_encode( $opt2['searchAppearance']['global']['separator'] ?? null ) . "\n";
$terms_table = $wpdb->prefix . 'aioseo_terms';
echo 'aioseo_terms table: ' . ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $terms_table ) ) ? 'yes' : 'no' ) . "\n";
$posts_table = $wpdb->prefix . 'aioseo_posts';
echo 'aioseo_posts columns: ' . implode( ',', $wpdb->get_col( "SHOW COLUMNS FROM $posts_table" ) ) . "\n";

foreach ( $rows as $r ) {
	$url  = $r['url'];
	$lang = 0 === strpos( $url, '/es/' ) ? 'es' : 'en';
	do_action( 'wpml_switch_language', $lang );
	if ( preg_match( '#/(product-category|categoria-producto)/([^/]+)/$#', $url, $m ) ) {
		$t = get_term_by( 'slug', $m[2], 'product_cat' );
		echo "TERM $url -> " . ( $t ? "term_id={$t->term_id} desc_len=" . strlen( $t->description ) : 'NOT FOUND' ) . "\n";
		continue;
	}
	$id = url_to_postid( home_url( $url ) );
	if ( ! $id ) {
		$slug = basename( untrailingslashit( $url ) );
		$p    = get_posts( array( 'name' => $slug, 'post_type' => array( 'product', 'page' ), 'post_status' => 'publish', 'numberposts' => 1, 'suppress_filters' => false ) );
		$id   = $p ? $p[0]->ID : 0;
	}
	if ( ! $id ) {
		echo "POST $url -> NOT FOUND\n";
		continue;
	}
	$plang = apply_filters( 'wpml_post_language_details', null, $id );
	$a     = $wpdb->get_row( $wpdb->prepare( "SELECT id,title,description FROM $posts_table WHERE post_id=%d", $id ) );
	echo "POST $url -> id=$id type=" . get_post_type( $id ) . ' lang=' . ( $plang['language_code'] ?? '?' ) . ' link=' . get_permalink( $id ) . "\n";
	echo '   aioseo row: ' . ( $a ? "id={$a->id} title=" . wp_json_encode( $a->title ) . ' desc=' . wp_json_encode( $a->description ) : 'NONE' ) . "\n";
}
do_action( 'wpml_switch_language', null );
