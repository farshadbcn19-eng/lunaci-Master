<?php
/**
 * READ-ONLY. Get the exact byte-for-byte hero wordmark block in post 772
 * (ES homepage) - the guarded fix found 0 occurrences of the EN block's
 * exact whitespace/formatting, so this dumps the real ES text to target
 * precisely instead of assuming it's identical to the EN block.
 */

global $wpdb;

$raw = $wpdb->get_var( $wpdb->prepare(
	"SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s", 772, '_elementor_data'
) );
$decoded = json_decode( (string) $raw, true );
$html = '';
$walk = function ( $elements ) use ( &$walk, &$html ) {
	foreach ( $elements as $el ) {
		if ( isset( $el['widgetType'], $el['settings']['html'] ) && $el['widgetType'] === 'html' ) {
			$html .= $el['settings']['html'];
		}
		if ( ! empty( $el['elements'] ) ) {
			$walk( $el['elements'] );
		}
	}
};
if ( is_array( $decoded ) ) {
	$walk( $decoded );
}

$pos = strpos( $html, 'ln-hero__wordmark' );
echo 'first ln-hero__wordmark mention at offset: ' . var_export( $pos, true ) . "\n";

// Find the actual USED element (not the CSS rule) - look past the style block.
$pos2 = strpos( $html, '<div class="ln-hero__wordmark"' );
$pos2_alt = strpos( $html, "<div class='ln-hero__wordmark'" );
echo "exact match <div class=\\\"ln-hero__wordmark\\\" found: " . var_export( $pos2, true ) . "\n";
echo "exact match <div class='ln-hero__wordmark' found: " . var_export( $pos2_alt, true ) . "\n";

$use_pos = $pos2 !== false ? $pos2 : $pos2_alt;
if ( $use_pos !== false ) {
	echo "\nraw bytes at that position (json_encode-safe dump, 300 chars):\n";
	echo json_encode( substr( $html, $use_pos, 300 ), JSON_UNESCAPED_UNICODE ) . "\n";
} else {
	echo "\nno exact <div class=...ln-hero__wordmark...> markup found. Dumping 400 chars around first mention instead:\n";
	echo json_encode( substr( $html, max( 0, $pos - 50 ), 400 ), JSON_UNESCAPED_UNICODE ) . "\n";
}

echo "\nOK: read-only diagnostic complete\n";
