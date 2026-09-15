<?php
/**
 * Read-only diagnostic: About Us ES (post 680) renders ~17,000px tall with
 * large empty stretches, while EN (post 59) does not. Both share identical
 * top-level container IDs (24428e2, ff5f046), so the structure itself is
 * shared/translated via Elementor - the gap must come from individual
 * widgets whose settings are empty, missing, or hidden on the ES side.
 * Walk both trees and compare widget-by-widget: type, key text/image
 * settings length, and any 0-content widgets, to find exactly what's
 * missing before writing any fix. No writes.
 */

function lunaci_walk_widgets( $node, $path, &$out ) {
	if ( ! is_array( $node ) ) {
		return;
	}
	if ( isset( $node['id'], $node['elType'] ) ) {
		$entry = array(
			'path'   => $path,
			'id'     => $node['id'],
			'elType' => $node['elType'],
		);
		if ( 'widget' === $node['elType'] ) {
			$entry['widgetType'] = $node['widgetType'] ?? '(unknown)';
			$settings = isset( $node['settings'] ) && is_array( $node['settings'] ) ? $node['settings'] : array();
			$summary = array();
			foreach ( array( 'title', 'editor', 'text', 'html', 'image', 'title_text' ) as $key ) {
				if ( isset( $settings[ $key ] ) ) {
					$val = $settings[ $key ];
					if ( is_array( $val ) ) {
						$summary[ $key ] = 'array(' . wp_json_encode( array_slice( $val, 0, 1 ) ) . ')';
					} else {
						$len = strlen( (string) $val );
						$summary[ $key ] = "len={$len}" . ( $len > 0 && $len < 60 ? ':' . $val : '' );
					}
				}
			}
			$entry['settings_summary'] = $summary;
		}
		$out[] = $entry;
	}
	if ( isset( $node['elements'] ) && is_array( $node['elements'] ) ) {
		foreach ( $node['elements'] as $i => $child ) {
			lunaci_walk_widgets( $child, $path . '/' . $i, $out );
		}
	}
}

foreach ( array( 'EN' => 59, 'ES' => 680 ) as $label => $post_id ) {
	echo "=====================================================================\n";
	echo "{$label} post {$post_id}\n";
	echo "=====================================================================\n";
	$raw = get_post_meta( $post_id, '_elementor_data', true );
	if ( ! $raw ) {
		echo "ERROR: no _elementor_data\n\n";
		continue;
	}
	$decoded = json_decode( $raw, true );
	if ( JSON_ERROR_NONE !== json_last_error() ) {
		echo "ERROR: json_decode failed\n\n";
		continue;
	}
	$widgets = array();
	foreach ( $decoded as $i => $top ) {
		lunaci_walk_widgets( $top, (string) $i, $widgets );
	}
	echo "total elements: " . count( $widgets ) . "\n\n";
	foreach ( $widgets as $w ) {
		if ( 'widget' === $w['elType'] ) {
			echo "[{$w['path']}] id={$w['id']} widgetType={$w['widgetType']}\n";
			foreach ( $w['settings_summary'] as $k => $v ) {
				echo "    {$k}: {$v}\n";
			}
		} else {
			echo "[{$w['path']}] id={$w['id']} elType={$w['elType']}\n";
		}
	}
	echo "\n";
}

echo "OK: read-only diagnostic complete, no writes performed\n";
