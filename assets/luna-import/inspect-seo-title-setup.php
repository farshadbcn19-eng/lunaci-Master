<?php
global $wpdb;

echo "--- active plugins (SEO-related) ---\n";
$active = get_option( 'active_plugins' );
foreach ( (array) $active as $p ) {
	if ( false !== stripos( $p, 'seo' ) || false !== stripos( $p, 'yoast' ) || false !== stripos( $p, 'rank-math' ) || false !== stripos( $p, 'aioseo' ) ) {
		echo "  {$p}\n";
	}
}
echo "(if nothing printed above, no SEO plugin is active)\n";

echo "\n--- front page setup ---\n";
$show_on_front = get_option( 'show_on_front' );
$page_on_front = (int) get_option( 'page_on_front' );
echo "show_on_front: {$show_on_front}\n";
echo "page_on_front: {$page_on_front}\n";
$front = get_post( $page_on_front );
echo 'front page title (post_title): ' . ( $front ? $front->post_title : '(none)' ) . "\n";

echo "\n--- site title / tagline (general settings) ---\n";
echo 'blogname: ' . get_option( 'blogname' ) . "\n";
echo 'blogdescription: ' . get_option( 'blogdescription' ) . "\n";

echo "\n--- Yoast meta on front page (if plugin present) ---\n";
$yoast_title = get_post_meta( $page_on_front, '_yoast_wpseo_title', true );
$yoast_desc = get_post_meta( $page_on_front, '_yoast_wpseo_metadesc', true );
echo 'yoast title: ' . ( $yoast_title ?: '(not set)' ) . "\n";
echo 'yoast metadesc: ' . ( $yoast_desc ?: '(not set)' ) . "\n";

echo "\n--- RankMath meta on front page (if plugin present) ---\n";
$rm_title = get_post_meta( $page_on_front, 'rank_math_title', true );
$rm_desc = get_post_meta( $page_on_front, 'rank_math_description', true );
echo 'rankmath title: ' . ( $rm_title ?: '(not set)' ) . "\n";
echo 'rankmath metadesc: ' . ( $rm_desc ?: '(not set)' ) . "\n";

echo "\n--- title-related filters currently hooked to 'pre_get_document_title' / 'wp_title' / 'document_title_parts' ---\n";
global $wp_filter;
foreach ( array( 'pre_get_document_title', 'wp_title', 'document_title_parts', 'wpseo_title' ) as $hook ) {
	if ( isset( $wp_filter[ $hook ] ) ) {
		echo "{$hook}:\n";
		foreach ( $wp_filter[ $hook ]->callbacks as $priority => $callbacks ) {
			foreach ( $callbacks as $cb ) {
				$fn = $cb['function'];
				if ( is_array( $fn ) ) {
					$label = ( is_object( $fn[0] ) ? get_class( $fn[0] ) : $fn[0] ) . '::' . $fn[1];
				} elseif ( is_string( $fn ) ) {
					$label = $fn;
				} else {
					$label = '(closure)';
				}
				echo "  priority {$priority}: {$label}\n";
			}
		}
	}
}
