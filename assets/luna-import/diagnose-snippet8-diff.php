<?php
global $wpdb;

$snippet_id = 8;
$snippets_table = $wpdb->prefix . 'snippets';

$reference = <<<'LUNACI_SNIPPET_CODE'
add_action( 'wp_footer', function () {
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
})();
</script>
	<?php
}, 100 );
LUNACI_SNIPPET_CODE;

$row = $wpdb->get_row(
	$wpdb->prepare( "SELECT code FROM $snippets_table WHERE id = %d", $snippet_id ),
	ARRAY_A
);
$live = $row['code'];

echo "reference length: " . strlen( $reference ) . "\n";
echo "live length: " . strlen( $live ) . "\n";

$min_len = min( strlen( $reference ), strlen( $live ) );
$divergence = null;
for ( $i = 0; $i < $min_len; $i++ ) {
	if ( $reference[ $i ] !== $live[ $i ] ) {
		$divergence = $i;
		break;
	}
}

if ( null === $divergence ) {
	echo "no divergence found in overlapping range (one is a prefix of the other)\n";
	echo "live content beyond reference length, starting at offset $min_len:\n";
	echo "----- EXTRA CONTENT START -----\n";
	echo substr( $live, $min_len );
	echo "\n----- EXTRA CONTENT END -----\n";
} else {
	echo "first divergence at byte offset: $divergence\n";
	$context_start = max( 0, $divergence - 150 );
	echo "\n----- REFERENCE context around divergence -----\n";
	echo substr( $reference, $context_start, 400 );
	echo "\n----- LIVE context around divergence -----\n";
	echo substr( $live, $context_start, 400 );
	echo "\n----- END context -----\n";

	// Try to find where the reference's tail re-syncs with the live content,
	// to bound the inserted/changed region.
	$tail_marker = substr( $reference, -200 );
	$resync_pos = strpos( $live, $tail_marker );
	echo "\nreference's last 200 bytes found in live content at offset: " . ( false === $resync_pos ? 'NOT FOUND' : $resync_pos ) . "\n";
	if ( false !== $resync_pos ) {
		echo "\n----- LIVE content strictly BETWEEN divergence and re-sync point (offset $divergence to $resync_pos) -----\n";
		echo substr( $live, $divergence, $resync_pos - $divergence );
		echo "\n----- END inserted/changed region -----\n";
	}
}

echo "\nOK: diff diagnostic completed (no writes)\n";
