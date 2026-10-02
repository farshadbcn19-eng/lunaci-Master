<?php
/**
 * Plugin Name: LUNACI Contact Form
 * Description: Handles the Contact page form (EN /contact/, ES /es/contacto/).
 *              Emails info@lunacibarcelona.com and keeps a private copy under
 *              WP Admin -> Enquiries (deleted automatically after 12 months
 *              by a daily WP-Cron job).
 * Author: LUNACI
 *
 * The form markup lives in the Contact page's Elementor HTML widget (posts 60
 * and 770). It posts to the page itself with lunaci_cf=1; this plugin catches
 * the POST on init, validates it, and redirects back with
 * ?enquiry=sent|invalid|limit|error so the page script can show a message.
 *
 * Spam protection: honeypot field, a token set only by the page script on
 * submit, and a per-IP rate limit. No nonce on purpose: the page is served from
 * LiteSpeed cache, so a nonce would go stale and reject real visitors.
 */

defined( 'ABSPATH' ) || exit;

const LUNACI_CF_TO             = 'info@lunacibarcelona.com';
const LUNACI_CF_RATE_LIMIT     = 20;   // submissions per IP per hour
const LUNACI_CF_RETENTION_DAYS = 365;

add_action( 'init', 'lunaci_cf_register_post_type' );
add_action( 'init', 'lunaci_cf_handle_post', 20 );
add_action( 'init', 'lunaci_cf_schedule_purge' );
add_action( 'lunaci_cf_daily_purge', 'lunaci_cf_purge_old' );

/** Daily retention cleanup, independent of new submissions. */
function lunaci_cf_schedule_purge() {
	if ( ! wp_next_scheduled( 'lunaci_cf_daily_purge' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'lunaci_cf_daily_purge' );
	}
}

function lunaci_cf_register_post_type() {
	register_post_type(
		'lunaci_enquiry',
		array(
			'labels'              => array(
				'name'          => 'Enquiries',
				'singular_name' => 'Enquiry',
				'menu_name'     => 'Enquiries',
			),
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_rest'        => false,
			'exclude_from_search' => true,
			'menu_icon'           => 'dashicons-email-alt',
			'supports'            => array( 'title', 'editor' ),
			'map_meta_cap'        => false,
			'capabilities'        => array(
				'edit_post'          => 'manage_options',
				'read_post'          => 'manage_options',
				'delete_post'        => 'manage_options',
				'edit_posts'         => 'manage_options',
				'edit_others_posts'  => 'manage_options',
				'delete_posts'       => 'manage_options',
				'read_private_posts' => 'manage_options',
				'publish_posts'      => 'do_not_allow',
				'create_posts'       => 'do_not_allow',
			),
		)
	);
}

function lunaci_cf_redirect( $lang, $status ) {
	$back = ( 'es' === $lang ) ? home_url( '/es/contacto/' ) : home_url( '/contact/' );
	wp_safe_redirect( add_query_arg( 'enquiry', $status, $back ) . '#contact-form', 303 );
	exit;
}

function lunaci_cf_field( $key, $max ) {
	$value = isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
	return mb_substr( $value, 0, $max );
}

function lunaci_cf_handle_post() {
	if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || empty( $_POST['lunaci_cf'] ) ) {
		return;
	}

	$lang = ( isset( $_POST['lang'] ) && 'es' === $_POST['lang'] ) ? 'es' : 'en';

	// Bots: filled honeypot, or the page script never ran. Pretend success.
	if ( ! empty( $_POST['website'] ) || 'ok' !== ( $_POST['lc_js'] ?? '' ) ) {
		lunaci_cf_redirect( $lang, 'sent' );
	}

	$subjects = array(
		'product'   => 'Product Enquiry',
		'order'     => 'Order & Shipping',
		'press'     => 'Press & Media',
		'wholesale' => 'Wholesale & Partnership',
		'other'     => 'Other',
	);

	$first   = lunaci_cf_field( 'first_name', 80 );
	$last    = lunaci_cf_field( 'last_name', 80 );
	$email   = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
	$phone   = lunaci_cf_field( 'phone', 40 );
	$topic   = isset( $subjects[ $_POST['subject'] ?? '' ] ) ? $subjects[ $_POST['subject'] ] : 'General';
	$message = mb_substr( sanitize_textarea_field( wp_unslash( $_POST['message'] ?? '' ) ), 0, 5000 );
	$consent = ! empty( $_POST['consent'] );

	if ( '' === $first || ! is_email( $email ) || '' === trim( $message ) || ! $consent ) {
		lunaci_cf_redirect( $lang, 'invalid' );
	}

	$ip_key = 'lunaci_cf_' . md5( $_SERVER['REMOTE_ADDR'] ?? 'unknown' );
	$count  = (int) get_transient( $ip_key );
	if ( $count >= LUNACI_CF_RATE_LIMIT ) {
		lunaci_cf_redirect( $lang, 'limit' );
	}
	set_transient( $ip_key, $count + 1, HOUR_IN_SECONDS );

	$name = trim( $first . ' ' . $last );
	$body = "Name: {$name}\nEmail: {$email}\nPhone: " . ( '' !== $phone ? $phone : '-' ) . "\nTopic: {$topic}\nLanguage: " . strtoupper( $lang ) . "\nPrivacy consent: yes (" . gmdate( 'Y-m-d H:i' ) . " UTC)\n\nMessage:\n{$message}\n";

	$enquiry_id = wp_insert_post(
		array(
			'post_type'    => 'lunaci_enquiry',
			'post_status'  => 'private',
			'post_title'   => "{$topic} — {$name}",
			'post_content' => $body,
		),
		true
	);

	$sent = wp_mail(
		LUNACI_CF_TO,
		"[LUNACI Contact] {$topic} — {$name}",
		$body,
		array( 'Reply-To: ' . $email )
	);

	if ( $enquiry_id && ! is_wp_error( $enquiry_id ) ) {
		update_post_meta( $enquiry_id, '_lunaci_email', $email );
		update_post_meta( $enquiry_id, '_lunaci_mail_sent', $sent ? '1' : '0' );
	}

	lunaci_cf_purge_old(); // fallback in case WP-Cron is not running

	$stored = $enquiry_id && ! is_wp_error( $enquiry_id );
	lunaci_cf_redirect( $lang, ( $sent || $stored ) ? 'sent' : 'error' );
}

/** GDPR retention: delete stored enquiries older than LUNACI_CF_RETENTION_DAYS (daily cron + on submit). */
function lunaci_cf_purge_old() {
	$old = get_posts(
		array(
			'post_type'      => 'lunaci_enquiry',
			'post_status'    => 'any',
			'posts_per_page' => 20,
			'fields'         => 'ids',
			'date_query'     => array( array( 'before' => LUNACI_CF_RETENTION_DAYS . ' days ago' ) ),
		)
	);
	foreach ( $old as $id ) {
		wp_delete_post( $id, true );
	}
}
