<?php
/**
 * READ-ONLY. Before wrapping homepage/products content in <main>,
 * confirm the exact anchor strings planned for the guarded fix appear
 * exactly once each in the RAW (decoded) _elementor_data HTML - not
 * the live-rendered output, which can differ slightly in whitespace.
 */

$targets = array(
	57  => array( 'after' => "</nav>\n\n<section class=\"ln-hero\">", 'before' => '<footer class="ln-foot">', 'label' => 'EN homepage' ),
	772 => array( 'after' => "</nav>\n\n<section class=\"ln-hero\">", 'before' => '<footer class="ln-foot">', 'label' => 'ES homepage' ),
	61  => array( 'after' => '</header>', 'before' => '<footer class="lp-footer">', 'label' => 'EN products archive' ),
	771 => array( 'after' => '</header>', 'before' => '<footer class="lp-footer">', 'label' => 'ES products archive' ),
);

function lunaci_find_html_widgets( array $elements, array &$out ): void {
	foreach ( $elements as $element ) {
		if ( isset( $element['widgetType'] ) && $element['widgetType'] === 'html' && isset( $element['settings']['html'] ) ) {
			$out[] = $element['settings']['html'];
		}
		if ( isset( $element['elements'] ) && is_array( $element['elements'] ) ) {
			lunaci_find_html_widgets( $element['elements'], $out );
		}
	}
}

foreach ( $targets as $post_id => $config ) {
	echo "=== Post $post_id ({$config['label']}) ===\n";
	$raw = get_post_meta( $post_id, '_elementor_data', true );
	if ( ! $raw ) {
		echo "  no _elementor_data\n\n";
		continue;
	}
	$decoded = json_decode( $raw, true );
	if ( ! is_array( $decoded ) ) {
		echo "  could not decode JSON\n\n";
		continue;
	}
	$html_blocks = array();
	lunaci_find_html_widgets( $decoded, $html_blocks );
	echo '  html widgets found: ' . count( $html_blocks ) . "\n";

	$total_after  = 0;
	$total_before = 0;
	$total_main   = 0;
	foreach ( $html_blocks as $html ) {
		$total_after  += substr_count( $html, $config['after'] );
		$total_before += substr_count( $html, $config['before'] );
		$total_main   += substr_count( $html, '<main' );
	}
	echo "  'after' anchor occurrences: $total_after (want 1)\n";
	echo "  'before' anchor occurrences: $total_before (want 1)\n";
	echo "  existing <main occurrences: $total_main (want 0)\n";

	if ( $total_after !== 1 || $total_before !== 1 ) {
		// Dump a bit of raw context to help adjust the anchor if it didn't match.
		foreach ( $html_blocks as $i => $html ) {
			if ( strpos( $html, '</nav>' ) !== false || strpos( $html, '</header>' ) !== false ) {
				$pos = strpos( $html, '</nav>' );
				if ( $pos === false ) {
					$pos = strpos( $html, '</header>' );
				}
				echo "  --- raw context around first </nav> or </header> in widget #$i ---\n";
				echo '  ' . str_replace( "\n", '\\n', substr( $html, max( 0, $pos - 20 ), 120 ) ) . "\n";
			}
		}
	}
	echo "\n";
}

echo "OK: read-only diagnostic complete\n";
