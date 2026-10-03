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
