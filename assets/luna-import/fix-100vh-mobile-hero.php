<?php
/**
 * Guarded fix: mobile hero banner not fully visible on real devices
 * (Home + About Us, EN + ES).
 *
 * Root cause: `.ln-hero`/`.lna-hero{...height:100vh;min-height:600px;...}`.
 * On real mobile Safari (and some Android browsers with a dynamic
 * address-bar/toolbar), `100vh` is calculated using the LARGEST possible
 * viewport - as if the browser chrome were completely hidden - which makes
 * the hero section noticeably TALLER than what's actually visible on
 * screen. Combined with the background image using `object-fit:cover;
 * object-position:center top`, users see mostly the top/dark portion of
 * the cover-cropped image and have to scroll before the actual subject
 * appears. This does not reproduce in headless Chromium testing (no real
 * dynamic toolbar there), which is why an earlier automated screenshot
 * audit missed it - it was only caught via a real iPhone screenshot the
 * user sent.
 *
 * Fix: add `height:100svh` and `height:100dvh` declarations after the
 * existing `height:100vh`. CSS falls back gracefully - a browser that
 * doesn't understand the `svh`/`dvh` units simply ignores those
 * (invalid-value) declarations and keeps the original `100vh`; a browser
 * that does understand them (all current mobile browsers) uses the later,
 * more accurate value. `100dvh` (dynamic viewport height) is listed last
 * so it wins where supported, since it live-adjusts as the toolbar shows/
 * hides - the most correct behavior. No `@supports` needed, and desktop
 * (which isn't affected by this bug) is unaffected either way.
 *
 * Confirmed via a full-site scan (diagnose-100vh-hero-scan.php) that only
 * Home (57, 772) and About Us (59, 680) contain this pattern - Contact and
 * Products do not use a `height:100vh` hero and are unaffected.
 */

global $wpdb;

$old_fragment = 'height:100vh;min-height:600px;';
$new_fragment = 'height:100vh;height:100svh;height:100dvh;min-height:600px;';

$pages = array(
	57  => 'EN Home',
	772 => 'ES Home (Inicio)',
	59  => 'EN About Us',
	680 => 'ES About Us (Sobre Nosotros)',
);

function lunaci_100vh_find_widget_with_fragment( $node, $fragment ) {
	if ( ! is_array( $node ) ) {
		return null;
	}
	if ( isset( $node['widgetType'], $node['settings']['html'] ) && 'html' === $node['widgetType'] && false !== strpos( $node['settings']['html'], $fragment ) ) {
		return $node;
	}
	foreach ( $node as $value ) {
		if ( is_array( $value ) ) {
			$found = lunaci_100vh_find_widget_with_fragment( $value, $fragment );
			if ( null !== $found ) {
				return $found;
			}
		}
	}
	return null;
}

function lunaci_100vh_set_widget_html_by_id( &$node, $target_id, $new_html_value ) {
	if ( ! is_array( $node ) ) {
		return false;
	}
	if ( isset( $node['id'] ) && $node['id'] === $target_id && isset( $node['settings']['html'] ) ) {
		$node['settings']['html'] = $new_html_value;
		return true;
	}
	foreach ( $node as &$value ) {
		if ( is_array( $value ) ) {
			if ( lunaci_100vh_set_widget_html_by_id( $value, $target_id, $new_html_value ) ) {
				return true;
			}
		}
	}
	return false;
}

echo "=====================================================================\n";
echo "STEP A: PREPARE - fresh-read + validate preconditions for all 4 pages\n";
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

	$widget = lunaci_100vh_find_widget_with_fragment( $decoded, $old_fragment );
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
echo "STEP B: COMMIT - race-check, replace, write for all 4 pages\n";
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
	if ( substr_count( $new_html, $new_fragment ) !== 1 || false !== strpos( $new_html, $old_fragment ) ) {
		echo "ABORT: replacement verification failed for page {$page_id}\n";
		exit( 1 );
	}

	$decoded_fresh = json_decode( $fresh_raw, true );

	$set_ok = lunaci_100vh_set_widget_html_by_id( $decoded_fresh, $data['widget_id'], $new_html );
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
echo "\nOK: caches cleared for all 4 pages, object cache flushed\n";

echo "\n=====================================================================\n";
echo "STEP C: VERIFY - fresh read-back for all 4 pages\n";
echo "=====================================================================\n";

$all_ok = true;
foreach ( $page_data as $page_id => $data ) {
	echo "\n--- {$data['label']} (post {$page_id}) ---\n";
	$verify_raw = $wpdb->get_var(
		$wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_elementor_data'", $page_id )
	);
	$has_new = $verify_raw && substr_count( $verify_raw, $new_fragment ) === 1;
	$has_old = $verify_raw && false !== strpos( $verify_raw, $old_fragment );
	echo "new fragment present(x1): " . ( $has_new ? 'yes' : 'no' ) . "   old fragment gone: " . ( ! $has_old ? 'yes' : 'no' ) . "\n";
	if ( ! $has_new || $has_old ) {
		$all_ok = false;
	}
}

echo "\n";
echo $all_ok ? "FINAL RESULT: SUCCESS\n" : "FINAL RESULT: FAILURE - see lines above\n";
