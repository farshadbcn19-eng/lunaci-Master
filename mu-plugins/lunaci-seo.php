<?php
/**
 * Plugin Name: LUNACI SEO Fixes
 * Description: Technical SEO fixes on top of All in One SEO. No visible copy changes.
 * Author: LUNACI
 *
 * From the SEO audit of 2026-10-03:
 *  1. The WooCommerce cart, checkout and account pages are noindex in English,
 *     but their Spanish translations (/es/carrito/, /es/finalizar-compra/,
 *     /es/mi-cuenta/) are indexable and listed in the sitemap. This sets
 *     noindex and removes them from the sitemap in every WPML language.
 *  2. Product schema has no brand, shipping or return policy, so Google
 *     reports missing fields for merchant listings. This adds them, using
 *     the published Shipping and Returns pages as the source.
 *  3. The home page sends og:type "article"; it should be "website".
 *  4. Spanish category base /es/categoria-producto/ with a 301 from the old
 *     /es/product-category/ URLs (option lunaci_es_cat_base).
 *  5. Meta title and description for product category archives (option
 *     lunaci_cat_meta, keyed by term slug). AIOSEO Lite has no per-term SEO
 *     fields, and the term description is printed on the archive, so the
 *     meta is set here without changing visible copy.
 *  6. Category archive intro copy (option lunaci_cat_intro, keyed by term
 *     slug): a short lead under the H1 and a collection block with an H2
 *     after the product grid. Inert while the option is unset.
 *  7. 301 from /es/about-us-es/ to the Spanish About page's current URL once
 *     its slug has changed (inert while the slug is still about-us-es).
 *  8. Theme footer copyright in Spanish on Spanish pages (option
 *     lunaci_es_copyright = on). The Hello theme reads one value from the
 *     Elementor kit setting hello_footer_copyright_text for every language.
 *  9. Guard for the Spanish Elementor pages (option lunaci_es_guard = on):
 *     refuses any write of _elementor_data to /es/, /es/sobre-nosotros/,
 *     /es/contacto/ or /es/productos/ whose text is mostly English, from any
 *     source (WPML rebuilding a translation from the English original, a
 *     save hook, cron). Spanish edits, including from the Elementor editor,
 *     go through. Every refusal is logged in option lunaci_es_guard_log.
 * 10. Product attribute label "Shade" shown as "Tono" on Spanish pages
 *     (display only; the attribute key and the variations are unchanged).
 * 11. WooCommerce checkout and registration privacy notices in Spanish on
 *     Spanish pages, with the privacy link mapped to the Spanish page.
 * 12. Guard for the Spanish Gutenberg pages (option lunaci_es_post_guard =
 *     on): /es/envio/, /es/devoluciones/, /es/terminos-de-servicio/ and
 *     /es/politica-de-privacidad/. A save that would replace their Spanish
 *     content with mostly English content keeps the current content and
 *     title; logged in lunaci_es_guard_log.
 * 13. Skip-link target on WooCommerce shop, category and product pages: the
 *     theme's skip links point to #content, which those templates lack.
 * 14. Favicon and touch icons (the LUNACI "L" mark; files in the web root):
 *     the site had no icon, so /favicon.ico returned 404.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Post IDs of the WooCommerce cart, checkout and account pages, in every language.
 */
function lunaci_seo_private_page_ids() {
	static $ids = null;
	if ( null !== $ids ) {
		return $ids;
	}
	$ids = array();
	if ( ! function_exists( 'wc_get_page_id' ) ) {
		return $ids;
	}
	$languages = array_keys( (array) apply_filters( 'wpml_active_languages', array(), array( 'skip_missing' => 0 ) ) );
	foreach ( array( 'cart', 'checkout', 'myaccount' ) as $page ) {
		$id = (int) wc_get_page_id( $page );
		if ( $id <= 0 ) {
			continue;
		}
		$ids[] = $id;
		foreach ( $languages as $lang ) {
			$translated = (int) apply_filters( 'wpml_object_id', $id, 'page', false, $lang );
			if ( $translated > 0 ) {
				$ids[] = $translated;
			}
		}
	}
	$ids = array_values( array_unique( $ids ) );
	return $ids;
}

// 1a. noindex on cart, checkout and account pages (all languages).
add_filter(
	'aioseo_robots_meta',
	function ( $attributes ) {
		if ( is_singular( 'page' ) && in_array( (int) get_queried_object_id(), lunaci_seo_private_page_ids(), true ) ) {
			$attributes['noindex'] = 'noindex';
		}
		return $attributes;
	}
);

// 1b. Keep them out of the XML sitemap.
add_filter(
	'aioseo_sitemap_exclude_posts',
	function ( $ids ) {
		return array_values( array_unique( array_merge( (array) $ids, lunaci_seo_private_page_ids() ) ) );
	}
);

// 2. Brand, shipping and return policy on Product schema. The Product node
// is WooCommerce's own structured data, not part of the AIOSEO graph.
add_filter(
	'woocommerce_structured_data_product',
	function ( $markup ) {
		if ( ! is_array( $markup ) ) {
			return $markup;
		}

		// EU member states. The Returns page applies to every order.
		$eu = array( 'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 'DE', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL', 'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE' );

		// Returns page: 14 days from delivery, unused and in original packaging,
		// return shipping paid by the customer.
		$return_policy = array(
			'@type'                => 'MerchantReturnPolicy',
			'applicableCountry'    => $eu,
			'returnPolicyCategory' => 'https://schema.org/MerchantReturnFiniteReturnWindow',
			'merchantReturnDays'   => 14,
			'returnMethod'         => 'https://schema.org/ReturnByMail',
			'returnFees'           => 'https://schema.org/ReturnFeesCustomerResponsibility',
			'itemCondition'        => 'https://schema.org/NewCondition',
		);

		// Shipping page: orders within Spain arrive in 5 to 8 business days at
		// no cost. The rest of Europe is priced at checkout, so it is left out.
		$business_days    = array(
			'@type'     => 'OpeningHoursSpecification',
			'dayOfWeek' => array(
				'https://schema.org/Monday',
				'https://schema.org/Tuesday',
				'https://schema.org/Wednesday',
				'https://schema.org/Thursday',
				'https://schema.org/Friday',
			),
		);
		$shipping_details = array(
			'@type'               => 'OfferShippingDetails',
			'shippingRate'        => array(
				'@type'    => 'MonetaryAmount',
				'value'    => 0,
				'currency' => 'EUR',
			),
			'shippingDestination' => array(
				'@type'          => 'DefinedRegion',
				'addressCountry' => 'ES',
			),
			'deliveryTime'        => array(
				'@type'        => 'ShippingDeliveryTime',
				'businessDays' => $business_days,
				'handlingTime' => array(
					'@type'    => 'QuantitativeValue',
					'minValue' => 0,
					'maxValue' => 0,
					'unitCode' => 'DAY',
				),
				'transitTime'  => array(
					'@type'    => 'QuantitativeValue',
					'minValue' => 5,
					'maxValue' => 8,
					'unitCode' => 'DAY',
				),
			),
		);

		if ( empty( $markup['brand'] ) ) {
			$markup['brand'] = array(
				'@type' => 'Brand',
				'name'  => 'LUNACI Barcelona',
			);
		}
		if ( empty( $markup['offers'] ) || ! is_array( $markup['offers'] ) ) {
			return $markup;
		}
		$single = isset( $markup['offers']['@type'] );
		$offers = $single ? array( $markup['offers'] ) : $markup['offers'];
		foreach ( $offers as $k => $offer ) {
			if ( ! is_array( $offer ) ) {
				continue;
			}
			if ( empty( $offer['shippingDetails'] ) ) {
				$offers[ $k ]['shippingDetails'] = $shipping_details;
			}
			if ( empty( $offer['hasMerchantReturnPolicy'] ) ) {
				$offers[ $k ]['hasMerchantReturnPolicy'] = $return_policy;
			}
		}
		$markup['offers'] = $single ? $offers[0] : $offers;
		return $markup;
	}
);

// 3. og:type "website" on the home page (both languages).
add_filter(
	'aioseo_facebook_tags',
	function ( $meta ) {
		if ( ! is_front_page() ) {
			return $meta;
		}
		$meta['og:type'] = 'website';
		foreach ( array_keys( $meta ) as $key ) {
			if ( 0 === strpos( $key, 'article:' ) ) {
				unset( $meta[ $key ] );
			}
		}
		return $meta;
	}
);

// 4. Spanish category base /es/categoria-producto/ (option lunaci_es_cat_base
// = on). WPML/WCML hold the Spanish base translation but do not apply it (the
// string is missing from WPML's compiled translation file), so Spanish
// archives used /es/product-category/<slug>/. Spanish term links use the
// Spanish base, a rewrite rule serves it, and the old URL redirects (301).
function lunaci_seo_es_cat_base_on() {
	return 'on' === get_option( 'lunaci_es_cat_base', 'off' );
}

add_action(
	'init',
	function () {
		if ( lunaci_seo_es_cat_base_on() ) {
			add_rewrite_rule( '^categoria-producto/(.+?)/page/?([0-9]{1,})/?$', 'index.php?product_cat=$matches[1]&paged=$matches[2]', 'top' );
			add_rewrite_rule( '^categoria-producto/(.+?)/?$', 'index.php?product_cat=$matches[1]', 'top' );
		}
	},
	20
);

add_filter(
	'term_link',
	function ( $link, $term, $taxonomy ) {
		if ( 'product_cat' !== $taxonomy || ! lunaci_seo_es_cat_base_on() || false === strpos( $link, '/es/product-category/' ) ) {
			return $link;
		}
		$lang = apply_filters( 'wpml_element_language_code', null, array( 'element_id' => (int) $term->term_taxonomy_id, 'element_type' => 'product_cat' ) );
		return 'es' === $lang ? str_replace( '/es/product-category/', '/es/categoria-producto/', $link ) : $link;
	},
	99,
	3
);

add_action(
	'template_redirect',
	function () {
		if ( ! lunaci_seo_es_cat_base_on() || empty( $_SERVER['REQUEST_URI'] ) ) {
			return;
		}
		$path = (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( ! preg_match( '#^/es/product-category/([a-z0-9-]+)/?(page/(\d+)/?)?$#', $path, $m ) ) {
			return;
		}
		$term = get_term_by( 'slug', $m[1], 'product_cat' );
		$link = $term ? get_term_link( $term, 'product_cat' ) : '';
		if ( ! $link || is_wp_error( $link ) || false === strpos( $link, '/es/categoria-producto/' ) ) {
			return;
		}
		if ( ! empty( $m[3] ) ) {
			$link = trailingslashit( $link ) . 'page/' . (int) $m[3] . '/';
		}
		wp_safe_redirect( $link, 301 );
		exit;
	},
	1
);

// 5. Product category archive meta (option lunaci_cat_meta:
// slug => array( 'title' => ..., 'desc' => ... )).
function lunaci_seo_cat_meta( $field ) {
	if ( ! is_tax( 'product_cat' ) ) {
		return '';
	}
	$term = get_queried_object();
	$meta = get_option( 'lunaci_cat_meta', array() );
	if ( ! $term || empty( $term->slug ) || ! is_array( $meta ) || empty( $meta[ $term->slug ][ $field ] ) ) {
		return '';
	}
	return (string) $meta[ $term->slug ][ $field ];
}

add_filter(
	'aioseo_title',
	function ( $title ) {
		$custom = lunaci_seo_cat_meta( 'title' );
		return '' !== $custom ? $custom : $title;
	},
	20
);

add_filter(
	'aioseo_description',
	function ( $description ) {
		$custom = lunaci_seo_cat_meta( 'desc' );
		return '' !== $custom ? $custom : $description;
	},
	20
);

// Keep og:/twitter: titles and descriptions in step on category archives.
add_filter(
	'aioseo_facebook_tags',
	function ( $meta ) {
		$t = lunaci_seo_cat_meta( 'title' );
		$d = lunaci_seo_cat_meta( 'desc' );
		if ( '' !== $t ) {
			$meta['og:title'] = $t;
		}
		if ( '' !== $d ) {
			$meta['og:description'] = $d;
		}
		return $meta;
	},
	20
);

add_filter(
	'aioseo_twitter_tags',
	function ( $meta ) {
		$t = lunaci_seo_cat_meta( 'title' );
		$d = lunaci_seo_cat_meta( 'desc' );
		if ( '' !== $t ) {
			$meta['twitter:title'] = $t;
		}
		if ( '' !== $d ) {
			$meta['twitter:description'] = $d;
		}
		return $meta;
	},
	20
);

// 6. Category archive intro (option lunaci_cat_intro: slug => array(
// 'lead' => ..., 'heading' => ..., 'body' => array( paragraphs ) )).
// {{/path/|Label}} in a paragraph becomes a link to that site path.
function lunaci_seo_cat_intro() {
	if ( ! is_product_category() ) {
		return null;
	}
	$term  = get_queried_object();
	$intro = get_option( 'lunaci_cat_intro', array() );
	if ( ! $term || empty( $term->slug ) || ! is_array( $intro ) || empty( $intro[ $term->slug ]['lead'] ) ) {
		return null;
	}
	return $intro[ $term->slug ];
}

function lunaci_seo_cat_intro_text( $text ) {
	$parts = preg_split( '/(\{\{[^}|]+\|[^}]+\}\})/', (string) $text, -1, PREG_SPLIT_DELIM_CAPTURE );
	$out   = '';
	foreach ( $parts as $part ) {
		if ( preg_match( '/^\{\{([^}|]+)\|([^}]+)\}\}$/', $part, $m ) ) {
			$out .= '<a href="' . esc_url( home_url( $m[1] ) ) . '">' . esc_html( $m[2] ) . '</a>';
		} else {
			$out .= esc_html( $part );
		}
	}
	return $out;
}

add_action(
	'woocommerce_archive_description',
	function () {
		$intro = lunaci_seo_cat_intro();
		if ( ! $intro ) {
			return;
		}
		echo '<style>'
			. '.lunaci-cat-lead{max-width:620px;margin:.75rem auto 0;padding:0 1.25rem;text-align:center;font-family:"Helvetica LUNACI",Helvetica,Arial,sans-serif;font-size:1rem;line-height:1.7;letter-spacing:.02em;color:rgba(247,244,238,.74)}'
			. '.lunaci-cat-about{max-width:720px;margin:4rem auto 3.5rem;padding:2.5rem 1.25rem 0;border-top:1px solid rgba(212,175,55,.35);text-align:center}'
			. '.lunaci-cat-about h2{font-family:"Trade Gothic LT Std Extended","Helvetica LUNACI",sans-serif;font-weight:400;font-size:clamp(.95rem,2.2vw,1.25rem);letter-spacing:.3em;text-transform:uppercase;color:#F7F4EE;margin:0 0 1.5rem}'
			. '.lunaci-cat-about p{font-family:"Helvetica LUNACI",Helvetica,Arial,sans-serif;font-size:.95rem;line-height:1.85;color:rgba(247,244,238,.78);margin:0 0 1.1rem}'
			. '.lunaci-cat-about a{color:inherit;text-decoration:none;border-bottom:1px solid rgba(212,175,55,.55)}'
			. '.lunaci-cat-about a:hover,.lunaci-cat-about a:focus{color:#D4AF37}'
			. '.lunaci-cat-about p.lunaci-cat-refrain{margin-top:1.75rem;font-size:.85rem;letter-spacing:.08em;color:#D4AF37}'
			. '</style>';
		echo '<p class="lunaci-cat-lead">' . esc_html( $intro['lead'] ) . '</p>';
	},
	10
);

add_action(
	'woocommerce_after_shop_loop',
	function () {
		$intro = lunaci_seo_cat_intro();
		if ( ! $intro || empty( $intro['heading'] ) || empty( $intro['body'] ) ) {
			return;
		}
		$body = array_values( (array) $intro['body'] );
		echo '<section class="lunaci-cat-about"><h2>' . esc_html( $intro['heading'] ) . '</h2>';
		foreach ( $body as $i => $paragraph ) {
			$class = ( count( $body ) - 1 === $i ) ? ' class="lunaci-cat-refrain"' : '';
			echo '<p' . $class . '>' . lunaci_seo_cat_intro_text( $paragraph ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in lunaci_seo_cat_intro_text().
		}
		echo '</section>';
	},
	20
);

// 7. Old Spanish About URL -> current permalink of the Spanish About page
// (post 680, the WPML translation of About Us).
add_action(
	'template_redirect',
	function () {
		if ( empty( $_SERVER['REQUEST_URI'] ) ) {
			return;
		}
		$path = (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( ! preg_match( '#^/es/about-us-es/?$#', $path ) ) {
			return;
		}
		$page = get_post( 680 );
		if ( ! $page || 'publish' !== $page->post_status || 'about-us-es' === $page->post_name ) {
			return;
		}
		$link = get_permalink( $page );
		if ( $link && false === strpos( $link, '/about-us-es/' ) ) {
			wp_safe_redirect( $link, 301 );
			exit;
		}
	},
	1
);

// 8. Spanish theme footer copyright (option lunaci_es_copyright = on).
add_filter(
	'get_post_metadata',
	function ( $value, $object_id, $meta_key, $single ) {
		static $busy = false;
		if ( $busy || '_elementor_page_settings' !== $meta_key || is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return $value;
		}
		if ( (int) $object_id !== (int) get_option( 'elementor_active_kit' ) || 'on' !== get_option( 'lunaci_es_copyright', 'off' ) || 'es' !== apply_filters( 'wpml_current_language', null ) ) {
			return $value;
		}
		$busy     = true;
		$settings = get_post_meta( $object_id, '_elementor_page_settings', true );
		$busy     = false;
		if ( ! is_array( $settings ) || 'All rights reserved' !== ( $settings['hello_footer_copyright_text'] ?? '' ) ) {
			return $value;
		}
		$settings['hello_footer_copyright_text'] = 'Todos los derechos reservados';
		return array( $settings );
	},
	10,
	4
);

// 9. Spanish page guard (option lunaci_es_guard = on). On 2026-10-05 WPML
// rebuilt the Spanish front page from the English original after an
// unrelated save; this refuses such writes.
function lunaci_seo_es_guard_posts() {
	return array( 772, 680, 770, 771 );
}

function lunaci_seo_es_guard_counts( $value ) {
	$data = is_array( $value ) ? $value : json_decode( (string) $value, true );
	$text = '';
	$walk = function ( $els ) use ( &$walk, &$text ) {
		foreach ( (array) $els as $el ) {
			foreach ( (array) ( $el['settings'] ?? array() ) as $v ) {
				if ( is_string( $v ) && strlen( $v ) > 40 ) {
					$text .= ' ' . $v;
				}
			}
			if ( ! empty( $el['elements'] ) ) {
				$walk( $el['elements'] );
			}
		}
	};
	$walk( $data );
	return lunaci_seo_es_text_counts( $text );
}

// Counts of common English and Spanish words in an HTML string.
function lunaci_seo_es_text_counts( $text ) {
	$text = preg_replace( '#<(script|style)[^>]*>.*?</\1>#is', ' ', (string) $text );
	$t    = ' ' . strtolower( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $text ) ) ) . ' ';
	$en   = 0;
	$es   = 0;
	foreach ( array( 'the', 'and', 'your', 'with', 'our', 'for', 'of', 'is', 'every', 'that', 'we' ) as $w ) {
		$en += substr_count( $t, " $w " );
	}
	foreach ( array( 'el', 'la', 'de', 'tu', 'con', 'para', 'los', 'las', 'que', 'y', 'una', 'del' ) as $w ) {
		$es += substr_count( $t, " $w " );
	}
	return array( $en, $es );
}

function lunaci_seo_es_guard_blocks( $value ) {
	list( $en, $es ) = lunaci_seo_es_guard_counts( $value );
	return $en >= 15 && $en > 2 * $es;
}

function lunaci_seo_es_guard_check( $check, $object_id, $meta_key, $meta_value ) {
	if ( false === $check ) {
		return $check;
	}
	if ( '_elementor_data' !== $meta_key || ! in_array( (int) $object_id, lunaci_seo_es_guard_posts(), true ) || 'on' !== get_option( 'lunaci_es_guard', 'off' ) ) {
		return $check;
	}
	if ( ! lunaci_seo_es_guard_blocks( $meta_value ) ) {
		return $check;
	}
	list( $en, $es ) = lunaci_seo_es_guard_counts( $meta_value );
	$trace = array();
	foreach ( array_slice( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS ), 3, 12 ) as $f ) {
		$trace[] = ( isset( $f['class'] ) ? $f['class'] . '::' : '' ) . ( $f['function'] ?? '?' ) . ( isset( $f['file'] ) ? ' @' . basename( dirname( $f['file'] ) ) . '/' . basename( $f['file'] ) . ':' . ( $f['line'] ?? 0 ) : '' );
	}
	$log   = (array) get_option( 'lunaci_es_guard_log', array() );
	$log[] = array(
		'time'  => gmdate( 'c' ),
		'post'  => (int) $object_id,
		'en'    => $en,
		'es'    => $es,
		'uri'   => isset( $_SERVER['REQUEST_URI'] ) ? substr( (string) wp_unslash( $_SERVER['REQUEST_URI'] ), 0, 200 ) : 'cli', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		'trace' => $trace,
	);
	update_option( 'lunaci_es_guard_log', array_slice( $log, -20 ), false );
	return false;
}

add_filter(
	'update_post_metadata',
	function ( $check, $object_id, $meta_key, $meta_value ) {
		return lunaci_seo_es_guard_check( $check, $object_id, $meta_key, $meta_value );
	},
	PHP_INT_MAX, // last, so no other filter can turn the refusal back into a write
	4
);

add_filter(
	'add_post_metadata',
	function ( $check, $object_id, $meta_key, $meta_value ) {
		return lunaci_seo_es_guard_check( $check, $object_id, $meta_key, $meta_value );
	},
	PHP_INT_MAX, // last, so no other filter can turn the refusal back into a write
	4
);

// 10. "Shade" -> "Tono" on Spanish pages. The shade attribute is a custom
// product attribute with one name for both languages; renaming it would change
// the variation keys, so only the displayed label is translated.
add_filter(
	'woocommerce_attribute_label',
	function ( $label, $name = '' ) {
		if ( 'Shade' === $label && 'es' === apply_filters( 'wpml_current_language', null ) ) {
			return 'Tono';
		}
		return $label;
	},
	20,
	2
);

// 11. WooCommerce privacy notices (checkout and registration) in Spanish on
// Spanish pages. The stored option text is English only; this changes the
// displayed text, not the option. [privacy_policy] is still replaced by
// WooCommerce with the link to the privacy page, which is mapped to its
// Spanish translation below.
add_filter(
	'woocommerce_get_privacy_policy_text',
	function ( $text, $type = '' ) {
		if ( 'es' !== apply_filters( 'wpml_current_language', null ) ) {
			return $text;
		}
		if ( 'checkout' === $type ) {
			return 'Utilizaremos tus datos personales para procesar tu pedido, facilitar tu experiencia en esta web y para los demás fines descritos en nuestra [privacy_policy].';
		}
		if ( 'registration' === $type ) {
			return 'Utilizaremos tus datos personales para facilitar tu experiencia en esta web, gestionar el acceso a tu cuenta y para los demás fines descritos en nuestra [privacy_policy].';
		}
		return $text;
	},
	20,
	2
);

add_filter(
	'woocommerce_privacy_policy_page_id',
	function ( $page_id ) {
		if ( $page_id && 'es' === apply_filters( 'wpml_current_language', null ) ) {
			$es_id = apply_filters( 'wpml_object_id', (int) $page_id, 'page', true, 'es' );
			if ( $es_id ) {
				return (int) $es_id;
			}
		}
		return $page_id;
	},
	20
);

// 12. Spanish Gutenberg page guard (option lunaci_es_post_guard = on): the
// legal and shipping/returns pages keep their text in post_content, so item 9
// does not cover them. A save that would replace their Spanish content with
// mostly English content (a WPML rebuild from the English original) keeps the
// current content and title instead, and is logged in lunaci_es_guard_log.
function lunaci_seo_es_post_guard_posts() {
	return array( 765, 766, 768, 769 ); // envio, devoluciones, terminos-de-servicio, politica-de-privacidad
}

function lunaci_seo_es_post_guard_blocks( $content ) {
	list( $en, $es ) = lunaci_seo_es_text_counts( $content );
	return $en >= 5 && $en > 2 * $es; // the shortest page (shipping) has about 65 words
}

add_filter(
	'wp_insert_post_data',
	function ( $data, $postarr ) {
		$id = isset( $postarr['ID'] ) ? (int) $postarr['ID'] : 0;
		if ( ! $id || ! in_array( $id, lunaci_seo_es_post_guard_posts(), true ) || 'on' !== get_option( 'lunaci_es_post_guard', 'off' ) ) {
			return $data;
		}
		if ( ! lunaci_seo_es_post_guard_blocks( wp_unslash( $data['post_content'] ?? '' ) ) ) {
			return $data;
		}
		$current = get_post( $id );
		if ( ! $current || lunaci_seo_es_post_guard_blocks( $current->post_content ) ) {
			return $data; // only protect content that is Spanish today
		}
		list( $en, $es ) = lunaci_seo_es_text_counts( wp_unslash( $data['post_content'] ) );
		$data['post_content'] = wp_slash( $current->post_content );
		$data['post_title']   = wp_slash( $current->post_title );
		$log   = (array) get_option( 'lunaci_es_guard_log', array() );
		$log[] = array(
			'time' => gmdate( 'c' ),
			'post' => $id,
			'en'   => $en,
			'es'   => $es,
			'uri'  => isset( $_SERVER['REQUEST_URI'] ) ? substr( (string) wp_unslash( $_SERVER['REQUEST_URI'] ), 0, 200 ) : 'cli', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			'kind' => 'post_content',
		);
		update_option( 'lunaci_es_guard_log', array_slice( $log, -20 ), false );
		return $data;
	},
	PHP_INT_MAX, // last, so no other filter can put the English content back
	2
);

// 13. Skip-link target on WooCommerce pages. The skip links ("Skip to
// content") point to #content; the WooCommerce templates open with
// main#main, so the target is added as the first element inside it.
add_action(
	'woocommerce_before_main_content',
	function () {
		echo '<div id="content" tabindex="-1" style="outline:none"></div>';
	},
	11 // right after WooCommerce opens div#primary / main#main (priority 10)
);

// 14. Favicon and touch icons, unless a WordPress site icon is ever set.
add_action(
	'wp_head',
	function () {
		if ( has_site_icon() ) {
			return;
		}
		$root = untrailingslashit( (string) get_option( 'home' ) ); // not home_url(): WPML adds /es/ to it
		echo '<link rel="icon" href="' . esc_url( $root . '/favicon.ico' ) . '" sizes="16x16 32x32 48x48">' . "\n";
		echo '<link rel="icon" type="image/png" sizes="192x192" href="' . esc_url( $root . '/lunaci-icon-192.png' ) . '">' . "\n";
		echo '<link rel="apple-touch-icon" href="' . esc_url( $root . '/lunaci-apple-touch-icon.png' ) . '">' . "\n";
	},
	5
);
