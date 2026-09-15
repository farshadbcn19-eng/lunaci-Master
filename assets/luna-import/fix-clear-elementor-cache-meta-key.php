<?php
/**
 * ROOT CAUSE FOUND: migrations-orchestrator.php's migrate_doc() shows
 * Elementor has a postmeta key Document::CACHE_META_KEY that holds a
 * cached rendering, only cleared via $document->delete_meta( CACHE_META_KEY )
 * inside its own migration callback - which only runs on a data-version
 * mismatch. Our raw $wpdb->update() edits never triggered a migration, so
 * this cache postmeta was never invalidated despite _elementor_data itself
 * being correct.
 *
 * Resolve the actual constant value, check/delete it for posts 60 and 770,
 * then re-verify via get_builder_content_for_display().
 */

if ( ! class_exists( '\Elementor\Core\Base\Document' ) ) {
	echo "ERROR: Document class not found\n";
	exit( 1 );
}

$reflection = new ReflectionClass( '\Elementor\Core\Base\Document' );
$constants  = $reflection->getConstants();

echo "--- Relevant Document constants ---\n";
foreach ( $constants as $name => $value ) {
	if ( false !== stripos( $name, 'cache' ) || false !== stripos( $name, 'elementor_data' ) ) {
		echo "$name = " . var_export( $value, true ) . "\n";
	}
}

$cache_meta_key = $constants['CACHE_META_KEY'] ?? null;

if ( ! $cache_meta_key ) {
	echo "\nERROR: CACHE_META_KEY constant not found - cannot proceed\n";
	exit( 1 );
}

echo "\nCACHE_META_KEY resolved to: '$cache_meta_key'\n\n";

foreach ( array( 'EN Contact' => 60, 'ES Contacto' => 770 ) as $label => $post_id ) {
	echo "==========================================================================\n";
	echo "{$label} (post {$post_id})\n";
	echo "==========================================================================\n";

	$existing = get_post_meta( $post_id, $cache_meta_key, true );
	echo "current value of '$cache_meta_key': " . ( $existing ? '(present, length=' . ( is_string( $existing ) ? strlen( $existing ) : strlen( wp_json_encode( $existing ) ) ) . ')' : '(empty/not set)' ) . "\n";

	if ( class_exists( '\Elementor\Plugin' ) ) {
		$before = \Elementor\Plugin::$instance->frontend->get_builder_content_for_display( $post_id );
		echo "get_builder_content_for_display() BEFORE - has 100dvh: " . ( false !== strpos( $before, '100dvh' ) ? 'YES' : 'NO' ) . " (length=" . strlen( $before ) . ")\n";
	}

	$deleted = delete_post_meta( $post_id, $cache_meta_key );
	echo "delete_post_meta('$cache_meta_key'): " . ( $deleted ? 'deleted' : 'nothing to delete / failed' ) . "\n";

	clean_post_cache( $post_id );

	if ( class_exists( '\Elementor\Plugin' ) ) {
		$after = \Elementor\Plugin::$instance->frontend->get_builder_content_for_display( $post_id );
		echo "get_builder_content_for_display() AFTER - has 100dvh: " . ( false !== strpos( $after, '100dvh' ) ? 'YES' : 'NO' ) . " (length=" . strlen( $after ) . ")\n";
	}
	echo "\n";
}

echo "OK: script complete\n";
