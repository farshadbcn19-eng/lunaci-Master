<?php
/**
 * Read-only. Shows the source of the callbacks registered on the WPML filter
 * wpml_get_translated_strings (and the page-builder string lookup it relies
 * on), then calls it with the package formats WPML uses, for the front page
 * package (Elementor, post 57), and prints what comes back.
 */
global $wp_filter;
foreach ( array( 'wpml_get_translated_strings', 'wpml_st_get_post_string_packages' ) as $hook ) {
	if ( empty( $wp_filter[ $hook ] ) ) {
		echo "$hook: no callbacks\n";
		continue;
	}
	foreach ( $wp_filter[ $hook ]->callbacks as $prio => $cbs ) {
		foreach ( $cbs as $cb ) {
			$f = $cb['function'];
			try {
				$r = is_array( $f ) ? new ReflectionMethod( $f[0], $f[1] ) : new ReflectionFunction( $f );
				echo "=== $hook prio $prio: " . ( is_array( $f ) ? ( is_object( $f[0] ) ? get_class( $f[0] ) : $f[0] ) . '::' . $f[1] : 'closure' ) . ' in ' . $r->getFileName() . ':' . $r->getStartLine() . "\n";
				$lines = file( $r->getFileName() );
				echo implode( '', array_slice( $lines, $r->getStartLine() - 1, min( 40, $r->getEndLine() - $r->getStartLine() + 1 ) ) );
			} catch ( Exception $e ) {
				echo "$hook: cannot reflect\n";
			}
		}
	}
}
$formats = array(
	'array kind/name'          => array( 'kind' => 'Elementor', 'name' => '57' ),
	'array kind_slug/name'     => array( 'kind_slug' => 'elementor', 'name' => '57' ),
	'array kind/name/post_id'  => array( 'kind' => 'Elementor', 'name' => 57, 'post_id' => 57, 'title' => 'Page Builder Page 57' ),
);
foreach ( $formats as $label => $pkg ) {
	$r = apply_filters( 'wpml_get_translated_strings', array(), $pkg );
	$k = is_array( $r ) ? array_keys( $r ) : array();
	echo "--- call ($label): " . count( $k ) . ' keys ' . wp_json_encode( array_slice( $k, 0, 5 ) ) . "\n";
	foreach ( (array) $r as $name => $langs ) {
		foreach ( (array) $langs as $lang => $v ) {
			echo "    $name [$lang] status=" . ( $v['status'] ?? '?' ) . ' len=' . strlen( (string) ( $v['value'] ?? '' ) ) . "\n";
		}
	}
}
