<?php
/**
 * Assets management.
 *
 * @package IBC\EnrollmentManager
 */

namespace IBC;

use IBC\FormBuilder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Assets
 */
class Assets {

	/**
	 * Form builder.
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
			$schema = $this->form_builder->get_public_schema();
			$colors = ibc_get_brand_colors_with_legacy();

			wp_enqueue_style(
				'ibc-form',
				IBC_ENROLLMENT_URL . 'public/assets/css/form.css',
				array(),
				IBC_ENROLLMENT_VERSION
			);

			wp_enqueue_script(
				'ibc-form',
				IBC_ENROLLMENT_URL . 'public/assets/js/form.js',
				array(),
				IBC_ENROLLMENT_VERSION,
				true
			);

			wp_localize_script(
				'ibc-form',
				'IBCForm',
				array(
					'restUrl'  => esc_url_raw( rtrim( rest_url( REST::ROUTE_NAMESPACE ), '/' ) ),
					'fields'   => $schema,
					'colors'   => $colors,
					'messages' => array(
						'success'   => esc_html__( 'Merci ! Votre demande a été reçue.', 'ibc-enrollment-manager' ),
						'duplicate' => esc_html__( 'Une inscription existe déjà pour ces coordonnées.', 'ibc-enrollment-manager' ),
						'capacity'  => esc_html__( 'La capacité maximale est atteinte.', 'ibc-enrollment-manager' ),
						'error'     => esc_html__( 'Une erreur est survenue. Merci de réessayer.', 'ibc-enrollment-manager' ),
						'nonJson'   => esc_html__( 'Réponse invalide du serveur.', 'ibc-enrollment-manager' ),
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
						'nonJson'      => esc_html__( 'Réponse invalide du serveur.', 'ibc-enrollment-manager' ),
						'extraTitle'   => esc_html__( 'Informations complémentaires', 'ibc-enrollment-manager' ),
						'download'     => esc_html__( 'Télécharger', 'ibc-enrollment-manager' ),
						'docRecto'     => esc_html__( 'Recto', 'ibc-enrollment-manager' ),
						'docVerso'     => esc_html__( 'Verso', 'ibc-enrollment-manager' ),
						'docMissing'   => esc_html__( 'Aucun fichier', 'ibc-enrollment-manager' ),
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
		$allowed_hooks = array(
			'toplevel_page_ibc-enrollment-settings',
			'ibc-enrollment-settings_page_ibc-enrollment-settings',
		);

		if ( ! in_array( $hook, $allowed_hooks, true ) ) {
			return;
		}

		wp_enqueue_style(
			'ibc-admin-settings',
			IBC_ENROLLMENT_URL . 'admin/assets/css/admin.css',
			array(),
			IBC_ENROLLMENT_VERSION
		);

		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'capacity'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( 'formbuilder' === $tab ) {
			wp_enqueue_script(
				'ibc-form-builder',
				IBC_ENROLLMENT_URL . 'admin/assets/js/builder.js',
				array(),
				IBC_ENROLLMENT_VERSION,
				true
			);

			wp_localize_script(
				'ibc-form-builder',
				'IBCBuilder',
				array(
					'schema' => $this->form_builder->get_schema(),
					'types'  => $this->form_builder->get_field_types(),
					'colors' => ibc_get_brand_colors_with_legacy(),
					'i18n'   => array(
						'addField'        => __( 'Ajouter un champ', 'ibc-enrollment-manager' ),
						'deleteField'     => __( 'Supprimer', 'ibc-enrollment-manager' ),
						'duplicateField'  => __( 'Dupliquer', 'ibc-enrollment-manager' ),
						'fieldRequired'   => __( 'Champ obligatoire', 'ibc-enrollment-manager' ),
						'fieldOptional'   => __( 'Champ actif', 'ibc-enrollment-manager' ),
						'confirmDelete'   => __( 'Supprimer ce champ du formulaire ?', 'ibc-enrollment-manager' ),
						'widthFull'       => __( 'Largeur complète', 'ibc-enrollment-manager' ),
						'widthHalf'       => __( 'Demi-largeur', 'ibc-enrollment-manager' ),
						'choicesPlaceholder' => __( "Saisissez une option par ligne (valeur|Libellé).", 'ibc-enrollment-manager' ),
						'lockedField'     => __( 'Ce champ est protégé : vous pouvez modifier son libellé mais pas le supprimer.', 'ibc-enrollment-manager' ),
						'copy'            => __( 'Copie', 'ibc-enrollment-manager' ),
						'selectField'     => __( 'Sélectionnez un champ.', 'ibc-enrollment-manager' ),
						'label'           => __( 'Libellé', 'ibc-enrollment-manager' ),
						'placeholder'     => __( 'Placeholder', 'ibc-enrollment-manager' ),
						'type'            => __( 'Type', 'ibc-enrollment-manager' ),
						'width'           => __( 'Largeur', 'ibc-enrollment-manager' ),
						'helpText'        => __( 'Texte d’aide', 'ibc-enrollment-manager' ),
						'choices'         => __( 'Options', 'ibc-enrollment-manager' ),
						'fileFormats'     => __( 'Formats acceptés', 'ibc-enrollment-manager' ),
						'previewSubmit'   => __( 'Envoyer', 'ibc-enrollment-manager' ),
						'newFieldLabel'   => __( 'Nouveau champ', 'ibc-enrollment-manager' ),
						'selectPlaceholder' => __( 'Sélectionnez…', 'ibc-enrollment-manager' ),
						'nonJson'         => __( 'Réponse invalide du serveur.', 'ibc-enrollment-manager' ),
					),
				)
			);
		}
	}
}
