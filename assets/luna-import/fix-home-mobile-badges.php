<?php
/**
 * Guarded fix: Home page mobile badge-row text overflow.
 *
 * Diagnosis (mobile screenshot audit + widget HTML dump): on a 390px
 * viewport, the "European Quality / Premium Ingredients / Cruelty Free /
 * Designed to Accompany" badge row switches to a 2-column grid
 * (`@media(max-width:768px){.ln-badges{grid-template-columns:1fr 1fr;}}`),
 * but the text block next to each icon is a flex item with no
 * `min-width:0`. Flex items default to `min-width:auto`, which prevents
 * them shrinking below their content's natural (single-line) width, so
 * instead of wrapping onto a second line the title/subtitle text overflows
 * past the right edge of the screen and gets visually clipped - e.g.
 * "Premium Ingredients" and "Designed to Accompany" are cut off mid-word.
 *
 * Fix: within the existing mobile media query, reduce badge padding/gap,
 * shrink the icon slightly, add `min-width:0` on the text wrapper so it
 * can shrink and wrap normally, and reduce title/subtitle font-size a
 * touch for better fit. This only affects the <=768px breakpoint - desktop
 * layout is untouched. Applied identically to both EN (post 57) and ES
 * (post 772) Home pages so no new EN/ES staleness gap is introduced.
 */

global $wpdb;

$old_rule = '@media(max-width:768px){.ln-badges{grid-template-columns:1fr 1fr;}.ln-badge{border-bottom:1px solid rgba(212,175,55,.1);}}';
$new_rule = '@media(max-width:768px){.ln-badges{grid-template-columns:1fr 1fr;}.ln-badge{border-bottom:1px solid rgba(212,175,55,.1);padding:20px 16px;gap:10px;}.ln-badge__icon{width:30px;height:30px;font-size:13px;}.ln-badge>div:last-child{min-width:0;}.ln-badge__title{font-size:11px;line-height:1.3;overflow-wrap:break-word;}.ln-badge__sub{font-size:10px;line-height:1.3;overflow-wrap:break-word;}}';

$pages = array(
	57  => 'EN Home',
	772 => 'ES Home (Inicio)',
);

function lunaci_badges_get_widget_node( $node, $target_widget_type ) {
	if ( ! is_array( $node ) ) {
		return null;
	}
	if ( isset( $node['widgetType'] ) && $target_widget_type === $node['widgetType'] && isset( $node['settings']['html'] ) && false !== strpos( $node['settings']['html'], 'ln-badges' ) ) {
		return $node;
	}
	foreach ( $node as $value ) {
		if ( is_array( $value ) ) {
			$found = lunaci_badges_get_widget_node( $value, $target_widget_type );
			if ( null !== $found ) {
				return $found;
			}
		}
	}
	return null;
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

	$widget = lunaci_badges_get_widget_node( $decoded, 'html' );
	if ( null === $widget ) {
		echo "ABORT: could not find the HTML widget containing 'ln-badges' on post {$page_id}\n";
		exit( 1 );
	}
	$widget_id = $widget['id'];
	$html      = $widget['settings']['html'];

	$old_count = substr_count( $html, $old_rule );
	$new_count = substr_count( $html, $new_rule );
	echo "widget id={$widget_id}  old_rule occurs {$old_count}x  new_rule already present {$new_count}x\n";

	if ( 1 !== $old_count ) {
		echo "ABORT: expected exactly 1 occurrence of the old media-query rule, found {$old_count} - refusing to proceed\n";
		exit( 1 );
	}
	if ( 0 !== $new_count ) {
		echo "ABORT: the new rule is already present - refusing to proceed (already fixed?)\n";
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

function lunaci_badges_set_widget_html_by_id( &$node, $target_id, $new_html_value ) {
	if ( ! is_array( $node ) ) {
		return false;
	}
	if ( isset( $node['id'] ) && $node['id'] === $target_id && isset( $node['settings']['html'] ) ) {
		$node['settings']['html'] = $new_html_value;
		return true;
	}
	foreach ( $node as $key => &$value ) {
		if ( is_array( $value ) ) {
			if ( lunaci_badges_set_widget_html_by_id( $value, $target_id, $new_html_value ) ) {
				return true;
			}
		}
	}
	return false;
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

	$new_html = str_replace( $old_rule, $new_rule, $data['html'] );
	if ( substr_count( $new_html, $new_rule ) !== 1 || false !== strpos( $new_html, $old_rule ) ) {
		echo "ABORT: replacement verification failed for page {$page_id}\n";
		exit( 1 );
	}

	$decoded_fresh = json_decode( $fresh_raw, true );

	$set_ok = lunaci_badges_set_widget_html_by_id( $decoded_fresh, $data['widget_id'], $new_html );
	if ( ! $set_ok ) {
		echo "ABORT: failed to locate widget {$data['widget_id']} in freshly-decoded data for page {$page_id}\n";
		exit( 1 );
	}

	$new_raw = wp_json_encode( $decoded_fresh );
	if ( false === $new_raw || substr_count( $new_raw, $new_rule ) < 1 ) {
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
	$has_new = $verify_raw && substr_count( $verify_raw, $new_rule ) === 1;
	$has_old = $verify_raw && false !== strpos( $verify_raw, $old_rule );
	echo "new rule present(x1): " . ( $has_new ? 'yes' : 'no' ) . "   old rule gone: " . ( ! $has_old ? 'yes' : 'no' ) . "\n";
	if ( ! $has_new || $has_old ) {
		$all_ok = false;
	}
}

echo "\n";
echo $all_ok ? "FINAL RESULT: SUCCESS\n" : "FINAL RESULT: FAILURE - see lines above\n";
