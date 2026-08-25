<?php
/**
 * Read-only, site-wide diagnostic: understand exactly why selecting Spanish
 * on the live site appears to serve an old/stale English version of pages
 * (reported starting with the Home page). No writes are performed.
 *
 * PART 1: WPML setup - active languages, default language, URL format.
 * PART 2: Full page inventory (post_type=page, any status).
 * PART 3: For every WPML translation group (trid) among those pages, list
 *         every language's post id/status/modified date/content length, so
 *         staleness (old post_modified, near-identical content, or missing
 *         translation rows) is visible at a glance.
 * PART 4: Live HTTP fetch (from the server itself) of the Home page in EN
 *         and in ES (via wpml_permalink), to see literally what each URL
 *         currently serves, plus cache-related response headers.
 */

global $wpdb;

echo "=====================================================================\n";
echo "PART 1: WPML setup\n";
echo "=====================================================================\n";

$table = $wpdb->prefix . 'icl_translations';
$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
if ( ! $exists ) {
	echo "ERROR: WPML translations table not found: {$table}\n";
	echo "This means WPML may not be active in the expected way. Stopping here.\n";
	exit( 1 );
}
echo "OK: found {$table}\n";

if ( function_exists( 'icl_get_languages' ) ) {
	$langs = icl_get_languages( 'skip_missing=0' );
	echo "Active languages (icl_get_languages):\n";
	foreach ( (array) $langs as $code => $l ) {
		echo "  code={$code} active=" . ( ! empty( $l['active'] ) ? '1' : '0' ) . " url=" . ( $l['url'] ?? '' ) . "\n";
	}
} else {
	echo "icl_get_languages() not available (WPML core may not be fully loaded)\n";
}

$default_lang = apply_filters( 'wpml_default_language', null );
echo "Default language: " . var_export( $default_lang, true ) . "\n";

$url_format = get_option( 'icl_language_negotiation_type' );
echo "icl_language_negotiation_type (1=dir,2=domain,3=param): " . var_export( $url_format, true ) . "\n";

echo "\n=====================================================================\n";
echo "PART 2: Page inventory (post_type=page)\n";
echo "=====================================================================\n";

$pages = $wpdb->get_results(
	"SELECT ID, post_title, post_status, post_modified_gmt FROM {$wpdb->posts}
	 WHERE post_type = 'page' ORDER BY ID ASC",
	ARRAY_A
);
echo "Found " . count( $pages ) . " page(s)\n";
foreach ( $pages as $p ) {
	echo "  ID={$p['ID']}\tstatus={$p['post_status']}\tmodified_gmt={$p['post_modified_gmt']}\ttitle={$p['post_title']}\n";
}

echo "\nFront page settings:\n";
echo "  show_on_front: " . get_option( 'show_on_front' ) . "\n";
echo "  page_on_front: " . get_option( 'page_on_front' ) . "\n";
echo "  page_for_posts: " . get_option( 'page_for_posts' ) . "\n";

echo "\n=====================================================================\n";
echo "PART 3: Translation groups (trid) for every page above\n";
echo "=====================================================================\n";

$seen_trids = array();
foreach ( $pages as $p ) {
	$pid = (int) $p['ID'];
	$row = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT trid FROM {$table} WHERE element_id = %d AND element_type LIKE 'post_%%'",
			$pid
		)
	);
	if ( ! $row ) {
		echo "\nPAGE ID={$pid} (\"{$p['post_title']}\"): NOT present in {$table} at all (no WPML record)\n";
		continue;
	}
	$trid = (int) $row->trid;
	if ( isset( $seen_trids[ $trid ] ) ) {
		continue;
	}
	$seen_trids[ $trid ] = true;

	$group = $wpdb->get_results(
		$wpdb->prepare( "SELECT translation_id, element_id, language_code, source_language_code FROM {$table} WHERE trid = %d", $trid ),
		ARRAY_A
	);

	echo "\n--- trid={$trid} (seed page ID={$pid}, \"{$p['post_title']}\") ---\n";
	foreach ( $group as $g ) {
		$eid  = (int) $g['element_id'];
		$post = get_post( $eid );
		if ( ! $post ) {
			echo "  lang={$g['language_code']} post_id={$eid} -- POST NOT FOUND (dangling WPML row)\n";
			continue;
		}
		$content_len   = strlen( $post->post_content );
		$elementor     = get_post_meta( $eid, '_elementor_data', true );
		$elementor_len = is_string( $elementor ) ? strlen( $elementor ) : 0;
		echo "  lang={$g['language_code']} (source={$g['source_language_code']}) post_id={$eid} status={$post->post_status} modified_gmt={$post->post_modified_gmt} title=\"{$post->post_title}\" content_len={$content_len} elementor_data_len={$elementor_len}\n";
	}
}

echo "\n=====================================================================\n";
echo "PART 4: Live HTTP fetch - Home page EN vs ES (from the server itself)\n";
echo "=====================================================================\n";

$home_url_en = home_url( '/' );
echo "home_url(EN): {$home_url_en}\n";

$home_url_es = apply_filters( 'wpml_permalink', $home_url_en, 'es' );
echo "wpml_permalink(ES): {$home_url_es}\n";

foreach ( array( 'EN' => $home_url_en, 'ES' => $home_url_es ) as $label => $url ) {
	echo "\n--- Fetching {$label}: {$url} ---\n";
	$response = wp_remote_get(
		$url,
		array(
			'timeout'     => 15,
			'redirection' => 5,
			'headers'     => array( 'Cache-Control' => 'no-cache' ),
		)
	);
	if ( is_wp_error( $response ) ) {
		echo "ERROR: " . $response->get_error_message() . "\n";
		continue;
	}
	$code    = wp_remote_retrieve_response_code( $response );
	$headers = wp_remote_retrieve_headers( $response );
	$body    = wp_remote_retrieve_body( $response );
	echo "HTTP status: {$code}\n";
	foreach ( array( 'cache-control', 'x-litespeed-cache', 'x-cache', 'age', 'content-language', 'location' ) as $h ) {
		if ( isset( $headers[ $h ] ) ) {
			echo "header {$h}: " . ( is_array( $headers[ $h ] ) ? implode( ', ', $headers[ $h ] ) : $headers[ $h ] ) . "\n";
		}
	}
	echo "body length: " . strlen( $body ) . "\n";
	if ( preg_match( '/<html[^>]*lang=["\']?([a-zA-Z-]+)/i', $body, $m ) ) {
		echo "html lang attribute: {$m[1]}\n";
	}
	if ( preg_match( '/<title>(.*?)<\/title>/is', $body, $m ) ) {
		echo "page <title>: " . trim( $m[1] ) . "\n";
	}
	// Look for a handful of known EN vs ES marker phrases to see which language actually rendered.
	$markers = array( 'Every woman is seen', 'Toda mujer es vista', 'Add to cart', 'Añadir al carrito', 'Shop', 'Tienda' );
	foreach ( $markers as $marker ) {
		if ( false !== stripos( $body, $marker ) ) {
			echo "contains marker: \"{$marker}\"\n";
		}
	}
}

echo "\nOK: read-only bilingual audit complete, no writes performed\n";
