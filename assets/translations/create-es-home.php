<?php
global $wpdb;
$source_post_id = 57;

// STEP a: pre-check no ES translation exists yet for trid=57.
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

// STEP b: fetch current _elementor_data and core fields from post 57.
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
	// nav - also point internal links to their ES equivalents where an ES page now exists
	'<a href="https://lunacibarcelona.com/" class="ln-nav__logo">' => '<a href="https://lunacibarcelona.com/es/" class="ln-nav__logo">',
	'<li><a href="https://lunacibarcelona.com/">Home</a></li>' => '<li><a href="https://lunacibarcelona.com/es/">Inicio</a></li>',
	'<li><a href="https://lunacibarcelona.com/products/">Products</a></li>' => '<li><a href="https://lunacibarcelona.com/es/productos/">Productos</a></li>',
	'<li><a href="https://lunacibarcelona.com/about">About</a></li>' => '<li><a href="https://lunacibarcelona.com/es/about-us-es/">Sobre Nosotros</a></li>',
	'<li><a href="https://lunacibarcelona.com/contact">Contact</a></li>' => '<li><a href="https://lunacibarcelona.com/es/contacto/">Contacto</a></li>',

	// hero
	'<span class="ln-hero__eye">Luxury Beauty · Barcelona</span>' => '<span class="ln-hero__eye">Belleza de Lujo · Barcelona</span>',
	'<p class="ln-hero__tagline">Every woman is seen. But your presence is remembered.</p>' =>
		'<p class="ln-hero__tagline">Todas las mujeres son vistas. Pero tu presencia es recordada.</p>',
	'<p class="ln-hero__desc">Inspired by the Mediterranean spirit of Barcelona, LUNACI creates premium cosmetics that celebrate natural beauty through thoughtful design, refined formulations, and quiet confidence.</p>' =>
		'<p class="ln-hero__desc">Inspirada en el espíritu mediterráneo de Barcelona, LUNACI crea cosmética premium que celebra la belleza natural a través de un diseño cuidado, fórmulas refinadas y una confianza serena.</p>',
	'<p class="ln-hero__subline">For women who choose elegance over excess.</p>' =>
		'<p class="ln-hero__subline">Para mujeres que eligen la elegancia sobre el exceso.</p>',
	'<a href="https://lunacibarcelona.com/products/" class="btn-gold">Explore the Collection</a>' =>
		'<a href="https://lunacibarcelona.com/es/productos/" class="btn-gold">Explorar la Colección</a>',

	// badges
	'<div class="ln-badge__title">European Quality</div><div class="ln-badge__sub">Made with care in Europe</div>' =>
		'<div class="ln-badge__title">Calidad Europea</div><div class="ln-badge__sub">Hecho con cuidado en Europa</div>',
	'<div class="ln-badge__title">Premium Ingredients</div><div class="ln-badge__sub">Selected for your skin</div>' =>
		'<div class="ln-badge__title">Ingredientes Premium</div><div class="ln-badge__sub">Seleccionados para tu piel</div>',
	'<div class="ln-badge__title">Cruelty Free</div><div class="ln-badge__sub">No animal testing</div>' =>
		'<div class="ln-badge__title">Libre de Crueldad Animal</div><div class="ln-badge__sub">Sin pruebas en animales</div>',
	'<div class="ln-badge__title">Designed to Accompany</div><div class="ln-badge__sub">Never to compete</div>' =>
		'<div class="ln-badge__title">Diseñado para Acompañar</div><div class="ln-badge__sub">Nunca para competir</div>',

	// marquee (appears twice each - same translation both times, safe with str_replace all)
	'<span>Mediterranean Luxury</span>' => '<span>Lujo Mediterráneo</span>',
	'<span>European Craftsmanship</span>' => '<span>Artesanía Europea</span>',
	'<span>Sophisticated Femininity</span>' => '<span>Feminidad Sofisticada</span>',
	'<span>Timeless Beauty</span>' => '<span>Belleza Atemporal</span>',
	'<span>Barcelona Elegance</span>' => '<span>Elegancia de Barcelona</span>',
	'<span>Premium Formulas</span>' => '<span>Fórmulas Premium</span>',

	// collections section
	'<span class="ln-lbl">Shop By Category</span>' => '<span class="ln-lbl">Compra por Categoría</span>',
	'<h2 class="ln-sec-title">Our <em>Collection</em></h2>' => '<h2 class="ln-sec-title">Nuestra <em>Colección</em></h2>',
	'<p class="ln-sec-intro">Beauty is personal. Every LUNACI collection is created to support your daily ritual with products that feel effortless, comfortable, and naturally elegant.</p>' =>
		'<p class="ln-sec-intro">La belleza es personal. Cada colección LUNACI está creada para acompañar tu ritual diario con productos que se sienten sencillos, cómodos y naturalmente elegantes.</p>',

	'<div class="ln-collection__name">Face</div><div class="ln-collection__p">Shop Face &#8594;</div>' =>
		'<div class="ln-collection__name">Rostro</div><div class="ln-collection__p">Comprar Rostro &#8594;</div>',
	'<div class="ln-collection__name">Eyes</div><div class="ln-collection__p">Shop Eyes &#8594;</div>' =>
		'<div class="ln-collection__name">Ojos</div><div class="ln-collection__p">Comprar Ojos &#8594;</div>',
	'<div class="ln-collection__name">Lips</div><div class="ln-collection__p">Shop Lips &#8594;</div>' =>
		'<div class="ln-collection__name">Labios</div><div class="ln-collection__p">Comprar Labios &#8594;</div>',
	'<div class="ln-collection__name">Nails</div><div class="ln-collection__p">Shop Nails &#8594;</div>' =>
		'<div class="ln-collection__name">Uñas</div><div class="ln-collection__p">Comprar Uñas &#8594;</div>',

	'<a href="https://lunacibarcelona.com/products/" class="btn-out">Shop All Products</a>' =>
		'<a href="https://lunacibarcelona.com/es/productos/" class="btn-out">Ver Todos los Productos</a>',

	// why section
	'<span class="ln-why2__eyebrow">Why Lunaci</span>' => '<span class="ln-why2__eyebrow">Por Qué Lunaci</span>',
	'<h2 class="ln-why2__title">In 15 <em>Seconds</em></h2>' => '<h2 class="ln-why2__title">En 15 <em>Segundos</em></h2>',

	'<div class="ln-why2__name">Mediterranean Inspiration</div>' => '<div class="ln-why2__name">Inspiración Mediterránea</div>',
	'<div class="ln-why2__d">Born in Barcelona, inspired by timeless Mediterranean beauty.</div>' =>
		'<div class="ln-why2__d">Nacida en Barcelona, inspirada en la belleza mediterránea atemporal.</div>',

	'<div class="ln-why2__name">Carefully Developed Formulas</div>' => '<div class="ln-why2__name">Fórmulas Cuidadosamente Desarrolladas</div>',
	'<div class="ln-why2__d">Selected ingredients for everyday confidence.</div>' =>
		'<div class="ln-why2__d">Ingredientes seleccionados para la confianza de cada día.</div>',

	'<div class="ln-why2__name">European Quality</div>' => '<div class="ln-why2__name">Calidad Europea</div>',
	'<div class="ln-why2__d">Attention to detail, consistency, and craftsmanship.</div>' =>
		'<div class="ln-why2__d">Atención al detalle, consistencia y artesanía.</div>',

	'<div class="ln-why2__name">Beauty with Respect</div>' => '<div class="ln-why2__name">Belleza con Respeto</div>',
	'<div class="ln-why2__d">Beauty that feels natural and stays with you.</div>' =>
		'<div class="ln-why2__d">Una belleza que se siente natural y te acompaña.</div>',

	// featured products
	'<span class="ln-lbl">Best Sellers</span>' => '<span class="ln-lbl">Más Vendidos</span>',
	'<h2 class="ln-sec-title">Featured <em>Products</em></h2>' => '<h2 class="ln-sec-title">Productos <em>Destacados</em></h2>',
	'<p class="ln-sec-intro">Discover some of our most-loved essentials.</p>' =>
		'<p class="ln-sec-intro">Descubre algunos de nuestros esenciales más queridos.</p>',
	'Compact Powder<span class="sep">·</span>Lip Fix<span class="sep">·</span>Velvet Lipgloss<span class="sep">·</span>Mascara Volume<span class="sep">·</span>Eye Pencil' =>
		'Polvo Compacto<span class="sep">·</span>Fijador de Labios<span class="sep">·</span>Brillo de Labios Velvet<span class="sep">·</span>Máscara Volumen<span class="sep">·</span>Lápiz de Ojos',
	'class="btn-out ln-rv">View Best Sellers</a>' => 'class="btn-out ln-rv">Ver Más Vendidos</a>',

	// philosophy
	'<p class="ln-philosophy__q ln-rv">We believe beauty is at its strongest when it feels authentic.</p>' =>
		'<p class="ln-philosophy__q ln-rv">Creemos que la belleza es más poderosa cuando se siente auténtica.</p>',
	'<p class="ln-philosophy__q2 ln-rv d1">Luxury is restraint — knowing exactly how much is enough.</p>' =>
		'<p class="ln-philosophy__q2 ln-rv d1">El lujo es contención: saber exactamente cuánto es suficiente.</p>',
	'<p class="ln-philosophy__p ln-rv d2">Every shade, every texture, and every finish is created to become part of your everyday confidence, with elegance and simplicity.</p>' =>
		'<p class="ln-philosophy__p ln-rv d2">Cada tono, cada textura y cada acabado está creado para formar parte de tu confianza diaria, con elegancia y sencillez.</p>',

	// closing
	'<p class="ln-closing__l1 ln-rv">Beauty may be noticed.</p>' => '<p class="ln-closing__l1 ln-rv">La belleza puede notarse.</p>',
	'<p class="ln-closing__l2 ln-rv d1">Presence is remembered.</p>' => '<p class="ln-closing__l2 ln-rv d1">La presencia se recuerda.</p>',

	// newsletter
	'<h2 class="ln-news__h ln-rv">Stay Connected With <em>Lunaci</em></h2>' => '<h2 class="ln-news__h ln-rv">Mantente Conectada con <em>Lunaci</em></h2>',
	'<p class="ln-news__s ln-rv">Be the first to discover new collections, exclusive launches, and beauty inspiration from Barcelona.</p>' =>
		'<p class="ln-news__s ln-rv">Sé la primera en descubrir nuevas colecciones, lanzamientos exclusivos e inspiración de belleza desde Barcelona.</p>',
	'placeholder="Your email address">' => 'placeholder="Tu correo electrónico">',
	'<button class="ln-news__btn">Join Our Community</button>' => '<button class="ln-news__btn">Únete a Nuestra Comunidad</button>',

	// footer
	'<p class="ln-foot__line">Mediterranean Beauty. Quiet Confidence. Timeless Elegance.</p>' =>
		'<p class="ln-foot__line">Belleza Mediterránea. Confianza Serena. Elegancia Atemporal.</p>',

	'<span class="ln-foot__ct">Shop</span>' => '<span class="ln-foot__ct">Tienda</span>',
	'<li><a href="https://lunacibarcelona.com/products/">All Products</a></li>' =>
		'<li><a href="https://lunacibarcelona.com/es/productos/">Todos los Productos</a></li>',
	'<li><a href="https://lunacibarcelona.com/product-category/lips/">Lip</a></li>' =>
		'<li><a href="https://lunacibarcelona.com/product-category/lips/">Labios</a></li>',
	'<li><a href="https://lunacibarcelona.com/product-category/face/">Face</a></li>' =>
		'<li><a href="https://lunacibarcelona.com/product-category/face/">Rostro</a></li>',
	'<li><a href="https://lunacibarcelona.com/product-category/eyes/">Eye</a></li>' =>
		'<li><a href="https://lunacibarcelona.com/product-category/eyes/">Ojos</a></li>',

	'<span class="ln-foot__ct">Brand</span>' => '<span class="ln-foot__ct">Marca</span>',
	'<li><a href="https://lunacibarcelona.com/about">About Lunaci</a></li>' =>
		'<li><a href="https://lunacibarcelona.com/es/about-us-es/">Sobre Lunaci</a></li>',
	'<li><a href="https://lunacibarcelona.com/contact">Contact</a></li>' =>
		'<li><a href="https://lunacibarcelona.com/es/contacto/">Contacto</a></li>',

	'<span class="ln-foot__ct">Support</span>' => '<span class="ln-foot__ct">Ayuda</span>',
	'<li><a href="https://lunacibarcelona.com/shipping">Shipping</a></li>' =>
		'<li><a href="https://lunacibarcelona.com/es/envio/">Envío</a></li>',
	'<li><a href="https://lunacibarcelona.com/returns">Returns</a></li>' =>
		'<li><a href="https://lunacibarcelona.com/es/devoluciones/">Devoluciones</a></li>',
	'<li><a href="https://lunacibarcelona.com/privacy-policy">Privacy Policy</a></li><li><a href="https://lunacibarcelona.com/terms-of-service">Terms of Service</a></li>' =>
		'<li><a href="https://lunacibarcelona.com/es/politica-de-privacidad/">Política de Privacidad</a></li><li><a href="https://lunacibarcelona.com/es/terminos-de-servicio/">Términos de Servicio</a></li>',

	'<p class="ln-foot__c">© 2026 LUNACI Barcelona. All rights reserved.</p>' =>
		'<p class="ln-foot__c">© 2026 LUNACI Barcelona. Todos los derechos reservados.</p>',
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
	'post_title'   => 'Inicio',
	'post_content' => '',
	'post_parent'  => $orig_post['post_parent'],
	'menu_order'   => $orig_post['menu_order'],
	'post_name'    => 'inicio',
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
