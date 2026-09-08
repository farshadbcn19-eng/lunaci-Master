<?php
/**
 * Read-only-ish: isolate why update_post_meta() on _elementor_data for
 * post 57 isn't persisting (fix-homepage-h1-and-links.php reported
 * "did not persist" for 4 of 5 guarded edits, with no PHP error and no
 * data corruption - the live content is confirmed unchanged). Writes a
 * trivial reversible marker, checks whether it lands, then restores the
 * original value immediately either way.
 */

$post_id = 57;
$meta_key = '_elementor_data';

$original = get_post_meta( $post_id, $meta_key, true );
echo 'original length: ' . strlen( (string) $original ) . "\n";

// registered meta / sanitize callback info
global $wp_meta_keys;
$registered = get_registered_meta_keys( 'post', 'page' );
echo "registered as protected meta with a sanitize_callback: " . ( isset( $registered[ $meta_key ] ) ? 'yes: ' . json_encode( $registered[ $meta_key ] ) : 'no (not registered via register_post_meta)' ) . "\n";

// current user / capability context this script runs as
echo 'current_user_can(edit_post, 57): ' . ( current_user_can( 'edit_post', $post_id ) ? 'yes' : 'no (running as: ' . wp_get_current_user()->user_login . ')' ) . "\n";

// try a trivial, obviously-safe append that we immediately revert
$marker = '<!--lunaci-write-test-->';
$test_value = $original . $marker;

$result1 = update_post_meta( $post_id, $meta_key, $test_value );
$readback1 = get_post_meta( $post_id, $meta_key, true );
echo "\nplain string append test:\n";
echo '  update_post_meta() returned: ' . var_export( $result1, true ) . "\n";
echo '  readback contains marker: ' . ( strpos( (string) $readback1, $marker ) !== false ? 'YES - persisted' : 'NO - did not persist' ) . "\n";
echo '  readback length: ' . strlen( (string) $readback1 ) . "\n";

// restore original immediately regardless of outcome
$restore = update_post_meta( $post_id, $meta_key, $original );
$readback2 = get_post_meta( $post_id, $meta_key, true );
echo "\nrestore original:\n";
echo '  update_post_meta() returned: ' . var_export( $restore, true ) . "\n";
echo '  readback matches original: ' . ( $readback2 === $original ? 'YES' : 'NO - MISMATCH, needs attention' ) . "\n";
echo '  readback length: ' . strlen( (string) $readback2 ) . "\n";

// check for a persistent object cache that could serve stale reads
echo "\nwp_using_ext_object_cache: " . ( wp_using_ext_object_cache() ? 'yes' : 'no' ) . "\n";

echo "\nOK: diagnostic complete (original value restored if it was touched)\n";
