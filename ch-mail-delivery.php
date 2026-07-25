<?php
/**
 * Plugin Name:       CH Mail Delivery
 * Description:       Routes all WordPress email through authenticated SMTP (Brevo) with configurable From and Reply-To. Companion utility for the club hockey site portfolio — fixes silent mail drops on shared hosting.
 * Version:           1.0.0
 * Author:            Connor Mesec
 * License:           GPL-2.0-or-later
 * Text Domain:       ch-mail-delivery
 * Update URI:        https://github.com/connormesec/ch-mail-delivery
 *
 * Why this exists: Hostinger silently discards unauthenticated PHP mail()
 * (shared 100/day account cap), so every site sends through Brevo SMTP from a
 * single authenticated domain instead. The From address must stay on that
 * domain to pass SPF/DKIM; Reply-To is what recipients actually answer to and
 * is configurable per site below (Settings → Mail Delivery).
 *
 * SMTP credentials live in each site's options table (set on the settings
 * screen or via WP-CLI) — never in this repo.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CH_MAILDEL_VERSION', '1.0.0' );
define( 'CH_MAILDEL_FILE', __FILE__ );
define( 'CH_MAILDEL_PATH', plugin_dir_path( __FILE__ ) );

/**
 * Settings accessor + defaults.
 *
 * @return array
 */
function ch_maildel_settings() {
	return wp_parse_args(
		(array) get_option( 'ch_maildel_settings', array() ),
		array(
			'smtp_host'  => 'smtp-relay.brevo.com',
			'smtp_port'  => '587',
			'smtp_login' => '',
			'smtp_key'   => '',
			'from_email' => '',
			'from_name'  => '', // blank = this site's name
			'reply_to'   => '', // blank = the site admin email
		)
	);
}

/**
 * Whether SMTP routing should engage: credentials + From present, and not a
 * Local dev site (Local's mail catcher should keep handling .local mail).
 *
 * @return bool
 */
function ch_maildel_active() {
	$s = ch_maildel_settings();
	if ( '' === $s['smtp_login'] || '' === $s['smtp_key'] || ! is_email( $s['from_email'] ) ) {
		return false;
	}
	$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
	return ! str_ends_with( $host, '.local' );
}

/** The effective Reply-To address: the setting, or the site admin email. */
function ch_maildel_reply_to() {
	$s = ch_maildel_settings();
	if ( is_email( $s['reply_to'] ) ) {
		return $s['reply_to'];
	}
	$admin = get_option( 'admin_email' );
	return is_email( $admin ) ? $admin : '';
}

/** The effective From display name: the setting, or this site's name. */
function ch_maildel_from_name() {
	$s = ch_maildel_settings();
	if ( '' !== trim( $s['from_name'] ) ) {
		return $s['from_name'];
	}
	return wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
}

/*
 * Route the mailer through SMTP, and give every email a Reply-To when the
 * sender didn't set one — so replies reach a real inbox, never the noreply
 * From address. Runs late (priority 20) so it wins over stray mailer tweaks
 * but still respects Reply-To addresses set by callers (e.g. form plugins).
 */
add_action( 'phpmailer_init', function ( $phpmailer ) {
	if ( ! ch_maildel_active() ) {
		return;
	}
	$s = ch_maildel_settings();

	$phpmailer->isSMTP();
	$phpmailer->Host       = $s['smtp_host'];
	$phpmailer->Port       = (int) $s['smtp_port'];
	$phpmailer->SMTPSecure = 'tls';
	$phpmailer->SMTPAuth   = true;
	$phpmailer->Username   = $s['smtp_login'];
	$phpmailer->Password   = $s['smtp_key'];

	if ( empty( $phpmailer->getReplyToAddresses() ) ) {
		$reply_to = ch_maildel_reply_to();
		if ( $reply_to ) {
			$phpmailer->addReplyTo( $reply_to, ch_maildel_from_name() );
		}
	}
}, 20 );

/*
 * From address/name defaults at priority 9, so plugins that set their own
 * (default priority 10+) still win — e.g. the tryout plugin's From name.
 */
add_filter( 'wp_mail_from', function ( $from ) {
	if ( ! ch_maildel_active() ) {
		return $from;
	}
	$s = ch_maildel_settings();
	return $s['from_email'];
}, 9 );

add_filter( 'wp_mail_from_name', function ( $name ) {
	if ( ! ch_maildel_active() ) {
		return $name;
	}
	if ( '' === $name || 'WordPress' === $name ) {
		return ch_maildel_from_name();
	}
	return $name;
}, 9 );

require_once CH_MAILDEL_PATH . 'inc/settings.php';
require_once CH_MAILDEL_PATH . 'inc/update.php';
