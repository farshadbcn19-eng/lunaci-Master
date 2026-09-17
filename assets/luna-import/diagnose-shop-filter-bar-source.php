<?php
/**
 * READ-ONLY follow-up to diagnose-shop-all-filter-and-redirect.php.
 * That script found nothing in mu-plugins, the active theme, wp_posts,
 * or wp_postmeta containing "lunaci-filter-btn" - yet it is definitely
 * present in the server-rendered HTML of both /product-category/face/
 * and (per this script) whatever page 56 ("Shop", slug "shop",
 * publish, empty post_content but 1132 bytes of _elementor_data)
 * renders. This widens the net: full dump of post 56's own
 * _elementor_data (it's tiny), a regular (non-mu) plugins directory
 * scan, an AIOSEO redirects table check, and a wp_options scan.
 */

global $wpdb;

echo "--- post 56 ('Shop') full _elementor_data ---\n";
$data56 = get_post_meta( 56, '_elementor_data', true );
echo var_export( $data56, true ) . "\n";

echo "\n--- post 56 other relevant postmeta keys ---\n";
$meta56 = get_post_meta( 56 );
foreach ( $meta56 as $k => $v ) {
	if ( '_elementor_data' === $k ) {
		continue;
	}
	$val = is_array( $v ) ? implode( ' | ', $v ) : $v;
	echo "{$k}: " . ( strlen( (string) $val ) > 300 ? substr( (string) $val, 0, 300 ) . '...(truncated)' : $val ) . "\n";
}

echo "\n--- elementor_library templates (Theme Builder: header/footer/archive) ---\n";
$templates = $wpdb->get_results( "SELECT ID, post_title, post_status FROM {$wpdb->posts} WHERE post_type = 'elementor_library'", ARRAY_A );
foreach ( (array) $templates as $t ) {
	$type = get_post_meta( $t['ID'], '_elementor_template_type', true );
	$conditions = get_post_meta( $t['ID'], '_elementor_conditions', true );
	echo "ID={$t['ID']} title={$t['post_title']} status={$t['post_status']} template_type=" . var_export( $type, true ) . " conditions=" . var_export( $conditions, true ) . "\n";
	$edata = get_post_meta( $t['ID'], '_elementor_data', true );
	if ( $edata && false !== strpos( $edata, 'lunaci-filter-btn' ) ) {
		echo "  *** CONTAINS 'lunaci-filter-btn' ***\n";
	}
	if ( $edata && false !== strpos( $edata, 'Discover Collection' ) ) {
		echo "  *** CONTAINS 'Discover Collection' ***\n";
	}
}

echo "\n--- regular (non-mu) plugins directory scan for 'lunaci-filter-btn' ---\n";
$plugins_dir = WP_PLUGIN_DIR;
if ( is_dir( $plugins_dir ) ) {
	$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $plugins_dir, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $iterator as $fileinfo ) {
		if ( 'php' === $fileinfo->getExtension() ) {
			$contents = @file_get_contents( $fileinfo->getPathname() );
			if ( $contents && false !== strpos( $contents, 'lunaci-filter-btn' ) ) {
				echo "FOUND in plugin file: {$fileinfo->getPathname()}\n";
			}
		}
	}
}

echo "\n--- parent theme directory scan (get_template_directory) ---\n";
$parent_theme_dir = get_template_directory();
if ( $parent_theme_dir && is_dir( $parent_theme_dir ) && $parent_theme_dir !== get_stylesheet_directory() ) {
	$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $parent_theme_dir, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $iterator as $fileinfo ) {
		if ( 'php' === $fileinfo->getExtension() ) {
			$contents = @file_get_contents( $fileinfo->getPathname() );
			if ( $contents && false !== strpos( $contents, 'lunaci-filter-btn' ) ) {
				echo "FOUND in parent theme file: {$fileinfo->getPathname()}\n";
			}
		}
	}
} else {
	echo "(parent theme == active theme, already scanned)\n";
}

echo "\n--- wp_options scan for 'lunaci-filter-btn' (custom HTML widgets, customizer settings) ---\n";
$opt_hits = $wpdb->get_results( "SELECT option_id, option_name, LENGTH(option_value) as len FROM {$wpdb->options} WHERE option_value LIKE '%lunaci-filter-btn%'", ARRAY_A );
foreach ( (array) $opt_hits as $o ) {
	echo "FOUND in wp_options: option_id={$o['option_id']} option_name={$o['option_name']} len={$o['len']}\n";
}
if ( ! $opt_hits ) {
	echo "(none)\n";
}

echo "\n--- AIOSEO redirects (table + option) ---\n";
$aioseo_redirects_table = $wpdb->prefix . 'aioseo_redirects';
$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $aioseo_redirects_table ) );
if ( $exists ) {
	$redirects = $wpdb->get_results( "SELECT * FROM {$aioseo_redirects_table} WHERE source_url LIKE '%shop%' OR target_url LIKE '%shop%' OR source_url LIKE '%products%' OR target_url LIKE '%products%'", ARRAY_A );
	echo 'matching redirect rows: ' . count( (array) $redirects ) . "\n";
	foreach ( (array) $redirects as $r ) {
		echo json_encode( $r ) . "\n";
	}
} else {
	echo "no {$aioseo_redirects_table} table\n";
}

echo "\n--- generic wp_options scan for any option mentioning both 'shop' redirect target 'products' (narrow, case-insensitive) ---\n";
$opt_hits2 = $wpdb->get_results( "SELECT option_id, option_name, LENGTH(option_value) as len FROM {$wpdb->options} WHERE option_name LIKE '%redirect%'", ARRAY_A );
foreach ( (array) $opt_hits2 as $o ) {
	echo "option_id={$o['option_id']} option_name={$o['option_name']} len={$o['len']}\n";
}

echo "\nOK: read-only diagnostic complete\n";
