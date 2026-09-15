<?php
/**
 * SMOKING GUN CANDIDATE: Frontend::get_builder_content() fetches CORRECT
 * data via $document->get_elements_data() (confirmed has 100dvh), then
 * immediately runs it through two filters:
 *   $data = apply_filters( 'elementor/document/load/data', $data, $document );
 *   $data = apply_filters( 'elementor/frontend/builder_content_data', $data, $post_id );
 * before rendering. If a custom snippet/theme hook on this site listens to
 * either filter and returns a cached/stale version instead of the passed
 * $data, that would exactly explain: correct data in, stale content out.
 *
 * List every callback registered on both filters.
 */

global $wp_filter;

foreach ( array( 'elementor/document/load/data', 'elementor/frontend/builder_content_data' ) as $hook ) {
	echo "=====================================================================\n";
	echo "Hook: $hook\n";
	echo "=====================================================================\n";
	if ( ! isset( $wp_filter[ $hook ] ) ) {
		echo "no callbacks registered\n\n";
		continue;
	}
	foreach ( $wp_filter[ $hook ]->callbacks as $priority => $callbacks ) {
		foreach ( $callbacks as $cb ) {
			$fn = $cb['function'];
			if ( is_array( $fn ) ) {
				$label = ( is_object( $fn[0] ) ? get_class( $fn[0] ) : $fn[0] ) . '::' . $fn[1];
			} elseif ( is_string( $fn ) ) {
				$label = $fn;
			} else {
				$label = 'Closure (anonymous function)';
				if ( $fn instanceof Closure ) {
					try {
						$ref = new ReflectionFunction( $fn );
						$label .= ' defined in ' . $ref->getFileName() . ' line ' . $ref->getStartLine();
					} catch ( Exception $e ) {
						// ignore
					}
				}
			}
			echo "priority=$priority  callback=$label\n";
		}
	}
	echo "\n";
}

echo "OK: read-only diagnostic complete\n";
