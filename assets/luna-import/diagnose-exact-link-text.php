<?php
/**
 * READ-ONLY. Dump every raw occurrence (with context) of "contact" in
 * post 57's html widget, and every occurrence of "Vendidos" in post 772's
 * html widget, to find the exact byte-for-byte text/encoding to target -
 * two prior attempts guessed the exact search strings and undercounted.
 */

global $wpdb;

function lunaci_get_html_widget_content( int $post_id ) {
	global $wpdb;
	$raw = $wpdb->get_var( $wpdb->prepare(
		"SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s", $post_id, '_elementor_data'
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
	return $html;
}

echo "--- post 57: every 'contact' occurrence (case-insensitive), with context ---\n";
$html57 = lunaci_get_html_widget_content( 57 );
$offset = 0;
$n = 0;
while ( ( $pos = stripos( $html57, 'contact', $offset ) ) !== false ) {
	$n++;
	echo "[{$n}] offset={$pos}: ..." . substr( $html57, max( 0, $pos - 60 ), 140 ) . "...\n\n";
	$offset = $pos + 1;
}
echo "total 'contact' occurrences (case-insensitive): {$n}\n";

echo "\n--- post 772: every 'Vendidos' occurrence, with context ---\n";
$html772 = lunaci_get_html_widget_content( 772 );
$offset = 0;
$n = 0;
while ( ( $pos = stripos( $html772, 'Vendidos', $offset ) ) !== false ) {
	$n++;
	echo "[{$n}] offset={$pos}: ..." . substr( $html772, max( 0, $pos - 80 ), 200 ) . "...\n\n";
	$offset = $pos + 1;
}
echo "total 'Vendidos' occurrences: {$n}\n";

echo "\nOK: read-only diagnostic complete\n";
