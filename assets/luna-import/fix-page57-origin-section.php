<?php
global $wpdb;

$page_id                  = 57;
$expected_byte_len        = 26266; // confirmed live _elementor_data byte length (read-only diagnostic, 2026-08-23)
$target_container_id      = 'bf4109a';
$target_widget_id         = '9b0a463';
$new_content_path         = '/tmp/lunaimport-page57-widget-new.html';
$expected_new_content_len = 26920; // byte length of the transferred replacement file

echo "####################################################################\n";
echo "Fix: swap 5 image src values + insert Our Origin section, page ID={$page_id}, container[id={$target_container_id}]/widget[id={$target_widget_id}]\n";
echo "####################################################################\n\n";

function lunaci_find_target_widget( $node, $target_container_id, $target_widget_id ) {
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
			$found = lunaci_find_target_widget( $value, $target_container_id, $target_widget_id );
			if ( null !== $found ) {
				return $found;
			}
		}
	}

	return null;
}

function lunaci_set_target_widget_html( &$node, $target_container_id, $target_widget_id, $new_html ) {
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
			if ( lunaci_set_target_widget_html( $value, $target_container_id, $target_widget_id, $new_html ) ) {
				return true;
			}
		}
	}
	unset( $value );

	return false;
}

function lunaci_count_elements( $node ) {
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
			$count += lunaci_count_elements( $value );
		}
	}
	return $count;
}

/**
 * Collect settings-leaf string values keyed by elType[id=...]/settings/key path,
 * EXCLUDING the target widget's 'html' key (that key is expected to change).
 */
function lunaci_collect_other_leaves( $node, $path, $target_container_id, $target_widget_id, &$results ) {
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
				continue; // expected to change, excluded from "unchanged" comparison
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
				lunaci_collect_other_leaves( $child, $path, $target_container_id, $target_widget_id, $results );
			}
		} elseif ( 'settings' !== $key ) {
			lunaci_collect_other_leaves( $value, $path, $target_container_id, $target_widget_id, $results );
		}
	}
}

function lunaci_adaptive_decode( $raw ) {
	$decoded = json_decode( $raw, true );
	if ( JSON_ERROR_NONE === json_last_error() ) {
		return $decoded;
	}
	$decoded = json_decode( wp_unslash( $raw ), true );
	if ( JSON_ERROR_NONE === json_last_error() ) {
		return $decoded;
	}
	return null;
}

echo "-- STEP A: PREPARE (read-only pre-checks) --\n\n";

if ( ! file_exists( $new_content_path ) ) {
	echo "ABORT: new content file not found at {$new_content_path}.\n";
	return;
}

$new_content     = file_get_contents( $new_content_path );
$new_content_len = strlen( $new_content );
echo "New content file byte length = {$new_content_len} (expected {$expected_new_content_len}): " . ( $new_content_len === $expected_new_content_len ? 'PASS' : 'FAIL' ) . "\n";
if ( $new_content_len !== $expected_new_content_len ) {
	echo "ABORT: new content file byte length mismatch - transfer may be incomplete/corrupted.\n";
	return;
}

// Sanity check: the 5 new image URLs and the new section marker must be present in the transferred file.
$required_substrings = array(
	'lunaimport-hero-luna.jpg?v=2',
	'lunaimport-collection-face-luna.jpg?v=2',
	'lunaimport-collection-eyes-luna.jpg?v=2',
	'lunaimport-collection-lips-luna.jpg?v=2',
	'lunaimport-collection-nails-luna.jpg?v=2',
	'lunaimport-origin-crafted-barcelona-luna.jpg',
	'class="ln-origin"',
);
foreach ( $required_substrings as $needle ) {
	if ( false === strpos( $new_content, $needle ) ) {
		echo "ABORT: expected substring not found in new content: {$needle}\n";
		return;
	}
}
echo "PASS: all 5 new image URLs and the new Our Origin section marker are present in the transferred file.\n\n";

$raw_before = $wpdb->get_var(
	$wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_elementor_data'", $page_id )
);

if ( null === $raw_before ) {
	echo "ABORT: _elementor_data not found for page ID={$page_id}.\n";
	return;
}

$actual_byte_len = strlen( $raw_before );
echo "Current _elementor_data byte length = {$actual_byte_len} (expected {$expected_byte_len}): " . ( $actual_byte_len === $expected_byte_len ? 'PASS' : 'FAIL' ) . "\n";
if ( $actual_byte_len !== $expected_byte_len ) {
	echo "ABORT: byte length mismatch - content has changed since the last diagnostic, needs re-diagnosis before a safe write.\n";
	return;
}

$decoded_before = json_decode( $raw_before, true );
if ( JSON_ERROR_NONE !== json_last_error() ) {
	echo "ABORT: json_decode failed on current _elementor_data: " . json_last_error_msg() . "\n";
	return;
}
echo "OK: json_decode succeeded.\n";

$target_widget_before = lunaci_find_target_widget( $decoded_before, $target_container_id, $target_widget_id );
if ( null === $target_widget_before ) {
	echo "ABORT: path container[id={$target_container_id}]/widget[id={$target_widget_id}] not found.\n";
	return;
}
echo "PASS: target widget path found.\n";

$widget_type = isset( $target_widget_before['widgetType'] ) ? $target_widget_before['widgetType'] : '(not set)';
if ( 'html' !== $widget_type ) {
	echo "ABORT: widgetType is " . var_export( $widget_type, true ) . ", expected 'html'.\n";
	return;
}
echo "PASS: widgetType === 'html'.\n";

if ( ! isset( $target_widget_before['settings']['html'] ) || ! is_string( $target_widget_before['settings']['html'] ) ) {
	echo "ABORT: settings.html missing or not a string.\n";
	return;
}
$current_html = $target_widget_before['settings']['html'];
echo "PASS: settings.html present and is string (byte length " . strlen( $current_html ) . ").\n";

$local_baseline_sha256 = hash( 'sha256', $current_html );
echo "Current settings.html sha256 (this run's local baseline) = {$local_baseline_sha256}\n\n";

$elements_count_before = lunaci_count_elements( $decoded_before );
echo "Total elements (id+elType nodes) in decoded structure before write: {$elements_count_before}\n\n";

echo "-- STEP B: COMMIT (write) --\n\n";

echo "Race-condition guard: re-reading _elementor_data immediately before write...\n";
$raw_guard = $wpdb->get_var(
	$wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_elementor_data'", $page_id )
);
if ( null === $raw_guard ) {
	echo "ABORT: _elementor_data disappeared between STEP A and STEP B.\n";
	return;
}
$decoded_guard = json_decode( $raw_guard, true );
if ( JSON_ERROR_NONE !== json_last_error() ) {
	echo "ABORT: json_decode failed on guard re-read: " . json_last_error_msg() . "\n";
	return;
}
$target_widget_guard = lunaci_find_target_widget( $decoded_guard, $target_container_id, $target_widget_id );
if ( null === $target_widget_guard || ! isset( $target_widget_guard['settings']['html'] ) || ! is_string( $target_widget_guard['settings']['html'] ) ) {
	echo "ABORT: target widget/settings.html not found on guard re-read.\n";
	return;
}
$guard_sha256 = hash( 'sha256', $target_widget_guard['settings']['html'] );
if ( $guard_sha256 !== $local_baseline_sha256 ) {
	echo "ABORT: race condition detected - settings.html changed between STEP A and STEP B (guard sha256 {$guard_sha256} != local baseline {$local_baseline_sha256}). No write performed.\n";
	return;
}
echo "PASS: race-condition guard confirms content unchanged immediately before write.\n\n";

$working = $decoded_guard;
$set_ok  = lunaci_set_target_widget_html( $working, $target_container_id, $target_widget_id, $new_content );
if ( ! $set_ok ) {
	echo "ABORT: failed to set new settings.html on the in-memory structure (target path not found during write).\n";
	return;
}
echo "OK: in-memory structure updated with new settings.html.\n";

$new_raw = wp_json_encode( $working, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
if ( false === $new_raw ) {
	echo "ABORT: wp_json_encode failed on updated structure.\n";
	return;
}
echo "OK: re-encoded structure (new byte length: " . strlen( $new_raw ) . ").\n\n";

$update_result = update_post_meta( $page_id, '_elementor_data', wp_slash( $new_raw ) );
if ( false === $update_result ) {
	echo "ABORT: update_post_meta() returned false - write failed.\n";
	return;
}
echo "OK: update_post_meta() succeeded.\n\n";

echo "-- STEP C: VERIFY --\n\n";

$raw_after = $wpdb->get_var(
	$wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_elementor_data'", $page_id )
);

if ( null === $raw_after ) {
	echo "ABORT: _elementor_data not found after write.\n";
	return;
}

$decoded_after = lunaci_adaptive_decode( $raw_after );
if ( null === $decoded_after ) {
	echo "FAIL: could not decode _elementor_data after write (tried direct json_decode and wp_unslash fallback).\n";
	echo "FINAL RESULT: FAILURE\n";
	return;
}
echo "OK: post-write content decodes successfully.\n";

$target_widget_after = lunaci_find_target_widget( $decoded_after, $target_container_id, $target_widget_id );
if ( null === $target_widget_after || ! isset( $target_widget_after['settings']['html'] ) || ! is_string( $target_widget_after['settings']['html'] ) ) {
	echo "FAIL: target widget/settings.html not found after write.\n";
	echo "FINAL RESULT: FAILURE\n";
	return;
}
$html_after = $target_widget_after['settings']['html'];

$content_matches = ( $html_after === $new_content );
echo "settings.html after write matches new content exactly (full string comparison): " . ( $content_matches ? 'PASS' : 'FAIL' ) . "\n";
echo "  Expected byte length: {$new_content_len}, actual byte length: " . strlen( $html_after ) . "\n\n";

$elements_count_after = lunaci_count_elements( $decoded_after );
$counts_match         = ( $elements_count_before === $elements_count_after );
echo "Total elements before: {$elements_count_before}, after: {$elements_count_after}: " . ( $counts_match ? 'PASS (unchanged)' : 'FAIL (structure changed)' ) . "\n\n";

$other_leaves_before = array();
$other_leaves_after  = array();
lunaci_collect_other_leaves( $decoded_before, '', $target_container_id, $target_widget_id, $other_leaves_before );
lunaci_collect_other_leaves( $decoded_after, '', $target_container_id, $target_widget_id, $other_leaves_after );

$same_keyset = ( array_keys( $other_leaves_before ) === array_keys( $other_leaves_after ) );
echo "Other settings leaf keyset (excluding target widget's html) identical before/after: " . ( $same_keyset ? 'PASS' : 'FAIL' ) . "\n";

$other_values_unchanged = true;
$diffs                  = array();
foreach ( $other_leaves_before as $k => $v ) {
	if ( ! array_key_exists( $k, $other_leaves_after ) || $other_leaves_after[ $k ] !== $v ) {
		$other_values_unchanged = false;
		$diffs[]                = $k;
	}
}
echo "All other settings leaf values unchanged: " . ( $other_values_unchanged ? 'PASS' : 'FAIL' ) . "\n";
if ( ! empty( $diffs ) ) {
	echo "  Unexpected diffs at: " . implode( ', ', $diffs ) . "\n";
}
echo "\n";

$new_total_byte_len = strlen( $raw_after );
echo "New total _elementor_data byte length: {$new_total_byte_len} (was {$actual_byte_len})\n\n";

$all_pass = $content_matches && $counts_match && $same_keyset && $other_values_unchanged;

echo "-- FINAL SUMMARY --\n\n";
echo "  content_matches: " . ( $content_matches ? 'PASS' : 'FAIL' ) . "\n";
echo "  counts_match: " . ( $counts_match ? 'PASS' : 'FAIL' ) . "\n";
echo "  same_keyset: " . ( $same_keyset ? 'PASS' : 'FAIL' ) . "\n";
echo "  other_values_unchanged: " . ( $other_values_unchanged ? 'PASS' : 'FAIL' ) . "\n\n";
echo 'FINAL RESULT: ' . ( $all_pass ? 'SUCCESS' : 'FAILURE' ) . "\n";

echo "\nOK: fix-page57-origin-section completed\n";
