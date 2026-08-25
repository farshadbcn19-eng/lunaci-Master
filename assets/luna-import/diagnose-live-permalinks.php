<?php
/**
 * Read-only diagnostic: resolve the live front-end permalink for the ES
 * About Us translation (post 680) and a couple of related pages, since the
 * Playwright mobile audit found that https://lunacibarcelona.com/es/sobre-nosotros/
 * returns a 404 ("No se ha podido encontrar la pagina.") - either the slug
 * guessed for the audit script is wrong, or the page itself is broken.
 */

$ids = array(
	59  => 'EN About Us',
	680 => 'ES About Us (guessed slug: sobre-nosotros)',
	56  => 'EN Shop',
	609 => 'ES Shop (Tienda)',
	60  => 'EN Contact',
	770 => 'ES Contact (Contacto)',
);

foreach ( $ids as $id => $label ) {
	$permalink = get_permalink( $id );
	$status    = get_post_status( $id );
	echo "ID={$id} ({$label}): status={$status} permalink=" . ( $permalink ?: '(none)' ) . "\n";
}

echo "\nOK: read-only diagnostic complete, no writes performed\n";
