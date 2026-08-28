<?php
/**
 * Guarded fix: move the "LUNACI Blusher" and "LUNACI Compact Powder"
 * products (and their ES counterparts) from the FIRST 2 positions in the
 * shop grid to the LAST 2 positions, per user confirmation. All 14 EN
 * products currently have menu_order=0 (confirmed via
 * diagnose-shop-grid-order.php), so WooCommerce's default "menu_order,
 * title" sorting falls back to alphabetical - which happens to put
 * Blusher and Compact Powder first (B, C come before the rest
 * alphabetically). Assigning explicit ascending menu_order values to all
 * 14 products, with Blusher/Compact Powder getting the two highest
 * values, reproduces the current relative order for the other 12 while
 * moving these two to the end. The same relative order is applied to the
 * 13 ES counterparts that exist (no ES translation was found for
 * "Shadow", so it is left alone - not part of this reorder).
 *
 * menu_order is a native wp_posts column (not postmeta), so this uses
 * $wpdb->update() directly on wp_posts, following the same guarded
 * STEP A/B/C pattern used throughout this session for other wp_posts
 * column writes (e.g. WPCode snippet post_content fixes).
 */

global $wpdb;

// [product_id => new menu_order], EN then ES, matching the confirmed
// current alphabetical order for the "other 12" (or 11 for ES, since
// Shadow has no ES translation), with Blusher/Compact Powder pushed last.
$new_order = array(
	// EN
	332 => 0,  // Eye Liner
	501 => 1,  // Eye Pencil
	497 => 2,  // Eyebrow Pencil
	324 => 3,  // Foundation
	328 => 4,  // Lip Fix
	491 => 5,  // Lip Pencil
	327 => 6,  // Lipgloss Velvet
	329 => 7,  // Lipstick
	330 => 8,  // Mascara Length
	331 => 9,  // Mascara Volume
	502 => 10, // Nail Polish Long Lasting
	515 => 11, // Shadow
	326 => 12, // Blusher (moved to end)
	325 => 13, // Compact Powder (moved to end)
	// ES (Shadow has no ES translation, so no ES entry for it)
	730 => 0,  // Delineador de Ojos (Eye Liner)
	741 => 1,  // Lápiz de Ojos (Eye Pencil)
	737 => 2,  // Lápiz de Cejas (Eyebrow Pencil)
	686 => 3,  // Base de Maquillaje (Foundation)
	711 => 4,  // Fijador de Labios (Lip Fix)
	731 => 5,  // Delineador de Labios (Lip Pencil)
	723 => 6,  // Brillo de Labios Velvet (Lipgloss Velvet)
	685 => 7,  // Pintalabios (Lipstick)
	728 => 8,  // Máscara de Pestañas Alargadora (Mascara Length)
	729 => 9,  // Máscara de Pestañas Voluminizadora (Mascara Volume)
	742 => 10, // Esmalte de Uñas Larga Duración (Nail Polish)
	687 => 12, // Colorete (Blusher, moved to end)
	718 => 13, // Polvo Compacto (Compact Powder, moved to end)
);

echo "--- STEP A: PREPARE ---\n";
$current_state = array();
$overall_success = true;
foreach ( $new_order as $product_id => $target_order ) {
	$row = $wpdb->get_row(
		$wpdb->prepare( "SELECT ID, post_title, menu_order FROM {$wpdb->posts} WHERE ID = %d AND post_type = 'product'", $product_id ),
		ARRAY_A
	);
	if ( ! $row ) {
		echo "ABORT: product {$product_id} not found\n";
		$overall_success = false;
		continue;
	}
	echo "product {$product_id} ('{$row['post_title']}'): current menu_order={$row['menu_order']} -> target={$target_order}\n";
	if ( '0' !== (string) $row['menu_order'] ) {
		echo "  ABORT: expected current menu_order=0 (unmodified precondition), found {$row['menu_order']} - refusing to overwrite unexpected state\n";
		$overall_success = false;
		continue;
	}
	$current_state[ $product_id ] = $row['menu_order'];
}

if ( ! $overall_success ) {
	echo "\nFINAL RESULT: FAILURE - preconditions not satisfied, no writes performed\n";
	exit( 1 );
}
echo "OK: all preconditions satisfied (14 EN + 13 ES products, all currently menu_order=0)\n";

echo "\n--- STEP B: COMMIT ---\n";
foreach ( $new_order as $product_id => $target_order ) {
	$fresh_order = $wpdb->get_var(
		$wpdb->prepare( "SELECT menu_order FROM {$wpdb->posts} WHERE ID = %d", $product_id )
	);
	if ( (string) $fresh_order !== (string) $current_state[ $product_id ] ) {
		echo "ABORT: product {$product_id} menu_order changed since STEP A (concurrent edit) - refusing to write\n";
		$overall_success = false;
		continue;
	}
	$updated = $wpdb->update(
		$wpdb->posts,
		array( 'menu_order' => $target_order ),
		array( 'ID' => $product_id ),
		array( '%d' ),
		array( '%d' )
	);
	echo "product {$product_id}: \$wpdb->update() rows affected: " . var_export( $updated, true ) . "\n";
	clean_post_cache( $product_id );
}

echo "\n--- STEP C: VERIFY ---\n";
foreach ( $new_order as $product_id => $target_order ) {
	$verify_order = $wpdb->get_var(
		$wpdb->prepare( "SELECT menu_order FROM {$wpdb->posts} WHERE ID = %d", $product_id )
	);
	$ok = ( (string) $verify_order === (string) $target_order );
	echo "product {$product_id}: menu_order now={$verify_order} (expected {$target_order})  " . ( $ok ? 'OK' : 'MISMATCH' ) . "\n";
	if ( ! $ok ) {
		$overall_success = false;
	}
}

if ( function_exists( 'wc_delete_shop_order_transients' ) ) {
	wc_delete_shop_order_transients();
}
wp_cache_flush();

echo "\n=====================================================================\n";
if ( $overall_success ) {
	echo "FINAL RESULT: SUCCESS\n";
} else {
	echo "FINAL RESULT: FAILURE - see per-product results above\n";
	exit( 1 );
}
