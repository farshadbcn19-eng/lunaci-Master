<?php
/**
 * READ-ONLY. Get real pixel dimensions for the 7 homepage images
 * (per language) that are missing explicit width/height, straight from
 * the files on disk via getimagesize() - not guessed, not assumed from
 * a media-library record that may or may not exist for these
 * import-script-uploaded files.
 */

$files = array(
	'lunaimport-hero-luna.jpg',
	'lunaimport-collection-face-luna.jpg',
	'lunaimport-collection-eyes-luna.jpg',
	'lunaimport-collection-lips-luna.jpg',
	'lunaimport-collection-nails-luna.jpg',
	'lunaimport-why2-luna-replacement.jpg',
	'lunaimport-origin-crafted-barcelona-luna.jpg',
);

$upload_dir = WP_CONTENT_DIR . '/uploads/2026/08/';

foreach ( $files as $file ) {
	$path = $upload_dir . $file;
	if ( ! file_exists( $path ) ) {
		echo "{$file}: FILE NOT FOUND at {$path}\n";
		continue;
	}
	$info = getimagesize( $path );
	if ( $info === false ) {
		echo "{$file}: getimagesize() failed\n";
		continue;
	}
	echo "{$file}: width={$info[0]} height={$info[1]}\n";
}

echo "\n=== LiteSpeed Cache options (option_name LIKE '%litespeed%') ===\n";
global $wpdb;
$opts = $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '%litespeed%'" );
foreach ( $opts as $opt_name ) {
	echo "  {$opt_name}\n";
}

echo "\n=== active plugins (looking for image-optimization plugins) ===\n";
$active = get_option( 'active_plugins' );
foreach ( (array) $active as $plugin ) {
	echo "  {$plugin}\n";
}

echo "\nOK: read-only diagnostic complete\n";
