<?php
/**
 * Read-only diagnostic: inspect the ES Home translation (post 772)'s
 * Elementor HTML widget content in detail, to design a precise fix that
 * brings it up to date with EN post 57 (adds the "Our Origin" section,
 * translated, and swaps the 5 stale image src values) without disturbing
 * anything else already correctly translated. No writes are performed.
 */

global $wpdb;

$page_id = 772;

function lunaci_find_html_widgets( $node, $path, &$results ) {
	if ( ! is_array( $node ) ) {
		return;
	}
	$current_id     = isset( $node['id'] ) ? $node['id'] : null;
	$current_eltype = isset( $node['elType'] ) ? $node['elType'] : null;
	if ( null !== $current_id && null !== $current_eltype ) {
		$path = $path . '/' . $current_eltype . '[id=' . $current_id . ']';
	}
	if ( 'widget' === $current_eltype && isset( $node['widgetType'] ) && 'html' === $node['widgetType'] ) {
		$results[] = array(
			'path'   => $path,
			'id'     => $current_id,
			'len'    => isset( $node['settings']['html'] ) ? strlen( $node['settings']['html'] ) : 0,
			'parent' => null,
		);
	}
	foreach ( $node as $key => $value ) {
		if ( is_array( $value ) ) {
			lunaci_find_html_widgets( $value, $path, $results );
		}
	}
}

$raw = $wpdb->get_var(
	$wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_elementor_data'", $page_id )
);
if ( null === $raw ) {
	echo "ABORT: _elementor_data not found for page ID={$page_id}.\n";
	exit( 1 );
}
echo "Raw _elementor_data byte length: " . strlen( $raw ) . "\n";

$decoded = json_decode( $raw, true );
if ( JSON_ERROR_NONE !== json_last_error() ) {
	echo "ABORT: json_decode failed: " . json_last_error_msg() . "\n";
	exit( 1 );
}
echo "OK: json_decode succeeded.\n\n";

echo "=== All HTML widgets found in post {$page_id} ===\n";
$widgets = array();
lunaci_find_html_widgets( $decoded, '', $widgets );
foreach ( $widgets as $w ) {
	echo "path={$w['path']}  id={$w['id']}  html_len={$w['len']}\n";
}

// Assume the largest HTML widget is the main page-builder widget (matches the pattern used on post 57).
usort( $widgets, function ( $a, $b ) { return $b['len'] - $a['len']; } );
$main = $widgets[0];
echo "\nLargest HTML widget selected: path={$main['path']} id={$main['id']} len={$main['len']}\n\n";

function lunaci_get_widget_html_by_id( $node, $target_id ) {
	if ( ! is_array( $node ) ) {
		return null;
	}
	if ( isset( $node['id'], $node['widgetType'] ) && $node['id'] === $target_id && 'html' === $node['widgetType'] ) {
		return isset( $node['settings']['html'] ) ? $node['settings']['html'] : null;
	}
	foreach ( $node as $value ) {
		if ( is_array( $value ) ) {
			$found = lunaci_get_widget_html_by_id( $value, $target_id );
			if ( null !== $found ) {
				return $found;
			}
		}
	}
	return null;
}

$html = lunaci_get_widget_html_by_id( $decoded, $main['id'] );
if ( null === $html ) {
	echo "ABORT: could not extract html string for widget id={$main['id']}\n";
	exit( 1 );
}

echo "=== Image src values (hero/collection related) ===\n";
if ( preg_match_all( '/<img[^>]*src="([^"]*)"[^>]*>/i', $html, $m ) ) {
	foreach ( $m[1] as $src ) {
		if ( stripos( $src, 'hero' ) !== false || stripos( $src, 'collection' ) !== false || stripos( $src, 'luna' ) !== false ) {
			echo "  {$src}\n";
		}
	}
}

echo "\n=== <style> block presence ===\n";
$has_style = ( false !== strpos( $html, '<style>' ) && false !== strpos( $html, '</style>' ) );
echo "has <style>...</style>: " . ( $has_style ? 'yes' : 'no' ) . "\n";
if ( $has_style ) {
	$style_start = strpos( $html, '<style>' );
	$style_end   = strpos( $html, '</style>' ) + strlen( '</style>' );
	echo "style block byte range: {$style_start} - {$style_end}\n";
	echo "last 400 chars of style block (before </style>):\n";
	echo substr( $html, max( $style_start, $style_end - 400 ), min( 400, $style_end - $style_start ) ) . "\n";
}

echo "\n=== Context around a 'Newsletter' marker (case-insensitive) ===\n";
if ( preg_match( '/newsletter/i', $html, $m2, PREG_OFFSET_CAPTURE ) ) {
	$offset = $m2[0][1];
	echo "First 'newsletter' match at offset {$offset}\n";
	echo "--- 600 chars before ---\n";
	echo substr( $html, max( 0, $offset - 600 ), 600 ) . "\n";
	echo "--- match + 200 chars after ---\n";
	echo substr( $html, $offset, 200 ) . "\n";
} else {
	echo "No 'newsletter' marker found (case-insensitive) - will need another anchor point.\n";
}

echo "\n=== Section-level structure: all <section class=\"...\"> openings in order ===\n";
if ( preg_match_all( '/<section[^>]*class="([^"]*)"[^>]*>/i', $html, $m3 ) ) {
	foreach ( $m3[1] as $i => $cls ) {
		echo "  [{$i}] class=\"{$cls}\"\n";
	}
}

echo "\nOK: read-only widget-content diagnostic complete, no writes performed\n";
