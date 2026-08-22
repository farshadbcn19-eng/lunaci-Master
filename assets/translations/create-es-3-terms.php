<?php
$en_id = 676; $slug = 'terminos-de-servicio'; $title = 'Términos de Servicio';
$content = '<!-- wp:paragraph -->
<p><strong>Última actualización:</strong> 1 de agosto de 2026</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">1. Quiénes Somos</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Este sitio web, lunacibarcelona.com, está operado por:</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Patriotic Trade Co.<br>Calle de Balmes, 152, P3 Pta 2, 08008 Barcelona, España<br>NIF/CIF: B65511842<br>Fecha de constitución: 14 de marzo de 2011<br>Nombre comercial: LUNACI Barcelona<br>Contacto: <a href="mailto:info@lunaci.es">info@lunaci.es</a></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Al acceder o utilizar este sitio web, aceptas quedar sujeto a estos Términos de Servicio.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">2. Productos y Precios</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Los precios se indican en euros (EUR) e incluyen el IVA aplicable, salvo que se indique lo contrario. Nos reservamos el derecho de modificar los precios, aunque los cambios no afectarán a los pedidos ya confirmados.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">3. Pedidos</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Realizar un pedido constituye una oferta de compra vinculante. Nos reservamos el derecho de rechazar o cancelar pedidos (falta de stock, errores de precio, sospecha de fraude). Una vez aceptado, se envía una confirmación del pedido por correo electrónico.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">4. Pago</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>El pago se procesa de forma segura a través de nuestro proveedor de pagos externo. No almacenamos los datos completos de la tarjeta en nuestros servidores.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">5. Envío</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Los gastos de envío y los plazos de entrega estimados se muestran en el momento de pagar y son orientativos, no garantizados.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">6. Derecho de Desistimiento</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>De acuerdo con la normativa de protección al consumidor de la UE, puedes desistir de tu compra en un plazo de 14 días naturales desde la recepción del producto, sin necesidad de justificar el motivo.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Excepción por motivos de higiene: por razones de salud e higiene, el derecho de desistimiento no se aplica a productos cosméticos sellados (labios, ojos, rostro) que hayan sido desprecintados o utilizados, salvo que el producto presente algún defecto &#8212; una excepción habitual en la normativa de la UE para productos sensibles por motivos de higiene.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Para ejercer este derecho, escríbenos a <a href="mailto:info@lunaci.es">info@lunaci.es</a> antes de que finalice el plazo. Los productos devueltos deben estar sin usar, sin abrir (cuando proceda) y en su embalaje original.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">7. Devoluciones y Reembolsos</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Una vez recibido e inspeccionado el producto devuelto, el reembolso se procesa mediante el mismo método de pago original en un plazo razonable, conforme a la normativa aplicable.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">8. Garantía Legal</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Los productos cuentan con la garantía legal de conformidad vigente en España (actualmente 3 años desde la entrega), que cubre los defectos existentes en el momento de la entrega.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">9. Propiedad Intelectual</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Todo el contenido del sitio web (textos, imágenes, logotipos, fotografía de producto, diseño) es propiedad de Patriotic Trade Co. / LUNACI Barcelona o de sus licenciantes. Queda prohibida su reproducción sin consentimiento previo por escrito.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">10. Limitación de Responsabilidad</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>En la medida en que lo permita la ley, queda excluida la responsabilidad por daños indirectos o consecuentes, salvo en los casos en que dicha exclusión no esté permitida por la normativa española o de la UE aplicable.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">11. Ley Aplicable y Jurisdicción</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Estos Términos se rigen por la legislación española. Cualquier disputa estará sujeta a la jurisdicción exclusiva de los tribunales de Barcelona, España, sin perjuicio de los derechos irrenunciables de protección al consumidor que asisten a los consumidores de la UE.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">12. Modificaciones de estos Términos</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Estos Términos pueden actualizarse periódicamente; el uso continuado del sitio web tras dichos cambios implica su aceptación.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">13. Contacto</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Si tienes alguna pregunta sobre estos Términos, escríbenos a <a href="mailto:info@lunaci.es">info@lunaci.es</a>.</p>
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
