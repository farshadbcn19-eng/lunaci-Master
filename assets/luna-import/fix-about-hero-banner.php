<?php
/**
 * Guarded fix: replace the About Us page's hero banner image
 * (lunaci-about-hero.png, attachment ID 307) with the new image already
 * imported via `wp media import` (this script receives its real URL as
 * $new_image_url, substituted by the workflow), on BOTH the EN page
 * (post 59) and its ES translation (post 680) - both currently reference
 * the exact same old image, confirmed via read-only diagnostics (PRs
 * #193/#194) - to avoid creating the same kind of EN/ES staleness gap
 * found and fixed on the Home page earlier in this session.
 *
 * Then safely deletes the old attachment (307), but ONLY after verifying:
 *   (a) the old image no longer appears in either page's widget content
 *       after the replacement, and
 *   (b) no OTHER attachment's metadata references the same physical files
 *       that are about to be deleted (the exact bug pattern found earlier
 *       this session with attachments 794/795 - diagnostic found
 *       attachment 552 also mentions this filename, so this is checked
 *       explicitly rather than assumed safe).
 *
 * STEP A: staleness gate - old image occurs exactly once in each widget,
 *         new image not already present, for BOTH post 59 and post 680.
 * STEP B: race-condition guard + targeted replace + write, for both pages.
 * STEP C: full read-back verification for both pages.
 * STEP D: safe deletion of the old attachment (only if the 552-conflict
 *         check clears; otherwise skipped with a clear report).
 */

global $wpdb;

$old_image_url        = 'https://lunacibarcelona.com/wp-content/uploads/2026/06/lunaci-about-hero.png';
$new_image_url         = '__NEW_IMAGE_URL__';
$old_attachment_id     = 307;
$target_container_id   = 'ff5f046';
$target_widget_id      = 'ce307e5';

$pages = array(
	59  => 'EN About Us',
	680 => 'ES About Us (Sobre Nosotros)',
);

if ( 0 !== strpos( $new_image_url, 'https://lunacibarcelona.com/wp-content/uploads/' ) ) {
	echo "ERROR: new image URL does not look like a valid lunacibarcelona.com uploads URL: {$new_image_url}\n";
	echo "ABORT\n";
	exit( 1 );
}

function lunaci_about_hero_find_widget( $node, $target_container_id, $target_widget_id ) {
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
			$found = lunaci_about_hero_find_widget( $value, $target_container_id, $target_widget_id );
			if ( null !== $found ) {
				return $found;
			}
		}
	}
	return null;
}

function lunaci_about_hero_set_widget_html( &$node, $target_container_id, $target_widget_id, $new_html ) {
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
			if ( lunaci_about_hero_set_widget_html( $value, $target_container_id, $target_widget_id, $new_html ) ) {
				return true;
			}
		}
	}
	unset( $value );
	return false;
}

$any_error   = false;
$page_state  = array();

echo "=====================================================================\n";
echo "STEP A: PREPARE - fresh-read + validate preconditions for both pages\n";
echo "=====================================================================\n";

foreach ( $pages as $page_id => $label ) {
	echo "\n--- {$label} (post {$page_id}) ---\n";
	$raw = $wpdb->get_var(
		$wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_elementor_data'", $page_id )
	);
	if ( null === $raw ) {
		echo "ABORT: _elementor_data not found for page ID={$page_id}.\n";
		exit( 1 );
	}
	$decoded = json_decode( $raw, true );
	if ( JSON_ERROR_NONE !== json_last_error() ) {
		echo "ABORT: json_decode failed: " . json_last_error_msg() . "\n";
		exit( 1 );
	}
	$widget = lunaci_about_hero_find_widget( $decoded, $target_container_id, $target_widget_id );
	if ( null === $widget || ! isset( $widget['settings']['html'] ) || ! is_string( $widget['settings']['html'] ) ) {
		echo "ABORT: target widget not found or settings.html missing/not a string for page {$page_id}.\n";
		exit( 1 );
	}
	$html = $widget['settings']['html'];

	$old_count = substr_count( $html, $old_image_url );
	echo "old image URL occurs {$old_count}x\n";
	if ( 1 !== $old_count ) {
		echo "ABORT: expected exactly 1 occurrence for page {$page_id}, found {$old_count}.\n";
		exit( 1 );
	}
	$new_count = substr_count( $html, $new_image_url );
	if ( 0 !== $new_count ) {
		echo "ABORT: new image URL already present in page {$page_id} - refusing to risk a duplicate.\n";
		exit( 1 );
	}

	$page_state[ $page_id ] = array(
		'decoded_before' => $decoded,
		'html_before'    => $html,
		'baseline_sha'   => hash( 'sha256', $html ),
	);
	echo "OK: preconditions satisfied for page {$page_id}\n";
}

echo "\n=====================================================================\n";
echo "STEP B: COMMIT - race-check, replace, write for both pages\n";
echo "=====================================================================\n";

foreach ( $pages as $page_id => $label ) {
	echo "\n--- {$label} (post {$page_id}) ---\n";
	$raw_guard = $wpdb->get_var(
		$wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_elementor_data'", $page_id )
	);
	$decoded_guard = json_decode( $raw_guard, true );
	if ( JSON_ERROR_NONE !== json_last_error() ) {
		echo "ABORT: json_decode failed on guard re-read for page {$page_id}.\n";
		exit( 1 );
	}
	$widget_guard = lunaci_about_hero_find_widget( $decoded_guard, $target_container_id, $target_widget_id );
	if ( null === $widget_guard || ! isset( $widget_guard['settings']['html'] ) ) {
		echo "ABORT: target widget not found on guard re-read for page {$page_id}.\n";
		exit( 1 );
	}
	$guard_sha = hash( 'sha256', $widget_guard['settings']['html'] );
	if ( $guard_sha !== $page_state[ $page_id ]['baseline_sha'] ) {
		echo "ABORT: race condition detected for page {$page_id} - content changed between STEP A and STEP B.\n";
		exit( 1 );
	}
	echo "PASS: race-condition guard confirms content unchanged\n";

	$new_html = str_replace( $old_image_url, $new_image_url, $page_state[ $page_id ]['html_before'] );

	$working = $decoded_guard;
	$set_ok  = lunaci_about_hero_set_widget_html( $working, $target_container_id, $target_widget_id, $new_html );
	if ( ! $set_ok ) {
		echo "ABORT: failed to set new settings.html for page {$page_id}.\n";
		exit( 1 );
	}
	$new_raw = wp_json_encode( $working, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	if ( false === $new_raw ) {
		echo "ABORT: wp_json_encode failed for page {$page_id}.\n";
		exit( 1 );
	}
	$update_result = update_post_meta( $page_id, '_elementor_data', wp_slash( $new_raw ) );
	if ( false === $update_result ) {
		echo "ABORT: update_post_meta() returned false for page {$page_id}.\n";
		exit( 1 );
	}
	echo "OK: update_post_meta() succeeded for page {$page_id}\n";

	clean_post_cache( $page_id );
	delete_post_meta( $page_id, '_elementor_css' );
	if ( class_exists( '\\Elementor\\Plugin' ) ) {
		try {
			\Elementor\Plugin::instance()->files_manager->clear_cache();
		} catch ( \Throwable $e ) {
			echo "WARNING: Elementor cache clear threw: " . $e->getMessage() . "\n";
		}
	}
	$page_state[ $page_id ]['new_html_expected'] = $new_html;
}
wp_cache_flush();
echo "\nOK: caches cleared for both pages, object cache flushed\n";

echo "\n=====================================================================\n";
echo "STEP C: VERIFY - fresh read-back for both pages\n";
echo "=====================================================================\n";

foreach ( $pages as $page_id => $label ) {
	echo "\n--- {$label} (post {$page_id}) ---\n";
	$raw_after = $wpdb->get_var(
		$wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_elementor_data'", $page_id )
	);
	$decoded_after = json_decode( $raw_after, true );
	if ( JSON_ERROR_NONE !== json_last_error() ) {
		echo "ERROR: json_decode failed on read-back for page {$page_id}.\n";
		$any_error = true;
		continue;
	}
	$widget_after = lunaci_about_hero_find_widget( $decoded_after, $target_container_id, $target_widget_id );
	if ( null === $widget_after || ! isset( $widget_after['settings']['html'] ) ) {
		echo "ERROR: target widget not found after write for page {$page_id}.\n";
		$any_error = true;
		continue;
	}
	$html_after = $widget_after['settings']['html'];
	$matches    = ( $html_after === $page_state[ $page_id ]['new_html_expected'] );
	echo "content matches expected: " . ( $matches ? 'PASS' : 'FAIL' ) . "\n";
	$old_gone    = ( 0 === substr_count( $html_after, $old_image_url ) );
	$new_present = ( 1 === substr_count( $html_after, $new_image_url ) );
	echo "old image gone: " . ( $old_gone ? 'yes' : 'no' ) . "  new image present(x1): " . ( $new_present ? 'yes' : 'no' ) . "\n";
	if ( ! $matches || ! $old_gone || ! $new_present ) {
		$any_error = true;
	}
	$page_state[ $page_id ]['old_gone_confirmed'] = $old_gone;
}

if ( $any_error ) {
	echo "\nFINAL RESULT: FAILURE (image replace step)\n";
	exit( 1 );
}
echo "\nOK: image replace confirmed successful on both pages\n";

echo "\n=====================================================================\n";
echo "STEP D: Safe deletion of old attachment ({$old_attachment_id})\n";
echo "=====================================================================\n";

if ( ! $page_state[59]['old_gone_confirmed'] || ! $page_state[680]['old_gone_confirmed'] ) {
	echo "ABORT deletion: old image still referenced on at least one page - refusing to delete.\n";
	echo "\nFINAL RESULT: SUCCESS (replace only, deletion skipped for safety)\n";
	exit( 0 );
}

$old_meta = wp_get_attachment_metadata( $old_attachment_id );
if ( ! is_array( $old_meta ) ) {
	echo "ABORT deletion: could not read metadata for attachment {$old_attachment_id} - refusing to delete blind.\n";
	echo "\nFINAL RESULT: SUCCESS (replace only, deletion skipped for safety)\n";
	exit( 0 );
}
$old_files = array();
if ( isset( $old_meta['file'] ) ) {
	$old_files[] = basename( $old_meta['file'] );
}
if ( isset( $old_meta['sizes'] ) && is_array( $old_meta['sizes'] ) ) {
	foreach ( $old_meta['sizes'] as $size ) {
		if ( isset( $size['file'] ) ) {
			$old_files[] = $size['file'];
		}
	}
}
echo "Attachment {$old_attachment_id}'s own files (" . count( $old_files ) . "): " . implode( ', ', $old_files ) . "\n";

// Check whether attachment 552 (flagged by the read-only diagnostic as also
// mentioning this filename) actually points at any of these same physical
// files - the exact bug pattern found earlier this session (attachments
// 794/795). If so, do NOT delete - report and stop, matching the earlier
// established remediation order (fix metadata conflicts before deleting).
$conflict_found = false;
$other_attachment_ids = $wpdb->get_col(
	$wpdb->prepare(
		"SELECT DISTINCT post_id FROM {$wpdb->postmeta}
		 WHERE post_id != %d AND meta_key IN ('_wp_attached_file', '_wp_attachment_metadata')
		 AND meta_value LIKE %s",
		$old_attachment_id,
		'%' . $wpdb->esc_like( 'lunaci-about-hero.png' ) . '%'
	)
);
foreach ( $other_attachment_ids as $other_id ) {
	$other_meta  = wp_get_attachment_metadata( $other_id );
	$other_files = array();
	if ( is_array( $other_meta ) ) {
		if ( isset( $other_meta['file'] ) ) {
			$other_files[] = basename( $other_meta['file'] );
		}
		if ( isset( $other_meta['sizes'] ) && is_array( $other_meta['sizes'] ) ) {
			foreach ( $other_meta['sizes'] as $size ) {
				if ( isset( $size['file'] ) ) {
					$other_files[] = $size['file'];
				}
			}
		}
	}
	$overlap = array_intersect( $old_files, $other_files );
	echo "Checked other attachment ID={$other_id}: files=" . implode( ', ', $other_files ) . " | overlap with {$old_attachment_id}: " . ( empty( $overlap ) ? 'none' : implode( ', ', $overlap ) ) . "\n";
	if ( ! empty( $overlap ) ) {
		$conflict_found = true;
	}
}

if ( $conflict_found ) {
	echo "\nABORT deletion: another attachment's metadata references the same physical file(s) as attachment {$old_attachment_id}.\n";
	echo "This is the same metadata-pointer bug pattern found earlier this session (attachments 794/795) - refusing to delete\n";
	echo "until that is investigated and fixed first, to avoid breaking the other attachment's URL.\n";
	echo "\nFINAL RESULT: SUCCESS (replace only, deletion skipped pending metadata investigation)\n";
	exit( 0 );
}
echo "OK: no metadata conflicts found - safe to delete attachment {$old_attachment_id}\n";

$delete_result = wp_delete_attachment( $old_attachment_id, true );
if ( false === $delete_result ) {
	echo "ERROR: wp_delete_attachment({$old_attachment_id}, true) returned false.\n";
	echo "\nFINAL RESULT: FAILURE (deletion step)\n";
	exit( 1 );
}
echo "OK: wp_delete_attachment({$old_attachment_id}, true) succeeded\n";

$verify_gone = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE ID = %d", $old_attachment_id ) );
if ( $verify_gone ) {
	echo "ERROR: attachment row still exists after deletion attempt.\n";
	echo "\nFINAL RESULT: FAILURE (deletion step)\n";
	exit( 1 );
}
echo "OK: attachment row confirmed gone from wp_posts\n";

echo "\nFINAL RESULT: SUCCESS\n";
