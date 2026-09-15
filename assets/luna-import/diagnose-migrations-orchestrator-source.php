<?php
/**
 * The 'elementor/document/load/data' filter (which runs right after
 * fetching the CORRECT data with 100dvh, before rendering) is hooked by a
 * closure inside Elementor's own atomic-widgets prop-type-migrations
 * orchestrator. This is almost certainly tied to the
 * 'elementor_atomic_cache_validity__*' options found earlier. Dump the
 * full source of this file to find its exact caching mechanism.
 */

$file = WP_CONTENT_DIR . '/plugins/elementor/modules/atomic-widgets/prop-type-migrations/migrations-orchestrator.php';

if ( ! file_exists( $file ) ) {
	echo "file not found: $file\n";
} else {
	echo "--- FULL SOURCE of migrations-orchestrator.php ---\n";
	echo file_get_contents( $file );
	echo "\n--- END SOURCE ---\n";
}

echo "\nOK: read-only diagnostic complete\n";
