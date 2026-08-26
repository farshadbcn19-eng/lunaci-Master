<?php
/**
 * Read-only diagnostic: our fix to post 59's _elementor_data is 100%
 * confirmed correct in the database (both raw SQL and get_post_meta()
 * agree), yet the live https://lunacibarcelona.com/about-us/ URL keeps
 * returning the OLD CSS text even on fresh (non-cached, x-litespeed-cache:
 * miss) responses, repeatedly, minutes apart. This checks whether
 * /about-us/ actually resolves to post 59 at all, in case WPML or some
 * other routing serves a different post/duplicate at that URL.
 */

$url = 'https://lunacibarcelona.com/about-us/';
$resolved_id = url_to_postid( $url );
echo "url_to_postid('{$url}'): {$resolved_id}\n";

if ( $resolved_id ) {
	$p = get_post( $resolved_id );
	echo "resolved post: ID={$p->ID} title='{$p->post_title}' type={$p->post_type} status={$p->post_status}\n";
}

// Also check what page is actually assigned by querying the rewrite/query directly
global $wp_rewrite;
echo "\n--- pages matching pagename 'about-us' ---\n";
global $wpdb;
$rows = $wpdb->get_results(
	"SELECT ID, post_title, post_name, post_status, post_type FROM {$wpdb->posts} WHERE post_name = 'about-us' AND post_type IN ('page','post')",
	ARRAY_A
);
foreach ( $rows as $r ) {
	echo "  ID={$r['ID']} title='{$r['post_title']}' name={$r['post_name']} status={$r['post_status']} type={$r['post_type']}\n";
}

// Check WPML mapping if present
if ( function_exists( 'wpml_object_id_filter' ) || defined( 'ICL_LANGUAGE_CODE' ) ) {
	echo "\n--- WPML is active ---\n";
	global $sitepress;
	if ( $sitepress ) {
		$lang = apply_filters( 'wpml_current_language', null );
		echo "current language: {$lang}\n";
	}
}

// Check icl_translations table for post 59's trid group
$trans = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT t.trid, t.element_id, t.language_code, t.source_language_code
		 FROM {$wpdb->prefix}icl_translations t
		 WHERE t.trid = (SELECT trid FROM {$wpdb->prefix}icl_translations WHERE element_id = %d AND element_type = 'post_page')",
		59
	),
	ARRAY_A
);
echo "\n--- icl_translations rows sharing post 59's trid ---\n";
foreach ( $trans as $t ) {
	$post = get_post( $t['element_id'] );
	echo "  trid={$t['trid']} element_id={$t['element_id']} lang={$t['language_code']} title=" . ( $post ? $post->post_title : 'N/A' ) . " status=" . ( $post ? $post->post_status : 'N/A' ) . "\n";
}

echo "\nOK: read-only diagnostic complete, no writes performed\n";
