<?php
/**
 * READ-ONLY. Before writing a redirect anywhere, need to see:
 * 1. The full source of the existing mu-plugin wp-content/mu-plugins/lunaci-products.php
 *    - it's the most likely place /products/ and /es/productos/ are already
 *    wired up as something other than a plain WordPress Page, and any new
 *    redirect logic needs to not conflict with whatever this already does.
 * 2. The current .htaccess content, to see what's already there (WP's own
 *    rewrite block, any existing redirects) before appending anything.
 */

$mu_plugin = WPMU_PLUGIN_DIR . '/lunaci-products.php';
echo "--- {$mu_plugin} ---\n";
if ( file_exists( $mu_plugin ) ) {
	echo file_get_contents( $mu_plugin );
} else {
	echo "(file does not exist)\n";
}

echo "\n\n--- " . ABSPATH . ".htaccess ---\n";
$htaccess = ABSPATH . '.htaccess';
if ( file_exists( $htaccess ) ) {
	echo file_get_contents( $htaccess );
} else {
	echo "(file does not exist)\n";
}

echo "\n\nOK: read-only diagnostic complete\n";
