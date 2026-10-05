<?php
/**
 * Create image.ext.webp next to every PNG/JPEG upload (original and all
 * generated sizes) using lunaci_perf_make_webp() from mu-plugins/lunaci-perf.php.
 * Idempotent: up-to-date copies are skipped. Originals are never modified.
 *
 * Usage: wp eval-file generate-webp.php
 */

if ( ! function_exists( 'lunaci_perf_make_webp' ) ) {
	fwrite( STDERR, "lunaci-perf mu-plugin not loaded\n" );
	exit( 1 );
}

$probe = wp_get_image_editor( ABSPATH . 'wp-admin/images/w-logo-blue.png' );
if ( is_wp_error( $probe ) || ! $probe->supports_mime_type( 'image/webp' ) ) {
	fwrite( STDERR, "image editor cannot write WebP on this server\n" );
	exit( 1 );
}
echo 'image editor: ' . get_class( $probe ) . "\n";

$ids = get_posts(
	array(
		'post_type'      => 'attachment',
		'post_mime_type' => array( 'image/png', 'image/jpeg' ),
		'post_status'    => 'inherit',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	)
);

$files = 0;
$made  = 0;
$orig  = 0;
$webp  = 0;
foreach ( $ids as $id ) {
	$file = get_attached_file( $id );
	if ( ! $file || ! file_exists( $file ) ) {
		continue;
	}
	$list = array( $file );
	$meta = wp_get_attachment_metadata( $id );
	$dir  = trailingslashit( dirname( $file ) );
	foreach ( (array) ( $meta['sizes'] ?? array() ) as $size ) {
		if ( ! empty( $size['file'] ) ) {
			$list[] = $dir . $size['file'];
		}
	}
	if ( ! empty( $meta['original_image'] ) ) {
		$list[] = $dir . $meta['original_image'];
	}
	foreach ( array_unique( $list ) as $f ) {
		if ( ! file_exists( $f ) ) {
			continue;
		}
		++$files;
		if ( lunaci_perf_make_webp( $f ) ) {
			++$made;
			$orig += filesize( $f );
			$webp += filesize( $f . '.webp' );
		}
	}
}

printf(
	"attachments: %d, image files: %d, with webp: %d, original %.1f MB -> webp %.1f MB\n",
	count( $ids ),
	$files,
	$made,
	$orig / 1048576,
	$webp / 1048576
);
