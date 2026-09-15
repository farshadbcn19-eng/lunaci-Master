<?php
global $wpdb;

$snippet_id = 8;

// Exact code re-embedded from the live snippet, confirmed byte-for-byte
// (length 7284, stable across 3 $wpdb reads plus a fresh mysqli connection,
// pure ASCII, first/last 60 bytes hex-verified) via
// diagnose-snippet8-full-dump.yml, diagnose-snippet8-encoding.yml and
// diagnose-snippet8-diff.yml run immediately before this workflow was
// authored. (An earlier version of this script used a stale reference that
// predated the WPML language-switcher block and was correctly rejected by
// the precondition check below without writing anything.)
$expected_code_before = <<<'LUNACI_SNIPPET_CODE'
add_action( 'wp_footer', function () {
	$lunaci_active_langs = apply_filters( 'wpml_active_languages', null, array( 'skip_missing' => 0, 'orderby' => 'code' ) );
	$lunaci_lang_items   = '';
	if ( is_array( $lunaci_active_langs ) ) {
		foreach ( $lunaci_active_langs as $lunaci_lang ) {
			$lunaci_code        = strtoupper( $lunaci_lang['language_code'] );
			$lunaci_name        = $lunaci_lang['native_name'];
			$lunaci_url         = $lunaci_lang['url'];
			$lunaci_active_cls  = ! empty( $lunaci_lang['active'] ) ? ' active' : '';
			$lunaci_lang_items .= '<li><a href="' . esc_url( $lunaci_url ) . '" class="ln-lang-switch__item' . $lunaci_active_cls . '">' . esc_html( $lunaci_code ) . '<span>' . esc_html( $lunaci_name ) . '</span></a></li>';
		}
	}
	?>
<style id="lunaci-global-header-css">
.ln-nav{position:fixed;top:0;left:0;right:0;z-index:9999;display:flex;align-items:center;justify-content:space-between;padding:16px 5%;background:linear-gradient(to bottom,rgba(11,11,11,.95),transparent);transition:background .4s;border-bottom:1px solid transparent;}
.ln-nav.scrolled{background:rgba(11,11,11,.97);border-bottom-color:rgba(212,175,55,.15);}
.ln-nav__logo img{height:90px;width:auto;display:block;mix-blend-mode:lighten;}
.ln-nav__links{display:flex;gap:36px;list-style:none;margin:0;padding:0;}
.ln-nav__links a{font-family:'Montserrat',sans-serif;font-size:10px;font-weight:400;letter-spacing:.3em;text-transform:uppercase;color:rgba(247,244,238,.9);text-decoration:none;transition:color .3s;}
.ln-nav__links a:hover{color:#D4AF37;}
.ln-nav__cart a{color:rgba(247,244,238,.9);text-decoration:none;}
@media(max-width:768px){.ln-nav__links{display:none;}.ln-nav{padding:14px 5%;}}
.ln-nav-toggle{display:none;background:none;border:none;}
@media(max-width:768px){
  .ln-nav__links.open{display:flex!important;flex-direction:column;position:absolute;top:100%;left:0;right:0;background:#0B0B0B;padding:20px 32px;border-top:1px solid rgba(212,175,55,.2);z-index:9999;}
  .ln-nav-toggle{display:flex!important;flex-direction:column;gap:5px;cursor:pointer;padding:8px;background:none;border:none;}
  .ln-nav-toggle span{display:block;width:24px;height:2px;background:#D4AF37;}
}

#lnNav { display: none !important; }
#lnaNav { display: none !important; }
.lp-header { display: none !important; }
.site-header, header.site-header,
.elementor-location-header, #masthead {
	display: none !important;
}

body:not(.home) { padding-top: 128px; }
@media (max-width: 768px) {
  body:not(.home) { padding-top: 110px; }
}

body:not(.home) .ln-nav {
  background: rgba(11,11,11,.97) !important;
}

.ln-lang-switch{position:relative;margin-left:14px;}
.ln-lang-switch__btn{display:flex;align-items:center;justify-content:center;width:34px;height:34px;background:transparent;border:1px solid rgba(212,175,55,.35);border-radius:50%;color:rgba(247,244,238,.85);cursor:pointer;transition:border-color .3s,color .3s;padding:0;}
.ln-lang-switch__btn:hover{border-color:#D4AF37;color:#D4AF37;}
.ln-lang-switch__menu{list-style:none;margin:0;padding:8px 0;position:absolute;top:calc(100% + 12px);right:0;min-width:170px;background:rgba(11,11,11,.98);border:1px solid rgba(212,175,55,.25);opacity:0;visibility:hidden;transform:translateY(-6px);transition:opacity .25s,transform .25s,visibility .25s;z-index:10000;}
.ln-lang-switch.open .ln-lang-switch__menu{opacity:1;visibility:visible;transform:translateY(0);}
.ln-lang-switch__item{display:flex;align-items:center;gap:8px;padding:10px 18px;font-family:'Montserrat',sans-serif;font-size:11px;letter-spacing:.15em;text-transform:uppercase;color:rgba(247,244,238,.75);text-decoration:none;transition:color .25s,background .25s;}
.ln-lang-switch__item:hover{color:#D4AF37;background:rgba(212,175,55,.06);}
.ln-lang-switch__item.active{color:#D4AF37;}
.ln-lang-switch__item span{font-size:9px;letter-spacing:.08em;text-transform:none;opacity:.55;font-style:italic;margin-left:2px;}
@media(max-width:768px){.ln-lang-switch{margin-left:6px;}}

/* Hide WPML's own auto-injected footer language switcher (legacy-list-horizontal) - we use our own header switcher instead */
.wpml-ls-statics-footer,
div[aria-label="Language Switcher"] { display: none !important; }
</style>
<nav class="ln-nav" id="lunaciGlobalNav">
  <a href="https://lunacibarcelona.com/" class="ln-nav__logo">
    <img src="https://lunacibarcelona.com/wp-content/uploads/2026/05/b320427b-bdbd-4220-926d-c2fecce7e9e4.jpeg" alt="LUNACI Barcelona" style="height:90px;width:auto;mix-blend-mode:lighten;">
  </a>
  <ul class="ln-nav__links" id="lunaciGlobalNavLinks">
    <li><a href="https://lunacibarcelona.com/">Home</a></li>
    <li><a href="https://lunacibarcelona.com/products/">Products</a></li>
    <li><a href="https://lunacibarcelona.com/about-us/">About</a></li>
    <li><a href="https://lunacibarcelona.com/contact">Contact</a></li>
  </ul>
  <div class="ln-nav__cart">
    <a href="https://lunacibarcelona.com/cart">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="rgba(247,244,238,.9)" stroke-width="1.5"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
    </a>
  </div>
  <div class="ln-lang-switch" id="lnLangSwitch">
    <button type="button" class="ln-lang-switch__btn" id="lnLangBtn" aria-haspopup="true" aria-expanded="false" aria-label="Select language">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
    </button>
    <ul class="ln-lang-switch__menu" id="lnLangMenu"><?php echo $lunaci_lang_items; ?></ul>
  </div>
  <button type="button" class="ln-nav-toggle" id="lunaciGlobalNavToggle" aria-label="Toggle navigation menu" aria-expanded="false" aria-controls="lunaciGlobalNavLinks">
    <span></span>
    <span></span>
    <span></span>
  </button>
</nav>
<script id="lunaci-global-header-js">
(function () {
  var navToggle = document.getElementById('lunaciGlobalNavToggle');
  var navLinks = document.getElementById('lunaciGlobalNavLinks');
  if (navToggle && navLinks) {
    navToggle.addEventListener('click', function () {
      var isOpen = navLinks.classList.toggle('open');
      navToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });
    navLinks.querySelectorAll('a').forEach(function (link) {
      link.addEventListener('click', function () {
        navLinks.classList.remove('open');
        navToggle.setAttribute('aria-expanded', 'false');
      });
    });
  }

  var langSwitch = document.getElementById('lnLangSwitch');
  var langBtn = document.getElementById('lnLangBtn');
  if (langSwitch && langBtn) {
    langBtn.addEventListener('click', function (e) {
      e.stopPropagation();
      var isOpen = langSwitch.classList.toggle('open');
      langBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });
    document.addEventListener('click', function (e) {
      if (!langSwitch.contains(e.target)) {
        langSwitch.classList.remove('open');
        langBtn.setAttribute('aria-expanded', 'false');
      }
    });
  }
})();
</script>
	<?php
}, 100 );
LUNACI_SNIPPET_CODE;

// The base .ln-nav rule fades from rgba(11,11,11,.95) at the very top of the
// fixed header down to fully transparent (alpha 0) by the bottom of the bar.
// On the Home page - the only page where this base gradient is still visible,
// since non-Home pages already got a solid override above - the hero photo's
// bright highlights sit directly under that zero-alpha point, producing a
// hard seam where the header appears to have no background at all. Floor the
// gradient at a low-but-nonzero alpha instead of transparent so the header
// always reads as a continuous dark bar, while keeping the same fade-in feel.
$old_gradient = 'background:linear-gradient(to bottom,rgba(11,11,11,.95),transparent)';
$new_gradient = 'background:linear-gradient(to bottom,rgba(11,11,11,.95),rgba(11,11,11,.55))';

echo "=====================================================\n";
echo "STEP A: PREPARE - fresh-read snippet id=$snippet_id and validate preconditions\n";
echo "=====================================================\n";

$snippets_table = $wpdb->prefix . 'snippets';
$row = $wpdb->get_row(
	$wpdb->prepare( "SELECT id, name, active, scope, code FROM $snippets_table WHERE id = %d", $snippet_id ),
	ARRAY_A
);

if ( ! $row ) {
	echo "ERROR: no snippet row found with id=$snippet_id\n";
	echo "ABORT: cannot modify a row that does not exist\n";
	exit( 1 );
}
echo "found row: id={$row['id']}  name=\"{$row['name']}\"  active={$row['active']}  scope={$row['scope']}\n";

if ( 1 !== (int) $row['active'] ) {
	echo "ERROR: expected active=1, found active={$row['active']}\n";
	echo "ABORT\n";
	exit( 1 );
}
echo "OK: active=1 confirmed\n";

echo "expected code length=" . strlen( $expected_code_before ) . "\n";
echo "stored code length=" . strlen( $row['code'] ) . "\n";
$code_matches = ( $row['code'] === $expected_code_before );
echo "stored code matches expected pre-change value byte-for-byte: " . ( $code_matches ? 'YES' : 'NO' ) . "\n";

if ( ! $code_matches ) {
	echo "ERROR: stored code has drifted since the last diagnostic read - refusing to modify\n";
	echo "ABORT\n";
	exit( 1 );
}
echo "OK: code drift check passed\n";

$old_count = substr_count( $row['code'], $old_gradient );
echo "occurrences of target gradient rule in stored code: $old_count\n";
if ( 1 !== $old_count ) {
	echo "ERROR: expected exactly 1 occurrence of the target gradient rule, found $old_count - refusing to modify (ambiguous target)\n";
	echo "ABORT\n";
	exit( 1 );
}
echo "OK: exactly one occurrence found\n";

echo "\n=====================================================\n";
echo "STEP B: COMMIT - floor the Home-page nav gradient at alpha .55 instead of transparent\n";
echo "=====================================================\n";

$updated_code = str_replace( $old_gradient, $new_gradient, $row['code'], $replace_count );
echo "replacements made: $replace_count\n";

if ( 1 !== $replace_count ) {
	echo "ERROR: expected exactly 1 replacement, made $replace_count - refusing to modify\n";
	echo "ABORT\n";
	exit( 1 );
}
echo "OK: computed updated code locally, new length=" . strlen( $updated_code ) . " (was " . strlen( $row['code'] ) . ")\n";

$update_ok = $wpdb->update(
	$snippets_table,
	array( 'code' => $updated_code ),
	array( 'id' => $snippet_id ),
	array( '%s' ),
	array( '%d' )
);

if ( false === $update_ok ) {
	echo "ERROR: \$wpdb->update() failed: {$wpdb->last_error}\n";
	echo "ABORT\n";
	exit( 1 );
}
echo "OK: update() returned $update_ok (rows affected)\n";

echo "\n=====================================================\n";
echo "STEP C: VERIFY - re-read, confirm new rule present, active still 1, rest unchanged\n";
echo "=====================================================\n";

$any_error = false;

$verify_row = $wpdb->get_row(
	$wpdb->prepare( "SELECT id, name, active, scope, code FROM $snippets_table WHERE id = %d", $snippet_id ),
	ARRAY_A
);

if ( ! $verify_row ) {
	echo "ERROR: could not re-read row id=$snippet_id after update\n";
	$any_error = true;
} else {
	echo "re-read id={$verify_row['id']}  name=\"{$verify_row['name']}\"  active={$verify_row['active']}  scope={$verify_row['scope']}\n";

	$active_ok = ( 1 === (int) $verify_row['active'] );
	echo "active is still exactly 1: " . ( $active_ok ? 'YES' : 'NO' ) . "\n";

	$has_new_rule = ( false !== strpos( $verify_row['code'], $new_gradient ) );
	echo "new gradient rule present in stored code: " . ( $has_new_rule ? 'YES' : 'NO' ) . "\n";

	$old_gone = ( false === strpos( $verify_row['code'], $old_gradient ) );
	echo "old transparent gradient rule fully removed: " . ( $old_gone ? 'YES' : 'NO' ) . "\n";

	$exact_match = ( $verify_row['code'] === $updated_code );
	echo "stored code matches computed updated code byte-for-byte (rest of snippet unchanged): " . ( $exact_match ? 'YES' : 'NO' ) . "\n";

	if ( ! $active_ok || ! $has_new_rule || ! $old_gone || ! $exact_match ) {
		echo "ERROR: verification FAILED\n";
		$any_error = true;
	} else {
		echo "OK: verification passed\n";
	}

	echo "\n--- FULL STORED CODE AFTER UPDATE (verbatim) ---\n";
	echo $verify_row['code'] . "\n";
	echo "--- END STORED CODE ---\n";
}

echo "\n=====================================================\n";
if ( $any_error ) {
	echo "ABORT: verification error - see ERROR notice(s) above. Recommend manual DB inspection immediately.\n";
	exit( 1 );
}
echo "OK: Home-page nav gradient floored at alpha .55 on snippet id=$snippet_id, active=1, rest of code unchanged.\n";
