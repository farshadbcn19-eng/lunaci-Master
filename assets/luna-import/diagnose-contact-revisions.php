<?php
/**
 * Three targeted attempts (post_modified bump, atomic cache-validity
 * option deletion) failed to change get_builder_content_for_display()'s
 * output for post 60 at all (byte-identical before/after each time).
 * Check whether a post revision or autosave with STALE _elementor_data is
 * being read instead of the main published post - a known Elementor
 * behavior in some contexts.
 */

$post_id = 60;

echo "--- Revisions of post $post_id ---\n";
$revisions = wp_get_post_revisions( $post_id );
if ( ! $revisions ) {
	echo "no revisions found\n";
} else {
	foreach ( $revisions as $rev ) {
		$rev_data = get_post_meta( $rev->ID, '_elementor_data', true );
		echo "revision ID={$rev->ID}  post_date={$rev->post_date}  post_modified={$rev->post_modified}  has_own_elementor_data=" .
			( $rev_data ? 'YES(len=' . strlen( $rev_data ) . ',has_dvh=' . ( false !== strpos( $rev_data, '100dvh' ) ? 'Y' : 'N' ) . ')' : 'NO' ) . "\n";
	}
}

echo "\n--- Autosave check ---\n";
$autosave = wp_get_post_autosave( $post_id );
echo "autosave exists: " . ( $autosave ? "YES (ID={$autosave->ID})" : 'NO' ) . "\n";

echo "\n--- Elementor Document object check ---\n";
if ( class_exists( '\Elementor\Plugin' ) ) {
	$document = \Elementor\Plugin::$instance->documents->get( $post_id );
	if ( $document ) {
		echo "document found, class: " . get_class( $document ) . "\n";
		echo "document post ID: " . $document->get_main_id() . "\n";
		$doc_elements = $document->get_elements_data();
		$doc_json = wp_json_encode( $doc_elements );
		echo "document->get_elements_data() contains '100dvh': " . ( false !== strpos( $doc_json, '100dvh' ) ? 'YES' : 'NO' ) . " (json length=" . strlen( $doc_json ) . ")\n";

		// Try the document's own render method directly.
		$doc_content = $document->get_content();
		echo "document->get_content() contains '100dvh': " . ( false !== strpos( $doc_content, '100dvh' ) ? 'YES' : 'NO' ) . " (length=" . strlen( $doc_content ) . ")\n";
	} else {
		echo "no document object returned\n";
	}
}

echo "\n--- Direct comparison: fresh get_post_meta vs get_builder_content_for_display, called twice in sequence ---\n";
$meta1 = get_post_meta( $post_id, '_elementor_data', true );
echo "get_post_meta call 1 - has_dvh: " . ( false !== strpos( $meta1, '100dvh' ) ? 'YES' : 'NO' ) . "\n";
$render1 = \Elementor\Plugin::$instance->frontend->get_builder_content_for_display( $post_id );
echo "render call 1 - has_dvh: " . ( false !== strpos( $render1, '100dvh' ) ? 'YES' : 'NO' ) . "\n";
$render2 = \Elementor\Plugin::$instance->frontend->get_builder_content_for_display( $post_id, true );
echo "render call 2 (with_css=true) - has_dvh: " . ( false !== strpos( $render2, '100dvh' ) ? 'YES' : 'NO' ) . "\n";

echo "\nOK: read-only diagnostic complete\n";
