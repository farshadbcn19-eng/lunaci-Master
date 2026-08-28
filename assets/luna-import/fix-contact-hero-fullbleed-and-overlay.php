<?php
/**
 * Guarded fix: two problems reported on the Contact page hero after the
 * banner image was set:
 *
 * 1. "Shrunk box with black bars on both sides" - the HTML widget sits
 *    directly inside an Elementor `container` (id fa1bfa7) with no
 *    full-width override, so it renders inside Elementor's default boxed
 *    max-width, leaving the page's own background visible on either side.
 *    Same root cause as the earlier About Us full-bleed fix. Fixed here via
 *    the standard CSS full-bleed breakout technique applied directly to
 *    .contact-hero (width:100vw + relative/left/margin offset), which does
 *    not depend on touching Elementor's own core classes.
 *
 * 2. "Too faded / washed out" banner - the overlay gradient on .contact-hero
 *    was linear-gradient(to bottom, rgba(15,15,16,.7) 0%, rgba(15,15,16,1) 100%),
 *    which darkens the photo heavily even at the very top. Lightened to
 *    .35 / .75 so the banner photo is actually visible while keeping the
 *    hero text legible.
 *
 * Applied to both EN (post 60) and ES (post 770) Contact pages, each with
 * its own independent copy of this HTML widget.
 *
 * Also proactively deletes _elementor_element_cache for both pages after
 * the update_post_meta() write (raw metadata writes do not go through
 * Elementor's own save hooks that would otherwise invalidate that
 * render-cache snapshot - root-caused earlier this session).
 */

global $wpdb;

$old_hero_open = ".contact-hero {\n  padding-top: 80px;\n  min-height: 52vh;\n  display: flex; align-items: center;\n  background:\n";
$new_hero_open = ".contact-hero {\n  padding-top: 80px;\n  min-height: 52vh;\n  display: flex; align-items: center;\n  width: 100vw;\n  position: relative;\n  left: 50%;\n  right: 50%;\n  margin-left: -50vw;\n  margin-right: -50vw;\n  background:\n";

$old_gradient = "linear-gradient(to bottom, rgba(15,15,16,.7) 0%, rgba(15,15,16,1) 100%),";
$new_gradient = "linear-gradient(to bottom, rgba(15,15,16,.35) 0%, rgba(15,15,16,.75) 100%),";

$pages = array(
	60  => 'EN Contact',
	770 => 'ES Contacto',
);

function lunaci_ch_find_html_widget( $node, $fragment, &$found_path ) {
	if ( $found_path ) return;
	if ( is_array( $node ) ) {
		if ( isset( $node['widgetType'] ) && 'html' === $node['widgetType'] && isset( $node['settings']['html'] ) ) {
			if ( false !== strpos( $node['settings']['html'], $fragment ) ) {
				$found_path = $node['id'];
				return;
			}
		}
		foreach ( $node as $child ) {
			lunaci_ch_find_html_widget( $child, $fragment, $found_path );
			if ( $found_path ) return;
		}
	}
}

function lunaci_ch_set_widget_html( &$node, $target_id, $new_html ) {
	if ( is_array( $node ) ) {
		if ( isset( $node['id'] ) && $node['id'] === $target_id && isset( $node['settings']['html'] ) ) {
			$node['settings']['html'] = $new_html;
			return true;
		}
		foreach ( $node as $key => &$child ) {
			if ( lunaci_ch_set_widget_html( $child, $target_id, $new_html ) ) {
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
	lunaci_ch_find_html_widget( $decoded, $old_hero_open, $widget_id );
	if ( null === $widget_id ) {
		echo "ABORT: could not find HTML widget containing the old .contact-hero open block on page {$page_id}\n";
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

	$open_old_count = substr_count( $widget_html, $old_hero_open );
	$open_new_count = substr_count( $widget_html, $new_hero_open );
	$grad_old_count = substr_count( $widget_html, $old_gradient );
	$grad_new_count = substr_count( $widget_html, $new_gradient );
	echo "open_old_count={$open_old_count} open_new_count={$open_new_count} grad_old_count={$grad_old_count} grad_new_count={$grad_new_count}\n";

	if ( 1 !== $open_old_count || 0 !== $open_new_count || 1 !== $grad_old_count || 0 !== $grad_new_count ) {
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

	$new_html = str_replace( $old_hero_open, $new_hero_open, $widget_html );
	$new_html = str_replace( $old_gradient, $new_gradient, $new_html );

	if ( 1 !== substr_count( $new_html, $new_hero_open ) || false !== strpos( $new_html, $old_hero_open )
		|| 1 !== substr_count( $new_html, $new_gradient ) || false !== strpos( $new_html, $old_gradient ) ) {
		echo "ABORT: in-memory replacement verification failed for page {$page_id}\n";
		$overall_success = false;
		continue;
	}

	$decoded_fresh = json_decode( $fresh_raw, true );
	$updated_ok    = lunaci_ch_set_widget_html( $decoded_fresh, $widget_id, $new_html );
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

	$has_new_open = null !== $verify_html && false !== strpos( $verify_html, $new_hero_open );
	$has_old_open = null !== $verify_html && false !== strpos( $verify_html, $old_hero_open );
	$has_new_grad = null !== $verify_html && false !== strpos( $verify_html, $new_gradient );
	$has_old_grad = null !== $verify_html && false !== strpos( $verify_html, $old_gradient );
	echo "new open present: " . ( $has_new_open ? 'yes' : 'no' ) . "   old open gone: " . ( ! $has_old_open ? 'yes' : 'no' ) . "\n";
	echo "new gradient present: " . ( $has_new_grad ? 'yes' : 'no' ) . "   old gradient gone: " . ( ! $has_old_grad ? 'yes' : 'no' ) . "\n";

	$element_cache_gone = '' === get_post_meta( $page_id, '_elementor_element_cache', true );
	echo "element cache confirmed gone: " . ( $element_cache_gone ? 'yes' : 'no' ) . "\n";

	if ( ! $has_new_open || $has_old_open || ! $has_new_grad || $has_old_grad || ! $element_cache_gone ) {
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
