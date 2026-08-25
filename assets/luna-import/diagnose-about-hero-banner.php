<?php
/**
 * Read-only diagnostic: locate the About Us page's (post 59) current hero
 * banner image, to design a guarded replacement fix. Mirrors the approach
 * used for the Home and Products page hero banners: find the HTML widget(s)
 * in _elementor_data, dump all image src values found, and check whether
 * this page's CSS lives inline in the widget or in a separate WPCode
 * snippet (like Products' post 483).
 */

global $wpdb;

$page_id = 59;

function lunaci_about_find_html_widgets( $node, $path, &$results ) {
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
			'path' => $path,
			'id'   => $current_id,
			'len'  => isset( $node['settings']['html'] ) ? strlen( $node['settings']['html'] ) : 0,
		);
	}
	foreach ( $node as $key => $value ) {
		if ( is_array( $value ) ) {
			lunaci_about_find_html_widgets( $value, $path, $results );
		}
	}
}

function lunaci_about_get_widget_html_by_id( $node, $target_id ) {
	if ( ! is_array( $node ) ) {
		return null;
	}
	if ( isset( $node['id'], $node['widgetType'] ) && $node['id'] === $target_id && 'html' === $node['widgetType'] ) {
		return isset( $node['settings']['html'] ) ? $node['settings']['html'] : null;
	}
	foreach ( $node as $value ) {
		if ( is_array( $value ) ) {
			$found = lunaci_about_get_widget_html_by_id( $value, $target_id );
			if ( null !== $found ) {
				return $found;
			}
		}
	}
	return null;
}

$raw = $wpdb->get_var(
	$wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_elementor_data'", $page_id )
);
if ( null === $raw ) {
	echo "ERROR: _elementor_data not found for page ID={$page_id}.\n";
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
lunaci_about_find_html_widgets( $decoded, '', $widgets );
foreach ( $widgets as $w ) {
	echo "path={$w['path']}  id={$w['id']}  html_len={$w['len']}\n";
}

usort( $widgets, function ( $a, $b ) { return $b['len'] - $a['len']; } );

foreach ( $widgets as $idx => $w ) {
	$html = lunaci_about_get_widget_html_by_id( $decoded, $w['id'] );
	if ( null === $html ) {
		continue;
	}
	echo "\n=== Widget #{$idx} (id={$w['id']}) image src values ===\n";
	if ( preg_match_all( '/<img[^>]*src="([^"]*)"[^>]*>/i', $html, $m ) ) {
		foreach ( $m[1] as $src ) {
			echo "  {$src}\n";
		}
	} else {
		echo "  (no <img> tags found)\n";
	}
	// also check for CSS background-image url()
	if ( preg_match_all( '/background(?:-image)?\s*:[^;]*url\([\'"]?([^\'")]+)[\'"]?\)/i', $html, $m2 ) ) {
		echo "  background-image url()s:\n";
		foreach ( $m2[1] as $src ) {
			echo "    {$src}\n";
		}
	}
	$has_style = ( false !== strpos( $html, '<style>' ) );
	echo "  has inline <style> block: " . ( $has_style ? 'yes' : 'no' ) . "\n";

	echo "\n  === Section-level structure ===\n";
	if ( preg_match_all( '/<section[^>]*class="([^"]*)"[^>]*>/i', $html, $m3 ) ) {
		foreach ( $m3[1] as $i => $cls ) {
			echo "    [{$i}] class=\"{$cls}\"\n";
		}
	}
}

echo "\n=== Check: does WPCode have any snippet with 'page-id-59' or About-related hero CSS? ===\n";
$wpcode_posts = $wpdb->get_results( "SELECT ID, post_title FROM {$wpdb->posts} WHERE post_type = 'wpcode' AND post_status = 'publish'", ARRAY_A );
foreach ( $wpcode_posts as $wp_row ) {
	$content = get_post_field( 'post_content', $wp_row['ID'] );
	$matches_59 = ( false !== strpos( $content, 'page-59' ) || false !== strpos( $content, 'page-id-59' ) );
	echo "wpcode post ID={$wp_row['ID']} title=\"{$wp_row['post_title']}\" mentions page 59: " . ( $matches_59 ? 'yes' : 'no' ) . " (content len=" . strlen( $content ) . ")\n";
}

echo "\nOK: read-only About-hero diagnostic complete, no writes performed\n";
