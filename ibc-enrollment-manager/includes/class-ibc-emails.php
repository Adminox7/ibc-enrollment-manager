<?php
/**
 * Email handler for IBC Enrollment Manager.
 *
 * @package IBC\EnrollmentManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class IBC_Emails
 */
class IBC_Emails {

	/**
	 * Singleton instance.
	 *
	 * @var IBC_Emails|null
	 */
	private static $instance = null;

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'phpmailer_init', array( $this, 'configure_phpmailer' ) );
		add_filter( 'wp_mail_from', array( $this, 'filter_mail_from' ) );
		add_filter( 'wp_mail_from_name', array( $this, 'filter_mail_from_name' ) );
	}

	/**
	 * Retrieve singleton.
	 *
	 * @return IBC_Emails
	 */
	public static function get_instance(): IBC_Emails {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Configure PHPMailer.
	 *
	 * @param PHPMailer $phpmailer PHPMailer instance.
	 *
	 * @return void
	 */
	public function configure_phpmailer( $phpmailer ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundInFunction
		$settings = ibc_get_settings();

		if ( empty( $settings['smtp_host'] ) ) {
			return;
		}

		$phpmailer->isSMTP();
		$phpmailer->Host       = $settings['smtp_host'];
		$phpmailer->Port       = (int) $settings['smtp_port'];
		$phpmailer->SMTPAuth   = true;
		$phpmailer->Username   = $settings['smtp_username'];
		$phpmailer->Password   = $settings['smtp_password'];
		$phpmailer->SMTPSecure = $settings['smtp_secure'] ?: 'tls';
		$phpmailer->CharSet    = 'UTF-8';
	}

	/**
	 * Filter from email.
	 *
	 * @param string $email Email.
	 *
	 * @return string
	 */
	public function filter_mail_from( string $email ): string {
		$settings = ibc_get_settings();

		if ( ! empty( $settings['email_from_address'] ) && is_email( $settings['email_from_address'] ) ) {
			return $settings['email_from_address'];
		}

		return $email;
	}

	/**
	 * Filter from name.
	 *
	 * @param string $name Name.
	 *
	 * @return string
	 */
	public function filter_mail_from_name( string $name ): string {
		$settings = ibc_get_settings();

		if ( ! empty( $settings['email_from_name'] ) ) {
			return $settings['email_from_name'];
		}

		return $name;
	}

	/**
	 * Send registration received email.
	 *
	 * @param array $student      Student data.
	 * @param array $session      Session data.
	 * @param array $registration Registration data.
	 *
	 * @return bool
	 */
	public function send_registration_received( array $student, array $session, array $registration ): bool {
		if ( empty( $student['email'] ) ) {
			return false;
		}

		$subject = sprintf(
			/* translators: %s: session title */
			__( 'Inscription reçue - %s', 'ibc-enrollment' ),
			$session['title']
		);

		$body  = sprintf( __( 'Bonjour %s,', 'ibc-enrollment' ), $student['full_name'] ) . "\n\n";
		$body .= __( 'Nous avons bien reçu votre demande d\'inscription.', 'ibc-enrollment' ) . "\n";
		$body .= __( 'Détails de la session :', 'ibc-enrollment' ) . "\n";
		$body .= '- ' . __( 'Titre', 'ibc-enrollment' ) . ' : ' . $session['title'] . "\n";
		$body .= '- ' . __( 'Date', 'ibc-enrollment' ) . ' : ' . ibc_format_datetime( $session['start_at'] ) . "\n";
		$body .= '- ' . __( 'Campus', 'ibc-enrollment' ) . ' : ' . $session['campus'] . "\n\n";
		$body .= __( 'Un conseiller vous contactera très prochainement pour finaliser votre inscription.', 'ibc-enrollment' ) . "\n\n";
		$body .= __( 'Merci de votre confiance,', 'ibc-enrollment' ) . "\n";
		$body .= 'IBC Morocco';

		return $this->send_mail( $student['email'], $subject, $body );
	}

	/**
	 * Send confirmation email.
	 *
	 * @param array $student Student data.
	 * @param array $session Session data.
	 *
	 * @return bool
	 */
	public function send_registration_confirmed( array $student, array $session ): bool {
		if ( empty( $student['email'] ) ) {
			return false;
		}

		$subject = sprintf(
			/* translators: %s: session title */
			__( 'Confirmation d\'inscription - %s', 'ibc-enrollment' ),
			$session['title']
		);

		$body  = sprintf( __( 'Bonjour %s,', 'ibc-enrollment' ), $student['full_name'] ) . "\n\n";
		$body .= __( 'Votre inscription est confirmée. Voici les détails :', 'ibc-enrollment' ) . "\n";
		$body .= '- ' . __( 'Titre', 'ibc-enrollment' ) . ' : ' . $session['title'] . "\n";
		$body .= '- ' . __( 'Date', 'ibc-enrollment' ) . ' : ' . ibc_format_datetime( $session['start_at'] ) . "\n";
		$body .= '- ' . __( 'Campus', 'ibc-enrollment' ) . ' : ' . $session['campus'] . "\n\n";
		$body .= __( 'Merci et à très bientôt.', 'ibc-enrollment' ) . "\n";
		$body .= 'IBC Morocco';

		return $this->send_mail( $student['email'], $subject, $body );
	}

	/**
	 * Send payment confirmation email.
	 *
	 * @param array $student Student data.
	 * @param array $session Session data.
	 * @param array $registration Registration data.
	 *
	 * @return bool
	 */
	public function send_payment_confirmed( array $student, array $session, array $registration ): bool {
		if ( empty( $student['email'] ) ) {
			return false;
		}

		$subject = sprintf(
			/* translators: %s: session title */
			__( 'Paiement confirmé - %s', 'ibc-enrollment' ),
			$session['title']
		);

		$body  = sprintf( __( 'Bonjour %s,', 'ibc-enrollment' ), $student['full_name'] ) . "\n\n";
		$body .= __( 'Nous confirmons la réception de votre paiement.', 'ibc-enrollment' ) . "\n";
		$body .= '- ' . __( 'Montant', 'ibc-enrollment' ) . ' : ' . ibc_format_currency( (float) $registration['amount'], $registration['currency'] ?? 'MAD' ) . "\n";
		$body .= '- ' . __( 'Référence', 'ibc-enrollment' ) . ' : ' . ( $registration['payment_ref'] ?? '' ) . "\n\n";
		$body .= __( 'Nous restons à votre disposition pour toute question.', 'ibc-enrollment' ) . "\n";
		$body .= 'IBC Morocco';

		return $this->send_mail( $student['email'], $subject, $body );
	}

	/**
	 * Send an email.
	 *
	 * @param string $to      Recipient.
	 * @param string $subject Subject.
	 * @param string $message Message.
	 *
	 * @return bool
	 */
	private function send_mail( string $to, string $subject, string $message ): bool {
		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );

		return wp_mail( $to, $subject, $message, $headers );
	}
}
