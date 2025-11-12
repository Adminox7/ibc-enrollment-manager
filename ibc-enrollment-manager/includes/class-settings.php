<?php
/**
 * Settings page.
 *
 * @package IBC\EnrollmentManager
 */

namespace IBC;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Settings
 */
class Settings {

	/**
	 * Page slug.
	 */
	private const PAGE_SLUG = 'ibc-enrollment-settings';

	/**
	 * Register WP hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_post_ibc_save_settings', array( $this, 'handle_save' ) );
	}

	/**
	 * Register admin menu.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		add_options_page(
			__( 'IBC Enrollment', 'ibc-enrollment-manager' ),
			__( 'IBC Enrollment', 'ibc-enrollment-manager' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Handle settings save.
	 *
	 * @return void
	 */
	public function handle_save(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Vous n’avez pas les droits suffisants.', 'ibc-enrollment-manager' ) );
		}

		check_admin_referer( 'ibc_save_settings' );

		$tab = sanitize_text_field( wp_unslash( $_POST['tab'] ?? 'capacity' ) );

		switch ( $tab ) {
			case 'capacity':
				$this->save_capacity_tab();
				break;
			case 'branding':
				$this->save_branding_tab();
				break;
			case 'payment':
				$this->save_payment_tab();
				break;
			case 'contact':
				$this->save_contact_tab();
				break;
			case 'security':
				$this->save_security_tab();
				break;
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'              => self::PAGE_SLUG,
					'settings-updated'  => 'true',
					'tab'               => $tab,
				),
				admin_url( 'options-general.php' )
			)
		);
		exit;
	}

	/**
	 * Render settings page.
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Vous n’avez pas les droits suffisants.', 'ibc-enrollment-manager' ) );
		}

		$tab     = sanitize_text_field( wp_unslash( $_GET['tab'] ?? 'capacity' ) );
		$tabs    = $this->get_tabs();
		$colors  = $this->get_colors();
		$updated = isset( $_GET['settings-updated'] );
		?>
		<div class="wrap ibc-settings-page">
			<h1><?php esc_html_e( 'IBC Enrollment – Paramètres', 'ibc-enrollment-manager' ); ?></h1>

			<?php if ( $updated ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Paramètres enregistrés avec succès.', 'ibc-enrollment-manager' ); ?></p></div>
			<?php endif; ?>

			<h2 class="nav-tab-wrapper">
				<?php foreach ( $tabs as $key => $label ) : ?>
					<?php
					$active = $key === $tab ? ' nav-tab-active' : '';
					$url    = add_query_arg(
						array(
							'page' => self::PAGE_SLUG,
							'tab'  => $key,
						),
						admin_url( 'options-general.php' )
					);
					?>
					<a class="nav-tab<?php echo esc_attr( $active ); ?>" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</h2>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ibc-settings-form">
				<?php wp_nonce_field( 'ibc_save_settings' ); ?>
				<input type="hidden" name="action" value="ibc_save_settings">
				<input type="hidden" name="tab" value="<?php echo esc_attr( $tab ); ?>">

				<?php
				switch ( $tab ) {
					case 'branding':
						$this->render_branding_tab( $colors );
						break;
					case 'payment':
						$this->render_payment_tab();
						break;
					case 'contact':
						$this->render_contact_tab();
						break;
					case 'security':
						$this->render_security_tab();
						break;
					default:
						$this->render_capacity_tab();
						break;
				}
				?>

				<?php submit_button( __( 'Enregistrer', 'ibc-enrollment-manager' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render capacity tab.
	 *
	 * @return void
	 */
	private function render_capacity_tab(): void {
		$capacity = (int) get_option( 'ibc_capacity_limit', 1066 );
		$price    = (int) get_option( 'ibc_price_prep', 1000 );
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="ibc_capacity_limit"><?php esc_html_e( 'Capacité maximale', 'ibc-enrollment-manager' ); ?></label></th>
				<td>
					<input name="ibc_capacity_limit" id="ibc_capacity_limit" type="number" min="0" value="<?php echo esc_attr( $capacity ); ?>" class="regular-text">
					<p class="description"><?php esc_html_e( 'Nombre maximum d’inscriptions actives autorisées.', 'ibc-enrollment-manager' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="ibc_price_prep"><?php esc_html_e( 'Tarif préparation (MAD)', 'ibc-enrollment-manager' ); ?></label></th>
				<td>
					<input name="ibc_price_prep" id="ibc_price_prep" type="number" min="0" value="<?php echo esc_attr( $price ); ?>" class="regular-text">
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Render branding tab.
	 *
	 * @param array $colors Colors array.
	 *
	 * @return void
	 */
	private function render_branding_tab( array $colors ): void {
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th><label for="ibc_color_primary"><?php esc_html_e( 'Couleur principale', 'ibc-enrollment-manager' ); ?></label></th>
				<td><input type="text" id="ibc_color_primary" name="ibc_brand_colors[primary]" value="<?php echo esc_attr( $colors['primary'] ?? '#e94162' ); ?>" class="regular-text"></td>
			</tr>
			<tr>
				<th><label for="ibc_color_secondary"><?php esc_html_e( 'Couleur secondaire', 'ibc-enrollment-manager' ); ?></label></th>
				<td><input type="text" id="ibc_color_secondary" name="ibc_brand_colors[secondary]" value="<?php echo esc_attr( $colors['secondary'] ?? '#0f172a' ); ?>" class="regular-text"></td>
			</tr>
			<tr>
				<th><label for="ibc_color_text"><?php esc_html_e( 'Couleur texte', 'ibc-enrollment-manager' ); ?></label></th>
				<td><input type="text" id="ibc_color_text" name="ibc_brand_colors[text]" value="<?php echo esc_attr( $colors['text'] ?? '#1f2937' ); ?>" class="regular-text"></td>
			</tr>
			<tr>
				<th><label for="ibc_color_muted"><?php esc_html_e( 'Couleur fond', 'ibc-enrollment-manager' ); ?></label></th>
				<td><input type="text" id="ibc_color_muted" name="ibc_brand_colors[muted]" value="<?php echo esc_attr( $colors['muted'] ?? '#f8fafc' ); ?>" class="regular-text"></td>
			</tr>
			<tr>
				<th><label for="ibc_color_border"><?php esc_html_e( 'Couleur bordure', 'ibc-enrollment-manager' ); ?></label></th>
				<td><input type="text" id="ibc_color_border" name="ibc_brand_colors[border]" value="<?php echo esc_attr( $colors['border'] ?? '#e2e8f0' ); ?>" class="regular-text"></td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Render payment tab.
	 *
	 * @return void
	 */
	private function render_payment_tab(): void {
		$fields = array(
			'ibc_brand_bankName'    => __( 'Banque', 'ibc-enrollment-manager' ),
			'ibc_brand_accountHolder' => __( 'Titulaire du compte', 'ibc-enrollment-manager' ),
			'ibc_brand_rib'         => __( 'RIB', 'ibc-enrollment-manager' ),
			'ibc_brand_iban'        => __( 'IBAN', 'ibc-enrollment-manager' ),
			'ibc_brand_bic'         => __( 'BIC / SWIFT', 'ibc-enrollment-manager' ),
			'ibc_brand_agency'      => __( 'Agence', 'ibc-enrollment-manager' ),
		);
		?>
		<table class="form-table" role="presentation">
			<?php foreach ( $fields as $option => $label ) : ?>
				<tr>
					<th><label for="<?php echo esc_attr( $option ); ?>"><?php echo esc_html( $label ); ?></label></th>
					<td><input type="text" id="<?php echo esc_attr( $option ); ?>" name="<?php echo esc_attr( $option ); ?>" value="<?php echo esc_attr( get_option( $option, '' ) ); ?>" class="regular-text"></td>
				</tr>
			<?php endforeach; ?>
			<tr>
				<th><label for="ibc_brand_paymentNote"><?php esc_html_e( 'Note de paiement', 'ibc-enrollment-manager' ); ?></label></th>
				<td>
					<textarea id="ibc_brand_paymentNote" name="ibc_brand_paymentNote" rows="3" class="large-text"><?php echo esc_textarea( get_option( 'ibc_brand_paymentNote', '' ) ); ?></textarea>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Render contact tab.
	 *
	 * @return void
	 */
	private function render_contact_tab(): void {
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th><label for="ibc_contact_address"><?php esc_html_e( 'Adresse', 'ibc-enrollment-manager' ); ?></label></th>
				<td><textarea id="ibc_contact_address" name="ibc_contact_address" rows="3" class="large-text"><?php echo esc_textarea( get_option( 'ibc_contact_address', '' ) ); ?></textarea></td>
			</tr>
			<tr>
				<th><label for="ibc_contact_email"><?php esc_html_e( 'Email', 'ibc-enrollment-manager' ); ?></label></th>
				<td><input type="email" id="ibc_contact_email" name="ibc_contact_email" value="<?php echo esc_attr( get_option( 'ibc_contact_email', get_option( 'admin_email' ) ) ); ?>" class="regular-text"></td>
			</tr>
			<tr>
				<th><label for="ibc_contact_phone"><?php esc_html_e( 'Mobile', 'ibc-enrollment-manager' ); ?></label></th>
				<td><input type="text" id="ibc_contact_phone" name="ibc_contact_phone" value="<?php echo esc_attr( get_option( 'ibc_contact_phone', '' ) ); ?>" class="regular-text"></td>
			</tr>
			<tr>
				<th><label for="ibc_contact_landline"><?php esc_html_e( 'Fixe', 'ibc-enrollment-manager' ); ?></label></th>
				<td><input type="text" id="ibc_contact_landline" name="ibc_contact_landline" value="<?php echo esc_attr( get_option( 'ibc_contact_landline', '' ) ); ?>" class="regular-text"></td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Render security tab.
	 *
	 * @return void
	 */
	private function render_security_tab(): void {
		$last_token = (string) get_option( 'ibc_last_token_issued', '' );
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="ibc_new_password"><?php esc_html_e( 'Mot de passe admin', 'ibc-enrollment-manager' ); ?></label></th>
				<td>
					<input type="password" id="ibc_new_password" name="ibc_new_password" class="regular-text" autocomplete="new-password">
					<p class="description"><?php esc_html_e( 'Laissez vide pour conserver le mot de passe actuel.', 'ibc-enrollment-manager' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Dernier token émis', 'ibc-enrollment-manager' ); ?></th>
				<td>
					<input type="text" readonly class="regular-text" value="<?php echo esc_attr( $last_token ); ?>">
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Save capacity tab.
	 *
	 * @return void
	 */
	private function save_capacity_tab(): void {
		$capacity = isset( $_POST['ibc_capacity_limit'] ) ? max( 0, (int) $_POST['ibc_capacity_limit'] ) : 0;
		$price    = isset( $_POST['ibc_price_prep'] ) ? max( 0, (int) $_POST['ibc_price_prep'] ) : 0;

		update_option( 'ibc_capacity_limit', $capacity );
		update_option( 'ibc_price_prep', $price );
	}

	/**
	 * Save branding tab.
	 *
	 * @return void
	 */
	private function save_branding_tab(): void {
		$colors = array_map(
			static fn( $value ) => sanitize_text_field( $value ),
			(array) ( $_POST['ibc_brand_colors'] ?? array() )
		);

		$current = $this->get_colors();
		update_option( 'ibc_brand_colors', array_merge( $current, $colors ) );
	}

	/**
	 * Save payment tab.
	 *
	 * @return void
	 */
	private function save_payment_tab(): void {
		$fields = array(
			'ibc_brand_bankName',
			'ibc_brand_accountHolder',
			'ibc_brand_rib',
			'ibc_brand_iban',
			'ibc_brand_bic',
			'ibc_brand_agency',
			'ibc_brand_paymentNote',
		);

		foreach ( $fields as $field ) {
			if ( isset( $_POST[ $field ] ) ) {
				$value = 'ibc_brand_paymentNote' === $field ? ibc_sanitize_textarea( wp_unslash( $_POST[ $field ] ) ) : sanitize_text_field( wp_unslash( $_POST[ $field ] ) );
				update_option( $field, $value );
			}
		}
	}

	/**
	 * Save contact tab.
	 *
	 * @return void
	 */
	private function save_contact_tab(): void {
		$address  = isset( $_POST['ibc_contact_address'] ) ? ibc_sanitize_textarea( wp_unslash( $_POST['ibc_contact_address'] ) ) : '';
		$email    = isset( $_POST['ibc_contact_email'] ) ? sanitize_email( wp_unslash( $_POST['ibc_contact_email'] ) ) : '';
		$phone    = isset( $_POST['ibc_contact_phone'] ) ? ibc_normalize_phone( wp_unslash( $_POST['ibc_contact_phone'] ) ) : '';
		$landline = isset( $_POST['ibc_contact_landline'] ) ? sanitize_text_field( wp_unslash( $_POST['ibc_contact_landline'] ) ) : '';

		update_option( 'ibc_contact_address', $address );
		update_option( 'ibc_contact_email', $email );
		update_option( 'ibc_contact_phone', $phone );
		update_option( 'ibc_contact_landline', $landline );
	}

	/**
	 * Save security tab.
	 *
	 * @return void
	 */
	private function save_security_tab(): void {
		$password = wp_unslash( $_POST['ibc_new_password'] ?? '' );

		if ( ! empty( $password ) ) {
			$hash = password_hash( $password, PASSWORD_DEFAULT );
			update_option( 'ibc_admin_password_hash', $hash );
		}
	}

	/**
	 * Get tabs list.
	 *
	 * @return array
	 */
	private function get_tabs(): array {
		return array(
			'capacity' => __( 'Capacité & Prix', 'ibc-enrollment-manager' ),
			'branding' => __( 'Branding', 'ibc-enrollment-manager' ),
			'payment'  => __( 'Paiement', 'ibc-enrollment-manager' ),
			'contact'  => __( 'Contact', 'ibc-enrollment-manager' ),
			'security' => __( 'Sécurité', 'ibc-enrollment-manager' ),
		);
	}

	/**
	 * Retrieve colors option.
	 *
	 * @return array
	 */
	private function get_colors(): array {
		$defaults = array(
			'primary'   => '#e94162',
			'secondary' => '#0f172a',
			'text'      => '#1f2937',
			'muted'     => '#f8fafc',
			'border'    => '#e2e8f0',
		);

		$colors = get_option( 'ibc_brand_colors', array() );
		if ( ! is_array( $colors ) ) {
			$colors = array();
		}

		return array_merge( $defaults, $colors );
	}
}
