<?php
$en_id = 760; $slug = 'devoluciones'; $title = 'Devoluciones';
$content = '<!-- wp:paragraph -->
<p>Si un producto no cumple tus expectativas, puedes devolverlo en un plazo de 14 días desde la entrega, siempre que esté sin usar y en su embalaje original.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Los gastos de envío de la devolución corren a cargo del cliente.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Una vez recibamos tu devolución, el reembolso se procesa en un plazo de 5 a 7 días laborables.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Para iniciar una devolución, escríbenos a <a href="mailto:info@lunaci.es">info@lunaci.es</a>.</p>
<!-- /wp:paragraph -->';

echo "start\n";
$existing = get_page_by_path( $slug );
if ( $existing ) {
	echo "ABORT: slug '$slug' already exists (ID={$existing->ID})\n";
} else {
	$trid = apply_filters( 'wpml_element_trid', null, $en_id, 'post_page' );
	echo "trid=$trid\n";
	$new_id = wp_insert_post( array(
		'post_title'   => $title,
		'post_name'    => $slug,
		'post_content' => $content,
		'post_status'  => 'publish',
		'post_type'    => 'page',
	), true );
	if ( is_wp_error( $new_id ) ) {
		echo "ABORT: wp_insert_post failed: " . $new_id->get_error_message() . "\n";
	} else {
		do_action( 'wpml_set_element_language_details', array(
			'element_id'           => $new_id,
			'element_type'         => 'post_page',
			'trid'                 => $trid,
			'language_code'        => 'es',
			'source_language_code' => 'en',
		) );
		echo "OK: created es_id=$new_id slug=$slug trid=$trid\n";
	}
}
echo "done\n";
