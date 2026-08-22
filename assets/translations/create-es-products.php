<?php
global $wpdb;
$source_post_id = 61;

// STEP a: pre-check no ES translation exists yet for trid=61.
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

// STEP b: fetch current _elementor_data and core fields from post 61.
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
	// nav (also update internal hrefs to their ES equivalents where an ES page exists)
	'<li><a href="https://lunacibarcelona.com/products/" class="active">Products</a></li>' =>
		'<li><a href="https://lunacibarcelona.com/es/productos/" class="active">Productos</a></li>',
	'<li><a href="https://lunacibarcelona.com/shop/">Shop</a></li>' => '<li><a href="https://lunacibarcelona.com/shop/">Tienda</a></li>',
	'<li><a href="https://lunacibarcelona.com/contact/">Contact</a></li>' => '<li><a href="https://lunacibarcelona.com/es/contacto/">Contacto</a></li>',
	'<a class="nav-cta" href="https://lunacibarcelona.com/shop/">Shop Now</a>' => '<a class="nav-cta" href="https://lunacibarcelona.com/shop/">Comprar Ahora</a>',

	// hero
	'<div class="hero-eyebrow">Product Universe</div>' => '<div class="hero-eyebrow">Universo de Producto</div>',
	'<h1 class="hero-h1">Beauty with<br><em>Purpose</em></h1>' => '<h1 class="hero-h1">Belleza con<br><em>Propósito</em></h1>',
	'<p class="hero-sub">Fourteen essentials, made without compromise — a complete portfolio of vegan luxury cosmetics crafted in Barcelona.</p>' =>
		'<p class="hero-sub">Catorce esenciales, hechos sin concesiones: un portafolio completo de cosmética vegana de lujo creada en Barcelona.</p>',

	// philosophy strip
	'<div class="phil-title">Face</div>' => '<div class="phil-title">Rostro</div>',
	'<div class="phil-desc">The canvas of confidence. Foundations and powders that unify and elevate every complexion.</div>' =>
		'<div class="phil-desc">El lienzo de la confianza. Bases y polvos que unifican y realzan cada tono de piel.</div>',
	'<div class="phil-title">Eye</div>' => '<div class="phil-title">Ojos</div>',
	'<div class="phil-desc">The language of expression. Mascaras, liners and brow pencils that define the gaze with precision.</div>' =>
		'<div class="phil-desc">El lenguaje de la expresión. Máscaras, delineadores y lápices de cejas que definen la mirada con precisión.</div>',
	'<div class="phil-title">Lip</div>' => '<div class="phil-title">Labios</div>',
	'<div class="phil-desc">The signature of presence. Lipsticks, glosses and liners in tones considered for the modern woman.</div>' =>
		'<div class="phil-desc">La firma de la presencia. Labiales, brillos y perfiladores en tonos pensados para la mujer moderna.</div>',
	'<div class="phil-title">Nail</div>' => '<div class="phil-title">Uñas</div>',
	'<div class="phil-desc">The final detail. A long-lasting polish, formulated for individuality down to the fingertips.</div>' =>
		'<div class="phil-desc">El detalle final. Un esmalte de larga duración, formulado para la individualidad hasta la punta de los dedos.</div>',

	// categories section
	'<div class="section-label">Explore Collections</div>' => '<div class="section-label">Explora las Colecciones</div>',
	'<h2 class="section-title">The LUNACI<br><em>Portfolio</em></h2>' => '<h2 class="section-title">El Portafolio<br><em>LUNACI</em></h2>',
	'<p class="section-text">Each collection is conceived as an independent world, unified by a single conviction: that beauty should be felt, not merely observed.</p>' =>
		'<p class="section-text">Cada colección está concebida como un mundo independiente, unificado por una sola convicción: que la belleza debe sentirse, no solo observarse.</p>',

	'alt="Face Collection — LUNACI Barcelona"' => 'alt="Colección Rostro — LUNACI Barcelona"',
	'<div class="cat-tag">Collection I</div>' => '<div class="cat-tag">Colección I</div>',
	'<div class="cat-name">Face</div>' => '<div class="cat-name">Rostro</div>',
	'<div class="cat-count">Foundation · Compact Powder · Blusher</div>' => '<div class="cat-count">Base · Polvo Compacto · Colorete</div>',

	'alt="Eye Collection — LUNACI Barcelona"' => 'alt="Colección Ojos — LUNACI Barcelona"',
	'<div class="cat-tag">Collection II</div>' => '<div class="cat-tag">Colección II</div>',
	'<div class="cat-name">Eye</div>' => '<div class="cat-name">Ojos</div>',
	'<div class="cat-count">Mascara · Eyeliner · Eyebrow & Eye Pencil</div>' => '<div class="cat-count">Máscara · Delineador · Lápiz de Cejas y Ojos</div>',

	'alt="Lip Collection — LUNACI Barcelona"' => 'alt="Colección Labios — LUNACI Barcelona"',
	'<div class="cat-tag">Collection III</div>' => '<div class="cat-tag">Colección III</div>',
	'<div class="cat-name">Lip</div>' => '<div class="cat-name">Labios</div>',
	'<div class="cat-count">Lipstick · Lip Gloss Velvet · Lip Liner · Lip Fix</div>' =>
		'<div class="cat-count">Labial · Brillo de Labios Velvet · Perfilador de Labios · Fijador de Labios</div>',

	'alt="Nail Collection — LUNACI Barcelona"' => 'alt="Colección Uñas — LUNACI Barcelona"',
	'<div class="cat-tag">Collection IV</div>' => '<div class="cat-tag">Colección IV</div>',
	'<div class="cat-name">Nail</div>' => '<div class="cat-name">Uñas</div>',
	'<div class="cat-count">Nail Polish, Long Lasting</div>' => '<div class="cat-count">Esmalte de Uñas de Larga Duración</div>',

	'class="cat-link">Discover Collection</a>' => 'class="cat-link">Descubrir Colección</a>',

	// featured products
	'<div class="section-label">Curated Selection</div>' => '<div class="section-label">Selección Curada</div>',
	'<h2 class="section-title">Signature<br><em>Pieces</em></h2>' => '<h2 class="section-title">Piezas<br><em>Insignia</em></h2>',
	'<div class="prod-badge">Hero Product</div>' => '<div class="prod-badge">Producto Estrella</div>',

	'<div class="prod-cat">Face</div>' => '<div class="prod-cat">Rostro</div>',
	'<div class="prod-desc">Buildable coverage in a breathable, satin finish.</div>' =>
		'<div class="prod-desc">Cobertura ajustable en un acabado satinado y transpirable.</div>',

	'<div class="prod-desc">A soft flush that reads as skin, not colour.</div>' =>
		'<div class="prod-desc">Un rubor suave que se percibe como piel, no como color.</div>',

	'<div class="prod-cat">Lip</div>' => '<div class="prod-cat">Labios</div>',
	'<div class="prod-desc">A matte that doesn\'t ask for attention. It simply holds it.</div>' =>
		'<div class="prod-desc">Un mate que no pide atención. Simplemente la mantiene.</div>',

	'<div class="prod-cat">Eye</div>' => '<div class="prod-cat">Ojos</div>',
	'<div class="prod-desc">Volume and definition in a single stroke.</div>' =>
		'<div class="prod-desc">Volumen y definición en un solo trazo.</div>',

	'class="prod-btn">Discover</a>' => 'class="prod-btn">Descubrir</a>',

	// ingredients / formulation philosophy
	'<div class="section-label">Formulation Philosophy</div>' => '<div class="section-label">Filosofía de Formulación</div>',
	'<h2 class="section-title">Crafted with<br><em>Intention</em></h2>' => '<h2 class="section-title">Creado con<br><em>Intención</em></h2>',
	'<p class="section-text">Every formulation begins with a question: does this ingredient genuinely serve the person using it?</p>' =>
		'<p class="section-text">Cada fórmula comienza con una pregunta: ¿este ingrediente realmente beneficia a quien lo usa?</p>',

	'<strong>Purposeful Formulation</strong>' => '<strong>Formulación con Propósito</strong>',
	'<span>Every ingredient is included for a reason — performance, stability or sensory experience, never for its story alone.</span>' =>
		'<span>Cada ingrediente se incluye por una razón: rendimiento, estabilidad o experiencia sensorial, nunca solo por su historia.</span>',

	'<strong>Evidence Before Claims</strong>' => '<strong>Evidencia Antes que Promesas</strong>',
	'<span>Formulations are guided by testing and technical documentation, not marketing language.</span>' =>
		'<span>Las fórmulas se guían por pruebas y documentación técnica, no por el lenguaje del marketing.</span>',

	'<strong>Considered Sourcing</strong>' => '<strong>Abastecimiento Cuidadoso</strong>',
	'<span>Ingredients are selected for consistency and quality, not simply for trend.</span>' =>
		'<span>Los ingredientes se seleccionan por su consistencia y calidad, no simplemente por tendencia.</span>',

	'<strong>Sensory Refinement</strong>' => '<strong>Refinamiento Sensorial</strong>',
	'<span>Textures developed to transform application from routine into ritual.</span>' =>
		'<span>Texturas desarrolladas para transformar la aplicación de rutina en ritual.</span>',

	// quote
	'<p class="quote-text">Every woman is seen. But your presence is remembered.</p>' =>
		'<p class="quote-text">Todas las mujeres son vistas. Pero tu presencia es recordada.</p>',
	'<div class="quote-source">LUNACI Barcelona — Brand Philosophy</div>' => '<div class="quote-source">LUNACI Barcelona — Filosofía de Marca</div>',

	// cta strip
	'<h2>Ready to begin<br>your ritual?</h2>' => '<h2>¿Lista para comenzar<br>tu ritual?</h2>',
	'<p>Explore the full LUNACI collection and find the pieces that define your presence.</p>' =>
		'<p>Explora la colección completa de LUNACI y encuentra las piezas que definen tu presencia.</p>',
	'<a href="https://lunacibarcelona.com/shop/" class="btn-dark">Shop All Products</a>' =>
		'<a href="https://lunacibarcelona.com/shop/" class="btn-dark">Ver Todos los Productos</a>',
	'<a href="https://lunacibarcelona.com/contact/" class="btn-ghost-dark">Speak to Us</a>' =>
		'<a href="https://lunacibarcelona.com/es/contacto/" class="btn-ghost-dark">Habla con Nosotros</a>',

	// footer
	'<div class="footer-brand-sub">Barcelona · Luxury Beauty</div>' => '<div class="footer-brand-sub">Barcelona · Belleza de Lujo</div>',
	'<p class="footer-brand-text">A Mediterranean Luxury Beauty House. Vegan cosmetics crafted in Barcelona for women who value quality, authenticity and considered presence.</p>' =>
		'<p class="footer-brand-text">Una Casa de Belleza de Lujo Mediterránea. Cosmética vegana creada en Barcelona para mujeres que valoran la calidad, la autenticidad y una presencia cuidada.</p>',

	'<h4>Collections</h4>' => '<h4>Colecciones</h4>',
	'<li><a href="https://lunacibarcelona.com/shop/">Face</a></li>' => '<li><a href="https://lunacibarcelona.com/shop/">Rostro</a></li>',
	'<li><a href="https://lunacibarcelona.com/shop/">Eye</a></li>' => '<li><a href="https://lunacibarcelona.com/shop/">Ojos</a></li>',
	'<li><a href="https://lunacibarcelona.com/shop/">Lip</a></li>' => '<li><a href="https://lunacibarcelona.com/shop/">Labios</a></li>',
	'<li><a href="https://lunacibarcelona.com/shop/">Nail</a></li>' => '<li><a href="https://lunacibarcelona.com/shop/">Uñas</a></li>',

	'<h4>Brand</h4>' => '<h4>Marca</h4>',
	'<li><a href="https://lunacibarcelona.com/about-us/">Our Philosophy</a></li>' =>
		'<li><a href="https://lunacibarcelona.com/es/about-us-es/">Nuestra Filosofía</a></li>',
	'<li><a href="#ingredients">Ingredients</a></li>' => '<li><a href="#ingredients">Ingredientes</a></li>',
	'<li><a href="https://lunacibarcelona.com/contact/">Contact</a></li>' => '<li><a href="https://lunacibarcelona.com/es/contacto/">Contacto</a></li>',

	'<h4>Support</h4>' => '<h4>Ayuda</h4>',
	'<li><a href="https://lunacibarcelona.com/shipping/">Shipping</a></li>' =>
		'<li><a href="https://lunacibarcelona.com/es/envio/">Envío</a></li>',
	'<li><a href="https://lunacibarcelona.com/returns/">Returns</a></li>' =>
		'<li><a href="https://lunacibarcelona.com/es/devoluciones/">Devoluciones</a></li>',
	'<li><a href="https://lunacibarcelona.com/privacy-policy/">Privacy Policy</a></li>' =>
		'<li><a href="https://lunacibarcelona.com/es/politica-de-privacidad/">Política de Privacidad</a></li>',

	'<div class="footer-copy">© 2026 LUNACI Barcelona. All Rights Reserved.</div>' =>
		'<div class="footer-copy">© 2026 LUNACI Barcelona. Todos los derechos reservados.</div>',
	'<div class="footer-copy">Barcelona, Catalonia, Spain &nbsp;·&nbsp; lunacibarcelona.com</div>' =>
		'<div class="footer-copy">Barcelona, Cataluña, España &nbsp;·&nbsp; lunacibarcelona.com</div>',
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
	'post_title'   => 'Productos',
	'post_content' => '',
	'post_parent'  => $orig_post['post_parent'],
	'menu_order'   => $orig_post['menu_order'],
	'post_name'    => 'productos',
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
