<?php
/**
 * Plugin settings screen (brand, capacity, contact, security).
 *
 * @package IBC\Enrollment
 */

declare( strict_types=1 );

namespace IBC\Enrollment\Admin;

use IBC\Enrollment\Security\Auth;
use function IBC\Enrollment\ibc_get_brand_colors_with_legacy;
use function IBC\Enrollment\ibc_get_brand_name;
use function IBC\Enrollment\ibc_normalize_phone;
use function IBC\Enrollment\ibc_sanitize_textarea;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders and processes the settings form exposed under Settings > IBC Enrollment.
 */
class Settings {

	public const PAGE_SLUG = 'ibc-enrollment-settings';

	public function __construct( private Auth $auth ) {}

	/**
	 * Hooks the menu + form handler.
	 */
	public function register(): void {
		add_action( 'admin_menu', [ $this, 'add_page' ] );
		add_action( 'admin_post_ibc_enrollment_save_settings', [ $this, 'handle_save' ] );
	}

	/**
	 * Adds the options page below "Settings".
	 */
	public function add_page(): void {
		add_options_page(
			__( 'IBC Enrollment – Paramètres', 'ibc-enrollment' ),
			__( 'IBC Enrollment', 'ibc-enrollment' ),
			'manage_options',
			self::PAGE_SLUG,
			[ $this, 'render_page' ]
		);
	}

	/**
	 * Outputs the settings form.
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Vous n’avez pas les droits suffisants pour accéder à cette page.', 'ibc-enrollment' ) );
		}

		$colors   = ibc_get_brand_colors_with_legacy();
		$updated  = isset( $_GET['updated'] );
		$capacity = (int) get_option( 'ibc_capacity_limit', 1466 );
		$price    = (int) get_option( 'ibc_price_prep', 1000 );
		?>
		<div class="wrap ibc-settings-page">
			<h1><?php esc_html_e( 'IBC Enrollment – Paramètres', 'ibc-enrollment' ); ?></h1>

			<?php if ( $updated ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Paramètres enregistrés avec succès.', 'ibc-enrollment' ); ?></p>
				</div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'ibc_enrollment_save_settings' ); ?>
				<input type="hidden" name="action" value="ibc_enrollment_save_settings">

				<h2><?php esc_html_e( 'Capacité & Tarif', 'ibc-enrollment' ); ?></h2>
				<table class="form-table">
					<tr>
						<th scope="row"><label for="ibc_capacity_limit"><?php esc_html_e( 'Capacité maximale', 'ibc-enrollment' ); ?></label></th>
						<td>
							<input type="number" class="regular-text" min="0" id="ibc_capacity_limit" name="ibc_capacity_limit" value="<?php echo esc_attr( $capacity ); ?>">
							<p class="description"><?php esc_html_e( 'Nombre maximum d’inscriptions confirmées simultanément.', 'ibc-enrollment' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="ibc_price_prep"><?php esc_html_e( 'Tarif préparation (MAD)', 'ibc-enrollment' ); ?></label></th>
						<td><input type="number" class="regular-text" min="0" id="ibc_price_prep" name="ibc_price_prep" value="<?php echo esc_attr( $price ); ?>"></td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Branding & Couleurs', 'ibc-enrollment' ); ?></h2>
				<table class="form-table">
					<tr>
						<th scope="row"><label for="ibc_brand_name"><?php esc_html_e( 'Nom de la marque', 'ibc-enrollment' ); ?></label></th>
						<td><input type="text" id="ibc_brand_name" name="ibc_brand_name" class="regular-text" value="<?php echo esc_attr( ibc_get_brand_name() ); ?>"></td>
					</tr>
					<?php
					$color_fields = [
						'ibc_brand_primary'        => __( 'Turquoise principale', 'ibc-enrollment' ),
						'ibc_brand_primary_dark'   => __( 'Turquoise foncée', 'ibc-enrollment' ),
						'ibc_brand_primary_light'  => __( 'Turquoise claire', 'ibc-enrollment' ),
						'ibc_brand_secondary'      => __( 'Couleur secondaire', 'ibc-enrollment' ),
						'ibc_brand_text_dark'      => __( 'Texte sombre', 'ibc-enrollment' ),
						'ibc_brand_text_muted'     => __( 'Texte atténué', 'ibc-enrollment' ),
						'ibc_brand_border'         => __( 'Bordures', 'ibc-enrollment' ),
						'ibc_brand_button'         => __( 'Bouton principal', 'ibc-enrollment' ),
						'ibc_brand_button_text'    => __( 'Texte du bouton', 'ibc-enrollment' ),
						'ibc_brand_success'        => __( 'Succès (accent)', 'ibc-enrollment' ),
						'ibc_brand_success_bg'     => __( 'Succès (fond)', 'ibc-enrollment' ),
						'ibc_brand_danger'         => __( 'Danger (accent)', 'ibc-enrollment' ),
						'ibc_brand_danger_bg'      => __( 'Danger (fond)', 'ibc-enrollment' ),
					];

					foreach ( $color_fields as $option => $label ) :
						$key = str_replace( 'ibc_brand_', '', $option );
						?>
						<tr>
							<th scope="row"><label for="<?php echo esc_attr( $option ); ?>"><?php echo esc_html( $label ); ?></label></th>
							<td><input type="color" id="<?php echo esc_attr( $option ); ?>" name="<?php echo esc_attr( $option ); ?>" value="<?php echo esc_attr( $colors[ $key ] ?? '#ffffff' ); ?>"></td>
						</tr>
					<?php endforeach; ?>
				</table>

				<h2><?php esc_html_e( 'Coordonnées & Contact', 'ibc-enrollment' ); ?></h2>
				<table class="form-table">
					<tr>
						<th scope="row"><label for="ibc_contact_address"><?php esc_html_e( 'Adresse', 'ibc-enrollment' ); ?></label></th>
						<td><textarea id="ibc_contact_address" name="ibc_contact_address" rows="3" class="large-text"><?php echo esc_textarea( (string) get_option( 'ibc_contact_address', '' ) ); ?></textarea></td>
					</tr>
					<tr>
						<th scope="row"><label for="ibc_contact_email"><?php esc_html_e( 'Email', 'ibc-enrollment' ); ?></label></th>
						<td><input type="email" class="regular-text" id="ibc_contact_email" name="ibc_contact_email" value="<?php echo esc_attr( (string) get_option( 'ibc_contact_email', get_option( 'admin_email' ) ) ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="ibc_contact_phone"><?php esc_html_e( 'Téléphone mobile', 'ibc-enrollment' ); ?></label></th>
						<td><input type="text" class="regular-text" id="ibc_contact_phone" name="ibc_contact_phone" value="<?php echo esc_attr( (string) get_option( 'ibc_contact_phone', '' ) ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="ibc_contact_landline"><?php esc_html_e( 'Téléphone fixe', 'ibc-enrollment' ); ?></label></th>
						<td><input type="text" class="regular-text" id="ibc_contact_landline" name="ibc_contact_landline" value="<?php echo esc_attr( (string) get_option( 'ibc_contact_landline', '' ) ); ?>"></td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Informations bancaires', 'ibc-enrollment' ); ?></h2>
				<table class="form-table">
					<?php
					$payment_fields = [
						'ibc_bank_name'      => __( 'Banque', 'ibc-enrollment' ),
						'ibc_account_holder' => __( 'Titulaire du compte', 'ibc-enrollment' ),
						'ibc_rib'            => __( 'RIB', 'ibc-enrollment' ),
						'ibc_bic'            => __( 'BIC / SWIFT', 'ibc-enrollment' ),
						'ibc_agency'         => __( 'Agence', 'ibc-enrollment' ),
					];

					foreach ( $payment_fields as $option => $label ) :
						?>
						<tr>
							<th scope="row"><label for="<?php echo esc_attr( $option ); ?>"><?php echo esc_html( $label ); ?></label></th>
							<td><input type="text" class="regular-text" id="<?php echo esc_attr( $option ); ?>" name="<?php echo esc_attr( $option ); ?>" value="<?php echo esc_attr( (string) get_option( $option, '' ) ); ?>"></td>
						</tr>
					<?php endforeach; ?>
					<tr>
						<th scope="row"><label for="ibc_payment_note"><?php esc_html_e( 'Note de paiement', 'ibc-enrollment' ); ?></label></th>
						<td><textarea id="ibc_payment_note" name="ibc_payment_note" rows="3" class="large-text"><?php echo esc_textarea( (string) get_option( 'ibc_payment_note', '' ) ); ?></textarea></td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Sécurité', 'ibc-enrollment' ); ?></h2>
				<table class="form-table">
					<tr>
						<th scope="row"><label for="ibc_admin_password"><?php esc_html_e( 'Mot de passe admin (dashboard)', 'ibc-enrollment' ); ?></label></th>
						<td>
							<input type="password" id="ibc_admin_password" name="ibc_admin_password" class="regular-text" autocomplete="new-password">
							<p class="description"><?php esc_html_e( 'Laisser vide pour conserver le mot de passe actuel.', 'ibc-enrollment' ); ?></p>
						</td>
					</tr>
				</table>

				<?php submit_button( __( 'Enregistrer les paramètres', 'ibc-enrollment' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Persists submitted values.
	 */
	public function handle_save(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Action non autorisée.', 'ibc-enrollment' ) );
		}

		check_admin_referer( 'ibc_enrollment_save_settings' );

		$capacity = isset( $_POST['ibc_capacity_limit'] ) ? max( 0, (int) $_POST['ibc_capacity_limit'] ) : 0;
		$price    = isset( $_POST['ibc_price_prep'] ) ? max( 0, (int) $_POST['ibc_price_prep'] ) : 0;

		update_option( 'ibc_capacity_limit', $capacity );
		update_option( 'ibc_price_prep', $price );

		if ( isset( $_POST['ibc_brand_name'] ) ) {
			update_option( 'ibc_brand_name', sanitize_text_field( wp_unslash( $_POST['ibc_brand_name'] ) ) );
		}

		$color_keys = [
			'primary',
			'primary_dark',
			'primary_light',
			'secondary',
			'text_dark',
			'text_muted',
			'border',
			'button',
			'button_text',
			'success',
			'success_bg',
			'danger',
			'danger_bg',
		];

		foreach ( $color_keys as $key ) {
			$field = 'ibc_brand_' . $key;
			if ( isset( $_POST[ $field ] ) ) {
				$value = sanitize_hex_color( wp_unslash( $_POST[ $field ] ) );
				if ( ! $value ) {
					$value = sanitize_text_field( wp_unslash( $_POST[ $field ] ) );
				}
				update_option( $field, $value );
			}
		}

		update_option( 'ibc_contact_address', isset( $_POST['ibc_contact_address'] ) ? ibc_sanitize_textarea( wp_unslash( $_POST['ibc_contact_address'] ) ) : '' );
		update_option( 'ibc_contact_email', isset( $_POST['ibc_contact_email'] ) ? sanitize_email( wp_unslash( $_POST['ibc_contact_email'] ) ) : '' );
		update_option( 'ibc_contact_phone', isset( $_POST['ibc_contact_phone'] ) ? ibc_normalize_phone( wp_unslash( $_POST['ibc_contact_phone'] ) ) : '' );
		update_option( 'ibc_contact_landline', isset( $_POST['ibc_contact_landline'] ) ? sanitize_text_field( wp_unslash( $_POST['ibc_contact_landline'] ) ) : '' );

		$payment_fields = [
			'ibc_bank_name',
			'ibc_account_holder',
			'ibc_rib',
			'ibc_bic',
			'ibc_agency',
		];

		foreach ( $payment_fields as $field ) {
			if ( isset( $_POST[ $field ] ) ) {
				update_option( $field, sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) );
			}
		}

		update_option(
			'ibc_payment_note',
			isset( $_POST['ibc_payment_note'] ) ? ibc_sanitize_textarea( wp_unslash( $_POST['ibc_payment_note'] ) ) : ''
		);

		$password = isset( $_POST['ibc_admin_password'] ) ? (string) wp_unslash( $_POST['ibc_admin_password'] ) : '';

		if ( '' !== trim( $password ) ) {
			$this->auth->update_password( $password );
		}

		wp_safe_redirect(
			add_query_arg(
				[
					'page'    => self::PAGE_SLUG,
					'updated' => 'true',
				],
				admin_url( 'options-general.php' )
			)
		);
		exit;
	}
}
