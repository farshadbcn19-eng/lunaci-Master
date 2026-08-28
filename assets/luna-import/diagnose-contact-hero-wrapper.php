<?php
/**
 * Read-only: continuing investigation of the "shrunk box / black bars"
 * report on the Contact hero. .contact-hero itself has no width/max-width
 * rule, so the boxed look is most likely Elementor's own wrapping
 * .elementor-container (default boxed max-width) around the HTML widget -
 * same root cause as the earlier About Us full-bleed fix (#lna{width:100vw}).
 * Dump: (1) the very start of the widget HTML (any wrapper id/class before
 * .contact-hero), (2) the Elementor JSON node settings for the
 * section/container that holds this widget (content_width, etc.).
 */

$page_id = 60;

$raw = get_post_meta( $page_id, '_elementor_data', true );
$decoded = json_decode( $raw, true );

$widget_html = null;
$path_trace  = array();

$finder = function ( $node, $trail ) use ( &$finder, &$widget_html, &$path_trace ) {
	if ( $widget_html ) {
		return;
	}
	if ( is_array( $node ) ) {
		$here = $trail;
		if ( isset( $node['elType'] ) ) {
			$here[] = array(
				'elType'   => $node['elType'],
				'id'       => $node['id'] ?? null,
				'settings_keys_of_interest' => isset( $node['settings'] ) ? array_intersect_key(
					$node['settings'],
					array_flip( array( 'content_width', 'width', 'layout', 'boxed_width', 'container_type' ) )
				) : array(),
			);
		}
		if ( isset( $node['widgetType'] ) && 'html' === $node['widgetType'] && isset( $node['settings']['html'] ) ) {
			if ( false !== strpos( $node['settings']['html'], '.contact-hero' ) ) {
				$widget_html = $node['settings']['html'];
				$path_trace  = $here;
				return;
			}
		}
		foreach ( $node as $key => $child ) {
			$finder( $child, $here );
			if ( $widget_html ) {
				return;
			}
		}
	}
};
$finder( $decoded, array() );

echo "===================== ANCESTOR CHAIN (elType / id / relevant settings) =====================\n";
foreach ( $path_trace as $i => $level ) {
	echo "level {$i}: elType={$level['elType']} id={$level['id']} settings=" . wp_json_encode( $level['settings_keys_of_interest'] ) . "\n";
}
echo "===================== END ANCESTOR CHAIN =====================\n\n";

echo "===================== FIRST 1200 CHARS OF WIDGET HTML =====================\n";
echo substr( $widget_html, 0, 1200 ) . "\n";
echo "===================== END FIRST 1200 CHARS =====================\n\n";

echo "OK: read-only diagnostic complete\n";
