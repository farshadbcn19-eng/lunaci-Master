<?php
/**
 * Read-only diagnostic, two unrelated questions surfaced by live verification
 * of the About Us hero fix:
 *
 * 1. A WPCode CSS snippet applies `.lna-hero__content{padding:0 5% 8vh
 *    !important;max-width:500px !important;}` unconditionally (no media
 *    query), which beats the new Elementor rule's `12vh` (no !important) in
 *    the cascade. Find which WPCode snippet this is (post + the
 *    wpcode_snippets option entry) so it can be patched.
 *
 * 2. The ES About Us page (post 680) 404s at /es/sobre-nosotros/ and
 *    /es/about-us/ doesn't serve the Spanish translation either. Find its
 *    real permalink.
 */

global $wpdb;

echo "=== 1. WPCode snippets containing 'lna-hero__content' ===\n";
$snippet_posts = $wpdb->get_results(
	"SELECT ID, post_title, post_status FROM {$wpdb->posts} WHERE post_type = 'wpcode' AND post_content LIKE '%lna-hero__content%'",
	ARRAY_A
);
echo "matching wpcode posts: " . count( $snippet_posts ) . "\n";
foreach ( $snippet_posts as $row ) {
	echo "  ID={$row['ID']} title={$row['post_title']} status={$row['post_status']}\n";
}

echo "\n=== 1b. wpcode_snippets option entries containing 'lna-hero__content' ===\n";
$option_raw = get_option( 'wpcode_snippets' );
if ( is_string( $option_raw ) ) {
	$snippets = maybe_unserialize( $option_raw );
} else {
	$snippets = $option_raw;
}
if ( is_array( $snippets ) ) {
	foreach ( $snippets as $key => $snip ) {
		$code = is_array( $snip ) && isset( $snip['code'] ) ? $snip['code'] : '';
		if ( is_string( $code ) && false !== strpos( $code, 'lna-hero__content' ) ) {
			$id    = is_array( $snip ) && isset( $snip['id'] ) ? $snip['id'] : 'N/A';
			$title = is_array( $snip ) && isset( $snip['title'] ) ? $snip['title'] : 'N/A';
			echo "  key={$key} id={$id} title={$title} code_length=" . strlen( $code ) . "\n";
			$pos = strpos( $code, 'lna-hero__content' );
			echo "  context: ..." . substr( $code, max( 0, $pos - 60 ), 200 ) . "...\n";
		}
	}
} else {
	echo "  wpcode_snippets option is not an array (type: " . gettype( $snippets ) . ")\n";
}

echo "\n=== 2. Post 680 permalink info ===\n";
$post = get_post( 680 );
if ( $post ) {
	echo "post_title={$post->post_title} post_status={$post->post_status} post_type={$post->post_type} post_name={$post->post_name}\n";
	echo "permalink: " . get_permalink( 680 ) . "\n";
} else {
	echo "post 680 not found\n";
}

echo "\n=== 2b. All pages with 'sobre' or 'about' in slug ===\n";
$about_pages = $wpdb->get_results(
	"SELECT ID, post_title, post_name, post_status, post_type FROM {$wpdb->posts} WHERE post_type = 'page' AND (post_name LIKE '%sobre%' OR post_name LIKE '%about%') AND post_status = 'publish'",
	ARRAY_A
);
foreach ( $about_pages as $row ) {
	echo "  ID={$row['ID']} title={$row['post_title']} slug={$row['post_name']} status={$row['post_status']} permalink=" . get_permalink( $row['ID'] ) . "\n";
}

echo "\n=== 2c. WPML translation mapping for post 59 (if WPML active) ===\n";
if ( function_exists( 'wpml_object_id_filter' ) || has_filter( 'wpml_object_id' ) ) {
	$es_id = apply_filters( 'wpml_object_id', 59, 'page', false, 'es' );
	echo "wpml_object_id(59, page, es) = " . var_export( $es_id, true ) . "\n";
	if ( $es_id ) {
		echo "permalink: " . get_permalink( $es_id ) . "\n";
	}
} else {
	echo "WPML filter not available in this context\n";
}

echo "\nOK: read-only diagnostic complete, no writes performed\n";
