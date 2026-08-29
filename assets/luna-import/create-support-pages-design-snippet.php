<?php
/**
 * Create a new "LUNACI Support Pages Design" snippet that brand-styles the
 * four Support footer pages (Shipping, Returns, Privacy Policy, Terms of
 * Service - EN + ES/WPML, 8 page IDs total). These pages currently use the
 * plain default hello-elementor page template - confirmed via live
 * computed-style check: white body background, #333 gray text, generic
 * "Trade Gothic LT Std Extended"/"Helvetica LUNACI" fonts already applied
 * (from the global Brand Fonts snippet) but a magenta-pink link color and
 * no dark-luxury treatment at all - completely disconnected from the rest
 * of the branded site.
 *
 * Reuses the exact live color tokens from the Shop Design snippet's :root
 * block (--lk #0D0D0D near-black bg, --lg #C4A35A gold, --lc #F5F0E8 cream
 * text, --ld #141414 surface, --lb translucent gold border) via var()
 * with hard-coded fallbacks, so this stays visually consistent even if
 * loaded before/without snippet 6, and automatically follows any future
 * palette change made there.
 *
 * CSS-only, scoped to the 8 exact page IDs via body:is(...) - no content
 * changes to the legal text itself.
 */

global $wpdb;

$table = $wpdb->prefix . 'snippets';
$name  = 'LUNACI Support Pages Design';

echo "--- STEP A: PREPARE ---\n";
$existing_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$table}` WHERE name = %s", $name ) );
if ( null !== $existing_id ) {
	echo "ABORT: a snippet named '{$name}' already exists (id={$existing_id}) - refusing to create a duplicate\n";
	exit( 1 );
}
echo "OK: no existing snippet named '{$name}' - safe to create\n";

$css = <<<'CSS'
body:is(.page-id-759, .page-id-760, .page-id-3, .page-id-676, .page-id-765, .page-id-766, .page-id-769, .page-id-768) {
  background: var(--lk, #0D0D0D) !important;
  color: var(--lc, #F5F0E8) !important;
}
body:is(.page-id-759, .page-id-760, .page-id-3, .page-id-676, .page-id-765, .page-id-766, .page-id-769, .page-id-768) #content {
  max-width: 760px !important;
  margin: 0 auto !important;
  padding-left: 24px !important;
  padding-right: 24px !important;
  padding-bottom: 110px !important;
}
body:is(.page-id-759, .page-id-760, .page-id-3, .page-id-676, .page-id-765, .page-id-766, .page-id-769, .page-id-768) .page-header {
  border-bottom: 1px solid var(--lb, rgba(196,163,90,.2));
  padding-bottom: 22px;
  margin-bottom: 36px;
}
body:is(.page-id-759, .page-id-760, .page-id-3, .page-id-676, .page-id-765, .page-id-766, .page-id-769, .page-id-768) h1.entry-title {
  color: var(--lg, #C4A35A) !important;
  text-transform: uppercase;
  letter-spacing: .1em;
  font-size: 32px !important;
  font-weight: 400 !important;
}
body:is(.page-id-759, .page-id-760, .page-id-3, .page-id-676, .page-id-765, .page-id-766, .page-id-769, .page-id-768) .page-content {
  font-size: 16px;
}
body:is(.page-id-759, .page-id-760, .page-id-3, .page-id-676, .page-id-765, .page-id-766, .page-id-769, .page-id-768) .page-content p {
  color: rgba(245,240,232,.82) !important;
  line-height: 1.9;
  margin-bottom: 20px;
}
body:is(.page-id-759, .page-id-760, .page-id-3, .page-id-676, .page-id-765, .page-id-766, .page-id-769, .page-id-768) .page-content li {
  color: rgba(245,240,232,.82) !important;
  line-height: 1.8;
}
body:is(.page-id-759, .page-id-760, .page-id-3, .page-id-676, .page-id-765, .page-id-766, .page-id-769, .page-id-768) .page-content strong {
  color: var(--lc, #F5F0E8) !important;
}
body:is(.page-id-759, .page-id-760, .page-id-3, .page-id-676, .page-id-765, .page-id-766, .page-id-769, .page-id-768) .page-content a {
  color: var(--lg, #C4A35A) !important;
  text-decoration: none;
  border-bottom: 1px solid var(--lb, rgba(196,163,90,.35));
  transition: color .2s ease, border-color .2s ease;
}
body:is(.page-id-759, .page-id-760, .page-id-3, .page-id-676, .page-id-765, .page-id-766, .page-id-769, .page-id-768) .page-content a:hover {
  color: #D4B870 !important;
  border-color: #D4B870;
}
body:is(.page-id-759, .page-id-760, .page-id-3, .page-id-676, .page-id-765, .page-id-766, .page-id-769, .page-id-768) .page-content h2.wp-block-heading {
  color: var(--lg, #C4A35A) !important;
  font-size: 19px !important;
  text-transform: uppercase;
  letter-spacing: .08em;
  font-weight: 400 !important;
  margin-top: 44px !important;
  padding-top: 28px;
  border-top: 1px solid var(--lb, rgba(196,163,90,.15));
}
body:is(.page-id-759, .page-id-760, .page-id-3, .page-id-676, .page-id-765, .page-id-766, .page-id-769, .page-id-768) .page-content h2.wp-block-heading:first-of-type {
  border-top: none;
  padding-top: 0;
  margin-top: 0 !important;
}
@media (max-width: 782px) {
  body:is(.page-id-759, .page-id-760, .page-id-3, .page-id-676, .page-id-765, .page-id-766, .page-id-769, .page-id-768) #content {
    padding-left: 20px !important;
    padding-right: 20px !important;
  }
  body:is(.page-id-759, .page-id-760, .page-id-3, .page-id-676, .page-id-765, .page-id-766, .page-id-769, .page-id-768) h1.entry-title {
    font-size: 26px !important;
  }
}
CSS;

$code = "add_action( 'wp_head', function () {\n\t?>\n<style id=\"lunaci-support-pages-css\">\n" . $css . "\n</style>\n\t<?php\n} );\n";

echo "\n--- STEP B: COMMIT ---\n";
$restale_check = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$table}` WHERE name = %s", $name ) );
if ( null !== $restale_check ) {
	echo "ABORT: a snippet named '{$name}' appeared since STEP A (concurrent write) - refusing to insert\n";
	exit( 1 );
}

$inserted = $wpdb->insert(
	$table,
	array(
		'name'         => $name,
		'description'  => 'CSS-only brand styling for the 4 Support footer pages (Shipping/Returns/Privacy Policy/Terms of Service, EN+ES) - dark background, gold accents, matching site typography.',
		'code'         => $code,
		'tags'         => '',
		'scope'        => 'global',
		'condition_id' => 0,
		'priority'     => 10,
		'active'       => 1,
		'modified'     => current_time( 'mysql' ),
		'revision'     => 1,
		'cloud_id'     => '',
	),
	array( '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%d', '%s' )
);

if ( false === $inserted ) {
	echo "ABORT: \$wpdb->insert() failed: {$wpdb->last_error}\n";
	exit( 1 );
}
$new_id = $wpdb->insert_id;
echo "OK: inserted new snippet id={$new_id}\n";

echo "\n--- STEP C: VERIFY ---\n";
$verify = $wpdb->get_row( $wpdb->prepare( "SELECT id, name, active, LENGTH(code) AS code_len FROM `{$table}` WHERE id = %d", $new_id ), ARRAY_A );
if ( ! $verify ) {
	echo "FAIL: could not read back new snippet id={$new_id}\n";
	exit( 1 );
}
echo "verify: id={$verify['id']} name=\"{$verify['name']}\" active={$verify['active']} code_len={$verify['code_len']}\n";
if ( $verify['name'] !== $name || '1' !== (string) $verify['active'] ) {
	echo "FAIL: verification mismatch\n";
	exit( 1 );
}

echo "\n=====================================================================\n";
echo "FINAL RESULT: SUCCESS\n";
