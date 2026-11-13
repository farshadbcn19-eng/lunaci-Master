<?php
// Self-check for mu-plugins/lunaci-product-page.php with WordPress stubbed.
// Run: php scripts/test-product-page-plugin.php   (exit 0 = all cases pass)
// Each case runs in its own PHP process because functions can't be redefined.

if ( getenv( 'LPP_CASE' ) ) {
	// --- child: stub WordPress, load the plugin, print what wp_head outputs ---
	define( 'ABSPATH', '/' );
	$case  = json_decode( getenv( 'LPP_CASE' ), true );
	$hooks = array();
	function add_action( $hook, $cb, $prio = 10 ) { global $hooks; $hooks[ $hook ][ $prio ][] = $cb; }
	function is_product() { global $case; return $case['product']; }
	if ( $case['helper'] ) {
		// Mirrors lunaci-perf.php: active for "on", or "test" with ?lunaci_perf=1.
		function lunaci_perf_mode_active( $option ) {
			global $case;
			return 'lunaci_product_page_mode' === $option
				&& ( 'on' === $case['mode'] || ( 'test' === $case['mode'] && $case['query'] ) );
		}
	}
	require __DIR__ . '/../mu-plugins/lunaci-product-page.php';
	ksort( $hooks['wp_head'] );
	foreach ( $hooks['wp_head'] as $cbs ) { foreach ( $cbs as $cb ) { $cb(); } }
	exit( 0 );
}

// --- parent: run each case, check whether the style block was printed ---
$cases = array(
	'on, product page'              => array( array( 'mode' => 'on',   'product' => true,  'helper' => true,  'query' => false ), true ),
	'test, product page, ?param'    => array( array( 'mode' => 'test', 'product' => true,  'helper' => true,  'query' => true ),  true ),
	'test, product page, visitor'   => array( array( 'mode' => 'test', 'product' => true,  'helper' => true,  'query' => false ), false ),
	'off, product page'             => array( array( 'mode' => 'off',  'product' => true,  'helper' => true,  'query' => true ),  false ),
	'on, not a product page'        => array( array( 'mode' => 'on',   'product' => false, 'helper' => true,  'query' => false ), false ),
	'on, lunaci-perf.php missing'   => array( array( 'mode' => 'on',   'product' => true,  'helper' => false, 'query' => false ), false ),
);
$fail = 0;
foreach ( $cases as $name => list( $case, $want ) ) {
	$out  = shell_exec( 'LPP_CASE=' . escapeshellarg( json_encode( $case ) ) . ' ' . escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __FILE__ ) . ' 2>&1' );
	$got  = 1 === substr_count( (string) $out, '<style id="lunaci-product-page">' );
	$ok   = $got === $want && ( $want || '' === trim( (string) $out ) );
	$fail += $ok ? 0 : 1;
	printf( "%s  %s (expected %s)%s\n", $ok ? 'PASS' : 'FAIL', $name, $want ? 'printed' : 'nothing', $ok ? '' : ' -> got: ' . trim( (string) $out ) );
}
exit( $fail ? 1 : 0 );
