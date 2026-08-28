<?php
/**
 * Guarded fix: user confirmed (with a fresh post-cache-purge screenshot)
 * that the desktop hero text is still bottom-anchored - glued to the
 * bottom-left corner, with the last line ("Born from the golden light
 * of...") partially cut off below the visible fold. The earlier fix only
 * added extra bottom padding (8vh -> 12vh via a WPCode !important
 * override), which nudges the block up slightly but doesn't solve the
 * real issue: `.lna-hero{display:flex;align-items:flex-end;}` always
 * bottom-anchors the content regardless of how tall it is, so on a
 * shorter/typical laptop viewport the multi-line heading + subtitle can
 * still overflow past the bottom edge.
 *
 * Real fix: vertically CENTER the hero content on desktop instead of
 * bottom-anchoring it, so it has equal breathing room top and bottom and
 * can never run off the bottom edge. Two independent places need
 * updating, both scoped to desktop only (min-width:1025px) so mobile
 * (already confirmed correct) is untouched:
 *
 * 1. _elementor_data (post 59 EN, 680 ES): `.lna-hero` currently has
 *    `align-items:flex-end` with no desktop override. Append a desktop
 *    media query switching it to `align-items:center`.
 *
 * 2. WPCode snippet post 319 (both its own post_content AND the
 *    wpcode_snippets option's mirrored copy, per the established
 *    two-location pattern from the earlier padding-override bug): the
 *    existing desktop override `padding:0 5% 12vh !important` adds an
 *    asymmetric bottom-only offset, which would fight true centering
 *    (biasing the content low). Change it to `padding:0 5% !important`
 *    (no extra vertical offset) so align-items:center produces genuine
 *    symmetric centering.
 */

global $wpdb;

echo "=====================================================================\n";
echo "PART 1: _elementor_data - desktop vertical centering for .lna-hero\n";
echo "=====================================================================\n";

$pages = array(
	59  => 'EN About Us',
	680 => 'ES About Us (Sobre Nosotros)',
);

$hero_old = '.lna-hero{position:relative;width:100%;height:100vh;height:100svh;height:100dvh;min-height:600px;overflow:hidden;background:#0B0B0B;display:flex;align-items:flex-end;}';
$hero_addition = '@media(min-width:1025px){.lna-hero{align-items:center;}}';
$hero_new = $hero_old . $hero_addition;

function lunaci_hero2_find_html_widget( $node, $fragment, &$found_path ) {
	if ( $found_path ) return;
	if ( is_array( $node ) ) {
		if ( isset( $node['widgetType'] ) && 'html' === $node['widgetType'] && isset( $node['settings']['html'] ) ) {
			if ( false !== strpos( $node['settings']['html'], $fragment ) ) {
				$found_path = $node['id'];
				return;
			}
		}
		foreach ( $node as $child ) {
			lunaci_hero2_find_html_widget( $child, $fragment, $found_path );
			if ( $found_path ) return;
		}
	}
}

function lunaci_hero2_set_widget_html( &$node, $target_id, $new_html ) {
	if ( is_array( $node ) ) {
		if ( isset( $node['id'] ) && $node['id'] === $target_id && isset( $node['settings']['html'] ) ) {
			$node['settings']['html'] = $new_html;
			return true;
		}
		foreach ( $node as $key => &$child ) {
			if ( lunaci_hero2_set_widget_html( $child, $target_id, $new_html ) ) {
				return true;
			}
		}
	}
	return false;
}

$part1_success = true;

foreach ( $pages as $page_id => $label ) {
	echo "--- page {$page_id} ({$label}) ---\n";

	$raw = $wpdb->get_var(
		$wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_elementor_data'", $page_id )
	);
	if ( null === $raw ) {
		echo "ABORT: _elementor_data not found for page {$page_id}\n";
		$part1_success = false;
		continue;
	}

	$decoded = json_decode( $raw, true );
	if ( JSON_ERROR_NONE !== json_last_error() ) {
		echo "ABORT: could not decode _elementor_data JSON for page {$page_id}\n";
		$part1_success = false;
		continue;
	}

	$widget_id = null;
	lunaci_hero2_find_html_widget( $decoded, '.lna-hero{position:relative', $widget_id );
	if ( null === $widget_id ) {
		echo "ABORT: could not find HTML widget for page {$page_id}\n";
		$part1_success = false;
		continue;
	}

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
		$part1_success = false;
		continue;
	}

	$old_count = substr_count( $widget_html, $hero_old );
	$already_has = false !== strpos( $widget_html, $hero_addition );
	echo "  old_count={$old_count} already_has_media_query=" . ( $already_has ? 'yes' : 'no' ) . "\n";

	if ( $already_has ) {
		echo "  OK: already applied, skipping\n";
		continue;
	}
	if ( 1 !== $old_count ) {
		echo "ABORT: expected exactly 1 occurrence of base .lna-hero rule for page {$page_id}, found {$old_count}\n";
		$part1_success = false;
		continue;
	}

	// race check
	$fresh_raw = $wpdb->get_var(
		$wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_elementor_data'", $page_id )
	);
	if ( $fresh_raw !== $raw ) {
		echo "ABORT: content changed since read for page {$page_id} - refusing to write\n";
		$part1_success = false;
		continue;
	}

	$new_html = str_replace( $hero_old, $hero_new, $widget_html );
	if ( 1 !== substr_count( $new_html, $hero_new ) ) {
		echo "ABORT: in-memory replacement verification failed for page {$page_id}\n";
		$part1_success = false;
		continue;
	}

	$decoded_fresh = json_decode( $fresh_raw, true );
	$updated_ok    = lunaci_hero2_set_widget_html( $decoded_fresh, $widget_id, $new_html );
	if ( ! $updated_ok ) {
		echo "ABORT: failed to set new widget html for page {$page_id}\n";
		$part1_success = false;
		continue;
	}

	$new_raw = wp_json_encode( $decoded_fresh, JSON_UNESCAPED_SLASHES );
	if ( false === $new_raw ) {
		echo "ABORT: wp_json_encode failed for page {$page_id}\n";
		$part1_success = false;
		continue;
	}

	update_post_meta( $page_id, '_elementor_data', wp_slash( $new_raw ) );
	clean_post_cache( $page_id );

	$verify_raw = $wpdb->get_var(
		$wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_elementor_data'", $page_id )
	);
	$has_new = false !== strpos( $verify_raw, addslashes( $hero_addition ) ) || false !== strpos( $verify_raw, $hero_addition );
	echo "  verify contains addition: " . ( $has_new ? 'yes' : 'no' ) . "\n";
	if ( ! $has_new ) {
		$part1_success = false;
	}
}
wp_cache_flush();

echo "\n=====================================================================\n";
echo "PART 2: WPCode post 319 - remove asymmetric desktop bottom offset\n";
echo "=====================================================================\n";

$wpcode_old = '@media (min-width:1025px){.lna-hero__content{padding:0 5% 12vh !important;}}';
$wpcode_new = '@media (min-width:1025px){.lna-hero__content{padding:0 5% !important;}}';

$post = get_post( 319 );
$part2_success = true;

if ( ! $post ) {
	echo "ABORT: post 319 not found\n";
	$part2_success = false;
} else {
	$occurrences = substr_count( $post->post_content, $wpcode_old );
	$already_has = false !== strpos( $post->post_content, $wpcode_new );
	echo "post_content occurrences of old override: {$occurrences}, already has new: " . ( $already_has ? 'yes' : 'no' ) . "\n";

	if ( $already_has ) {
		echo "OK: post_content already updated, skipping\n";
	} elseif ( 1 !== $occurrences ) {
		echo "ABORT: expected exactly 1 occurrence in post_content, found {$occurrences}\n";
		$part2_success = false;
	} else {
		$fresh_post = get_post( 319 );
		if ( 1 !== substr_count( $fresh_post->post_content, $wpcode_old ) ) {
			echo "ABORT: race check failed on post_content\n";
			$part2_success = false;
		} else {
			$new_content = str_replace( $wpcode_old, $wpcode_new, $fresh_post->post_content );
			$updated     = $wpdb->update(
				$wpdb->posts,
				array( 'post_content' => $new_content ),
				array( 'ID' => 319 ),
				array( '%s' ),
				array( '%d' )
			);
			echo "wpdb->update() rows affected: " . var_export( $updated, true ) . "\n";
			clean_post_cache( 319 );

			$verify_post = get_post( 319 );
			$has_new     = false !== strpos( $verify_post->post_content, $wpcode_new );
			$has_old     = false !== strpos( $verify_post->post_content, $wpcode_old );
			echo "post_content verify: new_present=" . ( $has_new ? 'yes' : 'no' ) . " old_gone=" . ( ! $has_old ? 'yes' : 'no' ) . "\n";
			if ( ! $has_new || $has_old ) {
				$part2_success = false;
			}
		}
	}
}

echo "\n--- wpcode_snippets option mirrored copy ---\n";

$location    = 'site_wide_header';
$target_id   = 319;
$snippets    = get_option( 'wpcode_snippets' );
$found_index = null;

if ( is_array( $snippets ) && isset( $snippets[ $location ] ) ) {
	foreach ( $snippets[ $location ] as $idx => $entry ) {
		if ( is_array( $entry ) && isset( $entry['id'] ) && (int) $entry['id'] === $target_id ) {
			$found_index = $idx;
			break;
		}
	}
}

if ( null === $found_index ) {
	echo "ABORT: option entry for id={$target_id} not found under {$location}\n";
	$part2_success = false;
} else {
	$code = $snippets[ $location ][ $found_index ]['code'];
	$occurrences = substr_count( $code, $wpcode_old );
	$already_has = false !== strpos( $code, $wpcode_new );
	echo "option code occurrences of old override: {$occurrences}, already has new: " . ( $already_has ? 'yes' : 'no' ) . "\n";

	if ( $already_has ) {
		echo "OK: option already updated, skipping\n";
	} elseif ( 1 !== $occurrences ) {
		echo "ABORT: expected exactly 1 occurrence in option code, found {$occurrences}\n";
		$part2_success = false;
	} else {
		$fresh_snippets = get_option( 'wpcode_snippets' );
		$fresh_code     = $fresh_snippets[ $location ][ $found_index ]['code'];
		if ( 1 !== substr_count( $fresh_code, $wpcode_old ) ) {
			echo "ABORT: race check failed on option code\n";
			$part2_success = false;
		} else {
			$fresh_snippets[ $location ][ $found_index ]['code'] = str_replace( $wpcode_old, $wpcode_new, $fresh_code );
			$updated = update_option( 'wpcode_snippets', $fresh_snippets );
			echo "update_option() returned: " . var_export( $updated, true ) . "\n";

			$verify_snippets = get_option( 'wpcode_snippets' );
			$verify_code     = $verify_snippets[ $location ][ $found_index ]['code'];
			$has_new         = false !== strpos( $verify_code, $wpcode_new );
			$has_old         = false !== strpos( $verify_code, $wpcode_old );
			echo "option verify: new_present=" . ( $has_new ? 'yes' : 'no' ) . " old_gone=" . ( ! $has_old ? 'yes' : 'no' ) . "\n";
			if ( ! $has_new || $has_old ) {
				$part2_success = false;
			}
		}
	}
}

wp_cache_flush();

echo "\n=====================================================================\n";
if ( $part1_success && $part2_success ) {
	echo "FINAL RESULT: SUCCESS\n";
} else {
	echo "FINAL RESULT: FAILURE - part1=" . ( $part1_success ? 'ok' : 'FAILED' ) . " part2=" . ( $part2_success ? 'ok' : 'FAILED' ) . "\n";
	exit( 1 );
}
