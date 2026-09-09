<?php
/**
 * READ-ONLY. LiteSpeed's img_optm-webp rewrite expects sibling files
 * named "file.jpg.webp" but the ones that exist (created earlier by
 * Hostinger's Image Optimization plugin despite its "failed" status)
 * are named "file.webp" (extension replaced, not appended) - so
 * LiteSpeed's automatic rewrite cannot find them. This dumps the exact
 * current <img ...> tag markup for each of the 7 target images on
 * both post 57 (EN) and post 772 (ES) so a precise <picture> wrapper
 * fix can be built against the real strings.
 */

$posts = array( 57 => 'EN', 772 => 'ES' );
$needles = array(
	'lunaimport-hero-luna',
	'lunaimport-collection-face-luna',
	'lunaimport-collection-eyes-luna',
	'lunaimport-collection-lips-luna',
	'lunaimport-collection-nails-luna',
	'lunaimport-origin-crafted-barcelona-luna',
	'lunaimport-why2-luna-replacement',
);

foreach ( $posts as $post_id => $label ) {
	echo "=== Post $post_id ($label) ===\n";
	$data = get_post_meta( $post_id, '_elementor_data', true );
	if ( ! $data ) {
		echo "  (no _elementor_data)\n";
		continue;
	}

	foreach ( $needles as $needle ) {
		if ( preg_match_all( '/<img[^>]*' . preg_quote( $needle, '/' ) . '[^>]*>/i', $data, $matches ) ) {
			foreach ( $matches[0] as $m ) {
				echo "  $m\n";
			}
		} else {
			echo "  (no <img> match for $needle)\n";
		}
	}
	echo "\n";
}

echo "OK: read-only diagnostic complete\n";
