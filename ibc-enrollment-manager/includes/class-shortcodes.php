<?php
/**
 * Shortcodes renderer.
 *
 * @package IBC\EnrollmentManager
 */

namespace IBC;

use IBC\FormBuilder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Shortcodes
 */
class Shortcodes {

	/**
	 * Registrations service.
	 *
	 * @var Registrations
	 */
	private Registrations $registrations;

	/**
	 * Auth service.
	 *
	 * @var Auth
	 */
	private Auth $auth;

	/**
	 * Form builder service.
	 *
	 * @var FormBuilder
	 */
	private FormBuilder $form_builder;

	/**
	 * Constructor.
	 *
	 * @param Registrations $registrations Registrations service.
	 * @param Auth          $auth          Auth service.
	 * @param FormBuilder   $form_builder  Form builder service.
	 */
	public function __construct( Registrations $registrations, Auth $auth, FormBuilder $form_builder ) {
		$this->registrations = $registrations;
		$this->auth          = $auth;
		$this->form_builder  = $form_builder;
	}

	/**
	 * Register shortcodes.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_shortcode( 'ibc_register', array( $this, 'render_register' ) );
		add_shortcode( 'ibc_admin_dashboard', array( $this, 'render_dashboard' ) );
	}

	/**
	 * Render registration form.
	 *
	 * @param array $atts Attributes.
	 *
	 * @return string
	 */
	public function render_register( array $atts ): string {
		$schema  = $this->form_builder->get_active_schema();
		$schema  = ! empty( $schema ) ? $schema : $this->form_builder->get_default_schema();
		$colors  = ibc_get_brand_colors_with_legacy();
		$atts = shortcode_atts(
			array(
				'title' => __( 'Préinscription IBC', 'ibc-enrollment-manager' ),
			),
			$atts
		);

		$style = sprintf(
			'--ibc-form-primary:%1$s;--ibc-form-secondary:%2$s;--ibc-form-text:%3$s;--ibc-form-muted:%4$s;--ibc-form-border:%5$s;--ibc-form-button:%6$s;--ibc-form-button-text:%7$s;--ibc-form-success-bg:%8$s;--ibc-form-success-text:%9$s;--ibc-form-error-bg:%10$s;--ibc-form-error-text:%11$s;',
			esc_attr( $colors['primary'] ),
			esc_attr( $colors['secondary'] ),
			esc_attr( $colors['text'] ),
			esc_attr( $colors['muted'] ),
			esc_attr( $colors['border'] ),
			esc_attr( $colors['button'] ),
			esc_attr( $colors['button_text'] ),
			esc_attr( $colors['success_bg'] ),
			esc_attr( $colors['success_text'] ),
			esc_attr( $colors['error_bg'] ),
			esc_attr( $colors['error_text'] )
		);

		ob_start();
		?>
		<div class="ibc-form-wrapper" data-ibc-form style="<?php echo esc_attr( $style ); ?>">
			<div class="ibc-form-card">
				<h2 class="ibc-form-title"><?php echo esc_html( $atts['title'] ); ?></h2>
				<p class="ibc-form-subtitle"><?php esc_html_e( 'Complétez le formulaire pour réserver votre place.', 'ibc-enrollment-manager' ); ?></p>

				<form class="ibc-form" enctype="multipart/form-data">
					<div class="ibc-form-grid">
						<?php foreach ( $schema as $field ) : ?>
							<?php echo $this->render_form_field( $field ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php endforeach; ?>
					</div>

					<div class="ibc-form-actions">
						<button type="submit" class="ibc-button-primary"><?php esc_html_e( 'Envoyer ma demande', 'ibc-enrollment-manager' ); ?></button>
					</div>

					<div class="ibc-form-feedback" hidden></div>
				</form>
			</div>

			<div class="ibc-popup" data-ibc-success hidden>
				<div class="ibc-popup-card">
					<h3><?php esc_html_e( 'Merci !', 'ibc-enrollment-manager' ); ?></h3>
					<p><?php esc_html_e( 'Votre préinscription a été enregistrée. Vérifiez votre boîte mail pour la suite.', 'ibc-enrollment-manager' ); ?></p>
					<button type="button" class="ibc-button-secondary" data-ibc-close><?php esc_html_e( 'Fermer', 'ibc-enrollment-manager' ); ?></button>
				</div>
			</div>

			<div class="ibc-popup" data-ibc-closed hidden>
				<div class="ibc-popup-card">
					<h3><?php esc_html_e( 'Capacité atteinte', 'ibc-enrollment-manager' ); ?></h3>
					<p><?php esc_html_e( 'Cette session est complète pour le moment. Contactez-nous pour être alerté en cas de disponibilité.', 'ibc-enrollment-manager' ); ?></p>
					<button type="button" class="ibc-button-secondary" data-ibc-close><?php esc_html_e( 'Fermer', 'ibc-enrollment-manager' ); ?></button>
				</div>
			</div>
		</div>
		<?php

		return (string) ob_get_clean();
	}

	/**
	 * Render a single form field.
	 *
	 * @param array $field Field definition.
	 *
	 * @return string
	 */
	private function render_form_field( array $field ): string {
		$id          = 'ibc_field_' . sanitize_html_class( $field['id'] );
		$name        = sanitize_key( $field['id'] );
		$type        = in_array( $field['type'], array( 'text', 'email', 'tel', 'date', 'textarea', 'select', 'file' ), true ) ? $field['type'] : 'text';
		$required    = ! empty( $field['required'] );
		$width       = 'half' === ( $field['width'] ?? 'full' ) ? 'is-half' : 'is-full';
		$placeholder = $field['placeholder'] ?? '';
		$help        = $field['help'] ?? '';
		$map         = sanitize_key( $field['map'] ?? '' );
		$default     = $field['default'] ?? '';
		$help_id     = $help ? $id . '_help' : '';
		$describedby = $help_id ? ' aria-describedby="' . esc_attr( $help_id ) . '"' : '';

		ob_start();
		?>
		<div class="ibc-form-field <?php echo esc_attr( $width ); ?>" data-ibc-field-id="<?php echo esc_attr( $name ); ?>" data-ibc-field-type="<?php echo esc_attr( $type ); ?>"<?php echo $map ? ' data-ibc-field-map="' . esc_attr( $map ) . '"' : ''; ?> data-ibc-field-required="<?php echo $required ? '1' : '0'; ?>">
			<label for="<?php echo esc_attr( $id ); ?>">
				<?php echo esc_html( $field['label'] ?? ucfirst( $name ) ); ?>
				<?php if ( $required ) : ?>
					<span class="ibc-form-required">*</span>
				<?php endif; ?>
			</label>
			<?php
			switch ( $type ) {
				case 'textarea':
					?>
					<textarea id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" rows="4" <?php echo $required ? 'required' : ''; ?><?php echo $describedby; ?> placeholder="<?php echo esc_attr( $placeholder ); ?>"><?php echo esc_textarea( $default ); ?></textarea>
					<?php
					break;
				case 'select':
					?>
					<select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>"<?php echo $required ? ' required' : ''; ?><?php echo $describedby; ?>>
						<option value=""><?php echo esc_html( $placeholder ?: __( 'Sélectionnez…', 'ibc-enrollment-manager' ) ); ?></option>
						<?php
						foreach ( (array) ( $field['choices'] ?? array() ) as $choice ) :
							$value = isset( $choice['value'] ) ? (string) $choice['value'] : '';
							$label = isset( $choice['label'] ) ? (string) $choice['label'] : $value;
							?>
							<option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
					<?php
					break;
				case 'file':
					?>
					<input type="file" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>"<?php echo $required ? ' required' : ''; ?><?php echo ! empty( $field['accept'] ) ? ' accept="' . esc_attr( $field['accept'] ) . '"' : ''; ?><?php echo $describedby; ?>>
					<?php
					break;
				default:
					?>
					<input
						type="<?php echo esc_attr( $type === 'email' ? 'email' : ( $type === 'tel' ? 'tel' : ( $type === 'date' ? 'date' : 'text' ) ) ); ?>"
						id="<?php echo esc_attr( $id ); ?>"
						name="<?php echo esc_attr( $name ); ?>"
						<?php echo $required ? 'required' : ''; ?>
						placeholder="<?php echo esc_attr( $placeholder ); ?>"
						value="<?php echo esc_attr( $default ); ?>"
						<?php echo $describedby; ?>
					>
					<?php
					break;
			}

			if ( $help ) :
				?>
				<p class="ibc-form-help" id="<?php echo esc_attr( $help_id ); ?>"><?php echo esc_html( $help ); ?></p>
			<?php endif; ?>
		</div>
		<?php

		return (string) ob_get_clean();
	}

	/**
	 * Render admin dashboard shortcode.
	 *
	 * @return string
	 */
	public function render_dashboard(): string {
		ob_start();
		?>
		<div class="ibc-dashboard" data-ibc-dashboard>
			<div class="ibc-dashboard-header">
				<h2><?php esc_html_e( 'Gestion des préinscriptions IBC', 'ibc-enrollment-manager' ); ?></h2>
				<button type="button" class="ibc-button-primary" data-ibc-export><?php esc_html_e( 'Exporter CSV', 'ibc-enrollment-manager' ); ?></button>
			</div>

			<div class="ibc-dashboard-toolbar">
				<div class="ibc-field">
					<label for="ibc_filter_search"><?php esc_html_e( 'Recherche', 'ibc-enrollment-manager' ); ?></label>
					<input type="search" id="ibc_filter_search" placeholder="<?php esc_attr_e( 'Nom, email, téléphone, référence…', 'ibc-enrollment-manager' ); ?>">
				</div>
				<div class="ibc-field">
					<label for="ibc_filter_level"><?php esc_html_e( 'Niveau', 'ibc-enrollment-manager' ); ?></label>
					<select id="ibc_filter_level">
						<option value=""><?php esc_html_e( 'Tous', 'ibc-enrollment-manager' ); ?></option>
						<option value="A1">A1</option>
						<option value="A2">A2</option>
						<option value="B1">B1</option>
						<option value="B2">B2</option>
						<option value="C1">C1</option>
						<option value="C2">C2</option>
					</select>
				</div>
				<div class="ibc-field">
					<label for="ibc_filter_status"><?php esc_html_e( 'Statut', 'ibc-enrollment-manager' ); ?></label>
					<select id="ibc_filter_status">
						<option value=""><?php esc_html_e( 'Tous', 'ibc-enrollment-manager' ); ?></option>
						<option value="Confirme"><?php esc_html_e( 'Confirmées', 'ibc-enrollment-manager' ); ?></option>
						<option value="Annule"><?php esc_html_e( 'Annulées', 'ibc-enrollment-manager' ); ?></option>
					</select>
				</div>
				<div class="ibc-field">
					<label for="ibc_filter_perpage"><?php esc_html_e( 'Par page', 'ibc-enrollment-manager' ); ?></label>
					<select id="ibc_filter_perpage">
						<option value="25">25</option>
						<option value="50" selected>50</option>
						<option value="100">100</option>
					</select>
				</div>
			</div>

			<div class="ibc-table-wrapper">
				<table class="ibc-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Référence', 'ibc-enrollment-manager' ); ?></th>
							<th><?php esc_html_e( 'Nom complet', 'ibc-enrollment-manager' ); ?></th>
							<th><?php esc_html_e( 'Coordonnées', 'ibc-enrollment-manager' ); ?></th>
							<th><?php esc_html_e( 'CIN', 'ibc-enrollment-manager' ); ?></th>
							<th><?php esc_html_e( 'Niveau', 'ibc-enrollment-manager' ); ?></th>
							<th><?php esc_html_e( 'Statut', 'ibc-enrollment-manager' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'ibc-enrollment-manager' ); ?></th>
						</tr>
					</thead>
					<tbody data-ibc-table-body>
						<tr class="ibc-table-empty">
							<td colspan="7"><?php esc_html_e( 'Chargement en cours…', 'ibc-enrollment-manager' ); ?></td>
						</tr>
					</tbody>
				</table>
			</div>

			<div class="ibc-pagination" data-ibc-pagination hidden>
				<button type="button" class="ibc-button-secondary" data-ibc-prev><?php esc_html_e( 'Précédent', 'ibc-enrollment-manager' ); ?></button>
				<span data-ibc-page-indicator></span>
				<button type="button" class="ibc-button-secondary" data-ibc-next><?php esc_html_e( 'Suivant', 'ibc-enrollment-manager' ); ?></button>
			</div>
		</div>

		<div class="ibc-modal" data-ibc-login hidden>
			<div class="ibc-modal-card">
				<h3><?php esc_html_e( 'Connexion requise', 'ibc-enrollment-manager' ); ?></h3>
				<p><?php esc_html_e( 'Veuillez saisir le mot de passe administrateur pour accéder au tableau de bord.', 'ibc-enrollment-manager' ); ?></p>
				<div class="ibc-form-group">
					<label for="ibc_admin_password"><?php esc_html_e( 'Mot de passe', 'ibc-enrollment-manager' ); ?></label>
					<input type="password" id="ibc_admin_password" autocomplete="current-password">
				</div>
				<div class="ibc-modal-actions">
					<button type="button" class="ibc-button-primary" data-ibc-login-submit><?php esc_html_e( 'Connexion', 'ibc-enrollment-manager' ); ?></button>
				</div>
				<div class="ibc-modal-feedback" hidden></div>
			</div>
		</div>

		<div class="ibc-modal" data-ibc-edit hidden>
			<div class="ibc-modal-card">
				<h3><?php esc_html_e( 'Modifier l’inscription', 'ibc-enrollment-manager' ); ?></h3>
				<form data-ibc-edit-form>
					<div class="ibc-form-columns">
						<div class="ibc-form-group">
							<label for="ibc_edit_prenom"><?php esc_html_e( 'Prénom', 'ibc-enrollment-manager' ); ?></label>
							<input type="text" id="ibc_edit_prenom" name="prenom">
						</div>
						<div class="ibc-form-group">
							<label for="ibc_edit_nom"><?php esc_html_e( 'Nom', 'ibc-enrollment-manager' ); ?></label>
							<input type="text" id="ibc_edit_nom" name="nom">
						</div>
					</div>
					<div class="ibc-form-group">
						<label for="ibc_edit_email"><?php esc_html_e( 'Email', 'ibc-enrollment-manager' ); ?></label>
						<input type="email" id="ibc_edit_email" name="email">
					</div>
					<div class="ibc-form-group">
						<label for="ibc_edit_phone"><?php esc_html_e( 'Téléphone', 'ibc-enrollment-manager' ); ?></label>
						<input type="text" id="ibc_edit_phone" name="phone">
					</div>
					<div class="ibc-form-group">
						<label for="ibc_edit_status"><?php esc_html_e( 'Statut', 'ibc-enrollment-manager' ); ?></label>
						<select id="ibc_edit_status" name="statut">
							<option value="Confirme"><?php esc_html_e( 'Confirmée', 'ibc-enrollment-manager' ); ?></option>
							<option value="Annule"><?php esc_html_e( 'Annulée', 'ibc-enrollment-manager' ); ?></option>
						</select>
					</div>
					<div class="ibc-form-group">
						<label for="ibc_edit_notes"><?php esc_html_e( 'Notes', 'ibc-enrollment-manager' ); ?></label>
						<textarea id="ibc_edit_notes" name="message" rows="3"></textarea>
					</div>
					<div class="ibc-modal-actions">
						<button type="button" class="ibc-button-secondary" data-ibc-edit-cancel><?php esc_html_e( 'Annuler', 'ibc-enrollment-manager' ); ?></button>
						<button type="submit" class="ibc-button-primary"><?php esc_html_e( 'Enregistrer', 'ibc-enrollment-manager' ); ?></button>
					</div>
					<input type="hidden" name="id" value="">
				</form>
				<div class="ibc-edit-docs" data-ibc-edit-docs hidden>
					<h4><?php esc_html_e( 'Pièce d’identité', 'ibc-enrollment-manager' ); ?></h4>
					<div class="ibc-edit-docs__grid">
						<a href="#" class="ibc-doc-chip" data-ibc-doc="recto" target="_blank" rel="noopener noreferrer" hidden><?php esc_html_e( 'Voir le recto', 'ibc-enrollment-manager' ); ?></a>
						<a href="#" class="ibc-doc-chip" data-ibc-doc="verso" target="_blank" rel="noopener noreferrer" hidden><?php esc_html_e( 'Voir le verso', 'ibc-enrollment-manager' ); ?></a>
						<span class="ibc-doc-chip is-muted" data-ibc-doc-empty hidden><?php esc_html_e( 'Aucun document disponible', 'ibc-enrollment-manager' ); ?></span>
					</div>
				</div>
				<div class="ibc-edit-extra" data-ibc-edit-extra hidden></div>
				<div class="ibc-modal-feedback" hidden></div>
			</div>
		</div>
		<?php

		return (string) ob_get_clean();
	}
}
