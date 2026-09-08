<?php
/**
 * READ-ONLY (mostly). Check whether Elementor maintains its own render/CSS
 * cache separate from LiteSpeed's page cache, and if a safe, standard
 * "flush" call resolves the render mismatch (DB confirmed correct via
 * exact readback; LiteSpeed cache confirmed MISS on fresh fetches; still
 * showing old content). If Elementor exposes a safe files-regeneration
 * method, call it - regenerating compiled CSS/cache files is a normal,
 * reversible maintenance action (same as Elementor's own "Regenerate
 * CSS & Data" admin tool), not a content change.
 */

echo "--- Elementor plugin active? ---\n";
echo 'ELEMENTOR_VERSION: ' . ( defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : 'not defined' ) . "\n";

echo "\n--- Elementor-related options that look like caches ---\n";
global $wpdb;
$opts = $wpdb->get_results(
	"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '%elementor%cache%' OR option_name LIKE '%elementor_css%' LIMIT 30",
	ARRAY_A
);
foreach ( $opts as $o ) {
	echo '  ' . $o['option_name'] . "\n";
}
if ( empty( $opts ) ) {
	echo "  (none found)\n";
}

echo "\n--- post 57 meta keys starting with _elementor ---\n";
$meta_keys = $wpdb->get_results( $wpdb->prepare(
	"SELECT meta_key, LENGTH(meta_value) as len FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key LIKE '\\_elementor%'", 57
), ARRAY_A );
foreach ( $meta_keys as $m ) {
	echo "  {$m['meta_key']} (len={$m['len']})\n";
}

echo "\n--- attempting a safe Elementor cache flush, if the class is available ---\n";
if ( class_exists( '\Elementor\Plugin' ) ) {
	try {
		$plugin = \Elementor\Plugin::instance();
		if ( isset( $plugin->files_manager ) && method_exists( $plugin->files_manager, 'clear_cache' ) ) {
			$plugin->files_manager->clear_cache();
			echo "called Plugin::instance()->files_manager->clear_cache()\n";
		} else {
			echo "files_manager->clear_cache() not available on this Elementor version\n";
		}
		// Also delete the per-post CSS file cache, if that API exists.
		if ( class_exists( '\Elementor\Core\Files\CSS\Post' ) ) {
			$css_file = \Elementor\Core\Files\CSS\Post::create( 57 );
			$css_file->delete();
			echo "deleted per-post CSS cache file for post 57\n";
		}
	} catch ( \Throwable $e ) {
		echo 'error during cache flush: ' . $e->getMessage() . "\n";
	}
} else {
	echo "\\Elementor\\Plugin class not found - Elementor may not be loaded in this WP-CLI context\n";
}

echo "\n--- re-fetch after flush attempt ---\n";
$response = wp_remote_get( home_url( '/?diag2=' . time() ), array( 'timeout' => 15, 'sslverify' => false ) );
if ( is_wp_error( $response ) ) {
	echo 'error: ' . $response->get_error_message() . "\n";
} else {
	$body = wp_remote_retrieve_body( $response );
	echo "contains '<h1 class=\\\"ln-hero__wordmark\\\"': " . ( strpos( $body, '<h1 class="ln-hero__wordmark"' ) !== false ? 'YES' : 'no' ) . "\n";
	echo "contains 'href=\\\"https://lunacibarcelona.com/about-us/\\\">About': " . ( strpos( $body, 'href="https://lunacibarcelona.com/about-us/">About' ) !== false ? 'YES' : 'no' ) . "\n";
	echo "contains 'href=\\\"https://lunacibarcelona.com/about\\\">About' (old, broken): " . ( strpos( $body, 'href="https://lunacibarcelona.com/about">About' ) !== false ? 'still present' : 'no' ) . "\n";
}

echo "\nOK: diagnostic complete\n";
