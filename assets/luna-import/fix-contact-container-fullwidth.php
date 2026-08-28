<?php
/**
 * Guarded fix: after the .contact-hero full-bleed breakout, the user
 * reported everything BELOW the hero (nav, contact form, map, FAQ,
 * newsletter, footer) remained at the old boxed width with black bars,
 * looking smaller than the now full-bleed hero.
 *
 * Root cause confirmed via live markup inspection: the HTML widget itself
 * sits inside an Elementor `container` element carrying Elementor's own
 * "e-con-boxed" class (visible in the rendered DOM as e.g.
 * `elementor-element-fa1bfa7 e-flex e-con-boxed e-con e-parent`), which
 * applies Elementor's default boxed max-width to everything inside it.
 * The .contact-hero breakout (width:100vw + negative margins) escapes this
 * on its own, but nothing else inside the widget does.
 *
 * Fix: add a targeted CSS override, scoped to this specific container's
 * unique element id (auto-detected per page rather than hardcoded, in case
 * EN/ES differ), forcing max-width:100% on both the container and its
 * .e-con-inner child - inserted right after the widget's own <style> tag.
 *
 * Applied to both EN (post 60) and ES (post 770) Contact pages. Also
 * proactively deletes _elementor_element_cache for both pages after the
 * update_post_meta() write (raw metadata writes do not go through
 * Elementor's own save hooks that would otherwise invalidate that
 * render-cache snapshot).
 */

global $wpdb;

$hero_fragment = '.contact-hero {'; // used to locate the right widget
$style_open    = '<style>';

$pages = array(
	60  => 'EN Contact',
	770 => 'ES Contacto',
);

function lunaci_cf_find_widget_and_parent( $node, $fragment, &$widget_id, &$parent_container_id, $current_parent_container_id = null ) {
	if ( $widget_id ) return;
	if ( is_array( $node ) ) {
		$next_parent_container_id = $current_parent_container_id;
		if ( isset( $node['elType'] ) && 'container' === $node['elType'] && isset( $node['id'] ) ) {
			$next_parent_container_id = $node['id'];
		}
		if ( isset( $node['widgetType'] ) && 'html' === $node['widgetType'] && isset( $node['settings']['html'] ) ) {
			if ( false !== strpos( $node['settings']['html'], $fragment ) ) {
				$widget_id            = $node['id'];
				$parent_container_id  = $current_parent_container_id;
				return;
			}
		}
		foreach ( $node as $child ) {
			lunaci_cf_find_widget_and_parent( $child, $fragment, $widget_id, $parent_container_id, $next_parent_container_id );
			if ( $widget_id ) return;
		}
	}
}

function lunaci_cf_set_widget_html( &$node, $target_id, $new_html ) {
	if ( is_array( $node ) ) {
		if ( isset( $node['id'] ) && $node['id'] === $target_id && isset( $node['settings']['html'] ) ) {
			$node['settings']['html'] = $new_html;
			return true;
		}
		foreach ( $node as $key => &$child ) {
			if ( lunaci_cf_set_widget_html( $child, $target_id, $new_html ) ) {
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

	$widget_id           = null;
	$parent_container_id = null;
	lunaci_cf_find_widget_and_parent( $decoded, $hero_fragment, $widget_id, $parent_container_id );

	if ( null === $widget_id || null === $parent_container_id ) {
		echo "ABORT: could not find widget/parent container for page {$page_id} (widget_id=" . var_export( $widget_id, true ) . " parent=" . var_export( $parent_container_id, true ) . ")\n";
		$overall_success = false;
		continue;
	}
	echo "found widget id: {$widget_id}, parent container id: {$parent_container_id}\n";

	$new_css_rule = ".elementor-element-{$parent_container_id}.e-con-boxed,\n  .elementor-element-{$parent_container_id}.e-con-boxed > .e-con-inner {\n    max-width: 100% !important;\n  }\n  ";
	$new_style_open = $style_open . "\n  " . $new_css_rule;

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

	$open_old_count = substr_count( $widget_html, $style_open );
	$open_new_count = substr_count( $widget_html, $new_style_open );
	echo "open_old_count={$open_old_count} open_new_count={$open_new_count}\n";

	if ( 1 !== $open_old_count || 0 !== $open_new_count ) {
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

	$new_html = str_replace( $style_open, $new_style_open, $widget_html );

	if ( 1 !== substr_count( $new_html, $new_style_open ) ) {
		echo "ABORT: in-memory replacement verification failed for page {$page_id}\n";
		$overall_success = false;
		continue;
	}

	$decoded_fresh = json_decode( $fresh_raw, true );
	$updated_ok    = lunaci_cf_set_widget_html( $decoded_fresh, $widget_id, $new_html );
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

	$has_new = null !== $verify_html && false !== strpos( $verify_html, $new_style_open );
	echo "new css override present: " . ( $has_new ? 'yes' : 'no' ) . "\n";

	$element_cache_gone = '' === get_post_meta( $page_id, '_elementor_element_cache', true );
	echo "element cache confirmed gone: " . ( $element_cache_gone ? 'yes' : 'no' ) . "\n";

	if ( ! $has_new || ! $element_cache_gone ) {
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
