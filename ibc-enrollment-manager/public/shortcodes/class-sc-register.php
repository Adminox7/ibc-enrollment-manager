<?php
/**
 * Registration shortcode.
 *
 * @package IBC\EnrollmentManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class IBC_SC_Register
 */
class IBC_SC_Register {

	/**
	 * Database layer.
	 *
	 * @var IBC_DB
	 */
	private $db;

	/**
	 * Seat lock handler.
	 *
	 * @var IBC_SeatLock
	 */
	private $seat_lock;

	/**
	 * Email handler.
	 *
	 * @var IBC_Emails
	 */
	private $emails;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->db        = IBC_DB::get_instance();
		$this->seat_lock = IBC_SeatLock::get_instance();
		$this->emails    = IBC_Emails::get_instance();

		add_action( 'wp_ajax_ibc_submit_registration', array( $this, 'handle_submission' ) );
		add_action( 'wp_ajax_nopriv_ibc_submit_registration', array( $this, 'handle_submission' ) );
	}

	/**
	 * Register shortcode.
	 *
	 * @return void
	 */
	public function register(): void {
		add_shortcode( 'ibc_register', array( $this, 'render' ) );
	}

	/**
	 * Render shortcode.
	 *
	 * @param array $atts Attributes.
	 *
	 * @return string
	 */
	public function render( array $atts ): string {
		$atts = shortcode_atts(
			array(
				'session' => 0,
			),
			$atts,
			'ibc_register'
		);

		$session_id = (int) $atts['session'];
		if ( empty( $session_id ) && isset( $_GET['session_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$session_id = (int) $_GET['session_id']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		if ( empty( $session_id ) ) {
			return '<div class="ibc-register-form"><p>' . esc_html__( 'Veuillez sélectionner une session.', 'ibc-enrollment' ) . '</p></div>';
		}

		$session = $this->db->get_session( $session_id );

		if ( ! $session || 'published' !== $session['status'] ) {
			return '<div class="ibc-register-form"><p>' . esc_html__( 'Cette session n’est pas disponible.', 'ibc-enrollment' ) . '</p></div>';
		}

		wp_enqueue_style( 'ibc-public' );
		wp_enqueue_style( 'intl-tel-input' );
		wp_enqueue_script( 'intl-tel-input' );
		wp_enqueue_script( 'ibc-public' );

		wp_localize_script(
			'ibc-public',
			'ibcRegister',
			array(
				'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
				'nonce'      => wp_create_nonce( 'ibc_register' ),
				'sessionId'  => $session_id,
				'recaptcha'  => ibc_get_setting( 'recaptcha_site_key' ),
				'messages'   => array(
					'success' => esc_html__( 'Merci ! Votre inscription est enregistrée.', 'ibc-enrollment' ),
					'error'   => esc_html__( 'Une erreur est survenue. Merci de réessayer.', 'ibc-enrollment' ),
				),
			)
		);

		ob_start();
		?>
		<div class="ibc-register-form" id="ibc-register">
			<h3><?php echo esc_html( sprintf( __( 'Inscription - %s', 'ibc-enrollment' ), $session['title'] ) ); ?></h3>
			<form id="ibc-registration-form">
				<div class="ibc-form-group">
					<label for="ibc_full_name"><?php esc_html_e( 'Nom complet', 'ibc-enrollment' ); ?> *</label>
					<input type="text" id="ibc_full_name" name="full_name" required/>
				</div>
				<div class="ibc-form-group">
					<label for="ibc_email"><?php esc_html_e( 'Email', 'ibc-enrollment' ); ?> *</label>
					<input type="email" id="ibc_email" name="email" required/>
				</div>
				<div class="ibc-form-group">
					<label for="ibc_phone"><?php esc_html_e( 'Téléphone', 'ibc-enrollment' ); ?> *</label>
					<input type="tel" id="ibc_phone" name="phone" required/>
				</div>
				<div class="ibc-form-group">
					<label for="ibc_cin"><?php esc_html_e( 'CIN / Passeport', 'ibc-enrollment' ); ?> *</label>
					<input type="text" id="ibc_cin" name="cin" required/>
				</div>
				<input type="hidden" name="session_id" value="<?php echo esc_attr( $session_id ); ?>"/>
				<input type="hidden" name="action" value="ibc_submit_registration"/>
				<?php wp_nonce_field( 'ibc_register', 'ibc_nonce' ); ?>
				<div class="ibc-form-notice" role="alert" hidden></div>
				<button type="submit" class="ibc-button"><?php esc_html_e( 'Valider mon inscription', 'ibc-enrollment' ); ?></button>
			</form>
		</div>
		<?php

		return (string) ob_get_clean();
	}

	/**
	 * Handle AJAX registration.
	 *
	 * @return void
	 */
	public function handle_submission(): void {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'ibc_register' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Jeton invalide.', 'ibc-enrollment' ) ), 400 );
		}

		$session_id = isset( $_POST['session_id'] ) ? (int) $_POST['session_id'] : 0;

		if ( empty( $session_id ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Session manquante.', 'ibc-enrollment' ) ), 400 );
		}

		$session = $this->db->get_session( $session_id );

		if ( ! $session || 'published' !== $session['status'] ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Cette session n’est pas disponible.', 'ibc-enrollment' ) ), 400 );
		}

		$fields = array(
			'full_name' => sanitize_text_field( wp_unslash( $_POST['full_name'] ?? '' ) ),
			'email'     => sanitize_email( wp_unslash( $_POST['email'] ?? '' ) ),
			'phone'     => ibc_sanitize_phone( wp_unslash( $_POST['phone'] ?? '' ) ),
			'cin'       => sanitize_text_field( wp_unslash( $_POST['cin'] ?? '' ) ),
		);

		foreach ( $fields as $key => $value ) {
			if ( empty( $value ) ) {
				wp_send_json_error( array( 'message' => esc_html__( 'Tous les champs sont requis.', 'ibc-enrollment' ) ), 400 );
			}
		}

		if ( ! is_email( $fields['email'] ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Email invalide.', 'ibc-enrollment' ) ), 400 );
		}

		$token = sanitize_text_field( wp_unslash( $_POST['recaptcha_token'] ?? '' ) );
		if ( ! ibc_verify_recaptcha( $token ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'La vérification reCAPTCHA a échoué.', 'ibc-enrollment' ) ), 400 );
		}

		if ( ! $this->seat_lock->has_available_seat( $session ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Plus de places disponibles pour cette session.', 'ibc-enrollment' ) ), 400 );
		}

		$student_id = $this->db->upsert_student(
			array(
				'full_name' => $fields['full_name'],
				'email'     => $fields['email'],
				'phone'     => $fields['phone'],
				'cin'       => $fields['cin'],
			)
		);

		if ( ! $student_id ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Impossible d’enregistrer l’étudiant.', 'ibc-enrollment' ) ), 400 );
		}

		$lock_until = $this->seat_lock->get_lock_expiration();

		$existing = $this->db->get_registration_by_session_student( $session_id, $student_id );

		if ( $existing ) {
			if ( in_array( $existing['status'], array( 'confirmed', 'paid' ), true ) ) {
				wp_send_json_error( array( 'message' => esc_html__( 'Vous êtes déjà inscrit à cette session.', 'ibc-enrollment' ) ), 400 );
			}

			$this->db->update_registration(
				(int) $existing['id'],
				array(
					'status'          => 'pending',
					'amount'          => (float) $session['price'],
					'currency'        => $session['currency'],
					'seat_lock_until' => $lock_until,
				)
			);

			$registration_id = (int) $existing['id'];
		} else {
			$registration_id = $this->db->create_registration(
				array(
					'session_id'     => $session_id,
					'student_id'     => $student_id,
					'status'         => 'pending',
					'amount'         => (float) $session['price'],
					'currency'       => $session['currency'],
					'seat_lock_until'=> $lock_until,
				)
			);
		}

		$registration = $this->db->get_registration( $registration_id );
		$student      = $this->db->get_student( $student_id );

		$this->emails->send_registration_received( $student, $session, $registration );
		$session_date = ! empty( $session['start_at'] ) ? wp_date( 'd/m/Y H:i', strtotime( $session['start_at'] ) ) : '';
		ibc_send_whatsapp_template(
			$student['phone'],
			array(
				$student['full_name'],
				$session['title'],
				$session_date,
			)
		);

		wp_send_json_success(
			array(
				'redirect' => esc_url_raw( home_url( '/merci-inscription' ) ),
			)
		);
	}
}
