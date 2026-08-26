<?php
/**
 * Guarded fix: two issues on the About Us page, both confirmed via live
 * browser diagnostics (real desktop-viewport screenshots + computed-style
 * inspection):
 *
 * (a) Desktop full-bleed width: the whole custom-built page lives inside
 *     a single HTML widget wrapped in `#lna`, which every section's CSS
 *     assumes spans the FULL viewport width (percentage-based side
 *     padding throughout: .lna-nav, .lna-hero__content, .lna-phil,
 *     .lna-vals__header, .lna-foot, etc). But `#lna` itself only declares
 *     `width:100%`, which resolves against its actual parent - an
 *     Elementor "boxed" container capped at 1140px. On wide viewports
 *     this leaves ~390px of plain black page background on each side,
 *     producing a hard visible seam right through the hero photo (the
 *     "crop is obviously cut, right side goes black" the user reported).
 *     Fixed with the standard full-bleed breakout pattern (100vw + 50%
 *     offset + negative margins), which lets `#lna` (and therefore every
 *     section inside it) span the true viewport width regardless of its
 *     boxed ancestor - matching what the page's own CSS was designed for.
 *     `overflow-x:hidden` added to html,body as a safety net against the
 *     breakout introducing a stray horizontal scrollbar.
 *
 * (b) Hero text position: `.lna-hero__content` has only `4vh` bottom
 *     padding while the hero itself is a full `100dvh` tall flex
 *     container aligned to the bottom (`align-items:flex-end`), so the
 *     "Our Story" heading sits very close to the bottom edge on desktop.
 *     Increased to `12vh` to move the text block up.
 *
 * Applied to both EN (59) and ES (680) About Us pages, since each page
 * carries its own independent copy of this widget's HTML/CSS (confirmed
 * pattern from the earlier 100vh and object-position fixes on this same
 * hero).
 */

global $wpdb;

$pages = array(
	59  => 'EN About Us',
	680 => 'ES About Us (Sobre Nosotros)',
);

$replacements = array(
	array(
		'old' => "html,body{margin:0;padding:0;background:#0B0B0B;}",
		'new' => "html,body{margin:0;padding:0;background:#0B0B0B;overflow-x:hidden;}",
	),
	array(
		'old' => "#lna{width:100%;background:#0B0B0B;overflow:hidden;}",
		'new' => "#lna{width:100vw;position:relative;left:50%;right:50%;margin-left:-50vw;margin-right:-50vw;background:#0B0B0B;overflow:hidden;}",
	),
	array(
		'old' => ".lna-hero__content{position:relative;z-index:2;padding:0 5% 4vh;max-width:680px;}",
		'new' => ".lna-hero__content{position:relative;z-index:2;padding:0 5% 12vh;max-width:680px;}",
	),
);

function lunaci_hero_find_html_widget( $node, $fragment, &$found_path ) {
	if ( $found_path ) return;
	if ( is_array( $node ) ) {
		if ( isset( $node['widgetType'] ) && 'html' === $node['widgetType'] && isset( $node['settings']['html'] ) ) {
			if ( false !== strpos( $node['settings']['html'], $fragment ) ) {
				$found_path = $node['id'];
				return;
			}
		}
		foreach ( $node as $child ) {
			lunaci_hero_find_html_widget( $child, $fragment, $found_path );
			if ( $found_path ) return;
		}
	}
}

function lunaci_hero_set_widget_html( &$node, $target_id, $new_html ) {
	if ( is_array( $node ) ) {
		if ( isset( $node['id'] ) && $node['id'] === $target_id && isset( $node['settings']['html'] ) ) {
			$node['settings']['html'] = $new_html;
			return true;
		}
		foreach ( $node as $key => &$child ) {
			if ( lunaci_hero_set_widget_html( $child, $target_id, $new_html ) ) {
				return true;
			}
		}
	}
	return false;
}

$overall_success = true;

foreach ( $pages as $page_id => $label ) {
	echo "=====================================================================\n";
	echo "PAGE {$page_id} ({$label})\n";
	echo "=====================================================================\n";

	echo "--- STEP A: PREPARE ---\n";
	$raw = $wpdb->get_var(
		$wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_elementor_data'", $page_id )
	);
	if ( null === $raw ) {
		echo "ABORT: _elementor_data not found for page {$page_id}\n\n";
		$overall_success = false;
		continue;
	}

	$decoded = json_decode( $raw, true );
	if ( JSON_ERROR_NONE !== json_last_error() ) {
		echo "ABORT: could not decode _elementor_data JSON for page {$page_id}\n\n";
		$overall_success = false;
		continue;
	}

	$widget_id = null;
	lunaci_hero_find_html_widget( $decoded, '#lna{width:100%', $widget_id );
	if ( null === $widget_id ) {
		echo "ABORT: could not find HTML widget containing '#lna{width:100%' on page {$page_id}\n\n";
		$overall_success = false;
		continue;
	}
	echo "found widget id: {$widget_id}\n";

	// locate the widget's current html to validate preconditions
	$widget_html = null;
	$finder = function ( $node ) use ( &$finder, $widget_id, &$widget_html ) {
		if ( is_array( $node ) ) {
			if ( isset( $node['id'] ) && $node['id'] === $widget_id && isset( $node['settings']['html'] ) ) {
				$widget_html = $node['settings']['html'];
				return;
			}
			foreach ( $node as $child ) {
				$finder( $child );
			}
		}
	};
	$finder( $decoded );

	if ( null === $widget_html ) {
		echo "ABORT: could not extract widget html for page {$page_id}\n\n";
		$overall_success = false;
		continue;
	}

	$preconditions_ok = true;
	foreach ( $replacements as $r ) {
		$old_count = substr_count( $widget_html, $r['old'] );
		$new_count = substr_count( $widget_html, $r['new'] );
		echo "  fragment old_count={$old_count} new_count={$new_count}: " . substr( $r['old'], 0, 60 ) . "...\n";
		if ( 1 !== $old_count || 0 !== $new_count ) {
			$preconditions_ok = false;
		}
	}
	if ( ! $preconditions_ok ) {
		echo "ABORT: preconditions not satisfied for page {$page_id} - refusing to proceed\n\n";
		$overall_success = false;
		continue;
	}
	echo "OK: preconditions satisfied for page {$page_id}\n";

	echo "--- STEP B: COMMIT ---\n";
	$fresh_raw = $wpdb->get_var(
		$wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_elementor_data'", $page_id )
	);
	if ( $fresh_raw !== $raw ) {
		echo "ABORT: content changed since STEP A (concurrent edit detected) for page {$page_id} - refusing to write\n\n";
		$overall_success = false;
		continue;
	}
	echo "PASS: race-condition guard confirms content unchanged\n";

	$new_html = $widget_html;
	foreach ( $replacements as $r ) {
		$new_html = str_replace( $r['old'], $r['new'], $new_html );
	}
	foreach ( $replacements as $r ) {
		if ( substr_count( $new_html, $r['new'] ) !== 1 || false !== strpos( $new_html, $r['old'] ) ) {
			echo "ABORT: in-memory replacement verification failed for page {$page_id}\n\n";
			$overall_success = false;
			continue 2;
		}
	}

	$decoded_fresh = json_decode( $fresh_raw, true );
	$updated_ok    = lunaci_hero_set_widget_html( $decoded_fresh, $widget_id, $new_html );
	if ( ! $updated_ok ) {
		echo "ABORT: failed to set new widget html in decoded tree for page {$page_id}\n\n";
		$overall_success = false;
		continue;
	}

	$new_raw = wp_json_encode( $decoded_fresh, JSON_UNESCAPED_SLASHES );
	if ( false === $new_raw ) {
		echo "ABORT: wp_json_encode failed for page {$page_id}\n\n";
		$overall_success = false;
		continue;
	}

	update_post_meta( $page_id, '_elementor_data', wp_slash( $new_raw ) );
	clean_post_cache( $page_id );
	wp_cache_flush();
	echo "OK: written and caches cleared\n";

	echo "--- STEP C: VERIFY ---\n";
	$verify_raw = $wpdb->get_var(
		$wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_elementor_data'", $page_id )
	);
	$verify_decoded = json_decode( $verify_raw, true );
	$verify_html    = null;
	$finder2 = function ( $node ) use ( &$finder2, $widget_id, &$verify_html ) {
		if ( is_array( $node ) ) {
			if ( isset( $node['id'] ) && $node['id'] === $widget_id && isset( $node['settings']['html'] ) ) {
				$verify_html = $node['settings']['html'];
				return;
			}
			foreach ( $node as $child ) {
				$finder2( $child );
			}
		}
	};
	$finder2( $verify_decoded );

	$page_ok = true;
	foreach ( $replacements as $r ) {
		$has_new = $verify_html && 1 === substr_count( $verify_html, $r['new'] );
		$has_old = $verify_html && false !== strpos( $verify_html, $r['old'] );
		echo "  old gone: " . ( ! $has_old ? 'yes' : 'no' ) . "   new present(x1): " . ( $has_new ? 'yes' : 'no' ) . "\n";
		if ( ! $has_new || $has_old ) {
			$page_ok = false;
		}
	}

	echo "PAGE {$page_id} RESULT: " . ( $page_ok ? 'SUCCESS' : 'FAILURE' ) . "\n\n";
	if ( ! $page_ok ) {
		$overall_success = false;
	}
}

if ( $overall_success ) {
	echo "FINAL RESULT: SUCCESS\n";
} else {
	echo "FINAL RESULT: FAILURE - see per-page results above\n";
	exit( 1 );
}
