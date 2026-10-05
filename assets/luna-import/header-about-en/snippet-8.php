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
	// Menu labels and links follow the WPML language (Spanish pages get the
	// Spanish menu). English output is unchanged.
	$lunaci_es  = ( 'es' === apply_filters( 'wpml_current_language', null ) );
	$lunaci_nav = $lunaci_es ? array(
		'logo'      => 'https://lunacibarcelona.com/es/',
		'links'     => array(
			array( 'https://lunacibarcelona.com/es/', 'Inicio' ),
			array( 'https://lunacibarcelona.com/es/productos/', 'Productos' ),
			array( 'https://lunacibarcelona.com/es/sobre-nosotros/', 'Sobre Nosotros' ),
			array( 'https://lunacibarcelona.com/es/contacto/', 'Contacto' ),
		),
		'cart'      => 'https://lunacibarcelona.com/es/carrito/',
		'cart_aria' => 'Carrito',
		'lang_aria' => 'Seleccionar idioma',
		'menu_aria' => 'Abrir o cerrar el menú',
	) : array(
		'logo'      => 'https://lunacibarcelona.com/',
		'links'     => array(
			array( 'https://lunacibarcelona.com/', 'Home' ),
			array( 'https://lunacibarcelona.com/products/', 'Products' ),
			array( 'https://lunacibarcelona.com/about-us/', 'About' ),
			array( 'https://lunacibarcelona.com/contact', 'Contact' ),
		),
		'cart'      => 'https://lunacibarcelona.com/cart',
		'cart_aria' => '',
		'lang_aria' => 'Select language',
		'menu_aria' => 'Toggle navigation menu',
	);
	?>
<style id="lunaci-global-header-css">
.ln-nav{position:fixed;top:0;left:0;right:0;z-index:9999;display:flex;align-items:center;justify-content:space-between;padding:16px 5%;background:rgba(11,11,11,.97);transition:background .4s;border-bottom:1px solid transparent;}
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
  <a href="<?php echo esc_url( $lunaci_nav['logo'] ); ?>" class="ln-nav__logo">
    <img src="https://lunacibarcelona.com/wp-content/uploads/2026/05/b320427b-bdbd-4220-926d-c2fecce7e9e4.jpeg" alt="LUNACI Barcelona" style="height:90px;width:auto;mix-blend-mode:lighten;">
  </a>
  <ul class="ln-nav__links" id="lunaciGlobalNavLinks">
<?php foreach ( $lunaci_nav['links'] as $lunaci_link ) : ?>
    <li><a href="<?php echo esc_url( $lunaci_link[0] ); ?>"><?php echo esc_html( $lunaci_link[1] ); ?></a></li>
<?php endforeach; ?>
  </ul>
  <div class="ln-nav__cart">
    <a href="<?php echo esc_url( $lunaci_nav['cart'] ); ?>"<?php echo $lunaci_nav['cart_aria'] ? ' aria-label="' . esc_attr( $lunaci_nav['cart_aria'] ) . '"' : ''; ?>>
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="rgba(247,244,238,.9)" stroke-width="1.5"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
    </a>
  </div>
  <div class="ln-lang-switch" id="lnLangSwitch">
    <button type="button" class="ln-lang-switch__btn" id="lnLangBtn" aria-haspopup="true" aria-expanded="false" aria-label="<?php echo esc_attr( $lunaci_nav['lang_aria'] ); ?>">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
    </button>
    <ul class="ln-lang-switch__menu" id="lnLangMenu"><?php echo $lunaci_lang_items; ?></ul>
  </div>
  <button type="button" class="ln-nav-toggle" id="lunaciGlobalNavToggle" aria-label="<?php echo esc_attr( $lunaci_nav['menu_aria'] ); ?>" aria-expanded="false" aria-controls="lunaciGlobalNavLinks">
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