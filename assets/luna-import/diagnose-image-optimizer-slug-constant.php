<?php
/**
 * READ-ONLY. The menu registration call found in modules/settings/module.php
 * uses self::SETTING_BASE_SLUG (a class constant), not a literal string.
 * This greps the plugin source for where SETTING_BASE_SLUG (and
 * SETTING_CAPABILITY, for completeness) is actually defined, to resolve
 * the real admin.php?page=... value.
 */

$plugin_dir = WP_PLUGIN_DIR . '/image-optimization';

function lunaci_scan_dir_slugconst( string $dir, int $max_depth, int $depth = 0 ): array {
	if ( $depth > $max_depth ) return array();
	$files = array();
	foreach ( scandir( $dir ) as $entry ) {
		if ( $entry === '.' || $entry === '..' ) continue;
		$path = $dir . '/' . $entry;
		if ( is_dir( $path ) ) {
			$files = array_merge( $files, lunaci_scan_dir_slugconst( $path, $max_depth, $depth + 1 ) );
		} elseif ( substr( $entry, -4 ) === '.php' ) {
			$files[] = $path;
		}
	}
	return $files;
}

$php_files = lunaci_scan_dir_slugconst( $plugin_dir, 6 );

echo "--- SETTING_BASE_SLUG / SETTING_CAPABILITY constant definitions ---\n";
foreach ( $php_files as $file ) {
	$lines = file( $file );
	if ( ! $lines ) continue;
	foreach ( $lines as $i => $line ) {
		if ( preg_match( '/const\s+SETTING_BASE_SLUG\s*=\s*[\'"]([^\'"]+)[\'"]/', $line, $m ) ) {
			echo '  SETTING_BASE_SLUG = ' . $m[1] . '  (' . str_replace( $plugin_dir . '/', '', $file ) . ':' . ( $i + 1 ) . ")\n";
		}
		if ( preg_match( '/const\s+SETTING_CAPABILITY\s*=\s*[\'"]([^\'"]+)[\'"]/', $line, $m ) ) {
			echo '  SETTING_CAPABILITY = ' . $m[1] . '  (' . str_replace( $plugin_dir . '/', '', $file ) . ':' . ( $i + 1 ) . ")\n";
		}
	}
}

echo "\n--- fallback: any line defining a constant containing 'BASE_SLUG' ---\n";
foreach ( $php_files as $file ) {
	$lines = file( $file );
	if ( ! $lines ) continue;
	foreach ( $lines as $i => $line ) {
		if ( stripos( $line, 'BASE_SLUG' ) !== false && stripos( $line, 'const' ) !== false ) {
			echo '  ' . str_replace( $plugin_dir . '/', '', $file ) . ':' . ( $i + 1 ) . ' -> ' . trim( $line ) . "\n";
		}
	}
}

echo "\nOK: read-only diagnostic complete\n";
