<?php
/**
 * READ-ONLY. Two plugins could plausibly own WebP/AVIF delivery on this
 * site: LiteSpeed Cache's built-in image optimization module
 * (litespeed.conf.img_optm-webp, confirmed to exist by the previous
 * diagnostic) and Hostinger's own "Image Optimization" plugin
 * (image-optimization/image-optimization.php, also active). Need the
 * current state of both, and Hostinger's plugin's own option shape,
 * before deciding which one to enable - turning both on could mean
 * double-processing or conflicting output.
 */

global $wpdb;

echo "--- LiteSpeed img_optm-* current values ---\n";
$keys = array(
	'litespeed.conf.img_optm-auto',
	'litespeed.conf.img_optm-webp',
	'litespeed.conf.img_optm-webp_attr',
	'litespeed.conf.img_optm-webp_replace_srcset',
	'litespeed.conf.img_optm-lossless',
	'litespeed.conf.img_optm-ori',
);
foreach ( $keys as $key ) {
	echo "  {$key} = " . var_export( get_option( $key ), true ) . "\n";
}

echo "\n--- LiteSpeed image optimization summary (cloud-service state, if any) ---\n";
$img_summary = get_option( 'litespeed-img-optm-summary' );
echo '  litespeed-img-optm-summary = ' . var_export( $img_summary, true ) . "\n";

echo "\n--- Hostinger Image Optimization plugin: all its options ---\n";
$hostinger_opts = $wpdb->get_results(
	"SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE '%image_optim%' OR option_name LIKE '%image-optim%' OR option_name LIKE '%img_optim%' OR option_name LIKE 'hostinger%'",
	ARRAY_A
);
foreach ( $hostinger_opts as $row ) {
	$val = $row['option_value'];
	echo '  ' . $row['option_name'] . ' = ' . ( strlen( $val ) > 300 ? substr( $val, 0, 300 ) . '...(truncated)' : $val ) . "\n";
}

echo "\n--- Hostinger Image Optimization plugin file header (version, description) ---\n";
$plugin_file = WP_PLUGIN_DIR . '/image-optimization/image-optimization.php';
if ( file_exists( $plugin_file ) ) {
	if ( ! function_exists( 'get_plugin_data' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	$data = get_plugin_data( $plugin_file, false, false );
	echo '  Name: ' . ( $data['Name'] ?? 'n/a' ) . "\n";
	echo '  Version: ' . ( $data['Version'] ?? 'n/a' ) . "\n";
	echo '  Description: ' . ( $data['Description'] ?? 'n/a' ) . "\n";
} else {
	echo "  plugin file not found\n";
}

echo "\nOK: read-only diagnostic complete\n";
