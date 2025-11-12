<?php
/**
 * Shortcodes renderer.
 *
 * @package IBC\EnrollmentManager
 */

namespace IBC;

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
	 * Constructor.
	 *
	 * @param Registrations $registrations Registrations service.
	 * @param Auth          $auth          Auth service.
	 */
	public function __construct( Registrations $registrations, Auth $auth ) {
		$this->registrations = $registrations;
		$this->auth          = $auth;
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
		$atts = shortcode_atts(
			array(
				'title' => __( 'Préinscription IBC', 'ibc-enrollment-manager' ),
			),
			$atts
		);

		ob_start();
		?>
		<div class="ibc-form-wrapper" data-ibc-form>
			<div class="ibc-form-card">
				<h2 class="ibc-form-title"><?php echo esc_html( $atts['title'] ); ?></h2>
				<p class="ibc-form-subtitle"><?php esc_html_e( 'Complétez le formulaire pour réserver votre place.', 'ibc-enrollment-manager' ); ?></p>

				<form class="ibc-form" enctype="multipart/form-data">
					<div class="ibc-form-columns">
						<div class="ibc-form-group">
							<label for="ibc_prenom"><?php esc_html_e( 'Prénom', 'ibc-enrollment-manager' ); ?> *</label>
							<input type="text" id="ibc_prenom" name="prenom" required>
						</div>
						<div class="ibc-form-group">
							<label for="ibc_nom"><?php esc_html_e( 'Nom', 'ibc-enrollment-manager' ); ?> *</label>
							<input type="text" id="ibc_nom" name="nom" required>
						</div>
					</div>

					<div class="ibc-form-columns">
						<div class="ibc-form-group">
							<label for="ibc_email"><?php esc_html_e( 'Email', 'ibc-enrollment-manager' ); ?> *</label>
							<input type="email" id="ibc_email" name="email" required>
						</div>
						<div class="ibc-form-group">
							<label for="ibc_phone"><?php esc_html_e( 'Téléphone', 'ibc-enrollment-manager' ); ?> *</label>
							<input type="tel" id="ibc_phone" name="phone" required>
						</div>
					</div>

					<div class="ibc-form-columns">
						<div class="ibc-form-group">
							<label for="ibc_birth_date"><?php esc_html_e( 'Date de naissance', 'ibc-enrollment-manager' ); ?></label>
							<input type="date" id="ibc_birth_date" name="birth_date">
						</div>
						<div class="ibc-form-group">
							<label for="ibc_birth_place"><?php esc_html_e( 'Lieu de naissance', 'ibc-enrollment-manager' ); ?></label>
							<input type="text" id="ibc_birth_place" name="birth_place">
						</div>
					</div>

					<div class="ibc-form-group">
						<label for="ibc_level"><?php esc_html_e( 'Niveau souhaité', 'ibc-enrollment-manager' ); ?> *</label>
						<select id="ibc_level" name="niveau" required>
							<option value=""><?php esc_html_e( 'Choisir…', 'ibc-enrollment-manager' ); ?></option>
							<option value="A1">A1</option>
							<option value="A2">A2</option>
							<option value="B1">B1</option>
							<option value="B2">B2</option>
							<option value="C1">C1</option>
							<option value="C2">C2</option>
						</select>
					</div>

					<div class="ibc-form-group">
						<label for="ibc_message"><?php esc_html_e( 'Message', 'ibc-enrollment-manager' ); ?></label>
						<textarea id="ibc_message" name="message" rows="4" placeholder="<?php esc_attr_e( 'Précisions éventuelles…', 'ibc-enrollment-manager' ); ?>"></textarea>
					</div>

					<div class="ibc-form-columns">
						<div class="ibc-form-group">
							<label for="ibc_cin_recto"><?php esc_html_e( 'CIN / Passeport (recto)', 'ibc-enrollment-manager' ); ?></label>
							<input type="file" id="ibc_cin_recto" name="cin_recto" accept=".jpg,.jpeg,.png,.pdf">
						</div>
						<div class="ibc-form-group">
							<label for="ibc_cin_verso"><?php esc_html_e( 'CIN / Passeport (verso)', 'ibc-enrollment-manager' ); ?></label>
							<input type="file" id="ibc_cin_verso" name="cin_verso" accept=".jpg,.jpeg,.png,.pdf">
						</div>
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
							<th><?php esc_html_e( 'Niveau', 'ibc-enrollment-manager' ); ?></th>
							<th><?php esc_html_e( 'Statut', 'ibc-enrollment-manager' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'ibc-enrollment-manager' ); ?></th>
						</tr>
					</thead>
					<tbody data-ibc-table-body>
						<tr class="ibc-table-empty">
							<td colspan="6"><?php esc_html_e( 'Chargement en cours…', 'ibc-enrollment-manager' ); ?></td>
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
				<div class="ibc-modal-feedback" hidden></div>
			</div>
		</div>
		<?php

		return (string) ob_get_clean();
	}
}
