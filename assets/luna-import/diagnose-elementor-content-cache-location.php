<?php
/**
 * document->get_elements_data() is correct (contains 100dvh) but
 * document->get_content() is stale (does not) - the cache is specifically
 * in Document::get_content()'s rendering step, not in the raw data.
 * List ALL postmeta keys for post 60 to find any cached-HTML-like field,
 * and inspect the Document class's render method chain for clues.
 */

$post_id = 60;

echo "--- ALL postmeta keys for post $post_id ---\n";
global $wpdb;
$rows = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT meta_key, LENGTH(meta_value) AS len FROM {$wpdb->postmeta} WHERE post_id = %d ORDER BY meta_key",
		$post_id
	),
	ARRAY_A
);
foreach ( $rows as $r ) {
	echo "{$r['meta_key']}  (len={$r['len']})\n";
}

echo "\n--- Elementor Document/Frontend class methods available ---\n";
if ( class_exists( '\Elementor\Plugin' ) ) {
	$document = \Elementor\Plugin::$instance->documents->get( $post_id );
	echo "Document class: " . get_class( $document ) . "\n";
	$parents = class_parents( $document );
	echo "Parent classes: " . implode( ', ', $parents ) . "\n";

	$reflection = new ReflectionClass( $document );
	if ( $reflection->hasMethod( 'get_content' ) ) {
		$method = $reflection->getMethod( 'get_content' );
		echo "get_content() declared in: " . $method->getDeclaringClass()->getName() . "\n";
		echo "get_content() file: " . $method->getFileName() . " line " . $method->getStartLine() . "\n";
	}

	// Check Frontend class too.
	$frontend_reflection = new ReflectionClass( \Elementor\Plugin::$instance->frontend );
	if ( $frontend_reflection->hasMethod( 'get_builder_content' ) ) {
		$m = $frontend_reflection->getMethod( 'get_builder_content' );
		echo "Frontend::get_builder_content() file: " . $m->getFileName() . " line " . $m->getStartLine() . "\n";
	}
}

echo "\n--- Check for any transient/option keyed by post ID 60 ---\n";
$rows2 = $wpdb->get_results(
	"SELECT option_name, LENGTH(option_value) AS len FROM {$wpdb->options}
	 WHERE option_name LIKE '%_60' OR option_name LIKE '%_60\\_%' OR option_name LIKE '%elementor%60%'
	 ORDER BY option_name",
	ARRAY_A
);
foreach ( $rows2 as $r ) {
	echo "option: {$r['option_name']}  len={$r['len']}\n";
}

echo "\nOK: read-only diagnostic complete\n";
