<?php
/**
 * READ-ONLY. Find the exact wp-admin menu slug the Image Optimization
 * plugin registers, so a direct admin.php?page=... link can be given
 * to the user instead of asking them to hunt through the sidebar.
 */

$plugin_dir = WP_PLUGIN_DIR . '/image-optimization';

function lunaci_scan_dir( string $dir, int $max_depth, int $depth = 0 ): array {
	if ( $depth > $max_depth ) return array();
	$files = array();
	foreach ( scandir( $dir ) as $entry ) {
		if ( $entry === '.' || $entry === '..' ) continue;
		$path = $dir . '/' . $entry;
		if ( is_dir( $path ) ) {
			$files = array_merge( $files, lunaci_scan_dir( $path, $max_depth, $depth + 1 ) );
		} elseif ( substr( $entry, -4 ) === '.php' ) {
			$files[] = $path;
		}
	}
	return $files;
}

$php_files = lunaci_scan_dir( $plugin_dir, 5 );

echo "--- add_menu_page() / add_submenu_page() calls ---\n";
foreach ( $php_files as $file ) {
	$content = file_get_contents( $file );
	if ( ! $content ) continue;
	if ( preg_match_all( '/add_(?:menu|submenu|options)_page\s*\([^)]*\)/s', $content, $matches ) ) {
		foreach ( $matches[0] as $m ) {
			$snippet = preg_replace( '/\s+/', ' ', substr( $m, 0, 300 ) );
			echo '  ' . str_replace( $plugin_dir . '/', '', $file ) . ' -> ' . $snippet . "\n";
		}
	}
}

echo "\n--- currently registered top-level and Hostinger submenu pages (from $GLOBALS) ---\n";
global $menu, $submenu;
if ( is_array( $menu ) ) {
	foreach ( $menu as $item ) {
		if ( isset( $item[2] ) && ( stripos( $item[0] ?? '', 'optim' ) !== false || stripos( $item[2], 'optim' ) !== false || stripos( $item[2], 'hostinger' ) !== false ) ) {
			echo '  top-level: label=' . wp_strip_all_tags( $item[0] ?? '' ) . ' slug=' . $item[2] . "\n";
		}
	}
}
if ( is_array( $submenu ) ) {
	foreach ( $submenu as $parent_slug => $items ) {
		foreach ( $items as $item ) {
			if ( isset( $item[2] ) && ( stripos( $item[0] ?? '', 'optim' ) !== false || stripos( $item[2], 'optim' ) !== false ) ) {
				echo "  submenu under '{$parent_slug}': label=" . wp_strip_all_tags( $item[0] ?? '' ) . ' slug=' . $item[2] . "\n";
			}
		}
	}
}

echo "\nOK: read-only diagnostic complete\n";
