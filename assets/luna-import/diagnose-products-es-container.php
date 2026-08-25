<?php
/**
 * Read-only diagnostic: the user reports that after switching to Spanish,
 * the Products page (ES post 771) breaks out of its frame with a white
 * margin all around - visually the same symptom as the original EN
 * "boxed container" bug fixed in PR #174, which was scoped by
 * .elementor-element-0329089 (an Elementor auto-generated per-instance
 * element ID unique to EN post 61). If post 771's equivalent top-level
 * container has a DIFFERENT auto-generated ID, that CSS override would
 * never match on the ES page - explaining exactly this symptom.
 *
 * This dumps every container element in post 771's _elementor_data with
 * its id and content_width/boxed setting, to find the real ID to target.
 * Also checks the _elementor_css postmeta cache status for both pages
 * (PR #174's fix only cleared it for post 61, not 771).
 */

global $wpdb;

function lunaci_walk_containers( $node, $depth, &$out ) {
	if ( ! is_array( $node ) ) {
		return;
	}
	if ( isset( $node['id'], $node['elType'] ) && 'container' === $node['elType'] ) {
		$settings = isset( $node['settings'] ) && is_array( $node['settings'] ) ? $node['settings'] : array();
		$out[] = array(
			'depth'         => $depth,
			'id'            => $node['id'],
			'content_width' => $settings['content_width'] ?? '(not set)',
			'boxed_width'   => $settings['boxed_width']['size'] ?? '',
		);
	}
	if ( isset( $node['elements'] ) && is_array( $node['elements'] ) ) {
		foreach ( $node['elements'] as $child ) {
			lunaci_walk_containers( $child, $depth + 1, $out );
		}
	}
}

foreach ( array( 'EN' => 61, 'ES' => 771 ) as $label => $post_id ) {
	echo "=====================================================================\n";
	echo "{$label} post {$post_id}: container inventory\n";
	echo "=====================================================================\n";

	$raw = $wpdb->get_var(
		$wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_elementor_data'", $post_id )
	);
	if ( null === $raw ) {
		echo "ERROR: no _elementor_data for post {$post_id}\n\n";
		continue;
	}
	$decoded = json_decode( $raw, true );
	if ( JSON_ERROR_NONE !== json_last_error() ) {
		echo "ERROR: json_decode failed: " . json_last_error_msg() . "\n\n";
		continue;
	}

	$containers = array();
	foreach ( $decoded as $top ) {
		lunaci_walk_containers( $top, 0, $containers );
	}

	foreach ( $containers as $c ) {
		$indent = str_repeat( '  ', $c['depth'] );
		echo "{$indent}id={$c['id']}  content_width={$c['content_width']}  boxed_width={$c['boxed_width']}\n";
	}

	$css_cache = get_post_meta( $post_id, '_elementor_css', true );
	echo "\n_elementor_css postmeta present: " . ( empty( $css_cache ) ? 'no/empty' : 'YES (cached, len=' . ( is_array( $css_cache ) ? strlen( wp_json_encode( $css_cache ) ) : strlen( (string) $css_cache ) ) . ')' ) . "\n\n";
}

echo "=====================================================================\n";
echo "Cross-check: does WPCode snippet 483's CSS reference EN's ID (0329089)?\n";
echo "=====================================================================\n";
$snippet_content = get_post_field( 'post_content', 483 );
echo "snippet 483 contains 'elementor-element-0329089': " . ( false !== strpos( $snippet_content, 'elementor-element-0329089' ) ? 'yes' : 'no' ) . "\n";

echo "\nOK: read-only diagnostic complete, no writes performed\n";
