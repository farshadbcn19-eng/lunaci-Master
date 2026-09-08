<?php
/**
 * Read-only: gather everything needed to implement the SEO audit's
 * fix list (og:image default, og:site_name, Organization schema
 * logo/sameAs, shop/products archive meta description, and the
 * WooCommerce "Shop page" setting driving the /shop/ vs /products/
 * duplicate). No writes.
 */

global $wpdb;

echo "--- WP site identity (source of og:site_name) ---\n";
echo 'blogname: ' . get_option( 'blogname' ) . "\n";
echo 'blogdescription: ' . get_option( 'blogdescription' ) . "\n";

echo "\n--- WooCommerce shop page setting ---\n";
$shop_page_id = get_option( 'woocommerce_shop_page_id' );
echo 'woocommerce_shop_page_id: ' . var_export( $shop_page_id, true ) . "\n";
if ( $shop_page_id ) {
	$shop_post = get_post( $shop_page_id );
	echo 'title: ' . ( $shop_post ? $shop_post->post_title : '(missing)' ) . "\n";
	echo 'slug: ' . ( $shop_post ? $shop_post->post_name : '(missing)' ) . "\n";
	echo 'status: ' . ( $shop_post ? $shop_post->post_status : '(missing)' ) . "\n";
	echo 'permalink: ' . get_permalink( $shop_page_id ) . "\n";
}

echo "\n--- resolve key page/post IDs by path ---\n";
$paths = array( 'shop', 'products', 'es/tienda', 'es/productos', '' );
foreach ( $paths as $path ) {
	$id = url_to_postid( home_url( '/' . $path . '/' ) );
	echo "path='{$path}' -> post_id={$id}";
	if ( $id ) {
		$p = get_post( $id );
		echo ' type=' . $p->post_type . ' status=' . $p->post_status . ' title=' . $p->post_title;
	}
	echo "\n";
}

echo "\n--- WooCommerce pages referencing shop page ID elsewhere ---\n";
echo 'woocommerce_cart_page_id: ' . get_option( 'woocommerce_cart_page_id' ) . "\n";
echo 'woocommerce_checkout_page_id: ' . get_option( 'woocommerce_checkout_page_id' ) . "\n";
echo 'woocommerce_myaccount_page_id: ' . get_option( 'woocommerce_myaccount_page_id' ) . "\n";
echo 'permalink structure - woocommerce_permalinks: ' . print_r( get_option( 'woocommerce_permalinks' ), true ) . "\n";

echo "\n--- wp_aioseo_posts columns ---\n";
$aioseo_table = $wpdb->prefix . 'aioseo_posts';
$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $aioseo_table ) ) );
if ( $exists ) {
	$cols = $wpdb->get_col( "DESCRIBE `{$aioseo_table}`", 0 );
	echo 'columns: ' . implode( ', ', $cols ) . "\n";

	if ( $shop_page_id ) {
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$aioseo_table}` WHERE post_id = %d", $shop_page_id ), ARRAY_A );
		echo "\nrow for shop page (post_id={$shop_page_id}):\n";
		if ( $row ) {
			foreach ( $row as $k => $v ) {
				echo "  {$k}: " . ( is_string( $v ) ? substr( $v, 0, 300 ) : $v ) . "\n";
			}
		} else {
			echo "  (no row yet)\n";
		}
	}
} else {
	echo "table {$aioseo_table} does not exist\n";
}

echo "\n--- aioseo_options: social (og:image default, sameAs profiles) ---\n";
$options_raw = get_option( 'aioseo_options' );
if ( $options_raw ) {
	$decoded = json_decode( $options_raw, true );
	if ( is_array( $decoded ) ) {
		if ( isset( $decoded['social'] ) ) {
			echo json_encode( $decoded['social'], JSON_PRETTY_PRINT ) . "\n";
		} else {
			echo "no 'social' key found; top-level keys: " . implode( ', ', array_keys( $decoded ) ) . "\n";
		}

		echo "\n--- aioseo_options: schema / Knowledge Graph (Organization logo, type) ---\n";
		if ( isset( $decoded['searchAppearance']['global']['schema'] ) ) {
			echo json_encode( $decoded['searchAppearance']['global']['schema'], JSON_PRETTY_PRINT ) . "\n";
		} elseif ( isset( $decoded['schema'] ) ) {
			echo json_encode( $decoded['schema'], JSON_PRETTY_PRINT ) . "\n";
		} else {
			echo "no schema key found under searchAppearance.global or top-level\n";
			if ( isset( $decoded['searchAppearance']['global'] ) ) {
				echo "searchAppearance.global keys: " . implode( ', ', array_keys( $decoded['searchAppearance']['global'] ) ) . "\n";
			}
		}
	} else {
		echo "aioseo_options did not decode as JSON\n";
	}
} else {
	echo "aioseo_options option not found/empty\n";
}

echo "\nOK: read-only diagnostic complete\n";
