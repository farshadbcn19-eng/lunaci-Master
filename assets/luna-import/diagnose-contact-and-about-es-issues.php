<?php
/**
 * Read-only diagnostic for two client-reported issues:
 *
 * 1. Contact page hero banner is shorter than the viewport, leaving an
 *    empty gap at the top of the page. Dump the .contact-hero CSS block
 *    and the markup immediately surrounding/preceding it (EN post 60),
 *    plus whether the theme's own page-title/header markup is still
 *    present and taking up space above the custom hero.
 *
 * 2. About Us page in Spanish has a white border/margin around the page.
 *    Same bug class as the previously-fixed Products ES container issue
 *    (PR #174 style): a full-bleed / boxed-width fix scoped to the EN
 *    post's auto-generated Elementor element ID does not carry over to
 *    the ES post, which has its own different auto-generated ID. Dump
 *    the container inventory for both About Us EN and ES posts.
 *
 * No writes are performed.
 */

global $wpdb;

echo "=====================================================================\n";
echo "PART 1: Contact page (EN post 60) - hero structure and what precedes it\n";
echo "=====================================================================\n";

$contact_id = 60;
$raw = get_post_meta( $contact_id, '_elementor_data', true );
if ( ! $raw ) {
	echo "ERROR: no _elementor_data for post {$contact_id}\n";
} else {
	$decoded = json_decode( $raw, true );
	$widget_html = null;
	$finder = function ( $node ) use ( &$finder, &$widget_html ) {
		if ( $widget_html ) {
			return;
		}
		if ( is_array( $node ) ) {
			if ( isset( $node['widgetType'] ) && 'html' === $node['widgetType'] && isset( $node['settings']['html'] ) ) {
				if ( false !== strpos( $node['settings']['html'], '.contact-hero' ) ) {
					$widget_html = $node['settings']['html'];
					return;
				}
			}
			foreach ( $node as $child ) {
				$finder( $child );
				if ( $widget_html ) {
					return;
				}
			}
		}
	};
	$finder( $decoded );

	if ( $widget_html ) {
		echo "widget html total length: " . strlen( $widget_html ) . "\n\n";
		if ( preg_match( '/\.contact-hero\s*\{([^}]*)\}/s', $widget_html, $m ) ) {
			echo "--- .contact-hero rule body ---\n" . $m[1] . "\n--- end rule ---\n\n";
		}
		// dump everything from the start of the widget html up to the contact-hero div opening tag
		$pos = strpos( $widget_html, 'contact-hero' );
		if ( false !== $pos ) {
			$before = substr( $widget_html, 0, $pos );
			// strip the <style>...</style> block from the "before" excerpt so we see markup, not CSS
			$before_no_style = preg_replace( '/<style[^>]*>.*?<\/style>/s', '[STYLE BLOCK OMITTED]', $before );
			echo "--- markup BEFORE 'contact-hero' first occurrence (style block omitted) ---\n";
			echo substr( $before_no_style, -1500 ) . "\n";
			echo "--- end markup ---\n\n";
		}
	} else {
		echo "no widget found containing '.contact-hero' on post {$contact_id}\n";
	}
}

echo "\n=====================================================================\n";
echo "PART 2: Live HTTP fetch of Contact page - check for theme header/title remnants\n";
echo "=====================================================================\n";
$contact_url = get_permalink( $contact_id );
echo "Contact URL: {$contact_url}\n";
$response = wp_remote_get( $contact_url, array( 'timeout' => 15, 'headers' => array( 'Cache-Control' => 'no-cache' ) ) );
if ( is_wp_error( $response ) ) {
	echo "ERROR: " . $response->get_error_message() . "\n";
} else {
	$body = wp_remote_retrieve_body( $response );
	echo "HTTP status: " . wp_remote_retrieve_response_code( $response ) . "\n";
	echo "body length: " . strlen( $body ) . "\n";
	foreach ( array( 'entry-header', 'page-title', 'elementor-location-header', 'site-header' ) as $marker ) {
		echo "  contains \"{$marker}\": " . ( false !== stripos( $body, $marker ) ? 'yes' : 'no' ) . "\n";
	}
	// grab everything between <body...> and the first occurrence of 'contact-hero' in the raw HTML
	$body_tag_pos = stripos( $body, '<body' );
	$hero_pos     = stripos( $body, 'contact-hero' );
	if ( false !== $body_tag_pos && false !== $hero_pos && $hero_pos > $body_tag_pos ) {
		echo "\n--- raw HTML from <body> to first 'contact-hero' occurrence (may be long) ---\n";
		echo substr( $body, $body_tag_pos, min( 3000, $hero_pos - $body_tag_pos ) ) . "\n";
		echo "--- end raw HTML excerpt ---\n";
	}
}

echo "\n=====================================================================\n";
echo "PART 3: About Us EN (post 59) vs ES - find ES post id and compare containers\n";
echo "=====================================================================\n";

$about_en_id = 59;
$trans = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT t.trid, t.element_id, t.language_code
		 FROM {$wpdb->prefix}icl_translations t
		 WHERE t.trid = (SELECT trid FROM {$wpdb->prefix}icl_translations WHERE element_id = %d AND element_type = 'post_page')",
		$about_en_id
	),
	ARRAY_A
);
$about_es_id = null;
foreach ( $trans as $t ) {
	$p = get_post( $t['element_id'] );
	echo "trid={$t['trid']} element_id={$t['element_id']} lang={$t['language_code']} title=" . ( $p ? $p->post_title : 'N/A' ) . " status=" . ( $p ? $p->post_status : 'N/A' ) . "\n";
	if ( 'es' === $t['language_code'] ) {
		$about_es_id = (int) $t['element_id'];
	}
}

if ( ! $about_es_id ) {
	echo "\nERROR: could not resolve About Us ES post id via icl_translations\n";
} else {
	echo "\nResolved About Us ES post id: {$about_es_id}\n\n";

	function lunaci_walk_containers_2( $node, $depth, &$out ) {
		if ( ! is_array( $node ) ) {
			return;
		}
		if ( isset( $node['id'], $node['elType'] ) && in_array( $node['elType'], array( 'container', 'section' ), true ) ) {
			$settings = isset( $node['settings'] ) && is_array( $node['settings'] ) ? $node['settings'] : array();
			$out[] = array(
				'depth'         => $depth,
				'id'            => $node['id'],
				'elType'        => $node['elType'],
				'content_width' => $settings['content_width'] ?? '(not set)',
				'layout'        => $settings['layout'] ?? '(not set)',
			);
		}
		if ( isset( $node['elements'] ) && is_array( $node['elements'] ) ) {
			foreach ( $node['elements'] as $child ) {
				lunaci_walk_containers_2( $child, $depth + 1, $out );
			}
		}
	}

	foreach ( array( 'EN' => $about_en_id, 'ES' => $about_es_id ) as $label => $post_id ) {
		echo "--- {$label} post {$post_id}: top-level container inventory ---\n";
		$raw2 = get_post_meta( $post_id, '_elementor_data', true );
		if ( ! $raw2 ) {
			echo "ERROR: no _elementor_data for post {$post_id}\n\n";
			continue;
		}
		$decoded2 = json_decode( $raw2, true );
		if ( JSON_ERROR_NONE !== json_last_error() ) {
			echo "ERROR: json_decode failed\n\n";
			continue;
		}
		$containers = array();
		foreach ( $decoded2 as $top ) {
			lunaci_walk_containers_2( $top, 0, $containers );
		}
		foreach ( array_slice( $containers, 0, 6 ) as $c ) {
			$indent = str_repeat( '  ', $c['depth'] );
			echo "{$indent}id={$c['id']} elType={$c['elType']} content_width={$c['content_width']} layout={$c['layout']}\n";
		}
		echo "\n";
	}

	echo "--- Live HTTP fetch of both About Us URLs, checking for full-bleed marker classes ---\n";
	foreach ( array( 'EN' => $about_en_id, 'ES' => $about_es_id ) as $label => $post_id ) {
		$url = get_permalink( $post_id );
		echo "\n{$label} URL: {$url}\n";
		$resp = wp_remote_get( $url, array( 'timeout' => 15, 'headers' => array( 'Cache-Control' => 'no-cache' ) ) );
		if ( is_wp_error( $resp ) ) {
			echo "ERROR: " . $resp->get_error_message() . "\n";
			continue;
		}
		$body2 = wp_remote_retrieve_body( $resp );
		echo "HTTP status: " . wp_remote_retrieve_response_code( $resp ) . "\n";
		if ( preg_match( '/<html[^>]*lang=["\']?([a-zA-Z-]+)/i', $body2, $m2 ) ) {
			echo "html lang: {$m2[1]}\n";
		}
		if ( preg_match( '/class="[^"]*elementor-\d+[^"]*"[^>]*data-id="[^"]*"[^>]*data-element_type="container"/i', $body2, $m3 ) ) {
			echo "first top-level container tag: " . substr( $m3[0], 0, 300 ) . "\n";
		}
		// look for common full-bleed marker patterns already used elsewhere on the site
		foreach ( array( 'e-con-boxed', '100vw', 'max-width:none', 'full-bleed' ) as $marker ) {
			echo "  body contains \"{$marker}\": " . ( false !== stripos( $body2, $marker ) ? 'yes' : 'no' ) . "\n";
		}
	}
}

echo "\nOK: read-only diagnostic complete, no writes performed\n";
