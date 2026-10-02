<?php
/**
 * LUNACI Phase 0 (brief 2026-10-01): Contact page, legal entity/email,
 * About footer social links, WooCommerce (Shadow private + selling
 * countries).
 *
 * Usage (from the workflow):
 *   wp eval-file phase0-contact-legal-woo.php dry-run <backup_dir>
 *   wp eval-file phase0-contact-legal-woo.php apply   <backup_dir>
 *
 * dry-run = read-only diagnose. Reports current state, runs every guard
 * and transform in memory, and prints what WOULD change. No writes, no
 * backups.
 * apply   = backs up every post / option it touches into <backup_dir>
 *           (outside the web root) BEFORE writing, then writes with
 *           read-verify-write-verify.
 *
 * Every Elementor post is all-or-nothing: if any guard fails for a post,
 * nothing is written for that post.
 *
 * Live state diagnosed 2026-10-02 from the rendered pages:
 *   Contact EN 60 / ES 770: Elementor HTML widget containing the
 *   ".contact-hero" CSS, with section comment markers
 *   (CONTACT METHODS / CONTACT MAIN / INFO / MAP / FAQ / NEWSLETTER / FOOTER).
 *   About EN 59 / ES 680: footer ".lna-foot__s" with IG/TK/PT href="#".
 *   Legal EN 3 / 676 / 759 / 760, ES 769 / 768 / 765 / 766: post_content
 *   (not Elementor). Shipping/Returns also carry info@lunaci.es in their
 *   AIOSEO meta description.
 *   Home EN 57 / ES 772 carry the same IG/TK/PT footer. They are NOT
 *   touched (hard rule: Home out of scope). Their _elementor_data md5 is
 *   recorded before and after to prove that.
 *
 * Writes go through $wpdb->update() rather than update_post_meta() or
 * wp_update_post(). Earlier this year, the KSES pipeline corrupted the
 * Contact <style> tags.
 */

global $wpdb;

$mode       = $args[0] ?? 'dry-run';
$backup_dir = $args[1] ?? '';
$apply      = ( 'apply' === $mode );

if ( ! in_array( $mode, array( 'dry-run', 'apply' ), true ) ) {
	echo "ABORT: mode must be 'dry-run' or 'apply' (got '{$mode}')\n";
	exit( 1 );
}
if ( $apply ) {
	if ( '' === $backup_dir || ! is_dir( $backup_dir ) || ! is_writable( $backup_dir ) ) {
		echo "ABORT: apply mode needs an existing writable backup dir (got '{$backup_dir}')\n";
		exit( 1 );
	}
}

echo "MODE: {$mode}\n";
echo $apply ? "BACKUP DIR: {$backup_dir}\n" : "(dry-run: nothing is written)\n";

// wp eval-file includes this file inside a method, so top-level variables
// are not real globals. Shared state lives in $GLOBALS explicitly.
$GLOBALS['p0_apply']      = $apply;
$GLOBALS['p0_backup_dir'] = $backup_dir;
$GLOBALS['p0_fail']       = 0;

function p0_h( $title ) {
	echo "\n==========================================================================\n{$title}\n==========================================================================\n";
}

function p0_backup_write( $name, $data ) {
	$path  = rtrim( $GLOBALS['p0_backup_dir'], '/' ) . '/' . $name;
	$bytes = file_put_contents( $path, wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
	if ( false === $bytes ) {
		echo "ERROR: could not write backup {$path}\n";
		return false;
	}
	echo "backup: {$path} ({$bytes} bytes)\n";
	return true;
}

/** Full backup of a post: row, every postmeta row, AIOSEO row. */
function p0_backup_post( $post_id ) {
	global $wpdb;
	$post = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->posts} WHERE ID = %d", $post_id ), ARRAY_A );
	$meta = $wpdb->get_results( $wpdb->prepare( "SELECT meta_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d ORDER BY meta_id", $post_id ), ARRAY_A );
	$aio  = null;
	$tbl  = $wpdb->prefix . 'aioseo_posts';
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $tbl ) ) === $tbl ) {
		$aio = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$tbl}` WHERE post_id = %d", $post_id ), ARRAY_A );
	}
	return p0_backup_write( "post-{$post_id}.json", array( 'post' => $post, 'postmeta' => $meta, 'aioseo_posts' => $aio ) );
}

/** Walk Elementor elements; apply $fn to the single HTML widget whose html contains $needle. */
function p0_walk( array $nodes, $needle, callable $fn, &$hits ) {
	foreach ( $nodes as $k => $node ) {
		if ( ! is_array( $node ) ) {
			continue;
		}
		if ( 'html' === ( $node['widgetType'] ?? '' )
			&& isset( $node['settings']['html'] ) && is_string( $node['settings']['html'] )
			&& false !== strpos( $node['settings']['html'], $needle ) ) {
			$hits++;
			$nodes[ $k ]['settings']['html'] = $fn( $node['settings']['html'] );
		}
		if ( ! empty( $node['elements'] ) && is_array( $node['elements'] ) ) {
			$nodes[ $k ]['elements'] = p0_walk( $node['elements'], $needle, $fn, $hits );
		}
	}
	return $nodes;
}

/** Replace from $start marker up to (not including) $end marker. Both must occur exactly once, in order. */
function p0_between( $html, $start, $end, $replacement, $include_end, &$errors ) {
	$cs = substr_count( $html, $start );
	$ce = substr_count( $html, $end );
	if ( 1 !== $cs || 1 !== $ce ) {
		$errors[] = "marker count: '{$start}'={$cs}, '{$end}'={$ce} (expected 1 / 1)";
		return $html;
	}
	$a = strpos( $html, $start );
	$b = strpos( $html, $end );
	if ( $b <= $a ) {
		$errors[] = "marker order: '{$end}' is not after '{$start}'";
		return $html;
	}
	if ( $include_end ) {
		$b += strlen( $end );
	}
	return substr( $html, 0, $a ) . $replacement . substr( $html, $b );
}

function p0_preg_once( $pattern, $replacement, $html, $label, &$errors ) {
	$out = preg_replace( $pattern, $replacement, $html, -1, $count );
	if ( 1 !== $count ) {
		$errors[] = "{$label}: expected exactly 1 match, found {$count}";
		return $html;
	}
	return $out;
}

function p0_str_once( $search, $replace, $html, $label, &$errors ) {
	$count = substr_count( $html, $search );
	if ( 1 !== $count ) {
		$errors[] = "{$label}: expected exactly 1 occurrence, found {$count}";
		return $html;
	}
	return str_replace( $search, $replace, $html );
}

function p0_elementor_cache_key() {
	if ( class_exists( '\Elementor\Core\Base\Document' ) ) {
		$c = ( new ReflectionClass( '\Elementor\Core\Base\Document' ) )->getConstants();
		if ( ! empty( $c['CACHE_META_KEY'] ) ) {
			return $c['CACHE_META_KEY'];
		}
	}
	return '_elementor_element_cache';
}

/**
 * Guarded edit of one HTML widget inside a post's _elementor_data.
 * $transform( $html, &$errors ) returns the new html; any error aborts the post.
 * $forbidden: strings that must be absent from the new widget html.
 * $required:  strings that must be present in the new widget html.
 */
function p0_edit_elementor( $post_id, $label, $needle, callable $transform, array $forbidden, array $required ) {
	global $wpdb;
	$apply = $GLOBALS['p0_apply'];
	p0_h( "{$label} (post {$post_id})" );

	$row = $wpdb->get_row( $wpdb->prepare( "SELECT meta_id, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_elementor_data'", $post_id ), ARRAY_A );
	if ( ! $row ) {
		echo "ERROR: no _elementor_data row - ABORT for this post\n";
		$GLOBALS['p0_fail']++;
		return;
	}
	$data = json_decode( $row['meta_value'], true );
	if ( ! is_array( $data ) ) {
		echo "ERROR: _elementor_data does not decode - ABORT for this post\n";
		$GLOBALS['p0_fail']++;
		return;
	}

	$errors   = array();
	$old_html = null;
	$new_html = null;
	$hits     = 0;
	$new_data = p0_walk(
		$data,
		$needle,
		function ( $html ) use ( $transform, &$errors, &$old_html, &$new_html ) {
			$old_html = $html;
			$new_html = $transform( $html, $errors );
			return $new_html;
		},
		$hits
	);

	if ( 1 !== $hits ) {
		echo "ERROR: expected exactly 1 HTML widget containing '{$needle}', found {$hits} - ABORT for this post\n";
		$GLOBALS['p0_fail']++;
		return;
	}

	echo "widget html length: " . strlen( $old_html ) . ' -> ' . strlen( $new_html ) . "\n";
	echo "BEFORE stored widget: <form=" . substr_count( $old_html, '<form' ) . ' <input=' . substr_count( $old_html, '<input' ) . ' <select=' . substr_count( $old_html, '<select' ) . ' <option=' . substr_count( $old_html, '<option' ) . ' <script=' . substr_count( $old_html, '<script' ) . "\n";

	foreach ( $forbidden as $f ) {
		$c = substr_count( $new_html, $f );
		if ( $c > 0 ) {
			$errors[] = "forbidden string still present after transform: '{$f}' x{$c}";
		}
	}
	foreach ( $required as $r ) {
		if ( false === strpos( $new_html, $r ) ) {
			$errors[] = "required string missing after transform: '{$r}'";
		}
	}

	if ( $errors ) {
		echo "GUARDS FAILED - nothing will be written for post {$post_id}:\n";
		foreach ( $errors as $e ) {
			echo "  ! {$e}\n";
		}
		$GLOBALS['p0_fail']++;
		return;
	}
	if ( $new_html === $old_html ) {
		echo "SKIP: already in the desired state\n";
		return;
	}
	echo "OK: all guards passed\n";

	$new_json = wp_json_encode( $new_data );
	if ( ! $new_json || json_decode( $new_json, true ) !== $new_data ) {
		echo "ERROR: re-encoded JSON does not round-trip - ABORT for this post\n";
		$GLOBALS['p0_fail']++;
		return;
	}

	if ( ! $apply ) {
		echo "DRY-RUN: would write new _elementor_data (" . strlen( $row['meta_value'] ) . ' -> ' . strlen( $new_json ) . " bytes)\n";
		echo "----- NEW WIDGET HTML (markup only, CSS block omitted) -----\n";
		$markup_at = strpos( $new_html, '</style>' );
		echo ( false !== $markup_at ? substr( $new_html, $markup_at + 8 ) : $new_html ) . "\n";
		echo "----- END -----\n";
		return;
	}

	if ( ! p0_backup_post( $post_id ) ) {
		echo "ERROR: backup failed - ABORT for this post\n";
		$GLOBALS['p0_fail']++;
		return;
	}
	file_put_contents( rtrim( $GLOBALS['p0_backup_dir'], '/' ) . "/post-{$post_id}-widget-before.html", $old_html );

	$ok = $wpdb->update( $wpdb->postmeta, array( 'meta_value' => $new_json ), array( 'meta_id' => $row['meta_id'] ), array( '%s' ), array( '%d' ) );
	if ( false === $ok ) {
		echo "ERROR: update failed: {$wpdb->last_error}\n";
		$GLOBALS['p0_fail']++;
		return;
	}
	$verify = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_id = %d", $row['meta_id'] ) );
	$match  = ( $verify === $new_json );
	echo 'VERIFY stored bytes match: ' . ( $match ? 'YES' : 'NO' ) . "\n";
	if ( ! $match ) {
		$GLOBALS['p0_fail']++;
	}

	$cache_key = p0_elementor_cache_key();
	delete_post_meta( $post_id, $cache_key );
	clean_post_cache( $post_id );
	echo "cleared Elementor cache meta '{$cache_key}' + post cache\n";
}

/* --------------------------------------------------------------------------
 * Content: Contact page blocks, EN and ES
 * ----------------------------------------------------------------------- */

$contact = array(
	'en' => array(
		'hero_desc' => '<p class="hero-desc">Whether you have a question about a product, an order, or simply wish to know us better — we welcome every conversation.</p>',
		'form_sub'  => '<p class="form-sub">Complete the form below and a member of our team will reply within 1–2 business days.</p>',
		'subject_option' => 'Beauty Consultation',
		'privacy_old' => '<a href="#">Privacy Policy</a>',
		'privacy_new' => '<a href="https://lunacibarcelona.com/privacy-policy/">Privacy Policy</a>',
		'methods'   => <<<'HTML'
<!-- CONTACT METHODS -->
<div class="methods-row">
  <div class="method-item">
    <span class="method-icon" aria-hidden="true">✉</span>
    <div class="method-label">Email</div>
    <div class="method-value">info@lunacibarcelona.com</div>
    <div class="method-sub">Product, order and general enquiries.<br>We reply within 1–2 business days.</div>
    <a href="mailto:info@lunacibarcelona.com" class="method-link" aria-label="Email info@lunacibarcelona.com">Write to Us</a>
  </div>
  <div class="method-item">
    <span class="method-icon" aria-hidden="true">✆</span>
    <div class="method-label">WhatsApp</div>
    <div class="method-value">+34 745 071 014</div>
    <div class="method-sub">Message us.</div>
    <a href="https://wa.me/34745071014" class="method-link" target="_blank" rel="noopener" aria-label="Message LUNACI Barcelona on WhatsApp">Message Us</a>
  </div>
  <div class="method-item">
    <span class="method-icon" aria-hidden="true">☎</span>
    <div class="method-label">Phone</div>
    <div class="method-value">+34 933 538 633</div>
    <div class="method-sub">Monday – Friday.</div>
    <a href="tel:+34933538633" class="method-link" aria-label="Call +34 933 538 633">Call Us</a>
  </div>
  <div class="method-item">
    <span class="method-icon" aria-hidden="true">⌂</span>
    <div class="method-label">Registered Office</div>
    <div class="method-value">Patriotic Trade, S.L.</div>
    <div class="method-sub">Calle de Balmes 152, P3 Pta 2<br>08008 Barcelona, Spain<br>Not open to the public.</div>
  </div>
</div>

HTML,
		'info'      => <<<'HTML'
<!-- INFO -->
  <div class="info-side">
    <div class="info-bg"></div>
    <div class="info-content">
      <div class="info-top">
        <div class="info-eyebrow">Direct Contact</div>
        <h3 class="info-title">LUNACI Barcelona</h3>
        <div class="info-block">
          <div class="info-block-label">Contact</div>
          <div class="info-block-text">
            <a href="mailto:info@lunacibarcelona.com">info@lunacibarcelona.com</a><br>
            <a href="https://wa.me/34745071014" target="_blank" rel="noopener">WhatsApp +34 745 071 014</a><br>
            <a href="tel:+34933538633">+34 933 538 633</a>
          </div>
        </div>
        <div class="info-block">
          <div class="info-block-label">Please Note</div>
          <div class="info-block-text">
            LUNACI Barcelona is an online boutique. We have no physical store, and our registered office is not open to the public.
          </div>
        </div>
      </div>
      <div>
        <div class="info-block-label" style="font-size:10px;letter-spacing:3px;text-transform:uppercase;color:var(--gold);margin-bottom:16px">Follow LUNACI</div>
        <div class="social-row">
          <a href="https://www.instagram.com/lunaci.barcelona/" class="soc-link" target="_blank" rel="noopener" aria-label="LUNACI Barcelona on Instagram">IG</a>
          <a href="https://www.linkedin.com/company/lunaci-barcelona/" class="soc-link" target="_blank" rel="noopener" aria-label="LUNACI Barcelona on LinkedIn">in</a>
        </div>
      </div>
    </div>
  </div>
</div>

HTML,
		'faq'       => <<<'HTML'
<!-- FAQ -->
<section class="faq-section">
  <div class="faq-header">
    <div>
      <div class="faq-section-label">Common Questions</div>
      <h2 class="faq-title">Frequently<br><em>Asked</em></h2>
    </div>
    <div class="faq-subtitle">
      Before reaching out, you may find your answer below. If not, our team is always happy to help — simply complete the contact form above.
    </div>
  </div>
  <div class="faq-grid">
    <div class="faq-item">
      <div class="faq-q">
        How much does shipping cost, and how long does it take?
        <span class="faq-arrow">+</span>
      </div>
      <div class="faq-a">Orders within Spain are delivered free of charge in 5–8 business days. For the rest of the European Union, shipping costs are calculated at checkout.</div>
    </div>
    <div class="faq-item">
      <div class="faq-q">
        What is your return policy?
        <span class="faq-arrow">+</span>
      </div>
      <div class="faq-a">You may return unused products in their original packaging within 14 days of delivery. Return shipping is paid by the customer. Refunds are issued within 5–7 business days of receipt. For hygiene reasons, unsealed or used cosmetics cannot be returned unless defective.</div>
    </div>
    <div class="faq-item">
      <div class="faq-q">
        Where do you ship?
        <span class="faq-arrow">+</span>
      </div>
      <div class="faq-a">Spain and the European Union.</div>
    </div>
    <div class="faq-item">
      <div class="faq-q">
        How can I track my order?
        <span class="faq-arrow">+</span>
      </div>
      <div class="faq-a">Once your order is dispatched you will receive a confirmation email containing your tracking number and a link to follow your delivery in real time.</div>
    </div>
  </div>
</section>

HTML,
		'footer'    => <<<'HTML'
<!-- FOOTER -->
<footer>
  <div class="footer-top">
    <div>
      <div class="footer-brand-name">Lunaci</div>
      <div class="footer-brand-sub">Barcelona · Luxury Beauty</div>
      <p class="footer-brand-text">A Mediterranean luxury beauty house crafting products for the modern woman who values quality, authenticity and meaningful presence.</p>
    </div>
    <div class="footer-col">
      <h4>Collections</h4>
      <ul>
        <li><a href="/shop/">Lips</a></li>
        <li><a href="/shop/">Eyes</a></li>
        <li><a href="/shop/">Face</a></li>
        <li><a href="/shop/">Nails</a></li>
      </ul>
    </div>
    <div class="footer-col">
      <h4>Brand</h4>
      <ul>
        <li><a href="https://lunacibarcelona.com/about-us/">Our Philosophy</a></li>
      </ul>
    </div>
    <div class="footer-col">
      <h4>Support</h4>
      <ul>
        <li><a href="https://lunacibarcelona.com/contact/">Contact</a></li>
        <li><a href="https://lunacibarcelona.com/shipping/">Shipping</a></li>
        <li><a href="https://lunacibarcelona.com/returns/">Returns</a></li>
        <li><a href="https://lunacibarcelona.com/privacy-policy/">Privacy Policy</a></li>
        <li><a href="https://lunacibarcelona.com/terms-of-service/">Terms of Service</a></li>
      </ul>
    </div>
  </div>
  <div class="footer-bottom">
    <div class="footer-copy">© 2026 LUNACI Barcelona. All Rights Reserved.</div>
    <div class="footer-copy">Barcelona, Catalonia, Spain &nbsp;·&nbsp; lunacibarcelona.com</div>
  </div>
</footer>
HTML,
	),
	'es' => array(
		'hero_desc' => '<p class="hero-desc">Ya sea una pregunta sobre un producto, sobre un pedido, o simplemente quieras conocernos mejor, damos la bienvenida a cada conversación.</p>',
		'form_sub'  => '<p class="form-sub">Completa el siguiente formulario y un miembro de nuestro equipo te responderá en un plazo de 1–2 días laborables.</p>',
		'subject_option' => 'Consulta de Belleza',
		'privacy_old' => '<a href="#">Política de Privacidad</a>',
		'privacy_new' => '<a href="https://lunacibarcelona.com/es/politica-de-privacidad/">Política de Privacidad</a>',
		'methods'   => <<<'HTML'
<!-- CONTACT METHODS -->
<div class="methods-row">
  <div class="method-item">
    <span class="method-icon" aria-hidden="true">✉</span>
    <div class="method-label">Correo Electrónico</div>
    <div class="method-value">info@lunacibarcelona.com</div>
    <div class="method-sub">Consultas sobre productos, pedidos y consultas generales.<br>Respondemos en un plazo de 1–2 días laborables.</div>
    <a href="mailto:info@lunacibarcelona.com" class="method-link" aria-label="Escribir a info@lunacibarcelona.com">Escríbenos</a>
  </div>
  <div class="method-item">
    <span class="method-icon" aria-hidden="true">✆</span>
    <div class="method-label">WhatsApp</div>
    <div class="method-value">+34 745 071 014</div>
    <div class="method-sub">Escríbenos un mensaje.</div>
    <a href="https://wa.me/34745071014" class="method-link" target="_blank" rel="noopener" aria-label="Escribir a LUNACI Barcelona por WhatsApp">Enviar Mensaje</a>
  </div>
  <div class="method-item">
    <span class="method-icon" aria-hidden="true">☎</span>
    <div class="method-label">Teléfono</div>
    <div class="method-value">+34 933 538 633</div>
    <div class="method-sub">De lunes a viernes.</div>
    <a href="tel:+34933538633" class="method-link" aria-label="Llamar al +34 933 538 633">Llámanos</a>
  </div>
  <div class="method-item">
    <span class="method-icon" aria-hidden="true">⌂</span>
    <div class="method-label">Domicilio Social</div>
    <div class="method-value">Patriotic Trade, S.L.</div>
    <div class="method-sub">Calle de Balmes 152, P3 Pta 2<br>08008 Barcelona, España<br>Sin atención al público.</div>
  </div>
</div>

HTML,
		'info'      => <<<'HTML'
<!-- INFO -->
  <div class="info-side">
    <div class="info-bg"></div>
    <div class="info-content">
      <div class="info-top">
        <div class="info-eyebrow">Contacto Directo</div>
        <h3 class="info-title">LUNACI Barcelona</h3>
        <div class="info-block">
          <div class="info-block-label">Contacto</div>
          <div class="info-block-text">
            <a href="mailto:info@lunacibarcelona.com">info@lunacibarcelona.com</a><br>
            <a href="https://wa.me/34745071014" target="_blank" rel="noopener">WhatsApp +34 745 071 014</a><br>
            <a href="tel:+34933538633">+34 933 538 633</a>
          </div>
        </div>
        <div class="info-block">
          <div class="info-block-label">Ten en Cuenta</div>
          <div class="info-block-text">
            LUNACI Barcelona es una boutique online. No disponemos de tienda física y nuestro domicilio social no ofrece atención al público.
          </div>
        </div>
      </div>
      <div>
        <div class="info-block-label" style="font-size:10px;letter-spacing:3px;text-transform:uppercase;color:var(--gold);margin-bottom:16px">Síguenos</div>
        <div class="social-row">
          <a href="https://www.instagram.com/lunaci.barcelona/" class="soc-link" target="_blank" rel="noopener" aria-label="LUNACI Barcelona en Instagram">IG</a>
          <a href="https://www.linkedin.com/company/lunaci-barcelona/" class="soc-link" target="_blank" rel="noopener" aria-label="LUNACI Barcelona en LinkedIn">in</a>
        </div>
      </div>
    </div>
  </div>
</div>

HTML,
		'faq'       => <<<'HTML'
<!-- FAQ -->
<section class="faq-section">
  <div class="faq-header">
    <div>
      <div class="faq-section-label">Preguntas Comunes</div>
      <h2 class="faq-title">Preguntas<br><em>Frecuentes</em></h2>
    </div>
    <div class="faq-subtitle">
      Antes de contactarnos, es posible que encuentres tu respuesta aquí abajo. Si no, nuestro equipo estará encantado de ayudarte: simplemente completa el formulario de contacto de arriba.
    </div>
  </div>
  <div class="faq-grid">
    <div class="faq-item">
      <div class="faq-q">
        ¿Cuánto cuesta el envío y cuánto tarda?
        <span class="faq-arrow">+</span>
      </div>
      <div class="faq-a">Los pedidos dentro de España se entregan sin coste en un plazo de 5–8 días laborables. Para el resto de la Unión Europea, los gastos de envío se calculan al finalizar la compra.</div>
    </div>
    <div class="faq-item">
      <div class="faq-q">
        ¿Cuál es vuestra política de devoluciones?
        <span class="faq-arrow">+</span>
      </div>
      <div class="faq-a">Puedes devolver productos sin usar y en su embalaje original en un plazo de 14 días desde la entrega. Los gastos de envío de la devolución corren a cargo del cliente. Los reembolsos se emiten en un plazo de 5–7 días laborables desde la recepción. Por motivos de higiene, los cosméticos desprecintados o usados no pueden devolverse, salvo que sean defectuosos.</div>
    </div>
    <div class="faq-item">
      <div class="faq-q">
        ¿A qué países enviáis?
        <span class="faq-arrow">+</span>
      </div>
      <div class="faq-a">A España y al resto de la Unión Europea.</div>
    </div>
    <div class="faq-item">
      <div class="faq-q">
        ¿Cómo puedo hacer seguimiento de mi pedido?
        <span class="faq-arrow">+</span>
      </div>
      <div class="faq-a">Una vez que tu pedido sea enviado, recibirás un correo electrónico de confirmación con tu número de seguimiento y un enlace para seguir tu entrega en tiempo real.</div>
    </div>
  </div>
</section>

HTML,
		'footer'    => <<<'HTML'
<!-- FOOTER -->
<footer>
  <div class="footer-top">
    <div>
      <div class="footer-brand-name">Lunaci</div>
      <div class="footer-brand-sub">Barcelona · Belleza de Lujo</div>
      <p class="footer-brand-text">Una casa de belleza de lujo mediterránea que crea productos para la mujer moderna que valora la calidad, la autenticidad y una presencia con sentido.</p>
    </div>
    <div class="footer-col">
      <h4>Colecciones</h4>
      <ul>
        <li><a href="/shop/">Labios</a></li>
        <li><a href="/shop/">Ojos</a></li>
        <li><a href="/shop/">Rostro</a></li>
        <li><a href="/shop/">Uñas</a></li>
      </ul>
    </div>
    <div class="footer-col">
      <h4>Marca</h4>
      <ul>
        <li><a href="https://lunacibarcelona.com/es/about-us-es/">Nuestra Filosofía</a></li>
      </ul>
    </div>
    <div class="footer-col">
      <h4>Ayuda</h4>
      <ul>
        <li><a href="https://lunacibarcelona.com/es/contacto/">Contacto</a></li>
        <li><a href="https://lunacibarcelona.com/es/envio/">Envío</a></li>
        <li><a href="https://lunacibarcelona.com/es/devoluciones/">Devoluciones</a></li>
        <li><a href="https://lunacibarcelona.com/es/politica-de-privacidad/">Política de Privacidad</a></li>
        <li><a href="https://lunacibarcelona.com/es/terminos-de-servicio/">Términos de Servicio</a></li>
      </ul>
    </div>
  </div>
  <div class="footer-bottom">
    <div class="footer-copy">© 2026 LUNACI Barcelona. Todos los derechos reservados.</div>
    <div class="footer-copy">Barcelona, Cataluña, España &nbsp;·&nbsp; lunacibarcelona.com</div>
  </div>
</footer>
HTML,
	),
);

// Must be absent from the Contact widget after the transform (brief §2 + §6).
$contact_forbidden_common = array(
	'xxxxxxx', 'Gracia 28', 'Gracia+28', 'Gràcia', 'Atelier', 'atelier', 'Flagship', 'Metro:',
	'maps.google', '538 663', '538663', 'Concierge', 'Book a Session', 'Reservar una Sesión',
	'ruelty', 'crueldad', 'igsh=', 'href="#"', 'Opening Hours', 'Horario de Apertura',
	'Middle East', 'Oriente Medio', 'Get Directions', 'Cómo Llegar', 'Visit Us', 'Visítanos',
	'Store Locator', 'Localizador de Tiendas', 'Sustainability', 'Sostenibilidad', 'lunaci.es',
	'within 24 hours', 'en 24 horas',
);
$contact_forbidden = array(
	'en' => array_merge( $contact_forbidden_common, array( 'Beauty Consultation', 'Ingredients', '>Press<', '>FAQ<' ) ),
	'es' => array_merge( $contact_forbidden_common, array( 'Consulta de Belleza', 'Ingredientes', '>Prensa<', 'Tienda Insignia', 'Envíos y Devoluciones' ) ),
);
$contact_required = array(
	'+34 933 538 633', 'tel:+34933538633', 'https://wa.me/34745071014', 'mailto:info@lunacibarcelona.com',
	'Patriotic Trade, S.L.', 'Calle de Balmes 152, P3 Pta 2', 'https://www.instagram.com/lunaci.barcelona/',
	'<h1 class="hero-title">', 'id="contact-form"', 'for="consent"', 'class="submit-btn"', '<!-- NEWSLETTER -->',
);

foreach ( array( 60 => 'en', 770 => 'es' ) as $post_id => $lang ) {
	$c = $contact[ $lang ];
	p0_edit_elementor(
		$post_id,
		'CONTACT ' . strtoupper( $lang ),
		'.contact-hero',
		function ( $html, &$errors ) use ( $c ) {
			preg_match( '#<h1 class="hero-title">.*?</h1>#s', $html, $h1_before );

			$html = p0_between( $html, '<!-- CONTACT METHODS -->', '<!-- CONTACT MAIN -->', $c['methods'], false, $errors );
			$html = p0_between( $html, '<!-- INFO -->', '<!-- MAP -->', $c['info'], false, $errors );
			$html = p0_between( $html, '<!-- MAP -->', '<!-- FAQ -->', '', false, $errors );
			$html = p0_between( $html, '<!-- FAQ -->', '<!-- NEWSLETTER -->', $c['faq'], false, $errors );
			$html = p0_between( $html, '<!-- FOOTER -->', '</footer>', $c['footer'], true, $errors );
			$html = p0_preg_once( '#<p class="hero-desc">.*?</p>#s', $c['hero_desc'], $html, 'hero-desc', $errors );
			$html = p0_preg_once( '#<p class="form-sub">.*?</p>#s', $c['form_sub'], $html, 'form-sub', $errors );
			$html = p0_preg_once( '#^[^\n]*' . preg_quote( $c['subject_option'], '#' ) . '[^\n]*\n#m', '', $html, 'subject option', $errors );
			$html = p0_str_once( $c['privacy_old'], $c['privacy_new'], $html, 'consent privacy link', $errors );

			preg_match( '#<h1 class="hero-title">.*?</h1>#s', $html, $h1_after );
			if ( empty( $h1_before ) || ( $h1_before[0] ?? '' ) !== ( $h1_after[0] ?? null ) ) {
				$errors[] = 'H1 changed or missing - the brief says keep the H1';
			}
			return $html;
		},
		$contact_forbidden[ $lang ],
		$contact_required
	);
}

/* --------------------------------------------------------------------------
 * About footer social links (brief §4). Home 57/772 deliberately excluded.
 * ----------------------------------------------------------------------- */

$about_social = array(
	'en' => <<<'HTML'
<div class="lna-foot__s">
      <a href="https://www.instagram.com/lunaci.barcelona/" target="_blank" rel="noopener" aria-label="LUNACI Barcelona on Instagram">IG</a>
      <a href="https://www.linkedin.com/company/lunaci-barcelona/" target="_blank" rel="noopener" aria-label="LUNACI Barcelona on LinkedIn">IN</a>
    </div>
HTML,
	'es' => <<<'HTML'
<div class="lna-foot__s">
      <a href="https://www.instagram.com/lunaci.barcelona/" target="_blank" rel="noopener" aria-label="LUNACI Barcelona en Instagram">IG</a>
      <a href="https://www.linkedin.com/company/lunaci-barcelona/" target="_blank" rel="noopener" aria-label="LUNACI Barcelona en LinkedIn">IN</a>
    </div>
HTML,
);

foreach ( array( 59 => 'en', 680 => 'es' ) as $post_id => $lang ) {
	$new = $about_social[ $lang ];
	p0_edit_elementor(
		$post_id,
		'ABOUT FOOTER SOCIAL ' . strtoupper( $lang ),
		'lna-foot__s',
		function ( $html, &$errors ) use ( $new ) {
			return p0_preg_once( '#<div class="lna-foot__s">.*?</div>#s', $new, $html, 'lna-foot__s block', $errors );
		},
		array( '>TK<', '>PT<', '<a href="#">IG</a>' ),
		array( 'https://www.instagram.com/lunaci.barcelona/', 'https://www.linkedin.com/company/lunaci-barcelona/', 'aria-label="LUNACI Barcelona' )
	);
}

/* --------------------------------------------------------------------------
 * Legal pages: entity + email (brief §3), plus phone on any target post's
 * non-Elementor meta and AIOSEO row.
 * ----------------------------------------------------------------------- */

$text_map = array(
	'mailto:info@lunaci.es' => 'mailto:info@lunacibarcelona.com',
	'info@lunaci.es'        => 'info@lunacibarcelona.com',
	'Patriotic Trade Co.'   => 'Patriotic Trade, S.L.',
	'+34 933 538 663'       => '+34 933 538 633',
	'+34933538663'          => '+34933538633',
	'933 538 663'           => '933 538 633',
);

function p0_map( $s, $map ) {
	return str_replace( array_keys( $map ), array_values( $map ), $s );
}

$legal = array(
	3   => 'Privacy Policy EN',
	676 => 'Terms of Service EN',
	759 => 'Shipping EN',
	760 => 'Returns EN',
	769 => 'Politica de Privacidad ES',
	768 => 'Terminos de Servicio ES',
	765 => 'Envio ES',
	766 => 'Devoluciones ES',
);

$aio_tbl    = $wpdb->prefix . 'aioseo_posts';
$aio_exists = ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $aio_tbl ) ) === $aio_tbl );

foreach ( $legal as $post_id => $label ) {
	p0_h( "LEGAL {$label} (post {$post_id})" );
	$post = $wpdb->get_row( $wpdb->prepare( "SELECT ID, post_title, post_status, post_content, post_excerpt FROM {$wpdb->posts} WHERE ID = %d", $post_id ), ARRAY_A );
	if ( ! $post ) {
		echo "ERROR: post not found - ABORT for this post\n";
		$GLOBALS['p0_fail']++;
		continue;
	}
	echo "title: {$post['post_title']} | status: {$post['post_status']}\n";

	$changes = array();
	foreach ( array( 'post_content', 'post_excerpt', 'post_title' ) as $col ) {
		$new = p0_map( $post[ $col ], $text_map );
		echo "{$col}: lunaci.es=" . substr_count( $post[ $col ], 'lunaci.es' ) . ' Patriotic Trade Co.=' . substr_count( $post[ $col ], 'Patriotic Trade Co.' ) . ' 538 663=' . substr_count( $post[ $col ], '538 663' ) . "\n";
		if ( $new !== $post[ $col ] ) {
			$changes['posts'][ $col ] = $new;
		}
	}

	$meta_rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT meta_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND ( meta_value LIKE %s OR meta_value LIKE %s OR meta_value LIKE %s )",
		$post_id, '%lunaci.es%', '%Patriotic Trade Co.%', '%538 663%'
	), ARRAY_A );
	foreach ( $meta_rows as $m ) {
		if ( is_serialized( $m['meta_value'] ) ) {
			echo "WARN: meta {$m['meta_key']} (meta_id {$m['meta_id']}) matches but is serialized - left untouched, report to Claude\n";
			continue;
		}
		$changes['meta'][ $m['meta_id'] ] = array( $m['meta_key'], p0_map( $m['meta_value'], $text_map ) );
	}

	if ( $aio_exists ) {
		$aio = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$aio_tbl}` WHERE post_id = %d", $post_id ), ARRAY_A );
		if ( $aio ) {
			foreach ( $aio as $col => $val ) {
				if ( is_string( $val ) && in_array( $col, array( 'id', 'post_id' ), false ) === false ) {
					$new = p0_map( $val, $text_map );
					if ( $new !== $val ) {
						$changes['aioseo'][ $col ] = $new;
					}
				}
			}
		}
	}

	if ( ! $changes ) {
		echo "SKIP: nothing to change\n";
		continue;
	}

	$after_content = $changes['posts']['post_content'] ?? $post['post_content'];
	foreach ( array( 'lunaci.es', 'Patriotic Trade Co.' ) as $f ) {
		if ( substr_count( $after_content, $f ) > 0 ) {
			echo "GUARD FAILED: '{$f}' would still be present in post_content (unmapped variant) - ABORT for this post\n";
			$GLOBALS['p0_fail']++;
			continue 2;
		}
	}

	foreach ( $changes['posts'] ?? array() as $col => $v ) {
		echo "will update posts.{$col}\n";
	}
	foreach ( $changes['meta'] ?? array() as $mid => $kv ) {
		echo "will update postmeta {$kv[0]} (meta_id {$mid})\n";
	}
	foreach ( $changes['aioseo'] ?? array() as $col => $v ) {
		echo "will update aioseo_posts.{$col} -> " . mb_substr( $v, 0, 140 ) . "\n";
	}

	if ( ! $apply ) {
		echo "DRY-RUN: no write\n";
		continue;
	}
	if ( ! p0_backup_post( $post_id ) ) {
		echo "ERROR: backup failed - ABORT for this post\n";
		$GLOBALS['p0_fail']++;
		continue;
	}
	if ( ! empty( $changes['posts'] ) ) {
		$r = $wpdb->update( $wpdb->posts, $changes['posts'], array( 'ID' => $post_id ) );
		echo 'posts update: ' . var_export( $r, true ) . ( false === $r ? " {$wpdb->last_error}" : '' ) . "\n";
		if ( false === $r ) {
			$GLOBALS['p0_fail']++;
		}
	}
	foreach ( $changes['meta'] ?? array() as $mid => $kv ) {
		$r = $wpdb->update( $wpdb->postmeta, array( 'meta_value' => $kv[1] ), array( 'meta_id' => $mid ) );
		echo "postmeta {$kv[0]} update: " . var_export( $r, true ) . "\n";
	}
	if ( ! empty( $changes['aioseo'] ) ) {
		$r = $wpdb->update( $aio_tbl, $changes['aioseo'], array( 'post_id' => $post_id ) );
		echo 'aioseo_posts update: ' . var_export( $r, true ) . "\n";
	}
	clean_post_cache( $post_id );
	wp_cache_delete( $post_id, 'posts' );

	$check = $wpdb->get_var( $wpdb->prepare( "SELECT post_content FROM {$wpdb->posts} WHERE ID = %d", $post_id ) );
	$left  = substr_count( $check, 'lunaci.es' );
	echo "VERIFY grep -c 'lunaci.es' in post_content = {$left}; 'Patriotic Trade, S.L.' = " . substr_count( $check, 'Patriotic Trade, S.L.' ) . "\n";
	if ( $left > 0 ) {
		$GLOBALS['p0_fail']++;
	}
}

/* --------------------------------------------------------------------------
 * AIOSEO global options: schema email / phone (brief §1 "replace everywhere")
 * ----------------------------------------------------------------------- */

p0_h( 'AIOSEO OPTIONS (schema email / phone)' );
$aio_opt = get_option( 'aioseo_options' );
if ( is_string( $aio_opt ) ) {
	$aio_dec = json_decode( $aio_opt, true );
	$schema  = $aio_dec['searchAppearance']['global']['schema'] ?? array();
	foreach ( array( 'phone', 'email', 'contactType', 'organizationName' ) as $k ) {
		echo "schema.{$k} = " . var_export( $schema[ $k ] ?? '(missing)', true ) . "\n";
	}
	$new_opt = p0_map( $aio_opt, $text_map );
	if ( $new_opt !== $aio_opt && is_array( json_decode( $new_opt, true ) ) ) {
		echo "aioseo_options contains a wrong email/phone/entity string - will replace\n";
		if ( $apply ) {
			p0_backup_write( 'option-aioseo_options.json', array( 'aioseo_options' => $aio_opt ) );
			update_option( 'aioseo_options', $new_opt );
			echo "updated aioseo_options\n";
		}
	} else {
		echo "aioseo_options: no wrong email/phone/entity strings - untouched\n";
	}
} else {
	echo "aioseo_options not a JSON string - untouched\n";
}

/* --------------------------------------------------------------------------
 * WooCommerce (brief §5)
 * ----------------------------------------------------------------------- */

p0_h( 'WOOCOMMERCE: Shadow (515) -> private' );
if ( ! function_exists( 'wc_get_product' ) ) {
	echo "ERROR: WooCommerce not loaded\n";
	$GLOBALS['p0_fail']++;
} else {
	$ids = array( 515 );
	$es  = apply_filters( 'wpml_object_id', 515, 'product', false, 'es' );
	if ( $es && (int) $es !== 515 ) {
		$ids[] = (int) $es;
	}
	echo 'targets (EN + WPML ES translation): ' . implode( ', ', $ids ) . "\n";
	foreach ( $ids as $pid ) {
		$product = wc_get_product( $pid );
		if ( ! $product ) {
			echo "ERROR: product {$pid} not found\n";
			$GLOBALS['p0_fail']++;
			continue;
		}
		$name = $product->get_name();
		echo "product {$pid}: '{$name}' status=" . $product->get_status() . ' type=' . $product->get_type() . "\n";
		if ( false === stripos( $name, 'shadow' ) && false === stripos( $name, 'sombra' ) ) {
			echo "GUARD FAILED: product {$pid} is not Shadow - untouched\n";
			$GLOBALS['p0_fail']++;
			continue;
		}
		if ( 'private' === $product->get_status() ) {
			echo "SKIP: already private\n";
			continue;
		}
		if ( $apply ) {
			p0_backup_post( $pid );
			$product->set_status( 'private' );
			$product->save();
			clean_post_cache( $pid );
			echo "VERIFY status now: " . get_post_status( $pid ) . "\n";
			if ( 'private' !== get_post_status( $pid ) ) {
				$GLOBALS['p0_fail']++;
			}
		} else {
			echo "DRY-RUN: would set {$pid} to private\n";
		}
	}

	p0_h( 'WOOCOMMERCE: selling + shipping locations = Spain + EU-27' );
	$eu27 = array( 'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 'DE', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL', 'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE' );
	sort( $eu27 );
	$targets = array(
		'woocommerce_allowed_countries'          => 'specific',
		'woocommerce_specific_allowed_countries' => $eu27,
		'woocommerce_ship_to_countries'          => '',   // "Ship to all countries you sell to"
		'woocommerce_specific_ship_to_countries' => $eu27,
	);
	$opt_backup = array();
	foreach ( $targets as $opt => $want ) {
		$cur                = get_option( $opt );
		$opt_backup[ $opt ] = $cur;
		$cur_cmp            = is_array( $cur ) ? array_values( $cur ) : $cur;
		if ( is_array( $cur_cmp ) ) {
			sort( $cur_cmp );
		}
		$same = ( $cur_cmp === $want );
		echo "{$opt}: current=" . ( is_array( $cur ) ? implode( ',', $cur ) . ' (' . count( $cur ) . ')' : var_export( $cur, true ) ) . ( $same ? ' [OK]' : ' [CHANGE]' ) . "\n";
	}
	if ( $apply ) {
		p0_backup_write( 'option-woocommerce-countries.json', $opt_backup );
		foreach ( $targets as $opt => $want ) {
			update_option( $opt, $want );
		}
		echo 'VERIFY allowed countries now: ' . implode( ',', array_keys( WC()->countries->get_allowed_countries() ) ) . ' (' . count( WC()->countries->get_allowed_countries() ) . ")\n";
		echo 'VERIFY shipping countries now: ' . count( WC()->countries->get_shipping_countries() ) . "\n";
	}

	p0_h( 'WOOCOMMERCE: shipping zones (report only)' );
	if ( class_exists( 'WC_Shipping_Zones' ) ) {
		$covered = array();
		foreach ( WC_Shipping_Zones::get_zones() as $z ) {
			$locs = array_map( function ( $l ) { return $l->type . ':' . $l->code; }, $z['zone_locations'] );
			$meth = array_map( function ( $m ) { return $m->id . ( 'yes' === $m->enabled ? '' : '(disabled)' ); }, $z['shipping_methods'] );
			echo "zone #{$z['id']} '{$z['zone_name']}': " . implode( ' ', $locs ) . ' | methods: ' . implode( ', ', $meth ) . "\n";
			foreach ( $z['zone_locations'] as $l ) {
				if ( 'country' === $l->type ) {
					$covered[] = $l->code;
				}
				if ( 'continent' === $l->type && 'EU' === $l->code ) {
					$covered = array_merge( $covered, $eu27 );
				}
			}
		}
		$rest = WC_Shipping_Zones::get_zone( 0 );
		echo 'rest-of-world zone methods: ' . count( $rest ? $rest->get_shipping_methods( true ) : array() ) . "\n";
		$missing = array_diff( $eu27, $covered );
		echo 'EU-27 countries not explicitly in any zone: ' . ( $missing ? implode( ',', $missing ) : 'none' ) . "\n";
	}
}

/* --------------------------------------------------------------------------
 * Read-only site-wide scan for leftovers (report only)
 * ----------------------------------------------------------------------- */

p0_h( 'SITE-WIDE LEFTOVER SCAN (report only)' );
$patterns = array( 'lunaci.es', '538 663', '538663', 'Patriotic Trade Co.', 'xxxxxxx', '>TK<', 'igsh=' );
foreach ( $patterns as $pat ) {
	$like  = '%' . $wpdb->esc_like( $pat ) . '%';
	$posts = $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_type, post_status, post_title FROM {$wpdb->posts} WHERE post_type NOT IN ('revision','nav_menu_item') AND post_status IN ('publish','private','draft') AND post_content LIKE %s LIMIT 40", $like ), ARRAY_A );
	$meta  = $wpdb->get_results( $wpdb->prepare( "SELECT pm.post_id, pm.meta_key, p.post_type FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE p.post_type <> 'revision' AND pm.meta_key NOT LIKE %s AND pm.meta_value LIKE %s LIMIT 40", '_elementor_element_cache%', $like ), ARRAY_A );
	$opts  = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name NOT LIKE %s AND option_value LIKE %s LIMIT 40", '%transient%', $like ) );
	echo "\n[{$pat}]\n";
	foreach ( $posts as $p ) {
		echo "  post {$p['ID']} {$p['post_type']}/{$p['post_status']} '{$p['post_title']}'\n";
	}
	foreach ( $meta as $m ) {
		echo "  meta post {$m['post_id']} ({$m['post_type']}) key {$m['meta_key']}\n";
	}
	foreach ( $opts as $o ) {
		echo "  option {$o}\n";
	}
	if ( ! $posts && ! $meta && ! $opts ) {
		echo "  none\n";
	}
}

/* --------------------------------------------------------------------------
 * Home guard: 57 / 772 must be byte-identical (brief §6)
 * ----------------------------------------------------------------------- */

p0_h( 'HOME GUARD (57 / 772 _elementor_data must be unchanged)' );
foreach ( array( 57, 772 ) as $hid ) {
	$raw = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_elementor_data'", $hid ) );
	echo "post {$hid}: md5=" . md5( (string) $raw ) . ' length=' . strlen( (string) $raw ) . ' (compare with HOME_BASELINE line printed by the workflow before this script ran)' . "\n";
	if ( $apply ) {
		p0_backup_write( "home-{$hid}-elementor-data-decoded.json", json_decode( (string) $raw, true ) );
	}
}

p0_h( 'SUMMARY' );
echo "mode: {$mode}\n";
echo "guard / write failures: {$GLOBALS['p0_fail']}\n";
if ( $GLOBALS['p0_fail'] > 0 ) {
	echo "RESULT: FAILURES PRESENT - review the log above before the next step\n";
	exit( 2 );
}
echo "RESULT: OK\n";
