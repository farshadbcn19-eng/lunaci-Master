<?php
/**
 * Fix: client reports the Contact page hero banner is shorter than the
 * viewport, leaving the top of the page feeling empty.
 *
 * A live layout diagnostic (getBoundingClientRect on the real DOM) ruled out
 * a double-offset bug: .contact-hero starts right where it should, at
 * y=138 on a 1000px-tall viewport, immediately after the 128px the body
 * reserves for the fixed global nav. Its height is exactly 521px, matching
 * the CSS's own `min-height: 52vh` (521/1000 = 52.1%). So the hero is
 * rendering exactly as designed - it was simply designed short (52% of the
 * viewport) compared to the Home hero (100vh), which reads as "empty" once
 * the darker contact-info section immediately continues below it in the
 * same near-black tone with no visual seam.
 *
 * Fix: raise `.contact-hero`'s `min-height` from 52vh to 100vh, matching
 * the Home hero's own height convention, so it fills the initial viewport
 * on both EN (post 60) and ES (post 770) - same two pages the original
 * full-bleed/overlay fix (fix-contact-hero-fullbleed-and-overlay.php)
 * touched, each with its own independent copy of this HTML widget.
 */

global $wpdb;

$old_min_height = 'min-height: 52vh;';
$new_min_height = 'min-height: 100vh;';

$pages = array(
	60  => 'EN Contact',
	770 => 'ES Contacto',
);

function lunaci_fch_find_html_widget( $node, $fragment, &$found_path ) {
	if ( $found_path ) return;
	if ( is_array( $node ) ) {
		if ( isset( $node['widgetType'] ) && 'html' === $node['widgetType'] && isset( $node['settings']['html'] ) ) {
			if ( false !== strpos( $node['settings']['html'], $fragment ) ) {
				$found_path = $node['id'];
				return;
			}
		}
		foreach ( $node as $child ) {
			lunaci_fch_find_html_widget( $child, $fragment, $found_path );
			if ( $found_path ) return;
		}
	}
}

function lunaci_fch_set_widget_html( &$node, $target_id, $new_html ) {
	if ( is_array( $node ) ) {
		if ( isset( $node['id'] ) && $node['id'] === $target_id && isset( $node['settings']['html'] ) ) {
			$node['settings']['html'] = $new_html;
			return true;
		}
		foreach ( $node as $key => &$child ) {
			if ( lunaci_fch_set_widget_html( $child, $target_id, $new_html ) ) {
				return true;
			}
		}
	}
	return false;
}

$overall_success = true;

foreach ( $pages as $page_id => $label ) {
	echo "\n=====================================================================\n";
	echo "PAGE {$page_id} ({$label})\n";
	echo "=====================================================================\n";

	echo "--- STEP A: PREPARE ---\n";
	$raw = $wpdb->get_var(
		$wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_elementor_data'", $page_id )
	);
	if ( null === $raw ) {
		echo "ABORT: _elementor_data not found for page {$page_id}\n";
		$overall_success = false;
		continue;
	}

	$decoded = json_decode( $raw, true );
	if ( JSON_ERROR_NONE !== json_last_error() ) {
		echo "ABORT: could not decode _elementor_data JSON for page {$page_id}\n";
		$overall_success = false;
		continue;
	}

	$widget_id = null;
	lunaci_fch_find_html_widget( $decoded, '.contact-hero', $widget_id );
	if ( null === $widget_id ) {
		echo "ABORT: could not find HTML widget containing '.contact-hero' on page {$page_id}\n";
		$overall_success = false;
		continue;
	}
	echo "found widget id: {$widget_id}\n";

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
		echo "ABORT: could not extract widget html for page {$page_id}\n";
		$overall_success = false;
		continue;
	}

	// Confirm the target rule sits inside the .contact-hero block specifically
	// (not some unrelated rule elsewhere) before touching it.
	$hero_block_ok = false;
	if ( preg_match( '/\.contact-hero\s*\{([^}]*)\}/s', $widget_html, $m ) ) {
		$hero_block_ok = ( false !== strpos( $m[1], $old_min_height ) );
	}
	$old_count = substr_count( $widget_html, $old_min_height );
	$new_count = substr_count( $widget_html, $new_min_height );
	echo "old_count={$old_count} new_count={$new_count} old_value_inside_.contact-hero_block={$hero_block_ok}\n";

	if ( 1 !== $old_count || 0 !== $new_count || ! $hero_block_ok ) {
		echo "ABORT: preconditions not satisfied for page {$page_id}\n";
		$overall_success = false;
		continue;
	}
	echo "OK: preconditions satisfied\n";

	echo "--- STEP B: COMMIT ---\n";
	$fresh_raw = $wpdb->get_var(
		$wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_elementor_data'", $page_id )
	);
	if ( $fresh_raw !== $raw ) {
		echo "ABORT: content changed since STEP A (concurrent edit) for page {$page_id}\n";
		$overall_success = false;
		continue;
	}

	$new_html = str_replace( $old_min_height, $new_min_height, $widget_html, $replace_count );
	echo "replacements made: {$replace_count}\n";

	if ( 1 !== $replace_count || 1 !== substr_count( $new_html, $new_min_height ) || false !== strpos( $new_html, $old_min_height ) ) {
		echo "ABORT: in-memory replacement verification failed for page {$page_id}\n";
		$overall_success = false;
		continue;
	}

	$decoded_fresh = json_decode( $fresh_raw, true );
	$updated_ok    = lunaci_fch_set_widget_html( $decoded_fresh, $widget_id, $new_html );
	if ( ! $updated_ok ) {
		echo "ABORT: failed to set new widget html for page {$page_id}\n";
		$overall_success = false;
		continue;
	}

	$new_raw = wp_json_encode( $decoded_fresh, JSON_UNESCAPED_SLASHES );
	if ( false === $new_raw ) {
		echo "ABORT: wp_json_encode failed for page {$page_id}\n";
		$overall_success = false;
		continue;
	}

	update_post_meta( $page_id, '_elementor_data', wp_slash( $new_raw ) );
	clean_post_cache( $page_id );

	$deleted_element_cache = delete_post_meta( $page_id, '_elementor_element_cache' );
	echo "delete_post_meta(_elementor_element_cache) returned: " . var_export( $deleted_element_cache, true ) . "\n";

	echo "OK: written and caches cleared\n";

	echo "--- STEP C: VERIFY ---\n";
	$verify_raw = $wpdb->get_var(
		$wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_elementor_data'", $page_id )
	);
	$verify_decoded = json_decode( $verify_raw, true );
	$verify_html = null;
	$vfinder = function ( $node ) use ( &$vfinder, $widget_id, &$verify_html ) {
		if ( is_array( $node ) ) {
			if ( isset( $node['id'] ) && $node['id'] === $widget_id && isset( $node['settings']['html'] ) ) {
				$verify_html = $node['settings']['html'];
				return;
			}
			foreach ( $node as $child ) {
				$vfinder( $child );
			}
		}
	};
	$vfinder( $verify_decoded );

	$has_new = null !== $verify_html && false !== strpos( $verify_html, $new_min_height );
	$has_old = null !== $verify_html && false !== strpos( $verify_html, $old_min_height );
	echo "new min-height present: " . ( $has_new ? 'yes' : 'no' ) . "   old min-height gone: " . ( ! $has_old ? 'yes' : 'no' ) . "\n";

	$element_cache_gone = '' === get_post_meta( $page_id, '_elementor_element_cache', true );
	echo "element cache confirmed gone: " . ( $element_cache_gone ? 'yes' : 'no' ) . "\n";

	if ( ! $has_new || $has_old || ! $element_cache_gone ) {
		$overall_success = false;
	}
}

wp_cache_flush();

echo "\n=====================================================================\n";
if ( $overall_success ) {
	echo "FINAL RESULT: SUCCESS\n";
} else {
	echo "FINAL RESULT: FAILURE - see per-page results above\n";
	exit( 1 );
}
