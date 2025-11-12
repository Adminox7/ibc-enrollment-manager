<?php
/**
 * Assets management.
 *
 * @package IBC\EnrollmentManager
 */

namespace IBC;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Assets
 */
class Assets {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_public' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin' ) );
	}

	/**
	 * Enqueue public assets when needed.
	 *
	 * @return void
	 */
	public function maybe_enqueue_public(): void {
		if ( is_admin() ) {
			return;
		}

		global $post;

		if ( ibc_has_shortcode( 'ibc_register', $post ) ) {
			wp_enqueue_style(
				'ibc-form',
				IBC_ENROLLMENT_URL . 'public/assets/css/form.css',
				array(),
				IBC_ENROLLMENT_VERSION
			);

			wp_enqueue_script(
				'ibc-form',
				IBC_ENROLLMENT_URL . 'public/assets/js/form.js',
				array( 'jquery' ),
				IBC_ENROLLMENT_VERSION,
				true
			);

			wp_localize_script(
				'ibc-form',
				'IBCForm',
				array(
					'restUrl'  => esc_url_raw( rtrim( rest_url( REST::ROUTE_NAMESPACE ), '/' ) ),
					'messages' => array(
						'success'   => esc_html__( 'Merci ! Votre demande a été reçue.', 'ibc-enrollment-manager' ),
						'duplicate' => esc_html__( 'Une inscription existe déjà pour ces coordonnées.', 'ibc-enrollment-manager' ),
						'capacity'  => esc_html__( 'La capacité maximale est atteinte.', 'ibc-enrollment-manager' ),
						'error'     => esc_html__( 'Une erreur est survenue. Merci de réessayer.', 'ibc-enrollment-manager' ),
					),
				)
			);
		}

		if ( ibc_has_shortcode( 'ibc_admin_dashboard', $post ) ) {
			wp_enqueue_style(
				'ibc-admin-dashboard',
				IBC_ENROLLMENT_URL . 'admin/assets/css/admin.css',
				array(),
				IBC_ENROLLMENT_VERSION
			);

			wp_enqueue_script(
				'ibc-admin-dashboard',
				IBC_ENROLLMENT_URL . 'admin/assets/js/admin.js',
				array( 'jquery' ),
				IBC_ENROLLMENT_VERSION,
				true
			);

			wp_localize_script(
				'ibc-admin-dashboard',
				'IBCDashboard',
				array(
					'restUrl' => esc_url_raw( rtrim( rest_url( REST::ROUTE_NAMESPACE ), '/' ) ),
					'texts'   => array(
						'loginTitle'   => esc_html__( 'Accès sécurisé', 'ibc-enrollment-manager' ),
						'loginError'   => esc_html__( 'Mot de passe incorrect.', 'ibc-enrollment-manager' ),
						'saveSuccess'  => esc_html__( 'Inscription mise à jour.', 'ibc-enrollment-manager' ),
						'deleteConfirm'=> esc_html__( 'Confirmer l’annulation de cette inscription ?', 'ibc-enrollment-manager' ),
						'deleteDone'   => esc_html__( 'Inscription annulée.', 'ibc-enrollment-manager' ),
						'page'         => esc_html__( 'Page', 'ibc-enrollment-manager' ),
						'edit'         => esc_html__( 'Modifier', 'ibc-enrollment-manager' ),
						'delete'       => esc_html__( 'Annuler', 'ibc-enrollment-manager' ),
						'empty'        => esc_html__( 'Aucune inscription trouvée.', 'ibc-enrollment-manager' ),
					),
				)
			);
		}
	}

	/**
	 * Enqueue admin assets for settings page.
	 *
	 * @param string $hook Hook suffix.
	 *
	 * @return void
	 */
	public function enqueue_admin( string $hook ): void {
		if ( 'settings_page_ibc-enrollment-settings' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'ibc-admin-settings',
			IBC_ENROLLMENT_URL . 'admin/assets/css/admin.css',
			array(),
			IBC_ENROLLMENT_VERSION
		);
	}
}
