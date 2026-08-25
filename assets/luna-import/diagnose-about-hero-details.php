<?php
/**
 * Read-only diagnostic, follow-up to the About-hero-banner scan: determine
 * (a) whether the current hero image (lunaci-about-hero.png) is a proper
 * WordPress Media Library attachment or an orphaned raw file (affects how
 * it can be safely deleted afterward), and whether it's referenced by any
 * OTHER post/postmeta besides post 59's own widget; and (b) what hero
 * image (if any) the ES translation of About Us (post 680, "Sobre
 * Nosotros") currently uses, so a replacement fix can keep both languages
 * in sync from the start instead of creating another staleness gap like
 * the Home page bug found earlier in this session.
 */

global $wpdb;

$old_image_filename = 'lunaci-about-hero.png';
$old_image_url       = 'https://lunacibarcelona.com/wp-content/uploads/2026/06/lunaci-about-hero.png';

echo "=====================================================================\n";
echo "PART 1: Is {$old_image_filename} a proper Media Library attachment?\n";
echo "=====================================================================\n";

$attachment = $wpdb->get_row(
	$wpdb->prepare(
		"SELECT ID, post_status, guid FROM {$wpdb->posts} WHERE post_type = 'attachment' AND guid = %s",
		$old_image_url
	),
	ARRAY_A
);
if ( $attachment ) {
	echo "FOUND attachment record: ID={$attachment['ID']} status={$attachment['post_status']} guid={$attachment['guid']}\n";
} else {
	echo "NOT a Media Library attachment (orphaned raw file, uploaded directly - like the earlier ingredients-image case)\n";
}

echo "\n=====================================================================\n";
echo "PART 2: Is {$old_image_filename} referenced anywhere OTHER than post 59?\n";
echo "=====================================================================\n";

$referencing_posts = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT ID, post_type, post_status FROM {$wpdb->posts} WHERE ID != 59 AND (post_content LIKE %s)",
		'%' . $wpdb->esc_like( $old_image_filename ) . '%'
	),
	ARRAY_A
);
echo "Other wp_posts.post_content references: " . count( $referencing_posts ) . "\n";
foreach ( $referencing_posts as $p ) {
	echo "  - ID={$p['ID']} type={$p['post_type']} status={$p['post_status']}\n";
}

$referencing_meta = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT post_id, meta_key FROM {$wpdb->postmeta} WHERE post_id != 59 AND meta_value LIKE %s",
		'%' . $wpdb->esc_like( $old_image_filename ) . '%'
	),
	ARRAY_A
);
echo "Other wp_postmeta references: " . count( $referencing_meta ) . "\n";
foreach ( $referencing_meta as $m ) {
	echo "  - post_id={$m['post_id']} meta_key={$m['meta_key']}\n";
}

echo "\n=====================================================================\n";
echo "PART 3: Physical file check on disk\n";
echo "=====================================================================\n";
$upload_dir = wp_upload_dir();
$path       = $upload_dir['basedir'] . '/2026/06/' . $old_image_filename;
echo "Expected path: {$path}\n";
echo "Exists on disk: " . ( file_exists( $path ) ? 'yes (size=' . filesize( $path ) . ' bytes)' : 'no' ) . "\n";

echo "\n=====================================================================\n";
echo "PART 4: ES About Us translation (post 680) - current hero image\n";
echo "=====================================================================\n";

function lunaci_details_find_html_widgets( $node, $path, &$results ) {
	if ( ! is_array( $node ) ) {
		return;
	}
	$current_id     = isset( $node['id'] ) ? $node['id'] : null;
	$current_eltype = isset( $node['elType'] ) ? $node['elType'] : null;
	if ( null !== $current_id && null !== $current_eltype ) {
		$path = $path . '/' . $current_eltype . '[id=' . $current_id . ']';
	}
	if ( 'widget' === $current_eltype && isset( $node['widgetType'] ) && 'html' === $node['widgetType'] ) {
		$results[] = array( 'path' => $path, 'id' => $current_id, 'len' => isset( $node['settings']['html'] ) ? strlen( $node['settings']['html'] ) : 0 );
	}
	foreach ( $node as $key => $value ) {
		if ( is_array( $value ) ) {
			lunaci_details_find_html_widgets( $value, $path, $results );
		}
	}
}
function lunaci_details_get_widget_html_by_id( $node, $target_id ) {
	if ( ! is_array( $node ) ) {
		return null;
	}
	if ( isset( $node['id'], $node['widgetType'] ) && $node['id'] === $target_id && 'html' === $node['widgetType'] ) {
		return isset( $node['settings']['html'] ) ? $node['settings']['html'] : null;
	}
	foreach ( $node as $value ) {
		if ( is_array( $value ) ) {
			$found = lunaci_details_get_widget_html_by_id( $value, $target_id );
			if ( null !== $found ) {
				return $found;
			}
		}
	}
	return null;
}

$raw_680 = $wpdb->get_var(
	$wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_elementor_data'", 680 )
);
if ( null === $raw_680 ) {
	echo "ERROR: no _elementor_data for post 680\n";
} else {
	$decoded_680 = json_decode( $raw_680, true );
	if ( JSON_ERROR_NONE !== json_last_error() ) {
		echo "ERROR: json_decode failed for post 680: " . json_last_error_msg() . "\n";
	} else {
		$widgets_680 = array();
		lunaci_details_find_html_widgets( $decoded_680, '', $widgets_680 );
		echo "HTML widgets in post 680:\n";
		foreach ( $widgets_680 as $w ) {
			echo "  path={$w['path']} id={$w['id']} html_len={$w['len']}\n";
		}
		usort( $widgets_680, function ( $a, $b ) { return $b['len'] - $a['len']; } );
		if ( ! empty( $widgets_680 ) ) {
			$main_680 = $widgets_680[0];
			$html_680 = lunaci_details_get_widget_html_by_id( $decoded_680, $main_680['id'] );
			echo "\nLargest widget (id={$main_680['id']}) image src values:\n";
			if ( $html_680 && preg_match_all( '/<img[^>]*src="([^"]*)"[^>]*>/i', $html_680, $m ) ) {
				foreach ( $m[1] as $src ) {
					echo "  {$src}\n";
				}
			}
			$count_old = $html_680 ? substr_count( $html_680, $old_image_filename ) : 0;
			echo "\nOccurrences of '{$old_image_filename}' in post 680's widget: {$count_old}\n";
		}
	}
}

echo "\nOK: read-only diagnostic complete, no writes performed\n";
