<?php
/**
 * Settings page.
 *
 * @package IBC\Enrollment
 */

namespace IBC\Enrollment;

use IBC\Enrollment\FormBuilder;

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
	 * Form builder service.
	 *
	 * @var FormBuilder
	 */
	private FormBuilder $form_builder;

	/**
	 * Constructor.
	 *
	 * @param FormBuilder $form_builder Form builder instance.
	 */
	public function __construct( FormBuilder $form_builder ) {
		$this->form_builder = $form_builder;
	}

	/**
	 * Register WP hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_post_ibc_save_settings', array( $this, 'handle_save' ) );
		add_action( 'admin_post_ibc_reset_tokens', array( $this, 'handle_reset_tokens' ) );
	}

	/**
	 * Register admin menu.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		$hook = add_menu_page(
			__( 'IBC Enrollment – Settings', 'ibc-enrollment-manager' ),
			__( 'IBC Enrollment', 'ibc-enrollment-manager' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' ),
			'dashicons-welcome-learn-more',
			56
		);

		add_submenu_page(
			self::PAGE_SLUG,
			__( 'Paramètres', 'ibc-enrollment-manager' ),
			__( 'Paramètres', 'ibc-enrollment-manager' ),
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

		$tab = sanitize_key( wp_unslash( $_POST['tab'] ?? 'capacity' ) );

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
			case 'formbuilder':
				$this->save_formbuilder_tab();
				break;
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'              => self::PAGE_SLUG,
					'settings-updated'  => 'true',
					'tab'               => $tab,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Handle token cache reset.
	 *
	 * @return void
	 */
	public function handle_reset_tokens(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Vous n’avez pas les droits suffisants.', 'ibc-enrollment-manager' ) );
		}

		check_admin_referer( 'ibc_reset_tokens' );

		$tokens = get_option( 'ibc_active_tokens', array() );
		if ( is_array( $tokens ) ) {
			foreach ( $tokens as $hash => $timestamp ) {
				delete_transient( $hash );
			}
		}

		update_option( 'ibc_active_tokens', array() );
		update_option( 'ibc_last_token_issued', '' );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'         => self::PAGE_SLUG,
					'tab'          => 'security',
					'tokens-reset' => 'true',
				),
				admin_url( 'admin.php' )
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

		$tab     = sanitize_key( wp_unslash( $_GET['tab'] ?? 'capacity' ) );
		$tabs    = $this->get_tabs();
		$colors  = $this->get_colors();
		$updated = isset( $_GET['settings-updated'] );
		$tokens_reset = isset( $_GET['tokens-reset'] );
		?>
		<div class="wrap ibc-settings-page">
			<h1><?php esc_html_e( 'IBC Enrollment – Paramètres', 'ibc-enrollment-manager' ); ?></h1>

			<?php if ( $updated ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Paramètres enregistrés avec succès.', 'ibc-enrollment-manager' ); ?></p></div>
			<?php endif; ?>
			<?php if ( $tokens_reset ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Tous les tokens actifs ont été réinitialisés.', 'ibc-enrollment-manager' ); ?></p></div>
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
						admin_url( 'admin.php' )
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
					case 'formbuilder':
						$this->render_formbuilder_tab();
						break;
					default:
						$this->render_capacity_tab();
						break;
				}
				?>

				<?php submit_button( __( 'Enregistrer', 'ibc-enrollment-manager' ) ); ?>
			</form>

			<?php if ( 'security' === $tab ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ibc-reset-tokens-form">
					<?php wp_nonce_field( 'ibc_reset_tokens' ); ?>
					<input type="hidden" name="action" value="ibc_reset_tokens">
					<button type="submit" class="button button-secondary"><?php esc_html_e( 'Réinitialiser les tokens actifs', 'ibc-enrollment-manager' ); ?></button>
				</form>
			<?php endif; ?>
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
				<th><label for="ibc_brand_name"><?php esc_html_e( 'Nom de la marque', 'ibc-enrollment-manager' ); ?></label></th>
				<td>
					<input type="text" id="ibc_brand_name" name="ibc_brand_name" value="<?php echo esc_attr( ibc_get_brand_name() ); ?>" class="regular-text" autocomplete="organization">
					<p class="description"><?php esc_html_e( 'Ce nom est utilisé dans les reçus PDF, les e-mails et le tableau de bord.', 'ibc-enrollment-manager' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="ibc_brand_primary"><?php esc_html_e( 'Couleur principale', 'ibc-enrollment-manager' ); ?></label></th>
				<td><input type="color" id="ibc_brand_primary" name="ibc_brand_primary" value="<?php echo esc_attr( $colors['primary'] ); ?>"></td>
			</tr>
			<tr>
				<th><label for="ibc_brand_secondary"><?php esc_html_e( 'Couleur secondaire', 'ibc-enrollment-manager' ); ?></label></th>
				<td><input type="color" id="ibc_brand_secondary" name="ibc_brand_secondary" value="<?php echo esc_attr( $colors['secondary'] ); ?>"></td>
			</tr>
			<tr>
				<th><label for="ibc_brand_text"><?php esc_html_e( 'Couleur texte', 'ibc-enrollment-manager' ); ?></label></th>
				<td><input type="color" id="ibc_brand_text" name="ibc_brand_text" value="<?php echo esc_attr( $colors['text'] ); ?>"></td>
			</tr>
			<tr>
				<th><label for="ibc_brand_muted"><?php esc_html_e( 'Couleur fond', 'ibc-enrollment-manager' ); ?></label></th>
				<td><input type="color" id="ibc_brand_muted" name="ibc_brand_muted" value="<?php echo esc_attr( $colors['muted'] ); ?>"></td>
			</tr>
			<tr>
				<th><label for="ibc_brand_border"><?php esc_html_e( 'Couleur bordure', 'ibc-enrollment-manager' ); ?></label></th>
				<td><input type="color" id="ibc_brand_border" name="ibc_brand_border" value="<?php echo esc_attr( $colors['border'] ); ?>"></td>
			</tr>
			<tr>
				<th><label for="ibc_brand_button"><?php esc_html_e( 'Bouton principal', 'ibc-enrollment-manager' ); ?></label></th>
				<td><input type="color" id="ibc_brand_button" name="ibc_brand_button" value="<?php echo esc_attr( $colors['button'] ); ?>"></td>
			</tr>
			<tr>
				<th><label for="ibc_brand_button_text"><?php esc_html_e( 'Texte bouton', 'ibc-enrollment-manager' ); ?></label></th>
				<td><input type="color" id="ibc_brand_button_text" name="ibc_brand_button_text" value="<?php echo esc_attr( $colors['button_text'] ); ?>"></td>
			</tr>
			<tr>
				<th><label for="ibc_brand_success_bg"><?php esc_html_e( 'Fond succès', 'ibc-enrollment-manager' ); ?></label></th>
				<td><input type="color" id="ibc_brand_success_bg" name="ibc_brand_success_bg" value="<?php echo esc_attr( $colors['success_bg'] ); ?>"></td>
			</tr>
			<tr>
				<th><label for="ibc_brand_success_text"><?php esc_html_e( 'Texte succès', 'ibc-enrollment-manager' ); ?></label></th>
				<td><input type="color" id="ibc_brand_success_text" name="ibc_brand_success_text" value="<?php echo esc_attr( $colors['success_text'] ); ?>"></td>
			</tr>
			<tr>
				<th><label for="ibc_brand_error_bg"><?php esc_html_e( 'Fond erreur', 'ibc-enrollment-manager' ); ?></label></th>
				<td><input type="color" id="ibc_brand_error_bg" name="ibc_brand_error_bg" value="<?php echo esc_attr( $colors['error_bg'] ); ?>"></td>
			</tr>
			<tr>
				<th><label for="ibc_brand_error_text"><?php esc_html_e( 'Texte erreur', 'ibc-enrollment-manager' ); ?></label></th>
				<td><input type="color" id="ibc_brand_error_text" name="ibc_brand_error_text" value="<?php echo esc_attr( $colors['error_text'] ); ?>"></td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Render form builder tab placeholder.
	 *
	 * @return void
	 */
	private function render_formbuilder_tab(): void {
		$schema   = $this->form_builder->get_schema();
		$schema_json = wp_json_encode( $schema );
		$colors   = $this->get_colors();
		?>
		<p class="description"><?php esc_html_e( 'Personnalisez l’ordre, les libellés et les paramètres des champs affichés sur le formulaire public.', 'ibc-enrollment-manager' ); ?></p>

		<input type="hidden" id="ibc_form_schema" name="ibc_form_schema" value="<?php echo esc_attr( $schema_json ); ?>">

		<div class="ibc-builder" data-ibc-builder>
			<div class="ibc-builder__columns">
				<div class="ibc-builder__column ibc-builder__column--fields">
					<div class="ibc-builder__panel">
						<div class="ibc-builder__panel-header">
							<h3><?php esc_html_e( 'Champs du formulaire', 'ibc-enrollment-manager' ); ?></h3>
							<button type="button" class="button button-primary" data-builder-add><?php esc_html_e( 'Ajouter un champ', 'ibc-enrollment-manager' ); ?></button>
						</div>
						<ul class="ibc-builder__list" data-builder-list></ul>
					</div>
				</div>
				<div class="ibc-builder__column ibc-builder__column--editor">
					<div class="ibc-builder__panel">
						<div class="ibc-builder__panel-header">
							<h3><?php esc_html_e( 'Propriétés du champ', 'ibc-enrollment-manager' ); ?></h3>
						</div>
						<div class="ibc-builder__editor" data-builder-editor>
							<p class="ibc-builder__placeholder"><?php esc_html_e( 'Sélectionnez un champ pour modifier ses paramètres.', 'ibc-enrollment-manager' ); ?></p>
						</div>
					</div>
				</div>
				<div class="ibc-builder__column ibc-builder__column--preview">
					<div class="ibc-builder__panel">
						<div class="ibc-builder__panel-header">
							<h3><?php esc_html_e( 'Prévisualisation', 'ibc-enrollment-manager' ); ?></h3>
						</div>
						<div class="ibc-builder__preview" data-builder-preview></div>
					</div>
				</div>
			</div>
		</div>

		<h3><?php esc_html_e( 'Couleurs du formulaire', 'ibc-enrollment-manager' ); ?></h3>
		<p class="description"><?php esc_html_e( 'Ces couleurs sont utilisées pour le bouton, les bordures et les messages du formulaire public.', 'ibc-enrollment-manager' ); ?></p>

		<div class="ibc-builder-theme">
			<div>
				<label for="ibc_brand_button"><?php esc_html_e( 'Bouton principal', 'ibc-enrollment-manager' ); ?></label>
				<input type="color" id="ibc_brand_button" name="ibc_brand_button" value="<?php echo esc_attr( $colors['button'] ); ?>">
			</div>
			<div>
				<label for="ibc_brand_button_text"><?php esc_html_e( 'Texte du bouton', 'ibc-enrollment-manager' ); ?></label>
				<input type="color" id="ibc_brand_button_text" name="ibc_brand_button_text" value="<?php echo esc_attr( $colors['button_text'] ); ?>">
			</div>
			<div>
				<label for="ibc_brand_border"><?php esc_html_e( 'Couleur de bordure', 'ibc-enrollment-manager' ); ?></label>
				<input type="color" id="ibc_brand_border" name="ibc_brand_border" value="<?php echo esc_attr( $colors['border'] ); ?>">
			</div>
			<div>
				<label for="ibc_brand_success_bg"><?php esc_html_e( 'Fond succès', 'ibc-enrollment-manager' ); ?></label>
				<input type="color" id="ibc_brand_success_bg" name="ibc_brand_success_bg" value="<?php echo esc_attr( $colors['success_bg'] ); ?>">
			</div>
			<div>
				<label for="ibc_brand_success_text"><?php esc_html_e( 'Texte succès', 'ibc-enrollment-manager' ); ?></label>
				<input type="color" id="ibc_brand_success_text" name="ibc_brand_success_text" value="<?php echo esc_attr( $colors['success_text'] ); ?>">
			</div>
			<div>
				<label for="ibc_brand_error_bg"><?php esc_html_e( 'Fond erreur', 'ibc-enrollment-manager' ); ?></label>
				<input type="color" id="ibc_brand_error_bg" name="ibc_brand_error_bg" value="<?php echo esc_attr( $colors['error_bg'] ); ?>">
			</div>
			<div>
				<label for="ibc_brand_error_text"><?php esc_html_e( 'Texte erreur', 'ibc-enrollment-manager' ); ?></label>
				<input type="color" id="ibc_brand_error_text" name="ibc_brand_error_text" value="<?php echo esc_attr( $colors['error_text'] ); ?>">
			</div>
		</div>
		<?php
	}

	/**
	 * Render payment tab.
	 *
	 * @return void
	 */
	private function render_payment_tab(): void {
		$fields = array(
			'ibc_bank_name'      => __( 'Banque', 'ibc-enrollment-manager' ),
			'ibc_account_holder' => __( 'Titulaire du compte', 'ibc-enrollment-manager' ),
			'ibc_rib'            => __( 'RIB', 'ibc-enrollment-manager' ),
			'ibc_iban'           => __( 'IBAN', 'ibc-enrollment-manager' ),
			'ibc_bic'            => __( 'BIC / SWIFT', 'ibc-enrollment-manager' ),
			'ibc_agency'         => __( 'Agence', 'ibc-enrollment-manager' ),
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
				<th><label for="ibc_payment_note"><?php esc_html_e( 'Note de paiement', 'ibc-enrollment-manager' ); ?></label></th>
				<td>
					<textarea id="ibc_payment_note" name="ibc_payment_note" rows="3" class="large-text"><?php echo esc_textarea( get_option( 'ibc_payment_note', '' ) ); ?></textarea>
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
		$last_token  = (string) get_option( 'ibc_last_token_issued', '' );
		$active      = get_option( 'ibc_active_tokens', array() );
		$active_count = is_array( $active ) ? count( $active ) : 0;
		\IBC\Enrollment\Admin\Admin_Page::heading(
			__( 'Sécurité API', 'ibc-enrollment-manager' ),
			__( 'Définissez le mot de passe opérateur et surveillez les jetons actifs utilisés par le tableau de bord.', 'ibc-enrollment-manager' )
		);
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
					<input type="text" readonly class="regular-text" value="<?php echo esc_attr( $last_token ?: '—' ); ?>">
					<p class="description"><?php echo esc_html( sprintf( /* translators: %d number of active tokens */ __( '%d token(s) actuellement valides.', 'ibc-enrollment-manager' ), $active_count ) ); ?></p>
				</td>
			</tr>
		</table>
		<?php
		\IBC\Enrollment\Admin\Admin_Page::definition_list(
			array(
				__( 'En-tête HTTP requis', 'ibc-enrollment-manager' ) => 'X-IBC-Token',
				__( 'Paramètre alternatif', 'ibc-enrollment-manager' ) => 'token',
			)
		);
		?>
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
		if ( isset( $_POST['ibc_brand_name'] ) ) {
			$name = sanitize_text_field( wp_unslash( $_POST['ibc_brand_name'] ) );
			update_option( 'ibc_brand_name', $name );
		}

		$keys = array(
			'ibc_brand_primary',
			'ibc_brand_secondary',
			'ibc_brand_text',
			'ibc_brand_muted',
			'ibc_brand_border',
			'ibc_brand_button',
			'ibc_brand_button_text',
			'ibc_brand_success_bg',
			'ibc_brand_success_text',
			'ibc_brand_error_bg',
			'ibc_brand_error_text',
		);

		foreach ( $keys as $option ) {
			if ( isset( $_POST[ $option ] ) ) {
				$value = sanitize_hex_color( wp_unslash( $_POST[ $option ] ) );
				if ( ! $value ) {
					$value = sanitize_text_field( wp_unslash( $_POST[ $option ] ) );
				}
				update_option( $option, $value );
			}
		}
	}

	/**
	 * Save payment tab.
	 *
	 * @return void
	 */
	private function save_payment_tab(): void {
		$fields = array(
			'ibc_bank_name',
			'ibc_account_holder',
			'ibc_rib',
			'ibc_iban',
			'ibc_bic',
			'ibc_agency',
			'ibc_payment_note',
		);

		foreach ( $fields as $field ) {
			if ( isset( $_POST[ $field ] ) ) {
				$value = 'ibc_payment_note' === $field ? ibc_sanitize_textarea( wp_unslash( $_POST[ $field ] ) ) : sanitize_text_field( wp_unslash( $_POST[ $field ] ) );
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
			delete_option( 'ibc_admin_password_plain' );
		}
	}

	/**
	 * Placeholder save handler for form builder (overridden when builder enabled).
	 *
	 * @return void
	 */
	private function save_formbuilder_tab(): void {
		$raw_schema = isset( $_POST['ibc_form_schema'] ) ? wp_unslash( $_POST['ibc_form_schema'] ) : '';
		$schema     = array();

		if ( is_string( $raw_schema ) && '' !== $raw_schema ) {
			$decoded = json_decode( $raw_schema, true );
			if ( is_array( $decoded ) ) {
				$schema = $decoded;
			}
		}

		if ( empty( $schema ) ) {
			$schema = $this->form_builder->get_default_schema();
		}

		$this->form_builder->save_schema( $schema );
		$this->save_builder_colors();
	}

	/**
	 * Persist color selections coming from the builder tab.
	 *
	 * @return void
	 */
	private function save_builder_colors(): void {
		$keys = array(
			'ibc_brand_button',
			'ibc_brand_button_text',
			'ibc_brand_border',
			'ibc_brand_success_bg',
			'ibc_brand_success_text',
			'ibc_brand_error_bg',
			'ibc_brand_error_text',
		);

		foreach ( $keys as $option ) {
			if ( isset( $_POST[ $option ] ) ) {
				$value = sanitize_hex_color( wp_unslash( $_POST[ $option ] ) );
				if ( ! $value ) {
					$value = sanitize_text_field( wp_unslash( $_POST[ $option ] ) );
				}
				update_option( $option, $value );
			}
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
			'formbuilder' => __( 'Form Builder', 'ibc-enrollment-manager' ),
		);
	}

	/**
	 * Retrieve colors option.
	 *
	 * @return array
	 */
	private function get_colors(): array {
		return ibc_get_brand_colors_with_legacy();
	}
}
