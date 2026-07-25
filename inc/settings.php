<?php
/**
 * Settings screen: Settings → Mail Delivery.
 *
 * Credentials + addressing for the SMTP router. The SMTP fields are shared
 * across the whole portfolio (one Brevo account); From name and Reply-To are
 * per-site. A test button sends to the current admin so delivery can be
 * verified end-to-end from wp-admin.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', 'ch_maildel_settings_menu' );
add_action( 'admin_init', 'ch_maildel_register_settings' );
add_action( 'admin_post_ch_maildel_test', 'ch_maildel_handle_test' );

function ch_maildel_settings_menu() {
	add_options_page(
		'Mail Delivery',
		'Mail Delivery',
		'manage_options',
		'ch-mail-delivery',
		'ch_maildel_render_settings'
	);
}

function ch_maildel_register_settings() {
	register_setting(
		'ch_maildel_settings_group',
		'ch_maildel_settings',
		array(
			'type'              => 'array',
			'sanitize_callback' => 'ch_maildel_sanitize_settings',
		)
	);
}

/**
 * @param array $input
 * @return array
 */
function ch_maildel_sanitize_settings( $input ) {
	$input = (array) $input;
	$prev  = ch_maildel_settings();

	$port = isset( $input['smtp_port'] ) ? absint( $input['smtp_port'] ) : 587;

	return array(
		'smtp_host'  => isset( $input['smtp_host'] ) && '' !== trim( $input['smtp_host'] )
			? sanitize_text_field( trim( $input['smtp_host'] ) )
			: 'smtp-relay.brevo.com',
		'smtp_port'  => $port ? (string) $port : '587',
		'smtp_login' => isset( $input['smtp_login'] ) ? sanitize_text_field( trim( $input['smtp_login'] ) ) : '',
		// Blank key field = keep the stored key (so saving other fields never wipes it).
		'smtp_key'   => isset( $input['smtp_key'] ) && '' !== trim( $input['smtp_key'] )
			? sanitize_text_field( trim( $input['smtp_key'] ) )
			: $prev['smtp_key'],
		'from_email' => isset( $input['from_email'] ) ? sanitize_email( $input['from_email'] ) : '',
		'from_name'  => isset( $input['from_name'] ) ? sanitize_text_field( $input['from_name'] ) : '',
		'reply_to'   => isset( $input['reply_to'] ) ? sanitize_email( $input['reply_to'] ) : '',
	);
}

/**
 * Send a test email to the current admin through the configured route.
 */
function ch_maildel_handle_test() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Insufficient permissions.' );
	}
	check_admin_referer( 'ch_maildel_test' );

	$to = wp_get_current_user()->user_email;
	$ok = is_email( $to ) && wp_mail(
		$to,
		'Mail Delivery test - ' . wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
		"This is a test from the CH Mail Delivery plugin.\n\nIf you received it, SMTP delivery works on this site. Replying should go to: " . ch_maildel_reply_to()
	);

	wp_safe_redirect(
		add_query_arg(
			array( 'ch_maildel_test' => $ok ? 'ok' : 'fail' ),
			admin_url( 'options-general.php?page=ch-mail-delivery' )
		)
	);
	exit;
}

function ch_maildel_render_settings() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$s      = ch_maildel_settings();
	$active = ch_maildel_active();

	if ( ! empty( $_GET['ch_maildel_test'] ) ) {
		if ( 'ok' === sanitize_key( wp_unslash( $_GET['ch_maildel_test'] ) ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>Test email sent to <strong>' . esc_html( wp_get_current_user()->user_email ) . '</strong> — check your inbox.</p></div>';
		} else {
			echo '<div class="notice notice-error is-dismissible"><p>The test email could not be sent — check the SMTP settings below.</p></div>';
		}
	}
	?>
	<div class="wrap">
		<h1>Mail Delivery</h1>

		<?php if ( $active ) : ?>
			<div class="notice notice-success inline"><p><strong>Active:</strong> all email from this site is sent through SMTP as <code><?php echo esc_html( $s['from_email'] ); ?></code>. Replies go to <code><?php echo esc_html( ch_maildel_reply_to() ); ?></code>.</p></div>
		<?php else : ?>
			<div class="notice notice-warning inline"><p><strong>Not active:</strong> WordPress is using plain PHP mail (unreliable on this host). Enter the SMTP login, key, and a From address below to activate.</p></div>
		<?php endif; ?>

		<form method="post" action="options.php">
			<?php settings_fields( 'ch_maildel_settings_group' ); ?>

			<h2 class="title">SMTP (shared across all sites)</h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="ch_maildel_smtp_host">SMTP host</label></th>
					<td><input name="ch_maildel_settings[smtp_host]" id="ch_maildel_smtp_host" type="text" class="regular-text" value="<?php echo esc_attr( $s['smtp_host'] ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="ch_maildel_smtp_port">Port</label></th>
					<td><input name="ch_maildel_settings[smtp_port]" id="ch_maildel_smtp_port" type="number" class="small-text" value="<?php echo esc_attr( $s['smtp_port'] ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="ch_maildel_smtp_login">SMTP login</label></th>
					<td>
						<input name="ch_maildel_settings[smtp_login]" id="ch_maildel_smtp_login" type="text" class="regular-text" value="<?php echo esc_attr( $s['smtp_login'] ); ?>" placeholder="xxxxxxx@smtp-brevo.com">
						<p class="description">From Brevo → SMTP &amp; API → SMTP.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="ch_maildel_smtp_key">SMTP key</label></th>
					<td>
						<input name="ch_maildel_settings[smtp_key]" id="ch_maildel_smtp_key" type="password" class="regular-text" value="" placeholder="<?php echo esc_attr( '' !== $s['smtp_key'] ? '•••••••• (saved — leave blank to keep)' : 'xsmtpsib-…' ); ?>" autocomplete="new-password">
						<p class="description">Stored in this site's database only. Leave blank when saving to keep the current key.</p>
					</td>
				</tr>
			</table>

			<h2 class="title">Addressing (this site)</h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="ch_maildel_from_email">From address</label></th>
					<td>
						<input name="ch_maildel_settings[from_email]" id="ch_maildel_from_email" type="email" class="regular-text" value="<?php echo esc_attr( $s['from_email'] ); ?>" placeholder="noreply@connormesec.com">
						<p class="description"><strong>Must stay on the authenticated sending domain</strong> (SPF/DKIM) or mail will be dropped again. Recipients don't reply here — they reply to the Reply-To below.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="ch_maildel_from_name">From name</label></th>
					<td>
						<input name="ch_maildel_settings[from_name]" id="ch_maildel_from_name" type="text" class="regular-text" value="<?php echo esc_attr( $s['from_name'] ); ?>" placeholder="<?php echo esc_attr( wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) ); ?>">
						<p class="description">Shown as the sender. Leave blank to use the site name.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="ch_maildel_reply_to">Reply-To</label></th>
					<td>
						<input name="ch_maildel_settings[reply_to]" id="ch_maildel_reply_to" type="email" class="regular-text" value="<?php echo esc_attr( $s['reply_to'] ); ?>" placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>">
						<p class="description">Where replies go when an email doesn't set its own Reply-To. Leave blank to use the site admin email (<code><?php echo esc_html( get_option( 'admin_email' ) ); ?></code>). Emails that set their own Reply-To (e.g. tryout notifications replying to the player) are not overridden.</p>
					</td>
				</tr>
			</table>

			<?php submit_button( 'Save settings' ); ?>
		</form>

		<h2 class="title">Test</h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="ch_maildel_test">
			<?php wp_nonce_field( 'ch_maildel_test' ); ?>
			<?php submit_button( 'Send test email to me', 'secondary', 'submit', false ); ?>
			<span class="description">Delivers to <code><?php echo esc_html( wp_get_current_user()->user_email ); ?></code> through the route above.</span>
		</form>

		<h2 class="title">Updates</h2>
		<p>This plugin updates itself from GitHub releases (<code>connormesec/ch-mail-delivery</code>). Installed version: <strong><?php echo esc_html( CH_MAILDEL_VERSION ); ?></strong>.</p>
	</div>
	<?php
}
