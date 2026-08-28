<?php
/**
 * Guarded fix: replace the Contact page's hero banner (.contact-hero
 * background) stock Unsplash placeholder with the user-supplied banner
 * image, already uploaded to the Media Library by the workflow step
 * before this script runs (attachment ID passed as the first CLI arg).
 *
 * Applied to both EN (post 60) and ES (post 770) Contact pages, since
 * each carries its own independent copy of this HTML widget (same
 * pattern confirmed for every other custom-page fix this session).
 *
 * Only the .contact-hero background image is touched - two other
 * occurrences of a DIFFERENT Unsplash photo elsewhere on the page
 * (a secondary/atelier image, not the banner) are left untouched, since
 * the user specifically asked for the banner.
 *
 * Also proactively deletes _elementor_element_cache for both pages
 * after the update_post_meta() write, since a raw metadata write does
 * not go through Elementor's own save hooks that would normally
 * invalidate that separate rendering-cache snapshot (root-caused
 * earlier this session on the About Us page - same mechanism applies
 * to any page).
 */

global $wpdb, $args;

if ( empty( $args[0] ) || ! is_numeric( $args[0] ) ) {
	echo "ABORT: expected attachment ID as first argument\n";
	exit( 1 );
}

$attachment_id = (int) $args[0];
$attachment_url = wp_get_attachment_url( $attachment_id );
if ( ! $attachment_url ) {
	echo "ABORT: could not resolve URL for attachment ID {$attachment_id}\n";
	exit( 1 );
}

$new_banner_url = $attachment_url . '?v=1';
echo "attachment_id={$attachment_id}\n";
echo "new_banner_url={$new_banner_url}\n";

$old_fragment = "url('https://images.unsplash.com/photo-1555396273-367ea4eb4db5?w=1800&q=80')";
$new_fragment = "url('{$new_banner_url}')";

$pages = array(
	60  => 'EN Contact',
	770 => 'ES Contacto',
);

function lunaci_contact_find_html_widget( $node, $fragment, &$found_path ) {
	if ( $found_path ) return;
	if ( is_array( $node ) ) {
		if ( isset( $node['widgetType'] ) && 'html' === $node['widgetType'] && isset( $node['settings']['html'] ) ) {
			if ( false !== strpos( $node['settings']['html'], $fragment ) ) {
				$found_path = $node['id'];
				return;
			}
		}
		foreach ( $node as $child ) {
			lunaci_contact_find_html_widget( $child, $fragment, $found_path );
			if ( $found_path ) return;
		}
	}
}

function lunaci_contact_set_widget_html( &$node, $target_id, $new_html ) {
	if ( is_array( $node ) ) {
		if ( isset( $node['id'] ) && $node['id'] === $target_id && isset( $node['settings']['html'] ) ) {
			$node['settings']['html'] = $new_html;
			return true;
		}
		foreach ( $node as $key => &$child ) {
			if ( lunaci_contact_set_widget_html( $child, $target_id, $new_html ) ) {
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
	lunaci_contact_find_html_widget( $decoded, $old_fragment, $widget_id );
	if ( null === $widget_id ) {
		echo "ABORT: could not find HTML widget containing the old banner URL on page {$page_id}\n";
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

	$old_count = substr_count( $widget_html, $old_fragment );
	$new_count = substr_count( $widget_html, $new_fragment );
	echo "old_fragment_count={$old_count} new_fragment_count={$new_count}\n";

	if ( 1 !== $old_count || 0 !== $new_count ) {
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

	$new_html = str_replace( $old_fragment, $new_fragment, $widget_html );
	if ( 1 !== substr_count( $new_html, $new_fragment ) || false !== strpos( $new_html, $old_fragment ) ) {
		echo "ABORT: in-memory replacement verification failed for page {$page_id}\n";
		$overall_success = false;
		continue;
	}

	$decoded_fresh = json_decode( $fresh_raw, true );
	$updated_ok    = lunaci_contact_set_widget_html( $decoded_fresh, $widget_id, $new_html );
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

	// proactively clear Elementor's own render-cache snapshot, since
	// update_post_meta() does not go through Elementor's save hooks
	$deleted_element_cache = delete_post_meta( $page_id, '_elementor_element_cache' );
	echo "delete_post_meta(_elementor_element_cache) returned: " . var_export( $deleted_element_cache, true ) . "\n";

	echo "OK: written and caches cleared\n";

	echo "--- STEP C: VERIFY ---\n";
	$verify_raw = $wpdb->get_var(
		$wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_elementor_data'", $page_id )
	);
	$has_new = false !== strpos( $verify_raw, addslashes( $new_fragment ) ) || false !== strpos( $verify_raw, $new_fragment );
	$has_old = false !== strpos( $verify_raw, $old_fragment ) || false !== strpos( $verify_raw, addslashes( $old_fragment ) );
	echo "new banner url present: " . ( $has_new ? 'yes' : 'no' ) . "   old url gone: " . ( ! $has_old ? 'yes' : 'no' ) . "\n";

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
