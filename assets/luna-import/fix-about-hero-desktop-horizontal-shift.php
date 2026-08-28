<?php
/**
 * Guarded fix, second pass: the first pass moved the desktop left inset
 * from 5% to 20%, which the user confirmed was too far ("خیلی اومد
 * وسط" - moved too much toward center). Dial back to 11% (a moderate
 * middle ground between the original 5% and the overshot 20%), same
 * two locations as before: post 319's own post_content and the
 * mirrored wpcode_snippets option copy. Desktop only
 * (min-width:1025px) - mobile stays untouched.
 */

global $wpdb;

$wpcode_old = '@media (min-width:1025px){.lna-hero__content{padding:0 5% 0 20% !important;}}';
$wpcode_new = '@media (min-width:1025px){.lna-hero__content{padding:0 5% 0 11% !important;}}';

echo "=====================================================================\n";
echo "PART 1: WPCode post 319 post_content\n";
echo "=====================================================================\n";

$part1_success = true;
$post = get_post( 319 );

if ( ! $post ) {
	echo "ABORT: post 319 not found\n";
	$part1_success = false;
} else {
	$occurrences = substr_count( $post->post_content, $wpcode_old );
	$already_has = false !== strpos( $post->post_content, $wpcode_new );
	echo "post_content occurrences of old: {$occurrences}, already has new: " . ( $already_has ? 'yes' : 'no' ) . "\n";

	if ( $already_has ) {
		echo "OK: already updated, skipping\n";
	} elseif ( 1 !== $occurrences ) {
		echo "ABORT: expected exactly 1 occurrence, found {$occurrences}\n";
		$part1_success = false;
	} else {
		$fresh_post = get_post( 319 );
		if ( 1 !== substr_count( $fresh_post->post_content, $wpcode_old ) ) {
			echo "ABORT: race check failed\n";
			$part1_success = false;
		} else {
			$new_content = str_replace( $wpcode_old, $wpcode_new, $fresh_post->post_content );
			$updated     = $wpdb->update(
				$wpdb->posts,
				array( 'post_content' => $new_content ),
				array( 'ID' => 319 ),
				array( '%s' ),
				array( '%d' )
			);
			echo "wpdb->update() rows affected: " . var_export( $updated, true ) . "\n";
			clean_post_cache( 319 );

			$verify_post = get_post( 319 );
			$has_new     = false !== strpos( $verify_post->post_content, $wpcode_new );
			$has_old     = false !== strpos( $verify_post->post_content, $wpcode_old );
			echo "verify: new_present=" . ( $has_new ? 'yes' : 'no' ) . " old_gone=" . ( ! $has_old ? 'yes' : 'no' ) . "\n";
			if ( ! $has_new || $has_old ) {
				$part1_success = false;
			}
		}
	}
}

echo "\n=====================================================================\n";
echo "PART 2: wpcode_snippets option mirrored copy\n";
echo "=====================================================================\n";

$part2_success = true;
$location    = 'site_wide_header';
$target_id   = 319;
$snippets    = get_option( 'wpcode_snippets' );
$found_index = null;

if ( is_array( $snippets ) && isset( $snippets[ $location ] ) ) {
	foreach ( $snippets[ $location ] as $idx => $entry ) {
		if ( is_array( $entry ) && isset( $entry['id'] ) && (int) $entry['id'] === $target_id ) {
			$found_index = $idx;
			break;
		}
	}
}

if ( null === $found_index ) {
	echo "ABORT: option entry for id={$target_id} not found under {$location}\n";
	$part2_success = false;
} else {
	$code        = $snippets[ $location ][ $found_index ]['code'];
	$occurrences = substr_count( $code, $wpcode_old );
	$already_has = false !== strpos( $code, $wpcode_new );
	echo "option code occurrences of old: {$occurrences}, already has new: " . ( $already_has ? 'yes' : 'no' ) . "\n";

	if ( $already_has ) {
		echo "OK: already updated, skipping\n";
	} elseif ( 1 !== $occurrences ) {
		echo "ABORT: expected exactly 1 occurrence, found {$occurrences}\n";
		$part2_success = false;
	} else {
		$fresh_snippets = get_option( 'wpcode_snippets' );
		$fresh_code     = $fresh_snippets[ $location ][ $found_index ]['code'];
		if ( 1 !== substr_count( $fresh_code, $wpcode_old ) ) {
			echo "ABORT: race check failed\n";
			$part2_success = false;
		} else {
			$fresh_snippets[ $location ][ $found_index ]['code'] = str_replace( $wpcode_old, $wpcode_new, $fresh_code );
			$updated = update_option( 'wpcode_snippets', $fresh_snippets );
			echo "update_option() returned: " . var_export( $updated, true ) . "\n";

			$verify_snippets = get_option( 'wpcode_snippets' );
			$verify_code     = $verify_snippets[ $location ][ $found_index ]['code'];
			$has_new         = false !== strpos( $verify_code, $wpcode_new );
			$has_old         = false !== strpos( $verify_code, $wpcode_old );
			echo "verify: new_present=" . ( $has_new ? 'yes' : 'no' ) . " old_gone=" . ( ! $has_old ? 'yes' : 'no' ) . "\n";
			if ( ! $has_new || $has_old ) {
				$part2_success = false;
			}
		}
	}
}

wp_cache_flush();

echo "\n=====================================================================\n";
if ( $part1_success && $part2_success ) {
	echo "FINAL RESULT: SUCCESS\n";
} else {
	echo "FINAL RESULT: FAILURE - part1=" . ( $part1_success ? 'ok' : 'FAILED' ) . " part2=" . ( $part2_success ? 'ok' : 'FAILED' ) . "\n";
	exit( 1 );
}
