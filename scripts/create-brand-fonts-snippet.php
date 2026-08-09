<?php
global $wpdb;

$snippet_name = 'LUNACI Brand Fonts (@font-face)';
$site_url     = get_site_url();

$snippet_code = <<<LUNACI_FONT_CSS
@font-face {
	font-family: 'Trade Gothic LT Std Extended';
	src: url('{$site_url}/wp-content/themes/hello-elementor-child/fonts/TradeGothicLTStd-Extended.woff2') format('woff2');
	font-weight: normal;
	font-style: normal;
	font-display: swap;
}
@font-face {
	font-family: 'Helvetica LUNACI';
	src: url('{$site_url}/wp-content/themes/hello-elementor-child/fonts/Helvetica.woff2') format('woff2');
	font-weight: normal;
	font-style: normal;
	font-display: swap;
}
body {
	font-family: 'Helvetica LUNACI', Helvetica, Arial, sans-serif;
}
h1, h2, h3, h4, h5, h6 {
	font-family: 'Trade Gothic LT Std Extended', 'Helvetica LUNACI', sans-serif;
}
LUNACI_FONT_CSS;

$snippets_table = $wpdb->prefix . 'snippets';

echo "====================================================================\n";
echo "STEP A: PREPARE - check for existing snippet with same name\n";
echo "====================================================================\n";

$existing = $wpdb->get_row(
	$wpdb->prepare( "SELECT id, name, active FROM $snippets_table WHERE name = %s", $snippet_name ),
	ARRAY_A
);

if ( $existing ) {
	echo "SKIP: a snippet named \"$snippet_name\" already exists (id={$existing['id']}, active={$existing['active']}) - not inserting a duplicate\n";
	echo "OK: nothing to do\n";
	exit( 0 );
}
echo "OK: no existing snippet with this name found - proceeding to insert\n";

echo "\n====================================================================\n";
echo "STEP B: COMMIT - insert new snippet row (active=0, scope=site-css)\n";
echo "====================================================================\n";

$insert_ok = $wpdb->insert(
	$snippets_table,
	array(
		'name'        => $snippet_name,
		'description' => 'Registers @font-face for Helvetica and Trade Gothic LT Std Extended (WOFF2), plus safe global body/heading font-family fallbacks. Created inactive for review before activation.',
		'code'        => $snippet_code,
		'tags'        => '',
		'scope'       => 'site-css',
		'active'      => 0,
		'priority'    => 10,
	),
	array( '%s', '%s', '%s', '%s', '%s', '%d', '%d' )
);

if ( false === $insert_ok ) {
	echo "ERROR: \$wpdb->insert() failed: {$wpdb->last_error}\n";
	echo "ABORT\n";
	exit( 1 );
}

$new_id = $wpdb->insert_id;
echo "OK: inserted snippet row, insert_id=$new_id\n";

echo "\n====================================================================\n";
echo "STEP C: VERIFY - re-read the row and compare code byte-for-byte\n";
echo "====================================================================\n";

$row = $wpdb->get_row(
	$wpdb->prepare( "SELECT id, name, active, scope, code FROM $snippets_table WHERE id = %d", $new_id ),
	ARRAY_A
);

$any_error = false;

if ( ! $row ) {
	echo "ERROR: could not re-read inserted row id=$new_id\n";
	$any_error = true;
} else {
	echo "re-read id={$row['id']}  name=\"{$row['name']}\"  active={$row['active']}  scope={$row['scope']}\n";

	$stored_code_matches = ( $row['code'] === $snippet_code );
	echo "stored code length=" . strlen( $row['code'] ) . " (expected " . strlen( $snippet_code ) . ")\n";
	echo "stored code matches intended code byte-for-byte: " . ( $stored_code_matches ? 'YES' : 'NO' ) . "\n";

	if ( ! $stored_code_matches || (int) $row['active'] !== 0 || $row['scope'] !== 'site-css' ) {
		echo "ERROR: verification FAILED - stored row does not match intended insert\n";
		$any_error = true;
	} else {
		echo "OK: verification passed\n";
	}

	echo "\n--- FULL STORED CODE (verbatim) ---\n";
	echo $row['code'] . "\n";
	echo "--- END STORED CODE ---\n";
}

echo "\n====================================================================\n";
if ( $any_error ) {
	echo "ABORT: verification error - see ERROR notice(s) above. Snippet id=$new_id was inserted but did NOT verify cleanly - recommend manual DB inspection before proceeding.\n";
	exit( 1 );
}
echo "OK: snippet created successfully with id=$new_id, active=0. Report this ID and the printed code back to the user for review.\n";
