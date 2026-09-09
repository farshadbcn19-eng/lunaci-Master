<?php
/**
 * READ-ONLY. The previous diagnostic found the Image Optimization plugin
 * registers its settings page via add_submenu_page('elementor-home', ...)
 * but truncated the call before the actual page slug argument. This dumps
 * the full add_submenu_page()/add_menu_page() call (untruncated) plus
 * surrounding context lines from modules/settings/module.php so the real
 * admin.php?page=... slug can be extracted.
 */

$file = WP_PLUGIN_DIR . '/image-optimization/modules/settings/module.php';

if ( ! file_exists( $file ) ) {
	echo "ERROR: file not found: $file\n";
	return;
}

$content = file_get_contents( $file );
$lines   = file( $file );

echo "--- full add_submenu_page()/add_menu_page() calls (untruncated) ---\n";
if ( preg_match_all( '/add_(?:menu|submenu)_page\s*\(.*?\)\s*;/s', $content, $matches ) ) {
	foreach ( $matches[0] as $m ) {
		echo $m . "\n\n";
	}
} else {
	echo "(no match with greedy pattern, trying line-based context)\n";
}

echo "\n--- line-numbered context around any 'add_submenu_page' / 'add_menu_page' occurrence ---\n";
foreach ( $lines as $i => $line ) {
	if ( stripos( $line, 'add_submenu_page' ) !== false || stripos( $line, 'add_menu_page' ) !== false ) {
		$start = max( 0, $i - 2 );
		$end   = min( count( $lines ) - 1, $i + 15 );
		for ( $j = $start; $j <= $end; $j++ ) {
			echo ( $j + 1 ) . ': ' . $lines[ $j ];
		}
		echo "----\n";
	}
}

echo "\nOK: read-only diagnostic complete\n";
