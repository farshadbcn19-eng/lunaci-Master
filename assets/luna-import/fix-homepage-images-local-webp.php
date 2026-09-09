<?php
/**
 * GUARDED FIX. Hostinger's Image Optimization plugin's bulk-optimize
 * feature (which calls an external cloud API) has failed 3/3 retries
 * for all 7 homepage images (image_optimizer_metadata status =
 * "optimization-failed"). Rather than depend on that external service,
 * generate .webp sibling files locally via GD/Imagick and enable
 * LiteSpeed's own WebP-serving rewrite (litespeed.conf.img_optm-webp),
 * which is already configured with the right webp_attr selectors
 * (img.src etc.) but currently disabled (value 0).
 *
 * Idempotent: skips any attachment that already has a valid .webp
 * sibling on disk.
 */

$attachment_ids = array( 776, 778, 780, 782, 784, 786, 788 );

if ( ! function_exists( 'imagewebp' ) && ! ( class_exists( 'Imagick' ) && in_array( 'WEBP', Imagick::queryFormats( 'WEBP' ), true ) ) ) {
	echo "ABORT: neither GD imagewebp() nor Imagick WEBP support is available on this server.\n";
	return;
}

$changed = 0;
$skipped = 0;
$failed  = 0;

foreach ( $attachment_ids as $id ) {
	$file = get_attached_file( $id );
	if ( ! $file || ! file_exists( $file ) ) {
		echo "SKIP $id: source file not found\n";
		$skipped++;
		continue;
	}

	$webp_path = preg_replace( '/\.(jpe?g|png)$/i', '.webp', $file );
	if ( $webp_path === $file ) {
		echo "SKIP $id: unsupported source extension ($file)\n";
		$skipped++;
		continue;
	}

	if ( file_exists( $webp_path ) && filesize( $webp_path ) > 0 ) {
		echo "SKIP $id: .webp already exists ($webp_path)\n";
		$skipped++;
		continue;
	}

	$ext    = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );
	$ok     = false;
	$method = '';

	if ( function_exists( 'imagewebp' ) ) {
		$src = ( $ext === 'png' ) ? @imagecreatefrompng( $file ) : @imagecreatefromjpeg( $file );
		if ( $src ) {
			if ( $ext === 'png' ) {
				imagepalettetotruecolor( $src );
				imagealphablending( $src, true );
				imagesavealpha( $src, true );
			}
			$ok     = imagewebp( $src, $webp_path, 82 );
			$method = 'GD';
			imagedestroy( $src );
		}
	}

	if ( ! $ok && class_exists( 'Imagick' ) ) {
		try {
			$img = new Imagick( $file );
			$img->setImageFormat( 'webp' );
			$img->setImageCompressionQuality( 82 );
			$ok     = $img->writeImage( $webp_path );
			$method = 'Imagick';
			$img->clear();
			$img->destroy();
		} catch ( Exception $e ) {
			echo "  Imagick error for $id: " . $e->getMessage() . "\n";
		}
	}

	if ( $ok && file_exists( $webp_path ) && filesize( $webp_path ) > 0 ) {
		echo "OK $id: generated $webp_path via $method (" . filesize( $webp_path ) . " bytes)\n";
		$changed++;
	} else {
		echo "FAIL $id: could not generate .webp for $file\n";
		$failed++;
	}
}

// Enable LiteSpeed's own WebP-serving rewrite (already configured with
// the right webp_attr selectors, just currently switched off).
$current = get_option( 'litespeed.conf.img_optm-webp' );
if ( $current !== '1' && $current !== 1 ) {
	update_option( 'litespeed.conf.img_optm-webp', 1 );
	$readback = get_option( 'litespeed.conf.img_optm-webp' );
	echo 'litespeed.conf.img_optm-webp: ' . var_export( $current, true ) . ' -> ' . var_export( $readback, true ) . "\n";
} else {
	echo "litespeed.conf.img_optm-webp already enabled, left as-is\n";
}

echo "\nSummary: changed=$changed skipped=$skipped failed=$failed\n";

if ( $failed > 0 ) {
	echo "ABORT: one or more conversions failed, review output above\n";
	return;
}

echo "OK: fix completed successfully\n";
