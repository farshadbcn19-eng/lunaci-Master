<?php
/**
 * LUNACI Phase 1 (2026-10-02): working Contact form, Returns hygiene clause,
 * stale post_content cleanup.
 *
 * Usage (from the workflow):
 *   wp eval-file phase1-contact-form-returns-cleanup.php dry-run <backup_dir>
 *   wp eval-file phase1-contact-form-returns-cleanup.php apply   <backup_dir>
 *
 * dry-run = read-only: runs every guard and transform in memory and prints
 * what WOULD change. apply = backs up each post before writing, then writes
 * with read-verify-write-verify. Same guarded helpers as Phase 0.
 *
 * 1. Contact EN 60 / ES 770: the form-side block (between the FORM and INFO
 *    markers) gets real <form>, <input>, <select> and consent checkbox markup,
 *    posting to mu-plugins/lunaci-contact-form.php (deployed by the workflow
 *    before this script runs). Live state 2026-10-02: the stored widget has
 *    0 <form>/<input>/<select>, so the form sends nothing.
 * 2. Returns EN 760 / ES 766: add the hygiene exception paragraph that the
 *    Terms (section 6) and the Contact FAQ already state
 *    (Art. 103(e) TRLGDCU / Directive 2011/83/EU Art. 16(e)).
 * 3. post_content of Contact 60 and About 59 / 680 still holds the pre-Phase-0
 *    text (old phone, xxxxxxx placeholders, igsh= link, TK/PT). It is not
 *    rendered (Elementor renders _elementor_data) but is used by search and
 *    feeds. It is regenerated from the current Elementor render.
 *
 * Home 57 / 772 is not touched; its md5 is printed for the workflow guard.
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
 * 0. Handler present? (the workflow deploys the mu-plugin before this runs)
 * ----------------------------------------------------------------------- */

p0_h( 'ENVIRONMENT' );
$handler_loaded = function_exists( 'lunaci_cf_handle_post' );
echo 'mu-plugin lunaci-contact-form loaded: ' . ( $handler_loaded ? 'YES' : 'NO' ) . "\n";
$active = (array) get_option( 'active_plugins', array() );
$mailers = array_values( array_filter( $active, function ( $p ) { return (bool) preg_match( '/smtp|mail/i', $p ); } ) );
echo 'active mail/SMTP plugins: ' . ( $mailers ? implode( ', ', $mailers ) : 'none (wp_mail uses the host PHP mail())' ) . "\n";
if ( $apply && ! $handler_loaded ) {
	echo "ERROR: form handler not loaded - the Contact form markup will NOT be written\n";
	$GLOBALS['p0_fail']++;
}

/* --------------------------------------------------------------------------
 * 1. Contact form markup, EN + ES
 * ----------------------------------------------------------------------- */

$form_text = array(
	'en' => array(
		'url'      => 'https://lunacibarcelona.com/contact/',
		'eyebrow'  => 'Send a Message',
		'title'    => 'Begin a<br><em>Conversation</em>',
		'sub'      => 'Complete the form below and a member of our team will reply within 1–2 business days.',
		'first'    => 'First Name',
		'last'     => 'Last Name',
		'email'    => 'Email Address',
		'phone'    => 'Phone (Optional)',
		'subject'  => 'Subject',
		'topics'   => array( '' => 'Select a topic', 'product' => 'Product Enquiry', 'order' => 'Order &amp; Shipping', 'press' => 'Press &amp; Media', 'wholesale' => 'Wholesale &amp; Partnership', 'other' => 'Other' ),
		'message'  => 'Your Message',
		'consent'  => 'I agree to the <a href="https://lunacibarcelona.com/privacy-policy/">Privacy Policy</a> and consent to LUNACI Barcelona storing and processing my data to respond to this enquiry.',
		'button'   => 'Send Message',
		'status'   => array(
			'sent'    => 'Thank you. Your message has been received, and we will reply within 1–2 business days.',
			'invalid' => 'Please complete your first name, email address and message, and accept the Privacy Policy.',
			'limit'   => 'We have received several messages from this connection. Please try again later, or write to info@lunacibarcelona.com.',
			'error'   => 'Your message could not be sent. Please write to info@lunacibarcelona.com.',
		),
	),
	'es' => array(
		'url'      => 'https://lunacibarcelona.com/es/contacto/',
		'eyebrow'  => 'Enviar un Mensaje',
		'title'    => 'Inicia una<br><em>Conversación</em>',
		'sub'      => 'Completa el siguiente formulario y un miembro de nuestro equipo te responderá en un plazo de 1–2 días laborables.',
		'first'    => 'Nombre',
		'last'     => 'Apellidos',
		'email'    => 'Correo Electrónico',
		'phone'    => 'Teléfono (Opcional)',
		'subject'  => 'Asunto',
		'topics'   => array( '' => 'Selecciona un tema', 'product' => 'Consulta sobre Producto', 'order' => 'Pedido y Envío', 'press' => 'Prensa y Medios', 'wholesale' => 'Mayoristas y Colaboraciones', 'other' => 'Otro' ),
		'message'  => 'Tu Mensaje',
		'consent'  => 'Acepto la <a href="https://lunacibarcelona.com/es/politica-de-privacidad/">Política de Privacidad</a> y doy mi consentimiento para que LUNACI Barcelona almacene y procese mis datos para responder a esta consulta.',
		'button'   => 'Enviar Mensaje',
		'status'   => array(
			'sent'    => 'Gracias. Hemos recibido tu mensaje y te responderemos en un plazo de 1–2 días laborables.',
			'invalid' => 'Completa tu nombre, correo electrónico y mensaje, y acepta la Política de Privacidad.',
			'limit'   => 'Hemos recibido varios mensajes desde esta conexión. Inténtalo más tarde o escríbenos a info@lunacibarcelona.com.',
			'error'   => 'No se ha podido enviar tu mensaje. Escríbenos a info@lunacibarcelona.com.',
		),
	),
);

function p1_form_block( $lang, array $t ) {
	$options = '';
	foreach ( $t['topics'] as $value => $label ) {
		$options .= "\n            <option value=\"{$value}\">{$label}</option>";
	}
	$status_json = wp_json_encode( $t['status'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );

	return <<<HTML
<!-- FORM -->
  <div class="form-side">
    <div class="form-eyebrow">{$t['eyebrow']}</div>
    <h2 class="form-title">{$t['title']}</h2>
    <p class="form-sub">{$t['sub']}</p>

    <div class="form-status" id="lunaci-form-status" role="status" aria-live="polite" hidden style="margin-bottom:28px;padding:16px 20px;border:1px solid rgba(212,175,55,.35);color:var(--cream);font-size:13px;line-height:1.7"></div>

    <form class="contact-form" method="post" action="{$t['url']}">
      <input type="hidden" name="lunaci_cf" value="1">
      <input type="hidden" name="lang" value="{$lang}">
      <input type="hidden" name="lc_js" value="">
      <div style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden" aria-hidden="true">
        <label for="lc-website">Website</label>
        <input type="text" id="lc-website" name="website" tabindex="-1" autocomplete="off">
      </div>

      <div class="form-row">
        <div class="field">
          <label for="first-name">{$t['first']}</label>
          <input type="text" id="first-name" name="first_name" autocomplete="given-name" maxlength="80" required>
        </div>
        <div class="field">
          <label for="last-name">{$t['last']}</label>
          <input type="text" id="last-name" name="last_name" autocomplete="family-name" maxlength="80">
        </div>
      </div>

      <div class="form-row">
        <div class="field">
          <label for="email">{$t['email']}</label>
          <input type="email" id="email" name="email" autocomplete="email" maxlength="120" required>
        </div>
        <div class="field">
          <label for="phone">{$t['phone']}</label>
          <input type="tel" id="phone" name="phone" autocomplete="tel" maxlength="40">
        </div>
      </div>

      <div class="form-row single">
        <div class="field">
          <label for="subject">{$t['subject']}</label>
          <select id="subject" name="subject">{$options}
          </select>
        </div>
      </div>

      <div class="form-row single">
        <div class="field">
          <label for="message">{$t['message']}</label>
          <textarea id="message" name="message" maxlength="5000" required></textarea>
        </div>
      </div>

      <div class="form-consent">
        <input type="checkbox" id="consent" name="consent" value="1" required>
        <label for="consent">{$t['consent']}</label>
      </div>

      <button type="submit" class="submit-btn">{$t['button']}</button>
    </form>
  </div>
<script>
(function () {
  var form = document.querySelector('.contact-form');
  if (!form) return;
  var messages = {$status_json};
  var button = form.querySelector('.submit-btn');
  form.addEventListener('submit', function () {
    form.querySelector('[name="lc_js"]').value = 'ok';
    button.disabled = true;
  });
  window.addEventListener('pageshow', function () { button.disabled = false; });
  var status = new URLSearchParams(window.location.search).get('enquiry');
  if (status && messages[status]) {
    var box = document.getElementById('lunaci-form-status');
    box.textContent = messages[status];
    box.hidden = false;
    if (status === 'sent') form.hidden = true;
  }
})();
</script>

HTML;
}

foreach ( array( 60 => 'en', 770 => 'es' ) as $post_id => $lang ) {
	if ( $apply && ! $handler_loaded ) {
		continue;
	}
	$block = p1_form_block( $lang, $form_text[ $lang ] );
	p0_edit_elementor(
		$post_id,
		'CONTACT FORM ' . strtoupper( $lang ),
		'.contact-hero',
		function ( $html, &$errors ) use ( $block ) {
			preg_match( '#<h1 class="hero-title">.*?</h1>#s', $html, $h1_before );
			$html = p0_between( $html, '<!-- FORM -->', '<!-- INFO -->', $block, false, $errors );
			preg_match( '#<h1 class="hero-title">.*?</h1>#s', $html, $h1_after );
			if ( empty( $h1_before ) || $h1_before[0] !== ( $h1_after[0] ?? null ) ) {
				$errors[] = 'H1 changed or missing';
			}
			return $html;
		},
		array( 'xxxxxxx', '538 663', 'Atelier', 'Flagship', 'igsh=', 'href="#"', 'Beauty Consultation', 'Consulta de Belleza' ),
		array(
			'<form class="contact-form" method="post"', 'name="lunaci_cf"', 'name="lc_js"', 'name="website"',
			'name="first_name"', 'name="email"', 'name="message"', 'id="consent" name="consent"', '</form>',
			'id="lunaci-form-status"', '<!-- INFO -->', 'Patriotic Trade, S.L.', '+34 933 538 633',
		)
	);
}

/* --------------------------------------------------------------------------
 * 2. Returns pages: hygiene exception paragraph
 * ----------------------------------------------------------------------- */

$returns = array(
	760 => array(
		'anchor' => 'provided it is unused and in its original packaging.',
		'marker' => 'For health and hygiene reasons',
		'text'   => 'For health and hygiene reasons, sealed cosmetics that have been unsealed or used after delivery cannot be returned, unless the product is defective (Article 103(e) of the Spanish General Law for the Defence of Consumers and Users). This does not affect your legal guarantee for defective products.',
	),
	766 => array(
		'anchor' => 'siempre que esté sin usar y en su embalaje original.',
		'marker' => 'Por motivos de protección de la salud',
		'text'   => 'Por motivos de protección de la salud y de higiene, los cosméticos precintados que se hayan desprecintado o utilizado tras la entrega no pueden devolverse, salvo que el producto sea defectuoso (artículo 103.e del Texto Refundido de la Ley General para la Defensa de los Consumidores y Usuarios). Esto no afecta a tu garantía legal por productos defectuosos.',
	),
);

foreach ( $returns as $post_id => $r ) {
	p0_h( "RETURNS HYGIENE CLAUSE (post {$post_id})" );
	$content = $wpdb->get_var( $wpdb->prepare( "SELECT post_content FROM {$wpdb->posts} WHERE ID = %d", $post_id ) );
	if ( null === $content ) {
		echo "ERROR: post not found\n";
		$GLOBALS['p0_fail']++;
		continue;
	}
	if ( false !== strpos( $content, $r['marker'] ) ) {
		echo "SKIP: clause already present\n";
		continue;
	}
	if ( 1 !== substr_count( $content, $r['anchor'] ) ) {
		echo 'GUARD FAILED: anchor sentence found ' . substr_count( $content, $r['anchor'] ) . " times (expected 1)\n";
		$GLOBALS['p0_fail']++;
		continue;
	}
	$p_end = strpos( $content, '</p>', strpos( $content, $r['anchor'] ) );
	if ( false === $p_end ) {
		echo "GUARD FAILED: no closing </p> after the anchor\n";
		$GLOBALS['p0_fail']++;
		continue;
	}
	$insert_at = $p_end + 4;
	$after     = ltrim( substr( $content, $insert_at ) );
	$is_block  = ( 0 === strpos( $after, '<!-- /wp:paragraph -->' ) );
	if ( $is_block ) {
		$insert_at = strpos( $content, '<!-- /wp:paragraph -->', $insert_at ) + strlen( '<!-- /wp:paragraph -->' );
		$snippet   = "\n\n<!-- wp:paragraph -->\n<p>" . esc_html( $r['text'] ) . "</p>\n<!-- /wp:paragraph -->";
	} else {
		$snippet = "\n<p>" . esc_html( $r['text'] ) . '</p>';
	}
	$new = substr( $content, 0, $insert_at ) . $snippet . substr( $content, $insert_at );
	echo 'format: ' . ( $is_block ? 'Gutenberg paragraph block' : 'plain <p>' ) . "\n";
	echo "will insert after the first paragraph:\n  " . $r['text'] . "\n";

	if ( ! $apply ) {
		echo "DRY-RUN: no write\n";
		continue;
	}
	if ( ! p0_backup_post( $post_id ) ) {
		echo "ERROR: backup failed - untouched\n";
		$GLOBALS['p0_fail']++;
		continue;
	}
	$ok = $wpdb->update( $wpdb->posts, array( 'post_content' => $new ), array( 'ID' => $post_id ) );
	clean_post_cache( $post_id );
	$check = $wpdb->get_var( $wpdb->prepare( "SELECT post_content FROM {$wpdb->posts} WHERE ID = %d", $post_id ) );
	$good  = ( false !== $ok && $check === $new );
	echo 'VERIFY clause stored: ' . ( $good ? 'YES' : 'NO' ) . "\n";
	if ( ! $good ) {
		$GLOBALS['p0_fail']++;
	}
}

/* --------------------------------------------------------------------------
 * 3. Stale post_content on Elementor pages (search / feed fallback)
 * ----------------------------------------------------------------------- */

$stale_markers = array( '538 663', '538663', 'xxxxxxx', 'igsh=', '>TK<', '>PT<', 'Atelier', 'Flagship', 'lunaci.es' );

foreach ( array( 60 => 'Contact EN', 770 => 'Contacto ES', 59 => 'About EN', 680 => 'About ES' ) as $post_id => $label ) {
	p0_h( "POST_CONTENT CLEANUP {$label} (post {$post_id})" );
	$content = (string) $wpdb->get_var( $wpdb->prepare( "SELECT post_content FROM {$wpdb->posts} WHERE ID = %d", $post_id ) );
	$found   = array();
	foreach ( $stale_markers as $m ) {
		if ( false !== strpos( $content, $m ) ) {
			$found[] = $m;
		}
	}
	echo 'post_content length ' . strlen( $content ) . '; stale markers: ' . ( $found ? implode( ', ', $found ) : 'none' ) . "\n";
	if ( ! $found ) {
		echo "SKIP: nothing stale\n";
		continue;
	}
	if ( ! class_exists( '\Elementor\Plugin' ) ) {
		echo "ERROR: Elementor not loaded - cannot regenerate\n";
		$GLOBALS['p0_fail']++;
		continue;
	}

	$rendered = \Elementor\Plugin::$instance->frontend->get_builder_content_for_display( $post_id );
	$text     = preg_replace( '#<(style|script|svg|title|noscript|form)\b[^>]*>.*?</\1>#is', '', (string) $rendered );
	$text     = preg_replace( '#<!--.*?-->#s', '', $text );
	$text     = wp_kses( $text, array(
		'h1' => array(), 'h2' => array(), 'h3' => array(), 'h4' => array(), 'p' => array(),
		'a'  => array( 'href' => true ), 'br' => array(), 'em' => array(), 'strong' => array(),
		'ul' => array(), 'li' => array(),
	) );
	$text = trim( preg_replace( "/\n\s*\n+/", "\n\n", preg_replace( '/[ \t]+/', ' ', $text ) ) );

	$left = array();
	foreach ( $stale_markers as $m ) {
		if ( false !== strpos( $text, $m ) ) {
			$left[] = $m;
		}
	}
	if ( strlen( $text ) < 200 || $left ) {
		echo 'GUARD FAILED: regenerated text too short (' . strlen( $text ) . ' bytes) or still stale (' . implode( ', ', $left ) . ") - untouched\n";
		$GLOBALS['p0_fail']++;
		continue;
	}
	echo 'regenerated plain content: ' . strlen( $text ) . " bytes\n";
	echo '--- first 500 chars ---' . "\n" . mb_substr( $text, 0, 500 ) . "\n--- end ---\n";

	if ( ! $apply ) {
		echo "DRY-RUN: no write\n";
		continue;
	}
	if ( ! p0_backup_post( $post_id ) ) {
		echo "ERROR: backup failed - untouched\n";
		$GLOBALS['p0_fail']++;
		continue;
	}
	$ok = $wpdb->update( $wpdb->posts, array( 'post_content' => $text ), array( 'ID' => $post_id ) );
	clean_post_cache( $post_id );
	echo 'VERIFY post_content updated: ' . ( false !== $ok ? 'YES' : 'NO ' . $wpdb->last_error ) . "\n";
	if ( false === $ok ) {
		$GLOBALS['p0_fail']++;
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
