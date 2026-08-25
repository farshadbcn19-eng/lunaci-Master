<?php
/**
 * Read-only diagnostic: scan all main EN/ES pages' _elementor_data for the
 * `height:100vh` hero-section anti-pattern. On real mobile Safari (and
 * some Android browsers with a dynamic address-bar/toolbar), `100vh` is
 * calculated using the LARGEST possible viewport (i.e. as if the browser
 * chrome were hidden), which makes a `height:100vh` hero section taller
 * than what's actually visible on screen without scrolling. Combined with
 * `object-position:center top` on the background image, users see mostly
 * the top/dark portion of the hero image and have to scroll to see the
 * actual subject - this does not reproduce in headless browser testing
 * (Playwright/Chromium) since there's no real dynamic toolbar there, which
 * is why automated screenshot testing missed it but a real iPhone caught
 * it (user-reported, with screenshot).
 */

global $wpdb;

$pages = array(
	57  => 'EN Home',
	772 => 'ES Home (Inicio)',
	59  => 'EN About Us',
	680 => 'ES About Us (Sobre Nosotros)',
	60  => 'EN Contact',
	770 => 'ES Contact (Contacto)',
	61  => 'EN Products',
	771 => 'ES Products (Productos)',
);

foreach ( $pages as $page_id => $label ) {
	$raw = $wpdb->get_var(
		$wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_elementor_data'", $page_id )
	);
	if ( null === $raw ) {
		echo "{$label} (post {$page_id}): NO _elementor_data found\n";
		continue;
	}
	$count_100vh  = substr_count( $raw, 'height:100vh' );
	$count_100svh = substr_count( $raw, 'height:100svh' );
	$count_100dvh = substr_count( $raw, 'height:100dvh' );
	echo "{$label} (post {$page_id}): height:100vh occurs {$count_100vh}x, height:100svh occurs {$count_100svh}x, height:100dvh occurs {$count_100dvh}x\n";
}

echo "\nOK: read-only diagnostic complete, no writes performed\n";
