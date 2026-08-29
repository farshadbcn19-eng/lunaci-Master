<?php
global $wpdb;

echo "--- page 768 (Terminos de Servicio) TODO context ---\n";
$content = get_post_field( 'post_content', 768 );
$pos = stripos( $content, 'TODO' );
if ( false !== $pos ) {
	echo substr( $content, max( 0, $pos - 200 ), 400 ) . "\n";
} else {
	echo "TODO not found (may have changed)\n";
}

echo "\n--- product 685 (Pintalabios LUNACI) TODO context ---\n";
$p = get_post( 685 );
echo "title: {$p->post_title}, status: {$p->post_status}\n";
$content = $p->post_content;
$excerpt = $p->post_excerpt;
$pos = stripos( $content, 'TODO' );
if ( false !== $pos ) {
	echo "in post_content:\n" . substr( $content, max( 0, $pos - 200 ), 400 ) . "\n";
}
$pos2 = stripos( $excerpt, 'TODO' );
if ( false !== $pos2 ) {
	echo "in post_excerpt:\n" . substr( $excerpt, max( 0, $pos2 - 200 ), 400 ) . "\n";
}

echo "\n--- product 637 (Concealer) draft details ---\n";
$c = get_post( 637 );
echo "title: {$c->post_title}, status: {$c->post_status}, date: {$c->post_date}\n";
echo 'content length: ' . strlen( $c->post_content ) . "\n";
echo 'excerpt: ' . substr( $c->post_excerpt, 0, 200 ) . "\n";
$thumb = get_post_thumbnail_id( 637 );
echo 'featured image: ' . ( $thumb ?: '(none)' ) . "\n";
$price = get_post_meta( 637, '_price', true );
$regular = get_post_meta( 637, '_regular_price', true );
echo "price: {$price}, regular_price: {$regular}\n";
// is there an EN Concealer that IS published? check WPML siblings
if ( function_exists( 'apply_filters' ) ) {
	global $sitepress;
}
$icl_table = $wpdb->prefix . 'icl_translations';
$trid = $wpdb->get_var( $wpdb->prepare( "SELECT trid FROM {$icl_table} WHERE element_id = %d AND element_type = 'post_product'", 637 ) );
echo "trid: {$trid}\n";
if ( $trid ) {
	$siblings = $wpdb->get_results( $wpdb->prepare( "SELECT t.language_code, t.element_id, p.post_title, p.post_status FROM {$icl_table} t JOIN {$wpdb->posts} p ON p.ID = t.element_id WHERE t.trid = %d", $trid ), ARRAY_A );
	foreach ( $siblings as $s ) {
		echo "  sibling: lang={$s['language_code']} ID={$s['element_id']} title=\"{$s['post_title']}\" status={$s['post_status']}\n";
	}
}

echo "\n--- Shadow (trid=515) - check ES sibling status precisely ---\n";
$shadow_siblings = $wpdb->get_results( $wpdb->prepare( "SELECT t.language_code, t.element_id, p.post_title, p.post_status FROM {$icl_table} t JOIN {$wpdb->posts} p ON p.ID = t.element_id WHERE t.trid = %d", 515 ), ARRAY_A );
foreach ( $shadow_siblings as $s ) {
	echo "  sibling: lang={$s['language_code']} ID={$s['element_id']} title=\"{$s['post_title']}\" status={$s['post_status']}\n";
}

echo "\n--- page 11 (Refund and Returns Policy, draft) - is it linked anywhere? ---\n";
$page11_content_len = strlen( get_post_field( 'post_content', 11 ) );
echo "content length: {$page11_content_len}\n";
$count_refs = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_content LIKE '%refund_returns%' OR post_content LIKE '%/?page_id=11%'" );
echo "referenced elsewhere: {$count_refs}\n";
