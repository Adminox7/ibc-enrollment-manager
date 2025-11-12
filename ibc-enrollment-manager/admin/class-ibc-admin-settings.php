<?php
/**
 * Settings page.
 *
 * @package IBC\EnrollmentManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class IBC_Admin_Settings
 */
class IBC_Admin_Settings {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Register settings.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		register_setting(
			'ibc_enrollment_settings_group',
			'ibc_enrollment_settings',
			array( $this, 'sanitize_settings' )
		);
	}

	/**
	 * Sanitize settings.
	 *
	 * @param array $input Input.
	 *
	 * @return array
	 */
	public function sanitize_settings( array $input ): array {
		$output = ibc_get_settings();

		$map = array(
			'smtp_host'            => 'sanitize_text_field',
			'smtp_port'            => 'absint',
			'smtp_username'        => 'sanitize_text_field',
			'smtp_password'        => 'sanitize_text_field',
			'smtp_secure'          => 'sanitize_text_field',
			'email_from_name'      => 'sanitize_text_field',
			'email_from_address'   => 'sanitize_email',
			'recaptcha_site_key'   => 'sanitize_text_field',
			'recaptcha_secret_key' => 'sanitize_text_field',
			'whatsapp_business_id' => 'sanitize_text_field',
			'whatsapp_token'       => 'sanitize_text_field',
			'whatsapp_template'    => 'sanitize_text_field',
			'stripe_public_key'    => 'sanitize_text_field',
			'stripe_secret_key'    => 'sanitize_text_field',
			'cmi_merchant_id'      => 'sanitize_text_field',
			'cmi_secret'           => 'sanitize_text_field',
		);

		foreach ( $map as $key => $callback ) {
			if ( isset( $input[ $key ] ) ) {
				if ( 'sanitize_email' === $callback ) {
					$output[ $key ] = sanitize_email( $input[ $key ] );
				} elseif ( 'absint' === $callback ) {
					$output[ $key ] = absint( $input[ $key ] );
				} else {
					$output[ $key ] = call_user_func( $callback, $input[ $key ] );
				}
			}
		}

		$output['delete_on_uninstall'] = isset( $input['delete_on_uninstall'] ) && 'yes' === $input['delete_on_uninstall'] ? 'yes' : 'no';

		return $output;
	}

	/**
	 * Render page.
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! ibc_current_user_can( IBC_Capabilities::CAPABILITY ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'ibc-enrollment' ) );
		}

		$settings = ibc_get_settings();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Paramètres IBC Enrollment', 'ibc-enrollment' ); ?></h1>

			<form method="post" action="options.php" class="ibc-form">
				<?php settings_fields( 'ibc_enrollment_settings_group' ); ?>

				<h2><?php esc_html_e( 'Email & SMTP', 'ibc-enrollment' ); ?></h2>
				<table class="form-table">
					<tr>
						<th scope="row"><label for="smtp_host"><?php esc_html_e( 'Serveur SMTP', 'ibc-enrollment' ); ?></label></th>
						<td><input type="text" id="smtp_host" name="ibc_enrollment_settings[smtp_host]" value="<?php echo esc_attr( $settings['smtp_host'] ); ?>" class="regular-text"/></td>
					</tr>
					<tr>
						<th scope="row"><label for="smtp_port"><?php esc_html_e( 'Port', 'ibc-enrollment' ); ?></label></th>
						<td><input type="number" id="smtp_port" name="ibc_enrollment_settings[smtp_port]" value="<?php echo esc_attr( $settings['smtp_port'] ); ?>" class="small-text"/></td>
					</tr>
					<tr>
						<th scope="row"><label for="smtp_username"><?php esc_html_e( 'Utilisateur', 'ibc-enrollment' ); ?></label></th>
						<td><input type="text" id="smtp_username" name="ibc_enrollment_settings[smtp_username]" value="<?php echo esc_attr( $settings['smtp_username'] ); ?>" class="regular-text"/></td>
					</tr>
					<tr>
						<th scope="row"><label for="smtp_password"><?php esc_html_e( 'Mot de passe', 'ibc-enrollment' ); ?></label></th>
						<td><input type="password" id="smtp_password" name="ibc_enrollment_settings[smtp_password]" value="<?php echo esc_attr( $settings['smtp_password'] ); ?>" class="regular-text" autocomplete="new-password"/></td>
					</tr>
					<tr>
						<th scope="row"><label for="smtp_secure"><?php esc_html_e( 'Sécurité', 'ibc-enrollment' ); ?></label></th>
						<td>
							<select id="smtp_secure" name="ibc_enrollment_settings[smtp_secure]">
								<option value=""><?php esc_html_e( 'Automatique (TLS)', 'ibc-enrollment' ); ?></option>
								<option value="ssl" <?php selected( $settings['smtp_secure'], 'ssl' ); ?>>SSL</option>
								<option value="tls" <?php selected( $settings['smtp_secure'], 'tls' ); ?>>TLS</option>
								<option value="starttls" <?php selected( $settings['smtp_secure'], 'starttls' ); ?>>STARTTLS</option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="email_from_name"><?php esc_html_e( 'Nom expéditeur', 'ibc-enrollment' ); ?></label></th>
						<td><input type="text" id="email_from_name" name="ibc_enrollment_settings[email_from_name]" value="<?php echo esc_attr( $settings['email_from_name'] ); ?>" class="regular-text"/></td>
					</tr>
					<tr>
						<th scope="row"><label for="email_from_address"><?php esc_html_e( 'Email expéditeur', 'ibc-enrollment' ); ?></label></th>
						<td><input type="email" id="email_from_address" name="ibc_enrollment_settings[email_from_address]" value="<?php echo esc_attr( $settings['email_from_address'] ); ?>" class="regular-text"/></td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'reCAPTCHA', 'ibc-enrollment' ); ?></h2>
				<table class="form-table">
					<tr>
						<th scope="row"><label for="recaptcha_site_key"><?php esc_html_e( 'Clé de site', 'ibc-enrollment' ); ?></label></th>
						<td><input type="text" id="recaptcha_site_key" name="ibc_enrollment_settings[recaptcha_site_key]" value="<?php echo esc_attr( $settings['recaptcha_site_key'] ); ?>" class="regular-text"/></td>
					</tr>
					<tr>
						<th scope="row"><label for="recaptcha_secret_key"><?php esc_html_e( 'Clé secrète', 'ibc-enrollment' ); ?></label></th>
						<td><input type="text" id="recaptcha_secret_key" name="ibc_enrollment_settings[recaptcha_secret_key]" value="<?php echo esc_attr( $settings['recaptcha_secret_key'] ); ?>" class="regular-text"/></td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'WhatsApp Cloud API', 'ibc-enrollment' ); ?></h2>
				<table class="form-table">
					<tr>
						<th scope="row"><label for="whatsapp_business_id"><?php esc_html_e( 'Business ID', 'ibc-enrollment' ); ?></label></th>
						<td><input type="text" id="whatsapp_business_id" name="ibc_enrollment_settings[whatsapp_business_id]" value="<?php echo esc_attr( $settings['whatsapp_business_id'] ); ?>" class="regular-text"/></td>
					</tr>
					<tr>
						<th scope="row"><label for="whatsapp_token"><?php esc_html_e( 'Access Token', 'ibc-enrollment' ); ?></label></th>
						<td><input type="text" id="whatsapp_token" name="ibc_enrollment_settings[whatsapp_token]" value="<?php echo esc_attr( $settings['whatsapp_token'] ); ?>" class="regular-text"/></td>
					</tr>
					<tr>
						<th scope="row"><label for="whatsapp_template"><?php esc_html_e( 'Template ID', 'ibc-enrollment' ); ?></label></th>
						<td><input type="text" id="whatsapp_template" name="ibc_enrollment_settings[whatsapp_template]" value="<?php echo esc_attr( $settings['whatsapp_template'] ); ?>" class="regular-text"/></td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Paiement', 'ibc-enrollment' ); ?></h2>
				<table class="form-table">
					<tr>
						<th scope="row"><label for="stripe_public_key"><?php esc_html_e( 'Stripe Public Key', 'ibc-enrollment' ); ?></label></th>
						<td><input type="text" id="stripe_public_key" name="ibc_enrollment_settings[stripe_public_key]" value="<?php echo esc_attr( $settings['stripe_public_key'] ); ?>" class="regular-text"/></td>
					</tr>
					<tr>
						<th scope="row"><label for="stripe_secret_key"><?php esc_html_e( 'Stripe Secret Key', 'ibc-enrollment' ); ?></label></th>
						<td><input type="text" id="stripe_secret_key" name="ibc_enrollment_settings[stripe_secret_key]" value="<?php echo esc_attr( $settings['stripe_secret_key'] ); ?>" class="regular-text"/></td>
					</tr>
					<tr>
						<th scope="row"><label for="cmi_merchant_id"><?php esc_html_e( 'CMI Merchant ID', 'ibc-enrollment' ); ?></label></th>
						<td><input type="text" id="cmi_merchant_id" name="ibc_enrollment_settings[cmi_merchant_id]" value="<?php echo esc_attr( $settings['cmi_merchant_id'] ); ?>" class="regular-text"/></td>
					</tr>
					<tr>
						<th scope="row"><label for="cmi_secret"><?php esc_html_e( 'CMI Secret', 'ibc-enrollment' ); ?></label></th>
						<td><input type="text" id="cmi_secret" name="ibc_enrollment_settings[cmi_secret]" value="<?php echo esc_attr( $settings['cmi_secret'] ); ?>" class="regular-text"/></td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Maintenance', 'ibc-enrollment' ); ?></h2>
				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Suppression lors de la désinstallation', 'ibc-enrollment' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="ibc_enrollment_settings[delete_on_uninstall]" value="yes" <?php checked( $settings['delete_on_uninstall'], 'yes' ); ?>/>
								<?php esc_html_e( 'Supprimer toutes les données lors de la désinstallation du plugin.', 'ibc-enrollment' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
