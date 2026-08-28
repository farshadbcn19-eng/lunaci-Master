<?php
/**
 * Read-only: before adding a banner image to the Contact page, find the
 * page (EN + ES if both exist), confirm its current builder (Elementor
 * HTML widget vs native blocks), and dump enough of its structure to
 * know exactly where/how a banner section should be inserted.
 */

global $wpdb;

echo "=== 1. Find pages with 'contact' in slug or title ===\n";
$pages = $wpdb->get_results(
	"SELECT ID, post_title, post_name, post_status, post_type FROM {$wpdb->posts} WHERE post_type = 'page' AND (post_name LIKE '%contact%' OR post_title LIKE '%Contact%' OR post_title LIKE '%Contacto%') AND post_status = 'publish'",
	ARRAY_A
);
foreach ( $pages as $row ) {
	echo "  ID={$row['ID']} title={$row['post_title']} slug={$row['post_name']} status={$row['post_status']} permalink=" . get_permalink( $row['ID'] ) . "\n";
}

echo "\n=== 2. For each, check _elementor_data presence + length, and page template meta ===\n";
foreach ( $pages as $row ) {
	$page_id = $row['ID'];
	$elementor_data = get_post_meta( $page_id, '_elementor_data', true );
	$edit_mode = get_post_meta( $page_id, '_elementor_edit_mode', true );
	$template = get_post_meta( $page_id, '_wp_page_template', true );
	echo "page {$page_id}: _elementor_edit_mode=" . var_export( $edit_mode, true ) . " template=" . var_export( $template, true ) . " elementor_data_length=" . ( is_string( $elementor_data ) ? strlen( $elementor_data ) : 'N/A' ) . "\n";
	echo "  post_content (native) length: " . strlen( get_post_field( 'post_content', $page_id ) ) . "\n";
}

echo "\n=== 3. If Elementor data exists, list widget types present ===\n";
foreach ( $pages as $row ) {
	$page_id = $row['ID'];
	$raw = get_post_meta( $page_id, '_elementor_data', true );
	if ( ! $raw ) {
		continue;
	}
	$decoded = json_decode( $raw, true );
	if ( JSON_ERROR_NONE !== json_last_error() ) {
		echo "page {$page_id}: could not decode elementor JSON\n";
		continue;
	}
	$widget_types = array();
	$collect = function ( $node ) use ( &$collect, &$widget_types ) {
		if ( is_array( $node ) ) {
			if ( isset( $node['widgetType'] ) ) {
				$widget_types[] = $node['widgetType'];
			}
			if ( isset( $node['elType'] ) ) {
				$widget_types[] = '[elType:' . $node['elType'] . ']';
			}
			foreach ( $node as $child ) {
				$collect( $child );
			}
		}
	};
	$collect( $decoded );
	echo "page {$page_id} widget/element types: " . implode( ', ', array_unique( $widget_types ) ) . "\n";
}

echo "\n=== 4. If native post_content (block editor / classic), dump first 3000 chars ===\n";
foreach ( $pages as $row ) {
	$page_id = $row['ID'];
	$content = get_post_field( 'post_content', $page_id );
	if ( strlen( $content ) > 0 ) {
		echo "--- page {$page_id} post_content (first 3000 chars) ---\n";
		echo substr( $content, 0, 3000 ) . "\n\n";
	}
}

echo "OK: read-only diagnostic complete\n";
