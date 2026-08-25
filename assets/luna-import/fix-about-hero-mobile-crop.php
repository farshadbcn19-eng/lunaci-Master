<?php
/**
 * Guarded fix: About Us hero image showing mostly empty/dark background on
 * mobile instead of the actual photo subject.
 *
 * Root cause: `.lna-hero__bg img{...object-fit:cover;object-position:center
 * top;}`. The hero photo (lunaimport-about-hero-luna.jpg) is a wide
 * landscape composition (1774x887) with the model positioned in roughly
 * the right 45% of the frame - the left/center portion is a dark
 * decorative marble-wall background. On a narrow mobile viewport, the
 * `.lna-hero` container is very tall relative to its width, so
 * `object-fit:cover` scales the image up until it matches the container's
 * height, then crops almost all of the width down to a narrow vertical
 * slice centered horizontally (`object-position:center`). Since the
 * subject sits well to the right of center in the source photo, that
 * centered slice lands mostly in the empty background, showing only a
 * sliver of hair/shoulder at the right edge - confirmed both by a real
 * iPhone screenshot from the user and by re-examining an earlier headless
 * screenshot from this session, which shows the identical crop (this
 * bug is unrelated to the separate 100vh/100dvh mobile-viewport-height fix
 * applied earlier - that fix was still correct/needed, just not what was
 * causing this specific visual problem).
 *
 * Fix: add a mobile-only override (`max-width:768px`) shifting the
 * horizontal object-position further right (78%) so the crop centers on
 * the subject instead of the background. Desktop/tablet (`>768px`, where
 * the container's aspect ratio is closer to the image's own and far less
 * cropping happens) keeps the original `center top` and is unaffected.
 * Only applied to the About Us hero (EN post 59, ES post 680) - the Home
 * hero uses a different photo that already has a rightward-biased
 * `object-position:70% center` baked in for all viewports and was
 * confirmed unaffected by this specific issue.
 */

global $wpdb;

$old_fragment = '.lna-hero__bg img{width:100%;height:100%;object-fit:cover;object-position:center top;}';
$new_fragment = '.lna-hero__bg img{width:100%;height:100%;object-fit:cover;object-position:center top;}@media(max-width:768px){.lna-hero__bg img{object-position:78% top;}}';

$pages = array(
	59  => 'EN About Us',
	680 => 'ES About Us (Sobre Nosotros)',
);

function lunaci_hero_crop_find_widget_with_fragment( $node, $fragment ) {
	if ( ! is_array( $node ) ) {
		return null;
	}
	if ( isset( $node['widgetType'], $node['settings']['html'] ) && 'html' === $node['widgetType'] && false !== strpos( $node['settings']['html'], $fragment ) ) {
		return $node;
	}
	foreach ( $node as $value ) {
		if ( is_array( $value ) ) {
			$found = lunaci_hero_crop_find_widget_with_fragment( $value, $fragment );
			if ( null !== $found ) {
				return $found;
			}
		}
	}
	return null;
}

function lunaci_hero_crop_set_widget_html_by_id( &$node, $target_id, $new_html_value ) {
	if ( ! is_array( $node ) ) {
		return false;
	}
	if ( isset( $node['id'] ) && $node['id'] === $target_id && isset( $node['settings']['html'] ) ) {
		$node['settings']['html'] = $new_html_value;
		return true;
	}
	foreach ( $node as &$value ) {
		if ( is_array( $value ) ) {
			if ( lunaci_hero_crop_set_widget_html_by_id( $value, $target_id, $new_html_value ) ) {
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

	$widget = lunaci_hero_crop_find_widget_with_fragment( $decoded, $old_fragment );
	if ( null === $widget ) {
		echo "ABORT: could not find a widget containing the old fragment on post {$page_id}\n";
		exit( 1 );
	}
	$widget_id = $widget['id'];
	$html      = $widget['settings']['html'];

	$old_count = substr_count( $html, $old_fragment );
	$new_count = substr_count( $html, $new_fragment );
	echo "widget id={$widget_id}  old_fragment occurs {$old_count}x  new_fragment already present {$new_count}x\n";

	if ( 1 !== $old_count ) {
		echo "ABORT: expected exactly 1 occurrence of the old fragment, found {$old_count} - refusing to proceed\n";
		exit( 1 );
	}
	if ( 0 !== $new_count ) {
		echo "ABORT: the new fragment is already present - refusing to proceed (already fixed?)\n";
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

	$new_html = str_replace( $old_fragment, $new_fragment, $data['html'] );
	if ( substr_count( $new_html, $new_fragment ) !== 1 || substr_count( $new_html, $old_fragment ) !== 1 ) {
		echo "ABORT: replacement verification failed for page {$page_id}\n";
		exit( 1 );
	}

	$decoded_fresh = json_decode( $fresh_raw, true );

	$set_ok = lunaci_hero_crop_set_widget_html_by_id( $decoded_fresh, $data['widget_id'], $new_html );
	if ( ! $set_ok ) {
		echo "ABORT: failed to locate widget {$data['widget_id']} in freshly-decoded data for page {$page_id}\n";
		exit( 1 );
	}

	$new_raw = wp_json_encode( $decoded_fresh );
	if ( false === $new_raw || substr_count( $new_raw, $new_fragment ) < 1 ) {
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
	$has_new = $verify_raw && substr_count( $verify_raw, $new_fragment ) === 1;
	echo "new fragment present(x1): " . ( $has_new ? 'yes' : 'no' ) . "\n";
	if ( ! $has_new ) {
		$all_ok = false;
	}
}

echo "\n";
echo $all_ok ? "FINAL RESULT: SUCCESS\n" : "FINAL RESULT: FAILURE - see lines above\n";
