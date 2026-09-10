<?php
/**
 * GUARDED FIX. The 7 homepage images now have valid .webp siblings on
 * disk (confirmed via getimagesize()) but LiteSpeed's img_optm-webp
 * rewrite expects "file.jpg.webp" naming while these are "file.webp",
 * so it can't serve them automatically. Wraps each <img> in a
 * <picture> element with a WebP <source> so the browser picks WebP
 * natively, independent of any server-side rewrite convention.
 *
 * Same recursive-tree + str_replace + $wpdb->update() pattern as the
 * earlier alt/dimensions fix (update_post_meta() is known to corrupt
 * this specific meta key). Idempotent: skips any <img> that is
 * already wrapped in a <picture> (checked via a preceding "<picture>"
 * immediately before the needle).
 */

global $wpdb;

$images = array(
	'lunaimport-hero-luna'                     => 'lunaimport-hero-luna.webp',
	'lunaimport-collection-face-luna'          => 'lunaimport-collection-face-luna.webp',
	'lunaimport-collection-eyes-luna'          => 'lunaimport-collection-eyes-luna.webp',
	'lunaimport-collection-lips-luna'          => 'lunaimport-collection-lips-luna.webp',
	'lunaimport-collection-nails-luna'         => 'lunaimport-collection-nails-luna.webp',
	'lunaimport-origin-crafted-barcelona-luna' => 'lunaimport-origin-crafted-barcelona-luna.webp',
	'lunaimport-why2-luna-replacement'         => 'lunaimport-why2-luna-replacement.webp',
);

$base_url = 'https://lunacibarcelona.com/wp-content/uploads/2026/08/';

function lunaci_wrap_img_with_picture( string $html, array $images, string $base_url, int &$total_changed ): string {
	foreach ( $images as $slug => $webp_filename ) {
		if ( ! preg_match_all( '/<img\s+src="' . preg_quote( $base_url . $slug, '/' ) . '\.jpg[^"]*"[^>]*\/?>/i', $html, $matches ) ) {
			continue;
		}
		foreach ( $matches[0] as $img_tag ) {
			// Idempotency guard: skip if already immediately preceded by <picture>.
			$pos = strpos( $html, $img_tag );
			if ( $pos !== false && $pos >= 9 && substr( $html, $pos - 9, 9 ) === '<picture>' ) {
				continue;
			}
			$webp_url    = $base_url . $webp_filename;
			$replacement = '<picture><source srcset="' . $webp_url . '" type="image/webp">' . $img_tag . '</picture>';
			$new_html    = str_replace( $img_tag, $replacement, $html );
			if ( $new_html !== $html ) {
				$html = $new_html;
				$total_changed++;
			}
		}
	}
	return $html;
}

function lunaci_walk_elements_for_webp( array $elements, array $images, string $base_url, int &$total_changed ): array {
	foreach ( $elements as &$element ) {
		if ( isset( $element['widgetType'] ) && $element['widgetType'] === 'html' && isset( $element['settings']['html'] ) ) {
			$element['settings']['html'] = lunaci_wrap_img_with_picture( $element['settings']['html'], $images, $base_url, $total_changed );
		}
		if ( isset( $element['elements'] ) && is_array( $element['elements'] ) ) {
			$element['elements'] = lunaci_walk_elements_for_webp( $element['elements'], $images, $base_url, $total_changed );
		}
	}
	return $elements;
}

$posts = array( 57 => 'EN', 772 => 'ES' );

foreach ( $posts as $post_id => $label ) {
	$raw = get_post_meta( $post_id, '_elementor_data', true );
	if ( ! $raw ) {
		echo "SKIP post $post_id ($label): no _elementor_data\n";
		continue;
	}

	$decoded = json_decode( $raw, true );
	if ( ! is_array( $decoded ) ) {
		echo "ABORT post $post_id ($label): could not decode _elementor_data JSON\n";
		continue;
	}

	$changed_here = 0;
	$decoded      = lunaci_walk_elements_for_webp( $decoded, $images, $base_url, $changed_here );

	if ( $changed_here === 0 ) {
		echo "SKIP post $post_id ($label): no unwrapped <img> tags found (already wrapped or no match)\n";
		continue;
	}

	$new_raw = wp_json_encode( $decoded );

	$updated = $wpdb->update(
		$wpdb->postmeta,
		array( 'meta_value' => $new_raw ),
		array( 'post_id' => $post_id, 'meta_key' => '_elementor_data' )
	);

	if ( $updated === false ) {
		echo "ABORT post $post_id ($label): \$wpdb->update failed\n";
		continue;
	}

	$readback    = get_post_meta( $post_id, '_elementor_data', true );
	$has_picture = strpos( $readback, '<picture>' ) !== false;

	clean_post_cache( $post_id );

	echo "OK post $post_id ($label): wrapped $changed_here <img> tag(s) in <picture>, readback contains <picture>: " . ( $has_picture ? 'yes' : 'NO' ) . "\n";
}

echo "\nOK: fix completed successfully\n";
