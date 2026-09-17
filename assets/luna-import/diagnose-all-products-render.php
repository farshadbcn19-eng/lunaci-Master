<?php
/**
 * READ-ONLY. The new /all-products/ page (ID 836) returns 200 but its
 * .page-content div renders completely empty - the [products] shortcode
 * never shows. Compares its postmeta against post 56 (which DOES render
 * correctly) to find what's missing/different.
 */

$new_id = 836;

foreach ( array( 56, $new_id ) as $id ) {
	echo "===== post {$id} =====\n";
	$post = get_post( $id );
	if ( ! $post ) {
		echo "not found\n\n";
		continue;
	}
	echo "post_type={$post->post_type} post_status={$post->post_status}\n";
	echo 'post_content length: ' . strlen( $post->post_content ) . "\n";
	echo '_wp_page_template: ' . var_export( get_post_meta( $id, '_wp_page_template', true ), true ) . "\n";
	echo '_elementor_edit_mode: ' . var_export( get_post_meta( $id, '_elementor_edit_mode', true ), true ) . "\n";
	echo '_elementor_template_type: ' . var_export( get_post_meta( $id, '_elementor_template_type', true ), true ) . "\n";
	echo '_elementor_version: ' . var_export( get_post_meta( $id, '_elementor_version', true ), true ) . "\n";
	echo '_elementor_page_settings: ' . var_export( get_post_meta( $id, '_elementor_page_settings', true ), true ) . "\n";
	$edata = get_post_meta( $id, '_elementor_data', true );
	echo '_elementor_data length: ' . strlen( (string) $edata ) . "\n";
	$decoded = json_decode( $edata, true );
	echo 'json_decode error: ' . json_last_error_msg() . "\n";
	echo 'decoded element count: ' . ( is_array( $decoded ) ? count( $decoded ) : 'N/A' ) . "\n";

	echo "\n--- rendering via Elementor's own frontend (if available) ---\n";
	if ( did_action( 'elementor/loaded' ) && class_exists( '\Elementor\Plugin' ) ) {
		echo "Elementor plugin loaded: yes\n";
		try {
			$rendered = \Elementor\Plugin::$instance->frontend->get_builder_content_for_display( $id );
			echo 'rendered length: ' . strlen( (string) $rendered ) . "\n";
			echo 'rendered preview: ' . substr( (string) $rendered, 0, 500 ) . "\n";
		} catch ( \Throwable $e ) {
			echo 'EXCEPTION: ' . $e->getMessage() . "\n";
		}
	} else {
		echo "Elementor plugin loaded: no (or class not found)\n";
	}
	echo "\n";
}

echo "OK: read-only diagnostic complete\n";
