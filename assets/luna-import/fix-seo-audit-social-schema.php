<?php
/**
 * Guarded fix, five independent SEO-audit items, each only applied if the
 * live site is still in the exact "broken" state confirmed by
 * diagnose-seo-audit-followups.php (2026-09-08). Any item whose current
 * value doesn't match the expected starting state is skipped, not
 * overwritten - so this is safe to re-run.
 *
 * 1. og:site_name renders as "LUNACI Barcelona -" because AIOSEO's
 *    Facebook siteName template is "#site_title #separator_sa #tagline"
 *    with an empty tagline. Drop the tagline segment from the template.
 * 2. No default og:image/twitter:image anywhere. Set the homepage hero
 *    image (1376x768, close to OG's 1200x630 ideal) as the sitewide
 *    default for Facebook + Twitter.
 * 3. Shop archive (/shop/, /es/tienda/) has no meta description. Add one,
 *    EN + ES.
 * 4. Organization schema has no logo. Import the brand's real logo file
 *    (provided by the user, staged at /tmp/lunaci-schema-logo.png by the
 *    workflow) into the media library and use it.
 * 5. Organization schema has no sameAs social profiles. Set the brand's
 *    confirmed Facebook, Instagram, LinkedIn and Threads URLs.
 */

global $wpdb;

$hero_image      = 'https://lunacibarcelona.com/wp-content/uploads/2026/08/lunaimport-hero-luna.jpg';
$staged_logo_path = '/tmp/lunaci-schema-logo.png';

$same_as = array(
	'facebookPageUrl' => 'https://www.facebook.com/lunaci.online',
	'instagramUrl'    => 'https://www.instagram.com/lunaci.barcelona/',
	'linkedinUrl'     => 'https://www.linkedin.com/company/lunaci-barcelona/',
	'threadsUrl'       => 'https://www.threads.net/@lunaci.barcelona',
);

$changed = array();
$skipped = array();

// ---- 1 + 2 + 4: aioseo_options (site-wide JSON blob) ----
$options_raw = get_option( 'aioseo_options' );
$decoded     = $options_raw ? json_decode( $options_raw, true ) : null;

if ( ! is_array( $decoded ) ) {
	echo "ABORT: aioseo_options did not decode as JSON - no site-wide changes made.\n";
} else {
	// 1. og:site_name template
	$fb_sitename_path = &$decoded['social']['facebook']['general']['siteName'];
	if ( isset( $fb_sitename_path ) && $fb_sitename_path === '#site_title #separator_sa #tagline' ) {
		$fb_sitename_path = '#site_title';
		$changed[]        = 'social.facebook.general.siteName -> "#site_title" (was templated with an empty tagline, rendering "LUNACI Barcelona -")';
	} else {
		$skipped[] = 'social.facebook.general.siteName (current value: ' . var_export( $fb_sitename_path ?? null, true ) . ', not the expected broken template - left untouched)';
	}

	// 2. Default social image - Facebook posts + homepage, Twitter posts + homepage
	$fb_general = &$decoded['social']['facebook']['general'];
	if ( isset( $fb_general['defaultImagePosts'] ) && $fb_general['defaultImagePosts'] === '' ) {
		$fb_general['defaultImagePosts'] = $hero_image;
		$changed[] = 'social.facebook.general.defaultImagePosts -> hero image';
	} else {
		$skipped[] = 'social.facebook.general.defaultImagePosts (not empty - left untouched)';
	}

	$fb_home = &$decoded['social']['facebook']['homePage'];
	if ( isset( $fb_home['image'] ) && $fb_home['image'] === '' ) {
		$fb_home['image'] = $hero_image;
		$changed[] = 'social.facebook.homePage.image -> hero image';
	} else {
		$skipped[] = 'social.facebook.homePage.image (not empty - left untouched)';
	}

	$tw_general = &$decoded['social']['twitter']['general'];
	if ( isset( $tw_general['defaultImagePosts'] ) && $tw_general['defaultImagePosts'] === '' ) {
		$tw_general['defaultImagePosts'] = $hero_image;
		$changed[] = 'social.twitter.general.defaultImagePosts -> hero image';
	} else {
		$skipped[] = 'social.twitter.general.defaultImagePosts (not empty - left untouched)';
	}

	$tw_home = &$decoded['social']['twitter']['homePage'];
	if ( isset( $tw_home['image'] ) && $tw_home['image'] === '' ) {
		$tw_home['image'] = $hero_image;
		$changed[] = 'social.twitter.homePage.image -> hero image';
	} else {
		$skipped[] = 'social.twitter.homePage.image (not empty - left untouched)';
	}

	// 4. Organization schema logo - import the real logo file provided by the brand
	// Lives at searchAppearance.global.schema.organizationLogo, confirmed by
	// diagnose-seo-audit-followups.php (which checks that path before falling
	// back to a top-level 'schema' key - the fallback is what actually matched).
	if ( array_key_exists( 'organizationLogo', $decoded['searchAppearance']['global']['schema'] ?? array() )
		&& $decoded['searchAppearance']['global']['schema']['organizationLogo'] === '' ) {
		$logo_url = null;

		// Reuse a prior import if this script already ran once (idempotent).
		$existing = get_posts( array(
			'post_type'      => 'attachment',
			'meta_key'       => '_lunaci_schema_logo_v1',
			'meta_value'     => '1',
			'posts_per_page' => 1,
			'fields'         => 'ids',
		) );
		if ( ! empty( $existing ) ) {
			$logo_url = wp_get_attachment_url( $existing[0] );
		} elseif ( file_exists( $staged_logo_path ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';

			$filedata = file_get_contents( $staged_logo_path );
			$upload   = wp_upload_bits( 'lunaci-schema-logo.png', null, $filedata );

			if ( empty( $upload['error'] ) ) {
				$attachment_id = wp_insert_attachment( array(
					'post_mime_type' => 'image/png',
					'post_title'     => 'LUNACI Barcelona Logo',
					'post_status'    => 'inherit',
				), $upload['file'] );

				if ( $attachment_id && ! is_wp_error( $attachment_id ) ) {
					wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $upload['file'] ) );
					update_post_meta( $attachment_id, '_lunaci_schema_logo_v1', '1' );
					$logo_url = $upload['url'];
				}
			}
		}

		if ( $logo_url ) {
			$decoded['searchAppearance']['global']['schema']['organizationLogo'] = $logo_url;
			$changed[] = "searchAppearance.global.schema.organizationLogo -> {$logo_url} (brand-provided logo, imported into media library)";
		} else {
			$skipped[] = 'searchAppearance.global.schema.organizationLogo (staged logo file not found / upload failed - left untouched)';
		}
	} else {
		$skipped[] = 'searchAppearance.global.schema.organizationLogo (not empty, or path missing - left untouched)';
	}

	// 5. Organization schema sameAs (social profiles)
	// Use array_key_exists (not ??/isset) - the live value is explicit JSON
	// null for every one of these fields, and isset()/?? both treat an
	// existing null the same as a missing key, which would wrongly skip it.
	foreach ( $same_as as $field => $url ) {
		$urls_path_exists = array_key_exists( 'urls', $decoded['social']['profiles'] ?? array() );
		if ( ! $urls_path_exists || ! array_key_exists( $field, $decoded['social']['profiles']['urls'] ) ) {
			$skipped[] = "social.profiles.urls.{$field} (field not found in current aioseo_options shape - left untouched)";
			continue;
		}
		$current = $decoded['social']['profiles']['urls'][ $field ];
		if ( $current === null || $current === '' ) {
			$decoded['social']['profiles']['urls'][ $field ] = $url;
			$changed[] = "social.profiles.urls.{$field} -> {$url}";
		} else {
			$skipped[] = "social.profiles.urls.{$field} (already set to '{$current}' - left untouched)";
		}
	}

	if ( count( $changed ) > 0 ) {
		$new_raw = wp_json_encode( $decoded );
		$updated = update_option( 'aioseo_options', $new_raw );
		echo $updated ? "aioseo_options: update_option succeeded.\n" : "aioseo_options: update_option returned false (value may be unchanged/identical).\n";
	} else {
		echo "aioseo_options: nothing to change (all four items already set).\n";
	}
}

// ---- 3: shop archive meta description, EN (post 56) + ES (post 609) ----
$aioseo_table = $wpdb->prefix . 'aioseo_posts';

$descriptions = array(
	56  => "Shop LUNACI Barcelona's full collection — Mediterranean luxury makeup crafted for lasting presence. Lips, eyes, face and nails, made in Barcelona.",
	609 => 'Descubre la colección completa de LUNACI Barcelona — cosmética de lujo mediterránea para una presencia que perdura. Labios, ojos, rostro y uñas.',
);

foreach ( $descriptions as $post_id => $desc ) {
	// get_var() returns null both for "no matching row" and for "row exists
	// but the column is SQL NULL" - check row existence separately so a
	// NULL/empty description isn't mistaken for a missing row.
	$row_exists = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$aioseo_table}` WHERE post_id = %d", $post_id ) );
	if ( ! $row_exists ) {
		$skipped[] = "shop meta description for post_id={$post_id} (no aioseo_posts row found)";
		continue;
	}
	$current = $wpdb->get_var( $wpdb->prepare( "SELECT description FROM `{$aioseo_table}` WHERE post_id = %d", $post_id ) );
	if ( trim( (string) $current ) === '' ) {
		$result = $wpdb->update( $aioseo_table, array( 'description' => $desc ), array( 'post_id' => $post_id ) );
		if ( $result !== false ) {
			$changed[] = "aioseo_posts.description for post_id={$post_id} -> set ({$result} row updated)";
		} else {
			$skipped[] = "aioseo_posts.description for post_id={$post_id} (update failed: {$wpdb->last_error})";
		}
	} else {
		$skipped[] = "aioseo_posts.description for post_id={$post_id} (already set to: '" . substr( $current, 0, 80 ) . "...' - left untouched)";
	}
}

echo "\n--- CHANGED (" . count( $changed ) . ") ---\n";
foreach ( $changed as $c ) {
	echo "  + {$c}\n";
}
echo "\n--- SKIPPED / already in desired state (" . count( $skipped ) . ") ---\n";
foreach ( $skipped as $s ) {
	echo "  - {$s}\n";
}

echo "\nOK: guarded fix complete\n";
