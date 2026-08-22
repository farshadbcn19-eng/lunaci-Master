<?php
global $wpdb;
$source_post_id = 60;

// STEP a: pre-check no ES translation exists yet for trid=60.
$existing_es = $wpdb->get_results( $wpdb->prepare(
	"SELECT * FROM {$wpdb->prefix}icl_translations WHERE trid = %d AND language_code = 'es'",
	$source_post_id
), ARRAY_A );
if ( ! empty( $existing_es ) ) {
	echo "ABORT: an ES translation already exists for trid=$source_post_id - no write performed\n";
	echo print_r( $existing_es, true );
	exit(1);
}
echo "OK: no existing ES translation found for trid=$source_post_id\n";

// STEP b: fetch current _elementor_data and core fields from post 60.
$raw = $wpdb->get_var( $wpdb->prepare(
	"SELECT meta_value FROM {$wpdb->prefix}postmeta WHERE post_id = %d AND meta_key = '_elementor_data'",
	$source_post_id
) );
if ( $raw === null ) { echo "ABORT: _elementor_data not found for post_id=$source_post_id\n"; exit(1); }
echo "Fetched _elementor_data, length=" . strlen( $raw ) . "\n";

$orig_post = get_post( $source_post_id, ARRAY_A );
if ( ! $orig_post ) { echo "ABORT: post_id=$source_post_id not found\n"; exit(1); }

$edit_mode     = get_post_meta( $source_post_id, '_elementor_edit_mode', true );
$template_type = get_post_meta( $source_post_id, '_elementor_template_type', true );
$page_template = get_post_meta( $source_post_id, '_wp_page_template', true );

// STEP c: decode elementor JSON and locate the html widget.
$data = json_decode( $raw, true );
if ( $data === null ) { echo "ABORT: json_decode failed: " . json_last_error_msg() . "\n"; exit(1); }
if ( ! isset( $data[0]['elements'][0]['settings']['html'] ) ) {
	echo "ABORT: expected path data[0]['elements'][0]['settings']['html'] not found\n";
	exit(1);
}
$html = $data[0]['elements'][0]['settings']['html'];
echo "Extracted html widget content, length=" . strlen( $html ) . "\n";

// STEP d: guarded translation via literal string replacement (small pairs, no giant literals).
$pairs = array(
	'lang="en"' => 'lang="es"',
	'<title>Contact — LUNACI Barcelona</title>' => '<title>Contacto — LUNACI Barcelona</title>',
	'content="Reach LUNACI Barcelona. Visit our atelier in Barcelona, speak with our beauty concierge, or send us a message. We are here to guide you.">' =>
		'content="Ponte en contacto con LUNACI Barcelona. Visita nuestro atelier en Barcelona, habla con nuestra beauty concierge o envíanos un mensaje. Estamos aquí para guiarte.">',
	'<li><a href="products.html">Products</a></li>' => '<li><a href="products.html">Productos</a></li>',
	'<li><a href="shop.html">Shop</a></li>' => '<li><a href="shop.html">Tienda</a></li>',
	'<li><a href="contact.html" class="active">Contact</a></li>' => '<li><a href="contact.html" class="active">Contacto</a></li>',
	'<a class="nav-cta" href="shop.html">Shop Now</a>' => '<a class="nav-cta" href="shop.html">Comprar Ahora</a>',
	'<div class="hero-eyebrow">Reach LUNACI</div>' => '<div class="hero-eyebrow">Contacta con LUNACI</div>',
	'<h1 class="hero-title">We are<br><em>here for you</em></h1>' => '<h1 class="hero-title">Estamos<br><em>aquí para ti</em></h1>',
	'<p class="hero-desc">Whether you seek guidance on a product, a personal beauty consultation, or simply wish to know us better — we welcome every conversation.</p>' =>
		'<p class="hero-desc">Ya sea que busques orientación sobre un producto, una consulta de belleza personalizada, o simplemente quieras conocernos mejor, damos la bienvenida a cada conversación.</p>',
	'<div class="method-label">Email</div>' => '<div class="method-label">Correo Electrónico</div>',
	'<div class="method-sub">Beauty enquiries & general contact.<br>Response within 24 hours.</div>' =>
		'<div class="method-sub">Consultas de belleza y contacto general.<br>Respuesta en 24 horas.</div>',
	'<a href="mailto:info@lunacibarcelona.com" class="method-link">Write to Us</a>' => '<a href="mailto:info@lunacibarcelona.com" class="method-link">Escríbenos</a>',
	'<div class="method-label">Telephone</div>' => '<div class="method-label">Teléfono</div>',
	'<div class="method-sub">Mon – Fri &nbsp;·&nbsp; 10:00 – 18:30<br>Saturday &nbsp;·&nbsp; 10:00 – 15:00</div>' =>
		'<div class="method-sub">Lun – Vie &nbsp;·&nbsp; 10:00 – 18:30<br>Sábado &nbsp;·&nbsp; 10:00 – 15:00</div>',
	'<a href="tel:+3493xxxxxxx" class="method-link">Call Us</a>' => '<a href="tel:+3493xxxxxxx" class="method-link">Llámanos</a>',
	'<div class="method-sub">Instant beauty advice.<br>Available Mon – Sat, 10:00 – 20:00.</div>' =>
		'<div class="method-sub">Asesoría de belleza instantánea.<br>Disponible de Lun a Sáb, 10:00 – 20:00.</div>',
	'<a href="https://wa.me/346xxxxxxxx" class="method-link">Message Us</a>' => '<a href="https://wa.me/346xxxxxxxx" class="method-link">Escríbenos por WhatsApp</a>',
	'<div class="method-label">Beauty Concierge</div>' => '<div class="method-label">Beauty Concierge</div>',
	'<div class="method-value">Personal Consultation</div>' => '<div class="method-value">Consulta Personal</div>',
	'<div class="method-sub">Book a 1-to-1 beauty session with our expert team at our Barcelona atelier.</div>' =>
		'<div class="method-sub">Reserva una sesión de belleza personalizada 1 a 1 con nuestro equipo experto en nuestro atelier de Barcelona.</div>',
	'<a href="#contact-form" class="method-link">Book a Session</a>' => '<a href="#contact-form" class="method-link">Reservar una Sesión</a>',
	'<div class="form-eyebrow">Send a Message</div>' => '<div class="form-eyebrow">Enviar un Mensaje</div>',
	'<h2 class="form-title">Begin a<br><em>Conversation</em></h2>' => '<h2 class="form-title">Inicia una<br><em>Conversación</em></h2>',
	'<p class="form-sub">Complete the form below and a member of our team will respond within 24 hours. For urgent matters, please call us directly.</p>' =>
		'<p class="form-sub">Completa el siguiente formulario y un miembro de nuestro equipo te responderá en 24 horas. Para asuntos urgentes, llámanos directamente.</p>',
	'<label for="first-name">First Name</label>' => '<label for="first-name">Nombre</label>',
	'placeholder="Isabelle"' => 'placeholder="Isabel"',
	'<label for="last-name">Last Name</label>' => '<label for="last-name">Apellidos</label>',
	'<label for="email">Email Address</label>' => '<label for="email">Correo Electrónico</label>',
	'placeholder="your@email.com"' => 'placeholder="tu@email.com"',
	'<label for="phone">Phone (Optional)</label>' => '<label for="phone">Teléfono (Opcional)</label>',
	'<label for="subject">Subject</label>' => '<label for="subject">Asunto</label>',
	'<option value="" disabled selected>Select a topic</option>' => '<option value="" disabled selected>Selecciona un tema</option>',
	'<option>Product Enquiry</option>' => '<option>Consulta sobre Producto</option>',
	'<option>Order & Shipping</option>' => '<option>Pedido y Envío</option>',
	'<option>Beauty Consultation</option>' => '<option>Consulta de Belleza</option>',
	'<option>Press & Media</option>' => '<option>Prensa y Medios</option>',
	'<option>Wholesale & Partnership</option>' => '<option>Mayoristas y Colaboraciones</option>',
	'<option>Other</option>' => '<option>Otro</option>',
	'<label for="message">Your Message</label>' => '<label for="message">Tu Mensaje</label>',
	'placeholder="Tell us how we can help you…"' => 'placeholder="Cuéntanos cómo podemos ayudarte…"',
	'<label for="consent">I agree to the <a href="#">Privacy Policy</a> and consent to LUNACI Barcelona storing and processing my data to respond to this enquiry.</label>' =>
		'<label for="consent">Acepto la <a href="#">Política de Privacidad</a> y doy mi consentimiento para que LUNACI Barcelona almacene y procese mis datos para responder a esta consulta.</label>',
	'<button type="submit" class="submit-btn">Send Message</button>' => '<button type="submit" class="submit-btn">Enviar Mensaje</button>',
	'<div class="info-eyebrow">Our Atelier</div>' => '<div class="info-eyebrow">Nuestro Atelier</div>',
	'<h3 class="info-title">LUNACI Barcelona<br>Flagship Store</h3>' => '<h3 class="info-title">LUNACI Barcelona<br>Tienda Insignia</h3>',
	'<div class="info-block-label">Address</div>' => '<div class="info-block-label">Dirección</div>',
	"Balmes, 152, P3 Pta 2<br>\n            08008 Barcelona, Catalonia<br>\n            Spain" =>
		"Balmes, 152, P3 Pta 2<br>\n            08008 Barcelona, Cataluña<br>\n            España",
	'<div class="info-block-label">Opening Hours</div>' => '<div class="info-block-label">Horario de Apertura</div>',
	'<tr><td>Monday – Friday</td><td>10:00 – 19:00</td></tr>' => '<tr><td>Lunes – Viernes</td><td>10:00 – 19:00</td></tr>',
	'<tr><td>Saturday</td><td>10:00 – 16:00</td></tr>' => '<tr><td>Sábado</td><td>10:00 – 16:00</td></tr>',
	'<tr class="closed"><td>Sunday</td><td>Closed</td></tr>' => '<tr class="closed"><td>Domingo</td><td>Cerrado</td></tr>',
	'<div class="info-block-label">Contact</div>' => '<div class="info-block-label">Contacto</div>',
	'style="font-size:10px;letter-spacing:3px;text-transform:uppercase;color:var(--gold);margin-bottom:16px">Follow LUNACI</div>' =>
		'style="font-size:10px;letter-spacing:3px;text-transform:uppercase;color:var(--gold);margin-bottom:16px">Síguenos</div>',
	'<div class="map-eyebrow">Visit Us</div>' => '<div class="map-eyebrow">Visítanos</div>',
	"Balmes, 152, P3 Pta 2<br>\n      08008 Barcelona<br>\n      Catalonia, Spain" =>
		"Balmes, 152, P3 Pta 2<br>\n      08008 Barcelona<br>\n      Cataluña, España",
	'<p class="map-note">Located in the heart of the Gràcia neighbourhood — a vibrant Barcelona district celebrated for its creativity, elegance and independent spirit. Metro: Diagonal (L3, L5)</p>' =>
		'<p class="map-note">Ubicado en el corazón del barrio de Gràcia, un vibrante distrito de Barcelona célebre por su creatividad, elegancia y espíritu independiente. Metro: Diagonal (L3, L5)</p>',
	'class="directions-btn">Get Directions</a>' => 'class="directions-btn">Cómo Llegar</a>',
	'<div class="faq-section-label">Common Questions</div>' => '<div class="faq-section-label">Preguntas Comunes</div>',
	'<h2 class="faq-title">Frequently<br><em>Asked</em></h2>' => '<h2 class="faq-title">Preguntas<br><em>Frecuentes</em></h2>',
	'Before reaching out, you may find your answer below. If not, our team is always happy to help — simply complete the contact form above.' =>
		'Antes de contactarnos, es posible que encuentres tu respuesta aquí abajo. Si no, nuestro equipo estará encantado de ayudarte: simplemente completa el formulario de contacto de arriba.',
	'Do you offer free shipping?' => '¿Ofrecéis envío gratuito?',
	'Yes. All orders over €120 within Europe are shipped complimentary. Standard delivery takes 2–5 business days. Express options are also available at checkout.' =>
		'Sí. Todos los pedidos superiores a 120€ dentro de Europa se envían de forma gratuita. La entrega estándar tarda de 2 a 5 días laborables. También hay opciones de envío exprés disponibles al finalizar la compra.',
	'What is your return policy?' => '¿Cuál es vuestra política de devoluciones?',
	'We accept returns of unopened, unused products within 30 days of receipt. Please contact our team to initiate the process. Personalised items cannot be returned.' =>
		'Aceptamos devoluciones de productos sin abrir y sin usar dentro de los 30 días posteriores a la recepción. Ponte en contacto con nuestro equipo para iniciar el proceso. Los artículos personalizados no pueden devolverse.',
	'Are LUNACI products cruelty-free?' => '¿Los productos LUNACI están libres de crueldad animal?',
	'Yes. All LUNACI formulations are cruelty-free. We do not test on animals at any stage of production, and we require the same commitment from all our ingredient suppliers.' =>
		'Sí. Todas las fórmulas de LUNACI están libres de crueldad animal. No realizamos pruebas en animales en ninguna etapa de la producción, y exigimos el mismo compromiso a todos nuestros proveedores de ingredientes.',
	'Can I book a personal beauty consultation?' => '¿Puedo reservar una consulta de belleza personalizada?',
	'Absolutely. Our Beauty Concierge service offers 1-to-1 consultations at our Barcelona atelier. Sessions are available by appointment. Use the contact form above or call us directly.' =>
		'Por supuesto. Nuestro servicio Beauty Concierge ofrece consultas 1 a 1 en nuestro atelier de Barcelona. Las sesiones están disponibles con cita previa. Utiliza el formulario de contacto de arriba o llámanos directamente.',
	'Do you ship internationally?' => '¿Enviáis a nivel internacional?',
	'We currently ship across Europe and selected Middle East markets. International shipping rates and timescales are calculated at checkout based on destination.' =>
		'Actualmente enviamos a toda Europa y a mercados seleccionados de Oriente Medio. Las tarifas y los plazos de envío internacional se calculan al finalizar la compra según el destino.',
	'How can I track my order?' => '¿Cómo puedo hacer seguimiento de mi pedido?',
	'Once your order is dispatched you will receive a confirmation email containing your tracking number and a link to follow your delivery in real time.' =>
		'Una vez que tu pedido sea enviado, recibirás un correo electrónico de confirmación con tu número de seguimiento y un enlace para seguir tu entrega en tiempo real.',
	'<h2>Stay close to<br>LUNACI</h2>' => '<h2>Mantente cerca de<br>LUNACI</h2>',
	'<p>New collections, beauty rituals and exclusive offers — delivered with intention.</p>' =>
		'<p>Nuevas colecciones, rituales de belleza y ofertas exclusivas, entregadas con intención.</p>',
	'placeholder="Your email address" aria-label="Email"' => 'placeholder="Tu correo electrónico" aria-label="Correo electrónico"',
	'<button class="nl-btn" type="submit">Subscribe</button>' => '<button class="nl-btn" type="submit">Suscribirse</button>',
	'<div class="footer-brand-sub">Barcelona · Luxury Beauty</div>' => '<div class="footer-brand-sub">Barcelona · Belleza de Lujo</div>',
	'<p class="footer-brand-text">A Mediterranean luxury beauty house crafting products for the modern woman who values quality, authenticity and meaningful presence.</p>' =>
		'<p class="footer-brand-text">Una casa de belleza de lujo mediterránea que crea productos para la mujer moderna que valora la calidad, la autenticidad y una presencia con sentido.</p>',
	'<h4>Collections</h4>' => '<h4>Colecciones</h4>',
	'<li><a href="/shop/">Lips</a></li>' => '<li><a href="/shop/">Labios</a></li>',
	'<li><a href="/shop/">Eyes</a></li>' => '<li><a href="/shop/">Ojos</a></li>',
	'<li><a href="/shop/">Face</a></li>' => '<li><a href="/shop/">Rostro</a></li>',
	'<li><a href="/shop/">Nails</a></li>' => '<li><a href="/shop/">Uñas</a></li>',
	'<h4>Brand</h4>' => '<h4>Marca</h4>',
	'<li><a href="products.html">Our Philosophy</a></li>' => '<li><a href="products.html">Nuestra Filosofía</a></li>',
	'<li><a href="#">Ingredients</a></li>' => '<li><a href="#">Ingredientes</a></li>',
	'<li><a href="#">Sustainability</a></li>' => '<li><a href="#">Sostenibilidad</a></li>',
	'<li><a href="#">Press</a></li>' => '<li><a href="#">Prensa</a></li>',
	'<h4>Support</h4>' => '<h4>Ayuda</h4>',
	'<li><a href="contact.html">Contact</a></li>' => '<li><a href="contact.html">Contacto</a></li>',
	'<li><a href="#">Shipping & Returns</a></li>' => '<li><a href="#">Envíos y Devoluciones</a></li>',
	'<li><a href="#">FAQ</a></li>' => '<li><a href="#">Preguntas Frecuentes</a></li>',
	'<li><a href="#">Store Locator</a></li>' => '<li><a href="#">Localizador de Tiendas</a></li>',
	'<div class="footer-copy">© 2026 LUNACI Barcelona. All Rights Reserved.</div>' => '<div class="footer-copy">© 2026 LUNACI Barcelona. Todos los derechos reservados.</div>',
	'<div class="footer-copy">Barcelona, Catalonia, Spain &nbsp;·&nbsp; lunacibarcelona.com</div>' => '<div class="footer-copy">Barcelona, Cataluña, España &nbsp;·&nbsp; lunacibarcelona.com</div>',
);

$any_mismatch = false;
echo "\n=== PRE-CHECK: verifying every search phrase exists at least once ===\n";
foreach ( $pairs as $old => $new ) {
	$count = substr_count( $html, $old );
	$label = ( strlen( $old ) > 70 ) ? substr( $old, 0, 67 ) . '...' : $old;
	if ( $count < 1 ) {
		echo "MISSING (count=0): '$label'\n";
		$any_mismatch = true;
	}
}

if ( $any_mismatch ) {
	echo "\nABORT: one or more search phrases not found in live content - no write performed\n";
	exit(1);
}
echo "OK: all " . count( $pairs ) . " search phrases found\n";

$new_html = $html;
foreach ( $pairs as $old => $new ) {
	$new_html = str_replace( $old, $new, $new_html );
}
echo "\nTranslated html length=" . strlen( $new_html ) . " (was " . strlen( $html ) . ")\n";

// STEP e: write translated html back into the decoded structure and re-encode.
$data[0]['elements'][0]['settings']['html'] = $new_html;
$new_raw = json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
if ( $new_raw === false ) { echo "ABORT: json_encode failed: " . json_last_error_msg() . "\n"; exit(1); }
echo "Re-encoded _elementor_data, length=" . strlen( $new_raw ) . "\n";

// sanity: re-decode and confirm structure/element count unchanged.
$decoded_check = json_decode( $new_raw, true );
if ( $decoded_check === null || count( $decoded_check ) !== count( $data ) ) {
	echo "ABORT: post-encode sanity check failed\n";
	exit(1);
}
echo "OK: post-encode sanity check passed\n";

// STEP f: create the new draft page.
$new_post_id = wp_insert_post( array(
	'post_type'    => 'page',
	'post_status'  => 'draft',
	'post_title'   => 'Contacto',
	'post_content' => '',
	'post_parent'  => $orig_post['post_parent'],
	'menu_order'   => $orig_post['menu_order'],
	'post_name'    => 'contacto',
), true );

if ( is_wp_error( $new_post_id ) ) {
	echo "ABORT: wp_insert_post failed: " . $new_post_id->get_error_message() . "\n";
	exit(1);
}
echo "\nCreated new draft post_id=$new_post_id\n";

// STEP g: copy Elementor meta as-is, write translated _elementor_data.
update_post_meta( $new_post_id, '_elementor_edit_mode', $edit_mode );
update_post_meta( $new_post_id, '_elementor_template_type', $template_type );
update_post_meta( $new_post_id, '_wp_page_template', $page_template );
$result = update_post_meta( $new_post_id, '_elementor_data', wp_slash( $new_raw ) );
echo "update_post_meta(_elementor_data) result: " . var_export( $result, true ) . "\n";

// STEP h: register the WPML translation link via the official hook.
do_action( 'wpml_set_element_language_details', array(
	'element_id'           => $new_post_id,
	'element_type'         => 'post_page',
	'trid'                 => $source_post_id,
	'language_code'        => 'es',
	'source_language_code' => 'en',
) );
echo "\nCalled wpml_set_element_language_details for element_id=$new_post_id, trid=$source_post_id, language_code=es\n";

// STEP i: verify.
$verify = $wpdb->get_results( $wpdb->prepare(
	"SELECT * FROM {$wpdb->prefix}icl_translations WHERE trid = %d", $source_post_id
), ARRAY_A );
echo "\nVERIFY: icl_translations rows for trid=$source_post_id\n";
echo print_r( $verify, true );
echo "row count: " . count( $verify ) . " (want 2)\n";

echo "\nNew post edit URL: " . admin_url( "post.php?post=$new_post_id&action=edit" ) . "\n";
echo "\nOK: guarded write completed - new post is a DRAFT, not published\n";
