<?php
// Self-check for mu-plugins/lunaci-product-page.php with WordPress stubbed.
// Run: php scripts/test-product-page-plugin.php   (exit 0 = all cases pass)
// Each case runs in its own PHP process because functions can't be redefined.

if ( getenv( 'LPP_CASE' ) ) {
	// --- child: stub WordPress, load the plugin, report what it outputs ---
	define( 'ABSPATH', '/' );
	$case    = json_decode( getenv( 'LPP_CASE' ), true );
	$hooks   = array();
	$filters = array();
	function add_action( $hook, $cb, $prio = 10 ) { global $hooks; $hooks[ $hook ][ $prio ][] = $cb; }
	function add_filter( $hook, $cb, $prio = 10, $args = 1 ) { global $filters; $filters[ $hook ][] = $cb; }
	function is_product() { global $case; return 'product' === $case['page']; }
	function is_product_taxonomy() { global $case; return 'category' === $case['page']; }
	function wp_strip_all_tags( $s ) { return strip_tags( $s ); }
	if ( $case['helper'] ) {
		// Mirrors lunaci-perf.php: active for "on", or "test" with ?lunaci_perf=1.
		function lunaci_perf_mode_active( $option ) {
			global $case;
			$mode = 'lunaci_product_page_mode' === $option ? $case['mode'] : ( $case['extras'] ?? 'off' );
			return 'on' === $mode || ( 'test' === $mode && $case['query'] );
		}
	}
	class Fake_Product {
		function __construct( $text, $name ) { $this->t = $text; $this->n = $name; }
		function add_to_cart_text() { return $this->t; }
		function get_name() { return $this->n; }
	}
	require __DIR__ . '/../mu-plugins/lunaci-product-page.php';
	ob_start();
	if ( isset( $hooks['wp_head'] ) ) {
		ksort( $hooks['wp_head'] );
		foreach ( $hooks['wp_head'] as $cbs ) { foreach ( $cbs as $cb ) { $cb(); } }
	}
	$head  = ob_get_clean();
	// Code Snippets id=7 prints the banner on woocommerce_before_main_content at priority 5.
	$hooks['woocommerce_before_main_content'][5][] = function () { echo '<div class="lunaci-category-banner"><img src="x.webp" class="lunaci-category-banner__img" alt="LUNACI Lips" /></div>'; };
	ob_start();
	ksort( $hooks['woocommerce_before_main_content'] );
	foreach ( $hooks['woocommerce_before_main_content'] as $cbs ) { foreach ( $cbs as $cb ) { $cb(); } }
	$foot  = ob_get_clean();
	$attrs = array( 'attribute_pa_color' => 1, 'attribute_shade' => 1 );
	foreach ( $filters['woocommerce_display_product_attributes'] ?? array() as $cb ) { $attrs = $cb( $attrs ); }
	$labels = array();
	foreach ( array( array( 'Seleccionar opciones', 'Elige las opciones para “Delineador”', 'Delineador' ), array( 'Select options', 'Select options for “Lipgloss”', 'Lipgloss' ) ) as list( $text, $aria, $name ) ) {
		$args = array( 'attributes' => array( 'aria-label' => $aria ) );
		foreach ( $filters['woocommerce_loop_add_to_cart_args'] ?? array() as $cb ) { $args = $cb( $args, new Fake_Product( $text, $name ) ); }
		$labels[] = $args['attributes']['aria-label'];
	}
	echo json_encode( array( 'head' => $head, 'foot' => $foot, 'attrs' => array_keys( $attrs ), 'labels' => $labels ) );
	exit( 0 );
}

// --- parent: run each case and check the outputs ---
$es_fixed = 'Seleccionar opciones – Delineador';
$es_orig  = 'Elige las opciones para “Delineador”';
$en_orig  = 'Select options for “Lipgloss”';
$cases    = array(
	// name => [case, product block printed?, extras block printed?, Color hidden?, ES label]
	'on, product page'                 => array( array( 'mode' => 'on',   'page' => 'product',  'helper' => true,  'query' => false ), true,  false, false, $es_orig ),
	'test, product page, ?param'       => array( array( 'mode' => 'test', 'page' => 'product',  'helper' => true,  'query' => true ),  true,  false, false, $es_orig ),
	'test, product page, visitor'      => array( array( 'mode' => 'test', 'page' => 'product',  'helper' => true,  'query' => false ), false, false, false, $es_orig ),
	'off, product page'                => array( array( 'mode' => 'off',  'page' => 'product',  'helper' => true,  'query' => true ),  false, false, false, $es_orig ),
	'on, not a product page'           => array( array( 'mode' => 'on',   'page' => 'other',    'helper' => true,  'query' => false ), false, false, false, $es_orig ),
	'on, lunaci-perf.php missing'      => array( array( 'mode' => 'on',   'page' => 'product',  'helper' => false, 'query' => false, 'extras' => 'on' ), false, false, false, $es_orig ),
	'extras on, product page'          => array( array( 'mode' => 'on',   'page' => 'product',  'helper' => true,  'query' => false, 'extras' => 'on' ), true,  true,  true,  $es_fixed ),
	'extras on, category page'         => array( array( 'mode' => 'on',   'page' => 'category', 'helper' => true,  'query' => false, 'extras' => 'on' ), false, true,  true,  $es_fixed ),
	'extras on, other page'            => array( array( 'mode' => 'on',   'page' => 'other',    'helper' => true,  'query' => false, 'extras' => 'on' ), false, false, true,  $es_fixed ),
	'extras test, visitor'             => array( array( 'mode' => 'on',   'page' => 'category', 'helper' => true,  'query' => false, 'extras' => 'test' ), false, false, false, $es_orig ),
	'extras test, ?param'              => array( array( 'mode' => 'on',   'page' => 'category', 'helper' => true,  'query' => true,  'extras' => 'test' ), false, true,  true,  $es_fixed ),
);
$fail = 0;
foreach ( $cases as $name => list( $case, $want_page, $want_extras, $want_hidden, $want_es ) ) {
	$raw = shell_exec( 'LPP_CASE=' . escapeshellarg( json_encode( $case ) ) . ' ' . escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __FILE__ ) . ' 2>&1' );
	$out = json_decode( (string) $raw, true );
	$got = is_array( $out ) ? array(
		1 === substr_count( $out['head'], '<style id="lunaci-product-page">' ),
		1 === substr_count( $out['head'], '<style id="lunaci-product-extras">' ),
		! in_array( 'attribute_pa_color', $out['attrs'], true ) && in_array( 'attribute_shade', $out['attrs'], true ),
		$out['labels'][0],
		$out['labels'][1],
		1 === substr_count( $out['foot'], '<div class="lunaci-category-banner"' ) ? ( false !== strpos( $out['foot'], '<div class="lunaci-category-banner" role="region" aria-label="LUNACI Lips">' ) ) : 'banner count wrong',
	) : null;
	// English label never changes; the banner becomes a named region only with extras on category pages (and is never lost or doubled).
	$want = array( $want_page, $want_extras, $want_hidden, $want_es, $en_orig, $want_extras && 'category' === $case['page'] );
	$ok   = $got === $want;
	$fail += $ok ? 0 : 1;
	printf( "%s  %s%s\n", $ok ? 'PASS' : 'FAIL', $name, $ok ? '' : ' -> got: ' . ( $got ? json_encode( $got, JSON_UNESCAPED_UNICODE ) : trim( (string) $raw ) ) );
}
exit( $fail ? 1 : 0 );
