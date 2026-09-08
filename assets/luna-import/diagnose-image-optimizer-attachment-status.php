<?php
/**
 * READ-ONLY. The Hostinger Image Optimization plugin (v1.7.6, active,
 * already configured: optimize_on_upload=1, convert_to_format=webp) only
 * acts on attachments it sees go through the normal upload/attachment
 * pipeline. The 7 homepage images were dropped onto disk directly by
 * earlier import work (getimagesize() found them on disk, but that says
 * nothing about media-library registration) - if they were never
 * registered as attachments, the optimizer never had a chance to touch
 * them, regardless of its settings. Confirm attachment status for each,
 * and look for a safe, official way to (re)trigger optimization -
 * either a WP-CLI command the plugin registers, or a documented public
 * function/hook - before attempting anything.
 */

global $wpdb;

$files = array(
	'lunaimport-hero-luna.jpg',
	'lunaimport-collection-face-luna.jpg',
	'lunaimport-collection-eyes-luna.jpg',
	'lunaimport-collection-lips-luna.jpg',
	'lunaimport-collection-nails-luna.jpg',
	'lunaimport-why2-luna-replacement.jpg',
	'lunaimport-origin-crafted-barcelona-luna.jpg',
);

echo "--- attachment registration status for each homepage image ---\n";
foreach ( $files as $file ) {
	$guid_like = '%' . $wpdb->esc_like( $file );
	$attachment_id = $wpdb->get_var( $wpdb->prepare(
		"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND guid LIKE %s LIMIT 1",
		$guid_like
	) );
	if ( $attachment_id ) {
		$optimized_meta = get_post_meta( $attachment_id, '_image_optimizer_status', true );
		$all_optimizer_meta = array();
		foreach ( get_post_meta( $attachment_id ) as $meta_key => $meta_val ) {
			if ( stripos( $meta_key, 'optim' ) !== false || stripos( $meta_key, 'webp' ) !== false || stripos( $meta_key, 'avif' ) !== false ) {
				$all_optimizer_meta[ $meta_key ] = $meta_val;
			}
		}
		echo "  {$file}: attachment_id={$attachment_id}\n";
		echo '    optimizer-related postmeta: ' . ( $all_optimizer_meta ? json_encode( $all_optimizer_meta ) : '(none found)' ) . "\n";
	} else {
		echo "  {$file}: NOT a registered attachment (no matching guid in wp_posts)\n";
	}
}

echo "\n--- WP-CLI commands registered by the Image Optimization plugin ---\n";
if ( class_exists( 'WP_CLI' ) ) {
	echo "  (checking WP_CLI::get_root_command() / registered commands is not straightforward from eval-file context)\n";
}
$plugin_dir = WP_PLUGIN_DIR . '/image-optimization';
if ( is_dir( $plugin_dir ) ) {
	$php_files = glob( $plugin_dir . '/**/*.php' );
	$cli_hits = array();
	$files_to_scan = glob( $plugin_dir . '/*.php' );
	$sub = glob( $plugin_dir . '/*/*.php' );
	$files_to_scan = array_merge( $files_to_scan, $sub );
	foreach ( $files_to_scan as $f ) {
		$contents = file_get_contents( $f );
		if ( stripos( $contents, 'WP_CLI::add_command' ) !== false ) {
			$cli_hits[] = $f;
		}
	}
	echo '  files referencing WP_CLI::add_command: ' . ( $cli_hits ? implode( ', ', $cli_hits ) : '(none found in top 2 directory levels)' ) . "\n";
} else {
	echo "  plugin directory not found at {$plugin_dir}\n";
}

echo "\nOK: read-only diagnostic complete\n";
