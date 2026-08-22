<?php
$en_id = 759; $slug = 'envio'; $title = 'Envío';
$content = '<!-- wp:paragraph -->
<p>Los pedidos dentro de España llegan en un plazo de 5 a 8 días laborables, sin coste adicional.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Para entregas al resto de Europa, los gastos de envío se calculan en el momento de pagar, según el destino.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Cada pedido se prepara con cuidado y se envía desde Barcelona.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Si tienes alguna duda sobre tu entrega, escríbenos a <a href="mailto:info@lunaci.es">info@lunaci.es</a>.</p>
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
