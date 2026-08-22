<?php
global $wpdb;

function lunaci_create_es_translation( $en_id, $slug, $title, $content ) {
	$existing = get_page_by_path( $slug );
	if ( $existing ) {
		echo "  ABORT for en_id=$en_id: slug '$slug' already exists (ID={$existing->ID})\n";
		return;
	}

	$trid = apply_filters( 'wpml_element_trid', null, $en_id, 'post_page' );
	if ( ! $trid ) {
		echo "  ABORT for en_id=$en_id: could not resolve trid\n";
		return;
	}

	$new_id = wp_insert_post( array(
		'post_title'   => $title,
		'post_name'    => $slug,
		'post_content' => $content,
		'post_status'  => 'publish',
		'post_type'    => 'page',
	), true );

	if ( is_wp_error( $new_id ) ) {
		echo "  ABORT for en_id=$en_id: wp_insert_post failed: " . $new_id->get_error_message() . "\n";
		return;
	}

	do_action( 'wpml_set_element_language_details', array(
		'element_id'           => $new_id,
		'element_type'         => 'post_page',
		'trid'                 => $trid,
		'language_code'        => 'es',
		'source_language_code' => 'en',
	) );

	echo "  OK: en_id=$en_id -> new es_id=$new_id slug=$slug trid=$trid\n";
}

echo "=== Shipping -> Envío ===\n";
lunaci_create_es_translation( 759, 'envio', 'Envío',
'<!-- wp:paragraph -->
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
<!-- /wp:paragraph -->'
);

echo "=== Returns -> Devoluciones ===\n";
lunaci_create_es_translation( 760, 'devoluciones', 'Devoluciones',
'<!-- wp:paragraph -->
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
<!-- /wp:paragraph -->'
);

echo "=== Terms of Service -> Términos de Servicio ===\n";
lunaci_create_es_translation( 676, 'terminos-de-servicio', 'Términos de Servicio',
'<!-- wp:paragraph -->
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
<!-- /wp:paragraph -->'
);

echo "=== Privacy Policy -> Política de Privacidad ===\n";
lunaci_create_es_translation( 3, 'politica-de-privacidad', 'Política de Privacidad',
'<!-- wp:paragraph -->
<p><strong>Última actualización:</strong> 1 de agosto de 2026</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">1. Responsable del Tratamiento</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>El responsable del tratamiento de tus datos personales es:</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Patriotic Trade Co.<br>Calle de Balmes, 152, P3 Pta 2, 08008 Barcelona, España<br>NIF/CIF: B65511842<br>Fecha de constitución: 14 de marzo de 2011<br>Nombre comercial: LUNACI Barcelona<br>Contacto: <a href="mailto:info@lunaci.es">info@lunaci.es</a></p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">2. Qué Datos Personales Recopilamos</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Según cómo interactúes con nuestro sitio web (lunacibarcelona.com), podemos recopilar:</p>
<!-- /wp:paragraph -->

<!-- wp:list -->
<ul class="wp-block-list">
<!-- wp:list-item -->
<li>Datos de identificación y contacto: nombre, dirección de correo electrónico, número de teléfono, dirección de envío y facturación.</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>Datos del pedido: productos comprados, historial de pedidos, confirmación de pago (no almacenamos los números completos de la tarjeta; los pagos los procesa nuestro proveedor de pagos).</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>Datos de la cuenta: si creas una cuenta, tus credenciales de acceso y preferencias.</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>Datos de comunicación: los mensajes que nos envías a través de formularios de contacto o correo electrónico.</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>Datos técnicos: dirección IP, tipo de navegador, información del dispositivo y cookies (consulta nuestra sección de Cookies más abajo).</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>Preferencias de marketing: si te suscribes a nuestro boletín.</li>
<!-- /wp:list-item -->
</ul>
<!-- /wp:list -->

<!-- wp:heading -->
<h2 class="wp-block-heading">3. Finalidad y Base Legal del Tratamiento</h2>
<!-- /wp:heading -->

<!-- wp:list -->
<ul class="wp-block-list">
<!-- wp:list-item -->
<li>Procesar y entregar tus pedidos &#8212; Ejecución de un contrato</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>Gestionar tu cuenta de cliente &#8212; Ejecución de un contrato</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>Responder a tus consultas &#8212; Interés legítimo / consentimiento</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>Enviar comunicaciones de marketing (boletín) &#8212; Consentimiento (suscripción voluntaria)</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>Cumplir con obligaciones fiscales y contables &#8212; Obligación legal</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>Prevenir el fraude y garantizar la seguridad del sitio web &#8212; Interés legítimo</li>
<!-- /wp:list-item -->
</ul>
<!-- /wp:list -->

<!-- wp:heading -->
<h2 class="wp-block-heading">4. Con Quién Compartimos Tus Datos</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Compartimos datos personales únicamente con terceros estrictamente necesarios para operar nuestro negocio, incluyendo: procesadores de pago, empresas de transporte y mensajería, proveedores de alojamiento web y servicios informáticos, y proveedores de servicios de correo electrónico/marketing (solo si te has suscrito). No vendemos tus datos personales a terceros.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">5. Transferencias Internacionales de Datos</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Cuando algún proveedor de servicios se encuentre fuera del Espacio Económico Europeo (EEE), garantizamos que existan las salvaguardas adecuadas (como las Cláusulas Contractuales Tipo) conforme al RGPD.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">6. Conservación de Datos</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Conservamos tus datos personales solo durante el tiempo necesario para cumplir con las finalidades descritas anteriormente, incluyendo cualquier requisito legal, contable o de información (por lo general, los datos de pedidos y facturación se conservan durante el plazo exigido por la normativa fiscal española).</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">7. Tus Derechos</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Conforme al RGPD, tienes derecho a: acceder, rectificar, suprimir (&#8220;derecho al olvido&#8221;), limitar u oponerte al tratamiento, portabilidad de datos, y retirar tu consentimiento en cualquier momento. Para ejercer estos derechos, escríbenos a <a href="mailto:info@lunaci.es">info@lunaci.es</a>. También tienes derecho a presentar una reclamación ante la Agencia Española de Protección de Datos (AEPD, <a href="https://www.aepd.es" target="_blank" rel="noopener">www.aepd.es</a>).</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">8. Cookies</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Nuestro sitio web utiliza cookies para garantizar su funcionalidad básica (por ejemplo, el carrito de compra) y, con tu consentimiento, para analizar el tráfico y personalizar el contenido. Puedes gestionar tus preferencias de cookies desde la configuración de tu navegador en cualquier momento.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">9. Seguridad</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Aplicamos medidas técnicas y organizativas adecuadas para proteger tus datos personales frente a accesos no autorizados, pérdida o uso indebido.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">10. Cambios en esta Política</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Podemos actualizar esta Política de Privacidad periódicamente. La versión actualizada se publicará en esta página con la fecha de &#8220;última actualización&#8221; revisada.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">11. Contacto</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Si tienes alguna pregunta sobre esta Política de Privacidad, escríbenos a <a href="mailto:info@lunaci.es">info@lunaci.es</a>.</p>
<!-- /wp:paragraph -->'
);

echo "\nDONE\n";
