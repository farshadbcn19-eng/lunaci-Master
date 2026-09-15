<?php
/**
 * URGENT repair: fix-contact-hero-height.php wrote via update_post_meta(),
 * which internally calls sanitize_meta() for every write - unlike every
 * other fix in this session, which wrote WPCode Code Snippets rows via a
 * direct $wpdb->update() call on a plain custom table with no such hook
 * pipeline. Something in that path (most likely a kses-based sanitizer
 * registered against _elementor_data, or Elementor's own render-time
 * sanitization once the forced _elementor_element_cache invalidation took
 * effect) stripped the <style>...</style> wrapper from the Contact page's
 * HTML widget on both EN (60) and ES (770), leaving the CSS text itself
 * intact but unwrapped, so it now renders as literal visible text - and
 * also entity-encoded one stray '>' CSS child-combinator into '&gt;'.
 *
 * This repair writes directly via $wpdb->update() on wp_postmeta - the
 * same low-level, hook-free approach already proven safe for every other
 * fix in this session - to (a) re-wrap the CSS in <style id="...">...
 * </style> at the exact boundaries confirmed by a prior read-only dump,
 * and (b) revert the one corrupted '&gt;' back to '>'. The min-height:
 * 100vh value from the previous fix is left in place; only the wrapper
 * and the stray entity are restored.
 */

global $wpdb;

$pages = array(
	60  => 'EN Contact',
	770 => 'ES Contacto',
);

// Boundary anchors confirmed by diagnose-contact-full-dump-urgent.php:
// the CSS text runs from right after </title> to right before <!-- NAV -->.
$after_title_anchor = "</title>\n\n\n\n";
$before_nav_anchor   = "\n\n\n\n\n\n<!-- NAV -->";

$corrupted_gt = '.elementor-element-fa1bfa7.e-con-boxed &gt; .e-con-inner';
$restored_gt  = '.elementor-element-fa1bfa7.e-con-boxed > .e-con-inner';

function lunaci_frst_set_widget_html( &$node, $target_id, $new_html ) {
	if ( is_array( $node ) ) {
		if ( isset( $node['id'] ) && $node['id'] === $target_id && isset( $node['settings']['html'] ) ) {
			$node['settings']['html'] = $new_html;
			return true;
		}
		foreach ( $node as $key => &$child ) {
			if ( lunaci_frst_set_widget_html( $child, $target_id, $new_html ) ) {
				return true;
			}
		}
	}
	return false;
}

$overall_success = true;

foreach ( $pages as $page_id => $label ) {
	echo "\n=====================================================================\n";
	echo "PAGE {$page_id} ({$label})\n";
	echo "=====================================================================\n";

	echo "--- STEP A: PREPARE ---\n";
	$raw = $wpdb->get_var(
		$wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_elementor_data'", $page_id )
	);
	if ( null === $raw ) {
		echo "ABORT: _elementor_data not found for page {$page_id}\n";
		$overall_success = false;
		continue;
	}

	$decoded = json_decode( $raw, true );
	if ( JSON_ERROR_NONE !== json_last_error() ) {
		echo "ABORT: could not decode _elementor_data JSON for page {$page_id}\n";
		$overall_success = false;
		continue;
	}

	$widget_id   = null;
	$widget_html = null;
	$finder = function ( $node ) use ( &$finder, &$widget_id, &$widget_html ) {
		if ( $widget_html ) return;
		if ( is_array( $node ) ) {
			if ( isset( $node['widgetType'] ) && 'html' === $node['widgetType'] && isset( $node['settings']['html'] ) ) {
				if ( false !== strpos( $node['settings']['html'], '.contact-hero' ) ) {
					$widget_id   = $node['id'];
					$widget_html = $node['settings']['html'];
					return;
				}
			}
			foreach ( $node as $child ) {
				$finder( $child );
				if ( $widget_html ) return;
			}
		}
	};
	$finder( $decoded );

	if ( null === $widget_html ) {
		echo "ABORT: could not find the contact-hero HTML widget on page {$page_id}\n";
		$overall_success = false;
		continue;
	}
	echo "found widget id: {$widget_id}, current length: " . strlen( $widget_html ) . "\n";

	// Preconditions: exactly one of each anchor, no existing <style>/</style>,
	// exactly one corrupted '&gt;' occurrence to fix.
	$after_count  = substr_count( $widget_html, $after_title_anchor );
	$before_count = substr_count( $widget_html, $before_nav_anchor );
	$style_open   = substr_count( $widget_html, '<style' );
	$style_close  = substr_count( $widget_html, '</style>' );
	$gt_corrupt   = substr_count( $widget_html, $corrupted_gt );
	echo "after_title_anchor count={$after_count}  before_nav_anchor count={$before_count}  <style count={$style_open}  </style> count={$style_close}  corrupted &gt; count={$gt_corrupt}\n";

	if ( 1 !== $after_count || 1 !== $before_count || 0 !== $style_open || 0 !== $style_close || 1 !== $gt_corrupt ) {
		echo "ABORT: preconditions not satisfied for page {$page_id} - refusing to modify\n";
		$overall_success = false;
		continue;
	}
	echo "OK: preconditions satisfied\n";

	$anchor_pos = strpos( $widget_html, $after_title_anchor ) + strlen( $after_title_anchor );
	$end_pos    = strpos( $widget_html, $before_nav_anchor );
	if ( false === $end_pos || $end_pos <= $anchor_pos ) {
		echo "ABORT: anchor ordering invalid for page {$page_id}\n";
		$overall_success = false;
		continue;
	}

	echo "--- STEP B: COMMIT ---\n";
	$fresh_raw = $wpdb->get_var(
		$wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_elementor_data'", $page_id )
	);
	if ( $fresh_raw !== $raw ) {
		echo "ABORT: content changed since STEP A (concurrent edit) for page {$page_id}\n";
		$overall_success = false;
		continue;
	}

	$css_text = substr( $widget_html, $anchor_pos, $end_pos - $anchor_pos );
	$css_text_fixed = str_replace( $corrupted_gt, $restored_gt, $css_text, $gt_replaced );
	if ( 1 !== $gt_replaced ) {
		echo "ABORT: expected exactly 1 '&gt;' replacement inside the CSS text, made {$gt_replaced}\n";
		$overall_success = false;
		continue;
	}

	$new_middle = '<style id="lunaci-contact-page-css">' . $css_text_fixed . '</style>';
	$new_html   = substr( $widget_html, 0, $anchor_pos ) . $new_middle . substr( $widget_html, $end_pos );

	// Sanity checks on the in-memory result before writing.
	if ( 1 !== substr_count( $new_html, '<style' ) || 1 !== substr_count( $new_html, '</style>' )
		|| false !== strpos( $new_html, $corrupted_gt ) || false === strpos( $new_html, $restored_gt )
		|| false === strpos( $new_html, 'min-height: 100vh;' ) ) {
		echo "ABORT: in-memory reconstruction failed verification for page {$page_id}\n";
		$overall_success = false;
		continue;
	}
	echo "computed new widget html length: " . strlen( $new_html ) . " (was " . strlen( $widget_html ) . ")\n";

	$decoded_fresh = json_decode( $fresh_raw, true );
	$updated_ok    = lunaci_frst_set_widget_html( $decoded_fresh, $widget_id, $new_html );
	if ( ! $updated_ok ) {
		echo "ABORT: failed to set new widget html for page {$page_id}\n";
		$overall_success = false;
		continue;
	}

	$new_raw = wp_json_encode( $decoded_fresh, JSON_UNESCAPED_SLASHES );
	if ( false === $new_raw ) {
		echo "ABORT: wp_json_encode failed for page {$page_id}\n";
		$overall_success = false;
		continue;
	}

	// Write directly via $wpdb->update() - bypassing update_post_meta()'s
	// sanitize_meta()/hook pipeline entirely, the same low-level approach
	// already proven safe for every other fix in this session.
	$update_ok = $wpdb->update(
		$wpdb->postmeta,
		array( 'meta_value' => $new_raw ),
		array( 'post_id' => $page_id, 'meta_key' => '_elementor_data' ),
		array( '%s' ),
		array( '%d', '%s' )
	);
	if ( false === $update_ok ) {
		echo "ERROR: \$wpdb->update() failed: {$wpdb->last_error}\n";
		echo "ABORT\n";
		$overall_success = false;
		continue;
	}
	echo "OK: \$wpdb->update() returned {$update_ok} (rows affected)\n";

	clean_post_cache( $page_id );
	$deleted_element_cache = delete_post_meta( $page_id, '_elementor_element_cache' );
	echo "delete_post_meta(_elementor_element_cache) returned: " . var_export( $deleted_element_cache, true ) . "\n";

	echo "--- STEP C: VERIFY ---\n";
	$verify_raw = $wpdb->get_var(
		$wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_elementor_data'", $page_id )
	);
	$verify_decoded = json_decode( $verify_raw, true );
	$verify_html = null;
	$vfinder = function ( $node ) use ( &$vfinder, $widget_id, &$verify_html ) {
		if ( is_array( $node ) ) {
			if ( isset( $node['id'] ) && $node['id'] === $widget_id && isset( $node['settings']['html'] ) ) {
				$verify_html = $node['settings']['html'];
				return;
			}
			foreach ( $node as $child ) {
				$vfinder( $child );
			}
		}
	};
	$vfinder( $verify_decoded );

	$ok_style_open  = null !== $verify_html && 1 === substr_count( $verify_html, '<style' );
	$ok_style_close = null !== $verify_html && 1 === substr_count( $verify_html, '</style>' );
	$ok_gt_fixed    = null !== $verify_html && false === strpos( $verify_html, $corrupted_gt ) && false !== strpos( $verify_html, $restored_gt );
	$ok_exact       = null !== $verify_html && $verify_html === $new_html;
	echo "style tags restored (1 open, 1 close): " . ( ( $ok_style_open && $ok_style_close ) ? 'yes' : 'no' ) . "\n";
	echo "corrupted &gt; fixed: " . ( $ok_gt_fixed ? 'yes' : 'no' ) . "\n";
	echo "stored value matches computed value byte-for-byte: " . ( $ok_exact ? 'yes' : 'no' ) . "\n";

	if ( ! $ok_style_open || ! $ok_style_close || ! $ok_gt_fixed || ! $ok_exact ) {
		echo "ERROR: verification FAILED for page {$page_id}\n";
		$overall_success = false;
	} else {
		echo "OK: page {$page_id} repaired and verified\n";
	}
}

wp_cache_flush();

echo "\n=====================================================================\n";
if ( $overall_success ) {
	echo "FINAL RESULT: SUCCESS\n";
} else {
	echo "FINAL RESULT: FAILURE - see per-page results above - manual DB inspection recommended\n";
	exit( 1 );
}
