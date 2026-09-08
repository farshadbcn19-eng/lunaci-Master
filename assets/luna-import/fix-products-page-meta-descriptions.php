<?php
/**
 * Guarded fix for the two new items found by the 8 September re-audit.
 *
 * /products/ (post 61) and /es/productos/ (post 771) are standalone
 * Elementor Pages - NOT the same post as the already-fixed WooCommerce
 * shop archive (/shop/ = post 56, /es/tienda/ = post 609) - confirmed by
 * diagnose-products-page-and-shop-redirect.php. Each has its own
 * wp_aioseo_posts row:
 *   - post 61: description = '#post_content' (an AIOSEO smart tag that
 *     falls back to page content; since this Elementor page's real
 *     post_content is mostly empty, AIOSEO auto-generates a snippet from
 *     rendered widget/nav text instead - the garbled result the audit
 *     found).
 *   - post 771: description = NULL (nothing set at all).
 *
 * Only writes if the current value still matches exactly what was
 * confirmed above - safe to re-run.
 */

global $wpdb;
$aioseo_table = $wpdb->prefix . 'aioseo_posts';

$fixes = array(
	61  => array(
		'expected' => '#post_content',
		'new'      => "Explore LUNACI Barcelona's full makeup collection — Mediterranean-made luxury for lips, eyes, face and nails, designed for a presence that lasts.",
	),
	771 => array(
		'expected' => null,
		'new'      => 'Descubre la colección completa de maquillaje de LUNACI Barcelona — lujo mediterráneo para labios, ojos, rostro y uñas, hecho en Barcelona.',
	),
);

$changed = array();
$skipped = array();

foreach ( $fixes as $post_id => $fix ) {
	$row_exists = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$aioseo_table}` WHERE post_id = %d", $post_id ) );
	if ( ! $row_exists ) {
		$skipped[] = "post_id={$post_id} (no aioseo_posts row found)";
		continue;
	}

	$current = $wpdb->get_var( $wpdb->prepare( "SELECT description FROM `{$aioseo_table}` WHERE post_id = %d", $post_id ) );

	$matches_expected = ( $fix['expected'] === null )
		? ( $current === null || trim( (string) $current ) === '' )
		: ( $current === $fix['expected'] );

	if ( ! $matches_expected ) {
		$skipped[] = "post_id={$post_id} (current description: " . var_export( $current, true ) . ' - not the expected starting value, left untouched)';
		continue;
	}

	$result = $wpdb->update( $aioseo_table, array( 'description' => $fix['new'] ), array( 'post_id' => $post_id ) );
	if ( $result !== false ) {
		$readback = $wpdb->get_var( $wpdb->prepare( "SELECT description FROM `{$aioseo_table}` WHERE post_id = %d", $post_id ) );
		if ( $readback === $fix['new'] ) {
			$changed[] = "post_id={$post_id}: description -> '" . substr( $fix['new'], 0, 60 ) . "...'";
		} else {
			$skipped[] = "post_id={$post_id} (write reported success but readback did not match - needs manual check)";
		}
	} else {
		$skipped[] = "post_id={$post_id} (\$wpdb->update failed: {$wpdb->last_error})";
	}
}

echo "--- CHANGED (" . count( $changed ) . ") ---\n";
foreach ( $changed as $c ) {
	echo "  + {$c}\n";
}
echo "\n--- SKIPPED (" . count( $skipped ) . ") ---\n";
foreach ( $skipped as $s ) {
	echo "  - {$s}\n";
}

echo "\nOK: guarded fix complete\n";
