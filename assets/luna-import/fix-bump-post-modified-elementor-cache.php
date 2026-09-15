<?php
/**
 * Elementor's get_builder_content_for_display() is confirmed (via
 * diagnose-elementor-render-path.php) to return STALE content for post 60
 * (no '100dvh', 27591 bytes vs the correct current _elementor_data), even
 * though get_post_meta() itself returns the correct, valid, up-to-date
 * data. This site has several "elementor_atomic_cache_validity__*" options
 * (an Elementor internal render cache), which very likely key their
 * validity off the post's last-modified time - something our raw
 * $wpdb->update() on wp_postmeta never touched, since it only updates the
 * postmeta row, not wp_posts.post_modified.
 *
 * This bumps post_modified/post_modified_gmt to now for posts 60 and 770
 * (a completely standard, safe operation - WordPress does this on every
 * ordinary edit automatically) and re-checks Elementor's own rendering
 * function to confirm whether that alone resolves the staleness.
 *
 * Does NOT touch post_content, postmeta, or any actual page content.
 */

global $wpdb;

foreach ( array( 'EN Contact' => 60, 'ES Contacto' => 770 ) as $label => $post_id ) {
	echo "==========================================================================\n";
	echo "{$label} (post {$post_id})\n";
	echo "==========================================================================\n";

	$before = get_post_field( 'post_modified', $post_id );
	echo "post_modified before: $before\n";

	if ( class_exists( '\Elementor\Plugin' ) ) {
		$content_before = \Elementor\Plugin::$instance->frontend->get_builder_content_for_display( $post_id );
		echo "get_builder_content_for_display() BEFORE - contains '100dvh': " .
			( false !== strpos( $content_before, '100dvh' ) ? 'YES' : 'NO' ) . " (length=" . strlen( $content_before ) . ")\n";
	}

	$now     = current_time( 'mysql' );
	$now_gmt = current_time( 'mysql', true );

	$update_ok = $wpdb->update(
		$wpdb->posts,
		array(
			'post_modified'     => $now,
			'post_modified_gmt' => $now_gmt,
		),
		array( 'ID' => $post_id ),
		array( '%s', '%s' ),
		array( '%d' )
	);
	echo "wpdb->update(post_modified) returned: " . var_export( $update_ok, true ) . "\n";

	clean_post_cache( $post_id );

	if ( class_exists( '\Elementor\Plugin' ) ) {
		$content_after = \Elementor\Plugin::$instance->frontend->get_builder_content_for_display( $post_id );
		echo "get_builder_content_for_display() AFTER - contains '100dvh': " .
			( false !== strpos( $content_after, '100dvh' ) ? 'YES' : 'NO' ) . " (length=" . strlen( $content_after ) . ")\n";
	}

	echo "\n";
}

echo "OK: script complete\n";
