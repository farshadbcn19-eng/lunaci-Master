<?php
/**
 * Dump the source code of Document::get_content() and the methods it
 * calls, to understand exactly why it returns stale content despite
 * get_elements_data() (its own data source) being correct. This rules out
 * any HTTP-layer minifier (wp-cli calls this directly, bypassing all
 * front-end output buffering/optimization hooks), confirming the
 * staleness is inside Elementor's own method.
 */

$doc_file = WP_CONTENT_DIR . '/plugins/elementor/core/base/document.php';
$frontend_file = WP_CONTENT_DIR . '/plugins/elementor/includes/frontend.php';

function dump_method_source( $file, $method_name, $context_lines_after = 60 ) {
	if ( ! file_exists( $file ) ) {
		echo "file not found: $file\n";
		return;
	}
	$lines = file( $file );
	foreach ( $lines as $i => $line ) {
		if ( false !== strpos( $line, "function $method_name(" ) ) {
			echo "--- $method_name() starting at line " . ( $i + 1 ) . " in $file ---\n";
			for ( $j = $i; $j < min( count( $lines ), $i + $context_lines_after ); $j++ ) {
				echo ( $j + 1 ) . ': ' . $lines[ $j ];
			}
			echo "--- end excerpt ---\n\n";
			return;
		}
	}
	echo "method $method_name() not found in $file\n";
}

dump_method_source( $doc_file, 'get_content', 50 );
dump_method_source( $frontend_file, 'get_builder_content', 80 );
dump_method_source( $frontend_file, 'get_builder_content_for_display', 40 );

echo "OK: read-only diagnostic complete\n";
