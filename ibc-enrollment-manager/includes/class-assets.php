<?php
/**
 * Centralized asset manager (front + admin).
 *
 * @package IBC\Enrollment
 */

declare( strict_types=1 );

namespace IBC\Enrollment\Support;

use IBC\Enrollment\Rest\RestController;
use function IBC\Enrollment\ibc_get_brand_colors_with_legacy;
use function IBC\Enrollment\ibc_get_brand_name;
use function IBC\Enrollment\ibc_has_shortcode;
use function IBC\Enrollment\ibc_get_option;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles CSS/JS loading and shared theme variables.
 */
class Assets {

	/**
	 * Shortcode tag for the public form.
	 */
	private const FORM_SHORTCODE = 'ibc_enrollment_form';

	/**
	 * Admin page slug where dashboard assets must load.
	 */
	public const DASHBOARD_PAGE_SLUG = 'ibc-enrollment-dashboard';

	/**
	 * Prints `:root` variables only once.
	 *
	 * @var bool
	 */
	private bool $printed_theme_vars = false;

	/**
	 * Registers inline theme variables on front + admin heads.
	 *
	 * @return void
	 */
	public function register_shared_theme_variables(): void {
		add_action( 'wp_head', [ $this, 'output_theme_variables' ], 1 );
		add_action( 'admin_head', [ $this, 'output_theme_variables' ], 1 );
	}

	/**
	 * Echoes CSS custom properties derived from plugin settings.
	 *
	 * @return void
	 */
	public function output_theme_variables(): void {
		if ( $this->printed_theme_vars ) {
			return;
		}

		$this->printed_theme_vars = true;

		$palette = $this->palette();

		printf(
			'<style id="ibc-enrollment-theme-vars">:root{--ibc-primary:%1$s;--ibc-primary-dark:%2$s;--ibc-primary-light:%3$s;--ibc-text-dark:%4$s;--ibc-text-muted:%5$s;--ibc-success:%6$s;--ibc-success-bg:%7$s;--ibc-danger:%8$s;--ibc-danger-bg:%9$s;font-family:"Inter",-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;}</style>',
			esc_html( $palette['primary'] ),
			esc_html( $palette['primary_dark'] ),
			esc_html( $palette['primary_light'] ),
			esc_html( $palette['text_dark'] ),
			esc_html( $palette['text_muted'] ),
			esc_html( $palette['success'] ),
			esc_html( $palette['success_bg'] ),
			esc_html( $palette['danger'] ),
			esc_html( $palette['danger_bg'] )
		);
	}

	/**
	 * Enqueues frontend assets only when the shortcode is present.
	 *
	 * @return void
	 */
	public function enqueue_public(): void {
		if ( is_admin() ) {
			return;
		}

		if ( ! ibc_has_shortcode( self::FORM_SHORTCODE ) ) {
			return;
		}

		$palette = $this->palette();

		wp_enqueue_style(
			'ibc-enrollment-form',
			IBC_ENROLLMENT_PLUGIN_URL . 'public/assets/css/form.css',
			[],
			IBC_ENROLLMENT_VERSION
		);

		wp_enqueue_script(
			'ibc-enrollment-form',
			IBC_ENROLLMENT_PLUGIN_URL . 'public/assets/js/form.js',
			[],
			IBC_ENROLLMENT_VERSION,
			true
		);

		wp_localize_script(
			'ibc-enrollment-form',
			'IBCEnrollmentForm',
			[
				'restUrl'  => esc_url_raw( rest_url( RestController::NAMESPACE . '/' ) ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'brand'    => [
					'name'    => ibc_get_brand_name(),
					'palette' => $palette,
				],
				'messages' => [
					'success'       => __( 'Inscription réussie. Téléchargez votre reçu PDF et vérifiez vos e-mails.', 'ibc-enrollment' ),
					'duplicate'     => __( 'Cet e-mail ou numéro est déjà inscrit.', 'ibc-enrollment' ),
					'capacity'      => __( 'Le quota est atteint pour cette session.', 'ibc-enrollment' ),
					'validation'    => __( 'Merci de vérifier les champs en surbrillance.', 'ibc-enrollment' ),
					'uploadError'   => __( 'Téléversement impossible. Utilisez un fichier JPG, PNG ou PDF.', 'ibc-enrollment' ),
					'serverError'   => __( 'Erreur serveur. Réessayez plus tard.', 'ibc-enrollment' ),
					'networkError'  => __( 'Connexion impossible. Vérifiez votre réseau.', 'ibc-enrollment' ),
				],
				'limits'   => [
					'capacity' => (int) ibc_get_option( 'ibc_capacity_limit', 1466 ),
					'price'    => (int) ibc_get_option( 'ibc_price_prep', 1000 ),
				],
			]
		);
	}

	/**
	 * Enqueues dashboard assets on our admin screen.
	 *
	 * @param string $hook Current screen hook.
	 *
	 * @return void
	 */
	public function enqueue_admin( string $hook ): void {
		if ( false === strpos( $hook, self::DASHBOARD_PAGE_SLUG ) ) {
			return;
		}

		wp_enqueue_style(
			'ibc-enrollment-admin',
			IBC_ENROLLMENT_PLUGIN_URL . 'admin/assets/css/admin.css',
			[],
			IBC_ENROLLMENT_VERSION
		);

		wp_enqueue_script(
			'ibc-enrollment-admin',
			IBC_ENROLLMENT_PLUGIN_URL . 'admin/assets/js/admin.js',
			[],
			IBC_ENROLLMENT_VERSION,
			true
		);

		wp_localize_script(
			'ibc-enrollment-admin',
			'IBCEnrollmentDashboard',
			[
				'restUrl' => esc_url_raw( rest_url( RestController::NAMESPACE . '/' ) ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
				'texts'   => [
					'loginTitle'    => __( 'Accès réservé — IBC Admin', 'ibc-enrollment' ),
					'loginError'    => __( 'Mot de passe incorrect.', 'ibc-enrollment' ),
					'ready'         => __( 'Prêt', 'ibc-enrollment' ),
					'loading'       => __( 'Chargement…', 'ibc-enrollment' ),
					'refreshed'     => __( 'Données mises à jour.', 'ibc-enrollment' ),
					'empty'         => __( 'Aucune inscription trouvée.', 'ibc-enrollment' ),
					'save'          => __( 'Sauver', 'ibc-enrollment' ),
					'saveSuccess'   => __( 'Inscription mise à jour.', 'ibc-enrollment' ),
					'saveError'     => __( 'Impossible d’enregistrer les modifications.', 'ibc-enrollment' ),
					'deleteConfirm' => __( 'Confirmer l’annulation de cette inscription ?', 'ibc-enrollment' ),
					'deleteDone'    => __( 'Inscription annulée.', 'ibc-enrollment' ),
					'deleteError'   => __( 'Impossible de supprimer l’inscription.', 'ibc-enrollment' ),
					'exported'      => __( 'Export CSV généré.', 'ibc-enrollment' ),
					'docRecto'      => __( 'Recto', 'ibc-enrollment' ),
					'docVerso'      => __( 'Verso', 'ibc-enrollment' ),
					'docMissing'    => __( 'Aucun document', 'ibc-enrollment' ),
					'statusConfirm' => __( 'Confirmé', 'ibc-enrollment' ),
					'statusCancel'  => __( 'Annulé', 'ibc-enrollment' ),
				],
				'brand'   => $this->palette(),
			]
		);
	}

	/**
	 * Returns merged palette used for inline vars + JS payloads.
	 *
	 * @return array<string,string>
	 */
	private function palette(): array {
		$colors = ibc_get_brand_colors_with_legacy();

		return [
			'primary'       => $colors['primary'] ?? '#4CB4B4',
			'primary_dark'  => $colors['primary_dark'] ?? '#3A9191',
			'primary_light' => $colors['primary_light'] ?? '#E0F5F5',
			'text_dark'     => $colors['text_dark'] ?? '#1f2937',
			'text_muted'    => $colors['text_muted'] ?? '#6b7280',
			'success'       => $colors['success'] ?? '#10b981',
			'success_bg'    => $colors['success_bg'] ?? '#d1fae5',
			'danger'        => $colors['danger'] ?? '#ef4444',
			'danger_bg'     => $colors['danger_bg'] ?? '#fee2e2',
		];
	}
}
