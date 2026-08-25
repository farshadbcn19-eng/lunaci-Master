<?php
/**
 * Guarded fix: replace the broken/badly-cropped Blusher product image on
 * the Products page (EN post 61 + ES post 771) with a new user-supplied
 * photo, then safely clean up the old attachment if nothing else
 * references its physical files - same pattern as
 * fix-about-story-image.php (PRs #212-#215), including the two JSON-
 * encoding lessons learned there: search/replace on the DECODED widget
 * HTML (not the raw, slash-escaped postmeta string), and re-encode with
 * JSON_UNESCAPED_SLASHES so the post-write verification checks match
 * plain-slash URLs correctly.
 */

global $wpdb;

$old_image_url = 'https://lunacibarcelona.com/wp-content/uploads/2026/06/lunaci-blush.jpg';
$new_image_url  = '__NEW_IMAGE_URL__';

$pages = array(
	61  => 'EN Products',
	771 => 'ES Products (Productos)',
);

function lunaci_blusher_find_widget_with_fragment( $node, $fragment ) {
	if ( ! is_array( $node ) ) {
		return null;
	}
	if ( isset( $node['widgetType'], $node['settings']['html'] ) && 'html' === $node['widgetType'] && false !== strpos( $node['settings']['html'], $fragment ) ) {
		return $node;
	}
	foreach ( $node as $value ) {
		if ( is_array( $value ) ) {
			$found = lunaci_blusher_find_widget_with_fragment( $value, $fragment );
			if ( null !== $found ) {
				return $found;
			}
		}
	}
	return null;
}

function lunaci_blusher_set_widget_html_by_id( &$node, $target_id, $new_html_value ) {
	if ( ! is_array( $node ) ) {
		return false;
	}
	if ( isset( $node['id'] ) && $node['id'] === $target_id && isset( $node['settings']['html'] ) ) {
		$node['settings']['html'] = $new_html_value;
		return true;
	}
	foreach ( $node as &$value ) {
		if ( is_array( $value ) ) {
			if ( lunaci_blusher_set_widget_html_by_id( $value, $target_id, $new_html_value ) ) {
				return true;
			}
		}
	}
	return false;
}

echo "=====================================================================\n";
echo "STEP A: PREPARE - fresh-read + validate preconditions for both pages\n";
echo "=====================================================================\n";

$page_data = array();

foreach ( $pages as $page_id => $label ) {
	echo "\n--- {$label} (post {$page_id}) ---\n";
	$raw = $wpdb->get_var(
		$wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_elementor_data'", $page_id )
	);
	if ( null === $raw ) {
		echo "ABORT: no _elementor_data found for post {$page_id}\n";
		exit( 1 );
	}
	$decoded = json_decode( $raw, true );
	if ( JSON_ERROR_NONE !== json_last_error() ) {
		echo "ABORT: json_decode failed for post {$page_id}: " . json_last_error_msg() . "\n";
		exit( 1 );
	}

	$widget = lunaci_blusher_find_widget_with_fragment( $decoded, $old_image_url );
	if ( null === $widget ) {
		echo "ABORT: could not find a widget containing the old image URL on post {$page_id}\n";
		exit( 1 );
	}
	$widget_id = $widget['id'];
	$html      = $widget['settings']['html'];

	$old_count = substr_count( $html, $old_image_url );
	$new_count = substr_count( $html, $new_image_url );
	echo "widget id={$widget_id}  old image URL occurs {$old_count}x  new image URL already present {$new_count}x\n";

	if ( 1 !== $old_count ) {
		echo "ABORT: expected exactly 1 occurrence of the old image URL, found {$old_count} - refusing to proceed\n";
		exit( 1 );
	}
	if ( 0 !== $new_count ) {
		echo "ABORT: the new image URL is already present - refusing to proceed (already fixed?)\n";
		exit( 1 );
	}

	echo "OK: preconditions satisfied for page {$page_id}\n";

	$page_data[ $page_id ] = array(
		'label'     => $label,
		'widget_id' => $widget_id,
		'raw'       => $raw,
		'html'      => $html,
	);
}

echo "\n=====================================================================\n";
echo "STEP B: COMMIT - race-check, replace, write for both pages\n";
echo "=====================================================================\n";

foreach ( $page_data as $page_id => $data ) {
	echo "\n--- {$data['label']} (post {$page_id}) ---\n";

	$fresh_raw = $wpdb->get_var(
		$wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_elementor_data'", $page_id )
	);
	if ( $fresh_raw !== $data['raw'] ) {
		echo "ABORT: content changed since STEP A (concurrent edit detected) - refusing to write to page {$page_id}\n";
		exit( 1 );
	}
	echo "PASS: race-condition guard confirms content unchanged\n";

	$new_html = str_replace( $old_image_url, $new_image_url, $data['html'] );
	if ( substr_count( $new_html, $new_image_url ) !== 1 || false !== strpos( $new_html, $old_image_url ) ) {
		echo "ABORT: replacement verification failed for page {$page_id}\n";
		exit( 1 );
	}

	$decoded_fresh = json_decode( $fresh_raw, true );

	$set_ok = lunaci_blusher_set_widget_html_by_id( $decoded_fresh, $data['widget_id'], $new_html );
	if ( ! $set_ok ) {
		echo "ABORT: failed to locate widget {$data['widget_id']} in freshly-decoded data for page {$page_id}\n";
		exit( 1 );
	}

	$new_raw = wp_json_encode( $decoded_fresh, JSON_UNESCAPED_SLASHES );
	if ( false === $new_raw || substr_count( $new_raw, $new_image_url ) < 1 ) {
		echo "ABORT: re-encoding verification failed for page {$page_id}\n";
		exit( 1 );
	}

	update_post_meta( $page_id, '_elementor_data', wp_slash( $new_raw ) );
	echo "OK: update_post_meta() succeeded for page {$page_id}\n";

	clean_post_cache( $page_id );
	delete_post_meta( $page_id, '_elementor_css' );
	if ( class_exists( '\Elementor\Plugin' ) ) {
		\Elementor\Plugin::instance()->files_manager->clear_cache();
	}
}

wp_cache_flush();
echo "\nOK: caches cleared for both pages, object cache flushed\n";

echo "\n=====================================================================\n";
echo "STEP C: VERIFY - fresh read-back for both pages\n";
echo "=====================================================================\n";

$all_ok = true;
foreach ( $page_data as $page_id => $data ) {
	echo "\n--- {$data['label']} (post {$page_id}) ---\n";
	$verify_raw = $wpdb->get_var(
		$wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_elementor_data'", $page_id )
	);
	$has_new = $verify_raw && substr_count( $verify_raw, $new_image_url ) === 1;
	$has_old = $verify_raw && false !== strpos( $verify_raw, $old_image_url );
	echo "old image gone: " . ( ! $has_old ? 'yes' : 'no' ) . "   new image present(x1): " . ( $has_new ? 'yes' : 'no' ) . "\n";
	if ( ! $has_new || $has_old ) {
		$all_ok = false;
	}
}

if ( ! $all_ok ) {
	echo "\nFINAL RESULT: FAILURE - see lines above (STEP D skipped)\n";
	exit( 1 );
}

echo "\nOK: image replace confirmed successful on both pages\n";

echo "\n=====================================================================\n";
echo "STEP D: Safe deletion of old attachment (lunaci-blush.jpg)\n";
echo "=====================================================================\n";

$old_attachment = $wpdb->get_row(
	$wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND guid = %s", $old_image_url ),
	ARRAY_A
);

if ( ! $old_attachment ) {
	echo "Old image is not a registered Media Library attachment (orphaned raw file) - nothing to clean up in the database.\n";
	echo "\nFINAL RESULT: SUCCESS (replace only, no attachment row to delete)\n";
	exit( 0 );
}

$old_attachment_id = (int) $old_attachment['ID'];
echo "Old image is attachment ID={$old_attachment_id}\n";

$old_meta  = wp_get_attachment_metadata( $old_attachment_id );
$old_files = array();
if ( $old_meta ) {
	if ( isset( $old_meta['file'] ) ) {
		$old_files[] = basename( $old_meta['file'] );
	}
	if ( isset( $old_meta['sizes'] ) && is_array( $old_meta['sizes'] ) ) {
		foreach ( $old_meta['sizes'] as $size_data ) {
			if ( isset( $size_data['file'] ) ) {
				$old_files[] = $size_data['file'];
			}
		}
	}
}
$old_files = array_unique( $old_files );
echo "Attachment {$old_attachment_id}'s own files (" . count( $old_files ) . "): " . implode( ', ', $old_files ) . "\n";

$conflict_found = false;
if ( ! empty( $old_files ) ) {
	$like_filename      = basename( $old_image_url );
	$other_attachments = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE post_id != %d AND (meta_key = '_wp_attached_file' OR meta_key = '_wp_attachment_metadata') AND meta_value LIKE %s",
			$old_attachment_id,
			'%' . $wpdb->esc_like( $like_filename ) . '%'
		),
		ARRAY_A
	);
	foreach ( $other_attachments as $row ) {
		$other_id    = (int) $row['post_id'];
		$other_meta  = wp_get_attachment_metadata( $other_id );
		$other_files = array();
		if ( $other_meta ) {
			if ( isset( $other_meta['file'] ) ) {
				$other_files[] = basename( $other_meta['file'] );
			}
			if ( isset( $other_meta['sizes'] ) && is_array( $other_meta['sizes'] ) ) {
				foreach ( $other_meta['sizes'] as $size_data ) {
					if ( isset( $size_data['file'] ) ) {
						$other_files[] = $size_data['file'];
					}
				}
			}
		}
		$overlap = array_intersect( $old_files, $other_files );
		echo "Checked other attachment ID={$other_id}: files=" . implode( ', ', $other_files ) . " | overlap: " . ( empty( $overlap ) ? '(none)' : implode( ', ', $overlap ) ) . "\n";
		if ( ! empty( $overlap ) ) {
			$conflict_found = true;
		}
	}
}

if ( $conflict_found ) {
	echo "\nABORT deletion: another attachment's metadata references the same physical file(s) - refusing to delete, mirroring earlier precedent this session.\n";
	echo "\nFINAL RESULT: SUCCESS (replace only, deletion skipped pending investigation)\n";
	exit( 0 );
}

$featured_of = $wpdb->get_var(
	$wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_thumbnail_id' AND meta_value = %s", (string) $old_attachment_id )
);
if ( $featured_of > 0 ) {
	echo "\nABORT deletion: attachment {$old_attachment_id} is used as a featured image on {$featured_of} post(s) - refusing to delete\n";
	echo "\nFINAL RESULT: SUCCESS (replace only, deletion skipped - still used as featured image)\n";
	exit( 0 );
}

$result = wp_delete_attachment( $old_attachment_id, true );
if ( false === $result ) {
	echo "ERROR: wp_delete_attachment({$old_attachment_id}, true) returned false\n";
	echo "\nFINAL RESULT: PARTIAL SUCCESS (replace succeeded, deletion failed)\n";
	exit( 0 );
}

$still_exists = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE ID = %d", $old_attachment_id ) );
if ( $still_exists ) {
	echo "FAIL: attachment {$old_attachment_id} row still exists after deletion attempt\n";
	echo "\nFINAL RESULT: PARTIAL SUCCESS (replace succeeded, deletion verification failed)\n";
	exit( 0 );
}

echo "OK: attachment {$old_attachment_id} confirmed deleted\n";
echo "\nFINAL RESULT: SUCCESS\n";
