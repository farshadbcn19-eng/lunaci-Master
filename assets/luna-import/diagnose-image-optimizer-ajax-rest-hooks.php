<?php
/**
 * READ-ONLY. Deeper investigation into how the Image Optimization plugin's
 * "Bulk Optimize" button actually triggers processing, so a direct,
 * server-side call can be attempted instead of relying on the user to
 * click through the wp-admin UI.
 *
 * Looks for:
 * 1. wp_ajax_* action registrations (classic AJAX handlers)
 * 2. register_rest_route() calls (modern REST API handlers)
 * 3. The plugin's own JS files, to see which endpoint the "Bulk Optimize"
 *    button's client-side code actually calls and with what parameters
 */

$plugin_dir = WP_PLUGIN_DIR . '/image-optimization';

function lunaci_scan_dir( string $dir, int $max_depth, int $depth = 0 ): array {
	if ( $depth > $max_depth ) {
		return array();
	}
	$files = array();
	$entries = scandir( $dir );
	foreach ( $entries as $entry ) {
		if ( $entry === '.' || $entry === '..' ) {
			continue;
		}
		$path = $dir . '/' . $entry;
		if ( is_dir( $path ) ) {
			$files = array_merge( $files, lunaci_scan_dir( $path, $max_depth, $depth + 1 ) );
		} else {
			$files[] = $path;
		}
	}
	return $files;
}

$all_files = lunaci_scan_dir( $plugin_dir, 5 );
$php_files = array_filter( $all_files, fn( $f ) => substr( $f, -4 ) === '.php' );
$js_files  = array_filter( $all_files, fn( $f ) => substr( $f, -3 ) === '.js' && strpos( $f, '.min.js' ) === false );

echo "total php files: " . count( $php_files ) . ", total non-minified js files: " . count( $js_files ) . "\n";

echo "\n--- wp_ajax_* registrations relevant to optimize/bulk ---\n";
foreach ( $php_files as $file ) {
	$lines = file( $file );
	if ( ! $lines ) continue;
	foreach ( $lines as $i => $line ) {
		if ( preg_match( '/add_action\s*\(\s*[\'"](wp_ajax_[a-zA-Z0-9_]*(?:optim|bulk)[a-zA-Z0-9_]*)[\'"]/i', $line, $m ) ) {
			echo '  ' . str_replace( $plugin_dir . '/', '', $file ) . ':' . ( $i + 1 ) . ' -> ' . $m[1] . "\n";
		}
	}
}

echo "\n--- register_rest_route() calls relevant to optimize/bulk ---\n";
foreach ( $php_files as $file ) {
	$content = file_get_contents( $file );
	if ( $content && preg_match_all( '/register_rest_route\s*\(\s*[\'"]([^\'"]+)[\'"]\s*,\s*[\'"]([^\'"]+)[\'"]/i', $content, $matches, PREG_SET_ORDER ) ) {
		foreach ( $matches as $m ) {
			if ( stripos( $m[1] . $m[2], 'optim' ) !== false || stripos( $m[1] . $m[2], 'bulk' ) !== false || stripos( $m[1] . $m[2], 'image' ) !== false ) {
				echo '  ' . str_replace( $plugin_dir . '/', '', $file ) . ' -> namespace=' . $m[1] . ' route=' . $m[2] . "\n";
			}
		}
	}
}

echo "\n--- ALL register_rest_route() calls (unfiltered, in case naming doesn't match) ---\n";
foreach ( $php_files as $file ) {
	$content = file_get_contents( $file );
	if ( $content && preg_match_all( '/register_rest_route\s*\(\s*[\'"]([^\'"]+)[\'"]\s*,\s*[\'"]([^\'"]+)[\'"]/i', $content, $matches, PREG_SET_ORDER ) ) {
		foreach ( $matches as $m ) {
			echo '  ' . str_replace( $plugin_dir . '/', '', $file ) . ' -> namespace=' . $m[1] . ' route=' . $m[2] . "\n";
		}
	}
}

echo "\n--- js files that mention 'bulk' or 'optimiz' (client-side trigger code) ---\n";
foreach ( $js_files as $file ) {
	$content = file_get_contents( $file );
	if ( $content && ( stripos( $content, 'bulk' ) !== false ) ) {
		echo '  ' . str_replace( $plugin_dir . '/', '', $file ) . "\n";
	}
}

echo "\nOK: read-only diagnostic complete\n";
