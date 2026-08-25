<?php
/**
 * Guarded fix: the Home page's ES translation (post 772) still has the
 * OLD "Why Lunaci / In 15 Seconds" section image
 * (lunaci-category-Lips-1.png), which was replaced on the EN page
 * (post 57) via a separate, earlier fix (PR #167/168,
 * fix-why2-image-swap.php, 2026-08-23 17:35) with
 * lunaimport-why2-luna-replacement.jpg?v=2. That swap predates the ES
 * snapshot but was NOT caught by the earlier Home-page staleness fix
 * (PR #186), which only targeted the Our Origin section + hero/collection
 * images from a different PR. User confirmed live: switching to Spanish
 * still shows the old image in this section.
 *
 * STEP A: staleness gate - confirm byte length, locate the same widget
 *         path used in PR #186 (container[id=bf4109a]/widget[id=9b0a463]),
 *         verify the old image URL occurs exactly once and the new one
 *         is not already present.
 * STEP B: race-condition guard + single targeted string replace + write.
 * STEP C: full read-back verification (new URL present, old URL gone,
 *         unrelated settings leaves unchanged).
 */

global $wpdb;

$page_id             = 772;
$target_container_id = 'bf4109a';
$target_widget_id    = '9b0a463';
$old_image_url        = 'https://lunacibarcelona.com/wp-content/uploads/2026/07/lunaci-category-Lips-1.png';
$new_image_url         = 'https://lunacibarcelona.com/wp-content/uploads/2026/08/lunaimport-why2-luna-replacement.jpg?v=2';

function lunaci772b_find_widget( $node, $target_container_id, $target_widget_id ) {
	if ( ! is_array( $node ) ) {
		return null;
	}
	if ( isset( $node['id'], $node['elType'] ) && 'container' === $node['elType'] && $node['id'] === $target_container_id ) {
		if ( isset( $node['elements'] ) && is_array( $node['elements'] ) ) {
			foreach ( $node['elements'] as $child ) {
				if ( isset( $child['id'], $child['elType'] ) && 'widget' === $child['elType'] && $child['id'] === $target_widget_id ) {
					return $child;
				}
			}
		}
	}
	foreach ( $node as $value ) {
		if ( is_array( $value ) ) {
			$found = lunaci772b_find_widget( $value, $target_container_id, $target_widget_id );
			if ( null !== $found ) {
				return $found;
			}
		}
	}
	return null;
}

function lunaci772b_set_widget_html( &$node, $target_container_id, $target_widget_id, $new_html ) {
	if ( ! is_array( $node ) ) {
		return false;
	}
	if ( isset( $node['id'], $node['elType'] ) && 'container' === $node['elType'] && $node['id'] === $target_container_id ) {
		if ( isset( $node['elements'] ) && is_array( $node['elements'] ) ) {
			foreach ( $node['elements'] as &$child ) {
				if ( isset( $child['id'], $child['elType'] ) && 'widget' === $child['elType'] && $child['id'] === $target_widget_id ) {
					$child['settings']['html'] = $new_html;
					return true;
				}
			}
			unset( $child );
		}
	}
	foreach ( $node as &$value ) {
		if ( is_array( $value ) ) {
			if ( lunaci772b_set_widget_html( $value, $target_container_id, $target_widget_id, $new_html ) ) {
				return true;
			}
		}
	}
	unset( $value );
	return false;
}

function lunaci772b_count_elements( $node ) {
	if ( ! is_array( $node ) ) {
		return 0;
	}
	$count = 0;
	if ( isset( $node['id'], $node['elType'] ) ) {
		$count = 1;
	}
	foreach ( $node as $key => $value ) {
		if ( 'settings' === $key ) {
			continue;
		}
		if ( is_array( $value ) ) {
			$count += lunaci772b_count_elements( $value );
		}
	}
	return $count;
}

function lunaci772b_collect_other_leaves( $node, $path, $target_widget_id, &$results ) {
	if ( ! is_array( $node ) ) {
		return;
	}
	$current_id     = isset( $node['id'] ) ? $node['id'] : null;
	$current_eltype = isset( $node['elType'] ) ? $node['elType'] : null;
	if ( null !== $current_id && null !== $current_eltype ) {
		$path = $path . '/' . $current_eltype . '[id=' . $current_id . ']';
	}
	$is_target_widget = ( 'widget' === $current_eltype && $current_id === $target_widget_id );
	if ( isset( $node['settings'] ) && is_array( $node['settings'] ) ) {
		foreach ( $node['settings'] as $key => $value ) {
			if ( $is_target_widget && 'html' === $key ) {
				continue;
			}
			if ( is_string( $value ) ) {
				$results[ $path . '/settings/' . $key ] = $value;
			}
		}
	}
	foreach ( $node as $key => $value ) {
		if ( ! is_array( $value ) ) {
			continue;
		}
		if ( 'elements' === $key ) {
			foreach ( $value as $child ) {
				lunaci772b_collect_other_leaves( $child, $path, $target_widget_id, $results );
			}
		} elseif ( 'settings' !== $key ) {
			lunaci772b_collect_other_leaves( $value, $path, $target_widget_id, $results );
		}
	}
}

echo "=====================================================================\n";
echo "STEP A: PREPARE - fresh-read, decode, locate widget, validate preconditions\n";
echo "=====================================================================\n";

$raw_before = $wpdb->get_var(
	$wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_elementor_data'", $page_id )
);
if ( null === $raw_before ) {
	echo "ABORT: _elementor_data not found for page ID={$page_id}.\n";
	exit( 1 );
}
$actual_byte_len = strlen( $raw_before );
echo "Current _elementor_data byte length = {$actual_byte_len} (informational only - the safety gate below is the exact-occurrence-count check on the target substrings, not a whole-file comparison, since legitimate concurrent edits by the site owner are expected on a live site)\n";

$decoded_before = json_decode( $raw_before, true );
if ( JSON_ERROR_NONE !== json_last_error() ) {
	echo "ABORT: json_decode failed: " . json_last_error_msg() . "\n";
	exit( 1 );
}
echo "OK: json_decode succeeded.\n";

$widget_before = lunaci772b_find_widget( $decoded_before, $target_container_id, $target_widget_id );
if ( null === $widget_before || ! isset( $widget_before['settings']['html'] ) || ! is_string( $widget_before['settings']['html'] ) ) {
	echo "ABORT: target widget path not found or settings.html missing/not a string.\n";
	exit( 1 );
}
$current_html = $widget_before['settings']['html'];
echo "OK: target widget found, html length=" . strlen( $current_html ) . "\n\n";

echo "Section classes currently present (for visibility into what else may have changed):\n";
if ( preg_match_all( '/<section[^>]*class="([^"]*)"[^>]*>/i', $current_html, $sec_m ) ) {
	foreach ( $sec_m[1] as $i => $cls ) {
		echo "  [{$i}] class=\"{$cls}\"\n";
	}
}
echo "\n";

$old_count = substr_count( $current_html, $old_image_url );
echo "old image URL occurs {$old_count}x: {$old_image_url}\n";
if ( 1 !== $old_count ) {
	echo "ABORT: expected exactly 1 occurrence, found {$old_count}. Refusing to write.\n";
	exit( 1 );
}

$new_count = substr_count( $current_html, $new_image_url );
echo "new image URL currently occurs {$new_count}x (expected 0): {$new_image_url}\n";
if ( 0 !== $new_count ) {
	echo "ABORT: new image URL already present - refusing to risk a duplicate/unexpected state.\n";
	exit( 1 );
}
echo "\n";

$local_baseline_sha256 = hash( 'sha256', $current_html );
echo "Baseline sha256 of current widget html: {$local_baseline_sha256}\n\n";

echo "=====================================================================\n";
echo "STEP B: COMMIT - race-check, apply targeted replacement, write\n";
echo "=====================================================================\n";

$raw_guard = $wpdb->get_var(
	$wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_elementor_data'", $page_id )
);
if ( null === $raw_guard ) {
	echo "ABORT: _elementor_data disappeared between STEP A and STEP B.\n";
	exit( 1 );
}
$decoded_guard = json_decode( $raw_guard, true );
if ( JSON_ERROR_NONE !== json_last_error() ) {
	echo "ABORT: json_decode failed on guard re-read: " . json_last_error_msg() . "\n";
	exit( 1 );
}
$widget_guard = lunaci772b_find_widget( $decoded_guard, $target_container_id, $target_widget_id );
if ( null === $widget_guard || ! isset( $widget_guard['settings']['html'] ) || ! is_string( $widget_guard['settings']['html'] ) ) {
	echo "ABORT: target widget not found on guard re-read.\n";
	exit( 1 );
}
$guard_sha256 = hash( 'sha256', $widget_guard['settings']['html'] );
if ( $guard_sha256 !== $local_baseline_sha256 ) {
	echo "ABORT: race condition detected - widget html changed between STEP A and STEP B. No write performed.\n";
	exit( 1 );
}
echo "PASS: race-condition guard confirms content unchanged immediately before write.\n\n";

$new_html = str_replace( $old_image_url, $new_image_url, $current_html );
echo "New widget html length: " . strlen( $new_html ) . " (was " . strlen( $current_html ) . ")\n\n";

$working = $decoded_guard;
$set_ok  = lunaci772b_set_widget_html( $working, $target_container_id, $target_widget_id, $new_html );
if ( ! $set_ok ) {
	echo "ABORT: failed to set new settings.html on the in-memory structure.\n";
	exit( 1 );
}
echo "OK: in-memory structure updated.\n";

$new_raw = wp_json_encode( $working, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
if ( false === $new_raw ) {
	echo "ABORT: wp_json_encode failed.\n";
	exit( 1 );
}
echo "OK: re-encoded structure (new byte length: " . strlen( $new_raw ) . ").\n\n";

$update_result = update_post_meta( $page_id, '_elementor_data', wp_slash( $new_raw ) );
if ( false === $update_result ) {
	echo "ABORT: update_post_meta() returned false.\n";
	exit( 1 );
}
echo "OK: update_post_meta() succeeded.\n\n";

clean_post_cache( $page_id );
delete_post_meta( $page_id, '_elementor_css' );
if ( class_exists( '\\Elementor\\Plugin' ) ) {
	try {
		\Elementor\Plugin::instance()->files_manager->clear_cache();
		echo "OK: Elementor files_manager cache cleared.\n";
	} catch ( \Throwable $e ) {
		echo "WARNING: Elementor cache clear threw: " . $e->getMessage() . "\n";
	}
}
wp_cache_flush();
echo "OK: post cache cleared, _elementor_css meta deleted, object cache flushed.\n\n";

echo "=====================================================================\n";
echo "STEP C: VERIFY - fresh read-back, confirm exact expected state\n";
echo "=====================================================================\n";

$any_error = false;

$raw_after     = $wpdb->get_var(
	$wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_elementor_data'", $page_id )
);
$decoded_after = json_decode( $raw_after, true );
if ( JSON_ERROR_NONE !== json_last_error() ) {
	echo "ERROR: json_decode failed on read-back: " . json_last_error_msg() . "\n";
	$any_error = true;
} else {
	echo "OK: read-back content decodes successfully.\n";
}

$widget_after = lunaci772b_find_widget( $decoded_after, $target_container_id, $target_widget_id );
if ( null === $widget_after || ! isset( $widget_after['settings']['html'] ) || ! is_string( $widget_after['settings']['html'] ) ) {
	echo "ERROR: target widget not found after write.\n";
	$any_error = true;
} else {
	$html_after = $widget_after['settings']['html'];

	$content_matches = ( $html_after === $new_html );
	echo "widget html after write matches intended new content exactly: " . ( $content_matches ? 'PASS' : 'FAIL' ) . "\n";
	if ( ! $content_matches ) {
		$any_error = true;
	}

	$old_gone    = ( 0 === substr_count( $html_after, $old_image_url ) );
	$new_present = ( 1 === substr_count( $html_after, $new_image_url ) );
	echo "old image URL gone: " . ( $old_gone ? 'yes' : 'no' ) . "\n";
	echo "new image URL present (x1): " . ( $new_present ? 'yes' : 'no' ) . "\n";
	if ( ! $old_gone || ! $new_present ) {
		$any_error = true;
	}
}

$elements_count_before = lunaci772b_count_elements( $decoded_before );
$elements_count_after  = lunaci772b_count_elements( $decoded_after );
$counts_match          = ( $elements_count_before === $elements_count_after );
echo "\nTotal elements before: {$elements_count_before}, after: {$elements_count_after}: " . ( $counts_match ? 'PASS (unchanged)' : 'FAIL (structure changed)' ) . "\n";
if ( ! $counts_match ) {
	$any_error = true;
}

$other_leaves_before = array();
$other_leaves_after  = array();
lunaci772b_collect_other_leaves( $decoded_before, '', $target_widget_id, $other_leaves_before );
lunaci772b_collect_other_leaves( $decoded_after, '', $target_widget_id, $other_leaves_after );
$same_keyset = ( array_keys( $other_leaves_before ) === array_keys( $other_leaves_after ) );
echo "Other settings leaf keyset identical before/after: " . ( $same_keyset ? 'PASS' : 'FAIL' ) . "\n";
if ( ! $same_keyset ) {
	$any_error = true;
}
$other_values_unchanged = true;
foreach ( $other_leaves_before as $k => $v ) {
	if ( ! array_key_exists( $k, $other_leaves_after ) || $other_leaves_after[ $k ] !== $v ) {
		$other_values_unchanged = false;
		echo "  unexpected diff at: {$k}\n";
	}
}
echo "All other settings leaf values unchanged: " . ( $other_values_unchanged ? 'PASS' : 'FAIL' ) . "\n";
if ( ! $other_values_unchanged ) {
	$any_error = true;
}

if ( $any_error ) {
	echo "\nFINAL RESULT: FAILURE\n";
	exit( 1 );
}

echo "\nFINAL RESULT: SUCCESS\n";
