<?php
/**
 * Read-only: found two things that could explain why the post 319 edit
 * isn't rendering live:
 * 1. wp-content/uploads/wpcode/ exists - WPCode ships a
 *    class-wpcode-file-cache.php, suggesting it compiles auto-insert
 *    snippets into static files for performance, refreshed only when
 *    snippets are saved through WPCode's own save flow.
 * 2. A LIKE search for '.lna-hero__ov2{' (a fragment only present in
 *    post 319's CSS) DID match the `wpcode_snippets` option - meaning it
 *    somehow contains this snippet's code after all, even though an
 *    earlier top-level array traversal (checking $snip['code'] on each
 *    top-level entry) found nothing. The option's entries are probably
 *    nested one level deeper than that traversal assumed.
 *
 * This digs into both: dumps the true nested structure of the
 * wpcode_snippets option entry containing post 319's code (so it can be
 * patched precisely, like the earlier Blusher CSS fix), and inspects the
 * relevant WPCode source files for a public cache-clearing method to
 * call instead of touching generated files directly.
 */

echo "=== 1. wpcode_snippets option: deep search for the entry containing post 319's code ===\n";
$option_raw = get_option( 'wpcode_snippets' );
$snippets   = is_string( $option_raw ) ? maybe_unserialize( $option_raw ) : $option_raw;

function lna_dump_find( $data, $path = '' ) {
	if ( is_array( $data ) ) {
		foreach ( $data as $k => $v ) {
			$new_path = $path . '[' . var_export( $k, true ) . ']';
			if ( is_string( $v ) && false !== strpos( $v, 'lna-hero__ov2' ) ) {
				echo "  *** FOUND string match at path: {$new_path} (length " . strlen( $v ) . ") ***\n";
				echo "  context: ..." . substr( $v, max( 0, strpos( $v, 'lna-hero__content' ) - 20 ), 250 ) . "...\n";
			} elseif ( is_array( $v ) || is_object( $v ) ) {
				lna_dump_find( $v, $new_path );
			}
		}
	} elseif ( is_object( $data ) ) {
		foreach ( get_object_vars( $data ) as $k => $v ) {
			$new_path = $path . '->' . $k;
			if ( is_string( $v ) && false !== strpos( $v, 'lna-hero__ov2' ) ) {
				echo "  *** FOUND string match at path: {$new_path} (length " . strlen( $v ) . ") ***\n";
				echo "  context: ..." . substr( $v, max( 0, strpos( $v, 'lna-hero__content' ) - 20 ), 250 ) . "...\n";
			} elseif ( is_array( $v ) || is_object( $v ) ) {
				lna_dump_find( $v, $new_path );
			}
		}
	}
}

lna_dump_find( $snippets, 'wpcode_snippets' );

echo "\n=== 2. Structure overview (top 2 levels) ===\n";
if ( is_array( $snippets ) ) {
	foreach ( $snippets as $loc => $entries ) {
		echo "  location key: {$loc} (type " . gettype( $entries ) . ")\n";
		if ( is_array( $entries ) ) {
			foreach ( $entries as $idx => $item ) {
				echo "    [{$idx}] type=" . gettype( $item );
				if ( is_array( $item ) ) {
					echo " keys=" . implode( ',', array_keys( $item ) );
					if ( isset( $item['id'] ) ) {
						echo " id=" . $item['id'];
					}
				}
				echo "\n";
			}
		}
	}
}

echo "\n=== 3. wp-content/uploads/wpcode directory listing ===\n";
$dir = WP_CONTENT_DIR . '/uploads/wpcode';
if ( is_dir( $dir ) ) {
	$files = scandir( $dir );
	foreach ( $files as $f ) {
		if ( '.' === $f || '..' === $f ) {
			continue;
		}
		$full = $dir . '/' . $f;
		echo "  {$f} " . ( is_dir( $full ) ? '[DIR]' : filesize( $full ) . ' bytes, modified ' . date( 'Y-m-d H:i:s', filemtime( $full ) ) ) . "\n";
		if ( is_dir( $full ) ) {
			foreach ( scandir( $full ) as $sub ) {
				if ( '.' === $sub || '..' === $sub ) {
					continue;
				}
				$subfull = $full . '/' . $sub;
				echo "    {$sub} " . ( is_dir( $subfull ) ? '[DIR]' : filesize( $subfull ) . ' bytes, modified ' . date( 'Y-m-d H:i:s', filemtime( $subfull ) ) ) . "\n";
			}
		}
	}
} else {
	echo "directory does not exist\n";
}

echo "\n=== 4. class-wpcode-file-cache.php relevant method names ===\n";
$plugin_dir = WP_PLUGIN_DIR . '/insert-headers-and-footers/includes/';
foreach ( array( 'class-wpcode-file-cache.php', 'class-wpcode-snippet-cache.php', 'class-wpcode-auto-insert.php' ) as $file ) {
	$path = $plugin_dir . $file;
	if ( file_exists( $path ) ) {
		$src = file_get_contents( $path );
		echo "--- {$file} ---\n";
		preg_match_all( '/function\s+([a-zA-Z0-9_]+)\s*\(/', $src, $matches );
		echo "  functions: " . implode( ', ', $matches[1] ) . "\n";
		preg_match_all( '/public static function\s+([a-zA-Z0-9_]+)\s*\(/', $src, $static_matches );
		echo "  static functions: " . implode( ', ', $static_matches[1] ) . "\n";
	} else {
		echo "--- {$file} not found ---\n";
	}
}

echo "\nOK: read-only diagnostic complete, no writes performed\n";
