<?php
/**
 * Read-only: call Elementor's own frontend rendering function directly
 * (the same one used to build the actual page HTML) to see whether the
 * 100dvh fix is present in ITS output, or whether Elementor has some
 * internal cache (separate from _elementor_data itself) that is serving
 * stale content - mirroring the WPCode 'wpcode_snippets' cache bug found
 * earlier this session.
 */

$post_id = 60;

echo "get_post_meta _elementor_data contains '100dvh': " .
	( false !== strpos( get_post_meta( $post_id, '_elementor_data', true ), '100dvh' ) ? 'YES' : 'NO' ) . "\n\n";

if ( class_exists( '\Elementor\Plugin' ) ) {
	$content = \Elementor\Plugin::$instance->frontend->get_builder_content_for_display( $post_id );
	echo "get_builder_content_for_display() length: " . strlen( $content ) . "\n";
	echo "contains '100dvh': " . ( false !== strpos( $content, '100dvh' ) ? 'YES' : 'NO' ) . "\n";
	echo "contains 'min-height: 100vh': " . ( false !== strpos( $content, 'min-height: 100vh' ) ? 'YES' : 'NO' ) . "\n";

	$pos = strpos( $content, 'min-height: 100vh' );
	if ( false !== $pos ) {
		echo "\n--- 100 bytes from 'min-height: 100vh' in rendered output ---\n";
		echo substr( $content, $pos, 100 ) . "\n";
	}
} else {
	echo "Elementor Plugin class not found\n";
}

echo "\n--- Check for any Elementor-related transients/cache options ---\n";
global $wpdb;
$rows = $wpdb->get_results(
	"SELECT option_name, LENGTH(option_value) AS len FROM {$wpdb->options}
	 WHERE option_name LIKE '%elementor%cache%' OR option_name LIKE '%_transient%elementor%'
	 ORDER BY option_name",
	ARRAY_A
);
foreach ( $rows as $r ) {
	echo "option: {$r['option_name']}  len={$r['len']}\n";
}

echo "\n--- Check _elementor_css meta and its generated file ---\n";
$css_meta = get_post_meta( $post_id, '_elementor_css', true );
if ( is_array( $css_meta ) ) {
	echo "_elementor_css keys: " . implode( ', ', array_keys( $css_meta ) ) . "\n";
	if ( isset( $css_meta['time'] ) ) {
		echo "_elementor_css['time']: " . $css_meta['time'] . " (" . date( 'Y-m-d H:i:s', $css_meta['time'] ) . ")\n";
	}
}

echo "\nOK: read-only diagnostic complete\n";
