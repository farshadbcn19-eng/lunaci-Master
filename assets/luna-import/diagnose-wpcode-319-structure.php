<?php
/**
 * Read-only: WPCode post 319 (the snippet containing '.lna-hero__content')
 * was found via wp_posts.post_content search, but the wpcode_snippets
 * option-cache search (same pattern as the earlier Blusher CSS fix, PR
 * #237) found NO match for it - meaning either the option stores this
 * snippet's code under a different key/shape than expected, or this
 * snippet's live CSS output doesn't come from the option cache at all.
 * Dump everything about post 319 and its option-cache counterpart so the
 * right place to patch can be identified precisely.
 */

echo "=== post 319 core fields ===\n";
$post = get_post( 319 );
if ( $post ) {
	echo "post_title={$post->post_title} post_status={$post->post_status} post_type={$post->post_type}\n";
	echo "post_content (full):\n---BEGIN---\n" . $post->post_content . "\n---END---\n";
} else {
	echo "post 319 not found\n";
}

echo "\n=== post 319 postmeta (all keys) ===\n";
$meta = get_post_meta( 319 );
foreach ( $meta as $key => $values ) {
	foreach ( $values as $v ) {
		$display = is_string( $v ) && strlen( $v ) > 300 ? substr( $v, 0, 300 ) . '...[truncated, full length ' . strlen( $v ) . ']' : $v;
		echo "  {$key} = {$display}\n";
	}
}

echo "\n=== wpcode_snippets option: structure + entry matching id/post 319 ===\n";
$option_raw = get_option( 'wpcode_snippets' );
$type_raw   = gettype( $option_raw );
echo "raw option gettype: {$type_raw}\n";
$snippets = is_string( $option_raw ) ? maybe_unserialize( $option_raw ) : $option_raw;
echo "unserialized gettype: " . gettype( $snippets ) . "\n";
if ( is_array( $snippets ) ) {
	echo "count: " . count( $snippets ) . "\n";
	$i = 0;
	foreach ( $snippets as $key => $snip ) {
		$snip_type = gettype( $snip );
		$id_val    = null;
		if ( is_array( $snip ) && isset( $snip['id'] ) ) {
			$id_val = $snip['id'];
		} elseif ( is_object( $snip ) && isset( $snip->id ) ) {
			$id_val = $snip->id;
		}
		if ( $i < 3 ) {
			echo "  sample entry key={$key} type={$snip_type} id=" . var_export( $id_val, true ) . "\n";
			if ( is_array( $snip ) ) {
				echo "    array keys: " . implode( ', ', array_keys( $snip ) ) . "\n";
			} elseif ( is_object( $snip ) ) {
				echo "    object props: " . implode( ', ', array_keys( get_object_vars( $snip ) ) ) . "\n";
			}
			$i++;
		}
		if ( '319' == $id_val || 319 === $id_val ) {
			echo "  *** MATCH id=319 at key={$key} ***\n";
			echo "  full entry: " . print_r( $snip, true ) . "\n";
		}
	}
} else {
	echo "not an array/could not unserialize\n";
}

echo "\nOK: read-only diagnostic complete, no writes performed\n";
