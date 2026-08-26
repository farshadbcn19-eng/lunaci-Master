<?php
/**
 * Guarded fix: every product image on the WooCommerce "All Products"
 * archive is served at the registered "woocommerce_thumbnail" size, which
 * is 300x300px, hard-cropped to a SQUARE (1:1). But every product card on
 * this site's theme displays at roughly a 3:4 portrait aspect ratio
 * (measured live: ~340x455px), so the browser stretches that square
 * 300x300 source via object-fit:cover to fill a much taller box - an
 * uneven ~1.5x upscale that's the real cause of the blur/haze reported.
 *
 * All 14 products' original photos are at least 800x800px (several are
 * 1122x1402, 1086x1448, 1254x1254), so there's room to register a sharper,
 * correctly-proportioned size without upscaling any of them: 600x800,
 * cropped to a 3:4 ratio matching the display - exactly 2x the current
 * width, with no aspect-ratio mismatch left for object-fit to stretch.
 *
 * This only updates the WooCommerce option that controls image
 * generation; a separate `wp media regenerate` step (run right after, in
 * the workflow) regenerates the actual thumbnail files from each
 * attachment's original.
 */

$changes = array(
	'woocommerce_thumbnail_image_width'          => array( 'old' => '300', 'new' => '600' ),
	'woocommerce_thumbnail_cropping'              => array( 'old' => false, 'new' => 'custom' ),
	'woocommerce_thumbnail_cropping_custom_width'  => array( 'old' => false, 'new' => '3' ),
	'woocommerce_thumbnail_cropping_custom_height' => array( 'old' => false, 'new' => '4' ),
);

echo "=====================================================================\n";
echo "STEP A: PREPARE - fresh-read + validate preconditions\n";
echo "=====================================================================\n";

foreach ( $changes as $name => $spec ) {
	$current = get_option( $name );
	echo "{$name}: current=" . var_export( $current, true ) . " expected_old=" . var_export( $spec['old'], true ) . "\n";
	if ( $current !== $spec['old'] ) {
		echo "ABORT: {$name} does not match expected current value - refusing to proceed (already changed?)\n";
		exit( 1 );
	}
}
echo "OK: preconditions satisfied\n";

echo "\n=====================================================================\n";
echo "STEP B: COMMIT - race-check, write\n";
echo "=====================================================================\n";

foreach ( $changes as $name => $spec ) {
	$fresh = get_option( $name );
	if ( $fresh !== $spec['old'] ) {
		echo "ABORT: {$name} changed since STEP A (concurrent edit detected) - refusing to write\n";
		exit( 1 );
	}
}
echo "PASS: race-condition guard confirms all values unchanged\n";

foreach ( $changes as $name => $spec ) {
	$updated = update_option( $name, $spec['new'] );
	echo "update_option('{$name}', '{$spec['new']}') returned: " . var_export( $updated, true ) . "\n";
}

wp_cache_flush();
echo "OK: caches cleared\n";

echo "\n=====================================================================\n";
echo "STEP C: VERIFY - fresh read-back\n";
echo "=====================================================================\n";

$all_ok = true;
foreach ( $changes as $name => $spec ) {
	$verify = get_option( $name );
	$ok = ( $verify === $spec['new'] );
	echo "{$name}: verify=" . var_export( $verify, true ) . " match=" . ( $ok ? 'yes' : 'no' ) . "\n";
	if ( ! $ok ) $all_ok = false;
}

if ( $all_ok ) {
	echo "\nFINAL RESULT: SUCCESS\n";
} else {
	echo "\nFINAL RESULT: FAILURE - see lines above\n";
	exit( 1 );
}
