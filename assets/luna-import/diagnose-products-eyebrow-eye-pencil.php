<?php
/**
 * Read-only diagnostic: user reports the Eyebrow Pencil and Eye Pencil
 * product images on the Products page are broken/not loading or
 * stretched/cropped incorrectly. Locate the relevant markup for both
 * products in the EN Products page (post 61) HTML widget - the image src
 * URLs, surrounding markup, and whether those URLs actually resolve to
 * real attachments in the Media Library - before making any changes.
 */

global $wpdb;

function lunaci_pep_find_html_widgets( $node, &$results ) {
	if ( ! is_array( $node ) ) {
		return;
	}
	if ( isset( $node['widgetType'], $node['settings']['html'] ) && 'html' === $node['widgetType'] ) {
		$results[] = array( 'id' => $node['id'], 'html' => $node['settings']['html'] );
	}
	foreach ( $node as $value ) {
		if ( is_array( $value ) ) {
			lunaci_pep_find_html_widgets( $value, $results );
		}
	}
}

$page_id = 61; // EN Products

$raw = $wpdb->get_var(
	$wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_elementor_data'", $page_id )
);
if ( null === $raw ) {
	echo "ERROR: no _elementor_data for post {$page_id}\n";
	exit( 1 );
}
$decoded = json_decode( $raw, true );
if ( JSON_ERROR_NONE !== json_last_error() ) {
	echo "ERROR: json_decode failed: " . json_last_error_msg() . "\n";
	exit( 1 );
}

$widgets = array();
lunaci_pep_find_html_widgets( $decoded, $widgets );
echo "Found " . count( $widgets ) . " html widget(s) on page {$page_id}\n";

$search_terms = array( 'Eyebrow', 'Eye Pencil', 'eyebrow', 'eye-pencil', 'eye_pencil' );

foreach ( $widgets as $w ) {
	$html = $w['html'];
	$found_any = false;
	foreach ( $search_terms as $term ) {
		if ( false !== stripos( $html, $term ) ) {
			$found_any = true;
			break;
		}
	}
	if ( ! $found_any ) {
		continue;
	}
	echo "\n=====================================================================\n";
	echo "Widget id={$w['id']} html_len=" . strlen( $html ) . " CONTAINS product terms\n";
	echo "=====================================================================\n";

	foreach ( array( 'Eyebrow', 'Eye Pencil' ) as $term ) {
		$offset = 0;
		$occurrence = 0;
		while ( false !== ( $pos = stripos( $html, $term, $offset ) ) ) {
			$occurrence++;
			echo "\n--- occurrence #{$occurrence} of '{$term}' at byte {$pos} ---\n";
			echo substr( $html, max( 0, $pos - 500 ), 900 ) . "\n";
			$offset = $pos + strlen( $term );
			if ( $occurrence >= 3 ) break; // cap per term
		}
		if ( 0 === $occurrence ) {
			echo "\n(term '{$term}' not found in this widget)\n";
		}
	}

	// extract all <img ...> tags with src near 'eyebrow' or 'eye-pencil' or 'eye_pencil' filenames
	if ( preg_match_all( '/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $html, $img_matches ) ) {
		echo "\n--- all <img> src values containing 'eyebrow' or 'eye' (case-insensitive) ---\n";
		foreach ( $img_matches[1] as $src ) {
			if ( false !== stripos( $src, 'eyebrow' ) || false !== stripos( $src, 'eye' ) ) {
				echo $src . "\n";
			}
		}
	}

	// also check for background-image: url(...) referencing eyebrow/eye
	if ( preg_match_all( '/background(?:-image)?\s*:\s*[^;]*url\([\'"]?([^\'")]+)[\'"]?\)/i', $html, $bg_matches ) ) {
		foreach ( $bg_matches[1] as $url ) {
			if ( false !== stripos( $url, 'eyebrow' ) || false !== stripos( $url, 'eye' ) ) {
				echo "background-image url: {$url}\n";
			}
		}
	}
}

echo "\nOK: read-only diagnostic complete, no writes performed\n";
