<?php
/**
 * Email utilities.
 *
 * @package IBC\EnrollmentManager
 */

namespace IBC;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Email
 */
class Email {

	/**
	 * Send confirmation email to registrant.
	 *
	 * @param string $to          Recipient email.
	 * @param array  $context     Contextual data (fullName, email, phone, level, ref, price, payDeadline).
	 * @param array  $attachments Optional attachments.
	 *
	 * @return bool
	 */
	public function send_confirmation( string $to, array $context, array $attachments = array() ): bool {
		if ( empty( $to ) || ! is_email( $to ) ) {
			return false;
		}

		$subject = \__( 'Préinscription reçue – IBC MOROCCO', 'ibc-enrollment-manager' );

		$headers   = array( 'Content-Type: text/html; charset=UTF-8' );
		$from_mail = (string) get_option( 'ibc_contact_email', '' );
		if ( is_email( $from_mail ) ) {
			$headers[] = 'From: IBC Morocco <' . $from_mail . '>';
		}

		$html = $this->build_email_html( $context );

		return wp_mail( $to, $subject, $html, $headers, $attachments );
	}

	/**
	 * Build HTML content for confirmation email.
	 *
	 * @param array $context Context array.
	 *
	 * @return string
	 */
	private function build_email_html( array $context ): string {
		$colors = get_option(
			'ibc_brand_colors',
			array(
				'primary'   => '#e94162',
				'secondary' => '#0f172a',
				'text'      => '#1f2937',
				'muted'     => '#f8fafc',
				'border'    => '#e2e8f0',
			)
		);

		$bank = array(
			'bankName'      => (string) get_option( 'ibc_brand_bankName', '' ),
			'accountHolder' => (string) get_option( 'ibc_brand_accountHolder', '' ),
			'rib'           => (string) get_option( 'ibc_brand_rib', '' ),
			'iban'          => (string) get_option( 'ibc_brand_iban', '' ),
			'bic'           => (string) get_option( 'ibc_brand_bic', '' ),
			'agency'        => (string) get_option( 'ibc_brand_agency', '' ),
			'paymentNote'   => (string) get_option( 'ibc_brand_paymentNote', '' ),
		);

		$contact = array(
			'address'  => (string) get_option( 'ibc_contact_address', '' ),
			'email'    => (string) get_option( 'ibc_contact_email', '' ),
			'phone'    => (string) get_option( 'ibc_contact_phone', '' ),
			'landline' => (string) get_option( 'ibc_contact_landline', '' ),
		);

		$deadline = ! empty( $context['payDeadline'] ) ? $context['payDeadline'] : \__( 'Sous 24 heures.', 'ibc-enrollment-manager' );

		$body  = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>IBC Morocco</title></head>';
		$body .= '<body style="margin:0;padding:0;font-family:Arial,sans-serif;background:' . esc_attr( $colors['muted'] ) . ';color:' . esc_attr( $colors['text'] ) . ';">';
		$body .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding:24px 0;"><tr><td align="center">';
		$body .= '<table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;border:1px solid ' . esc_attr( $colors['border'] ) . ';overflow:hidden;">';
		$body .= '<tr><td style="background:' . esc_attr( $colors['primary'] ) . ';color:#ffffff;padding:24px;text-align:center;font-size:20px;font-weight:bold;">IBC Enrollment Manager</td></tr>';
		$body .= '<tr><td style="padding:24px;line-height:1.6;font-size:15px;">';
		$body .= wp_kses_post(
			sprintf(
				/* translators: %s full name */
				\__( 'Bonjour %s,', 'ibc-enrollment-manager' ),
				esc_html( $context['fullName'] ?? '' )
			)
		);
		$body .= '<br>' . wp_kses_post( \__( 'Nous confirmons la réception de votre préinscription à la préparation IBC.', 'ibc-enrollment-manager' ) );
		$body .= '<br><br><strong>' . \esc_html__( 'Détails de votre dossier', 'ibc-enrollment-manager' ) . '</strong>';
		$body .= '<ul style="margin:16px 0;padding-left:20px;">';
		$body .= '<li><strong>' . \esc_html__( 'Référence', 'ibc-enrollment-manager' ) . ':</strong> ' . esc_html( $context['ref'] ?? '' ) . '</li>';
		$body .= '<li><strong>' . \esc_html__( 'Niveau', 'ibc-enrollment-manager' ) . ':</strong> ' . esc_html( $context['level'] ?? '' ) . '</li>';
		$body .= '<li><strong>' . \esc_html__( 'Email', 'ibc-enrollment-manager' ) . ':</strong> ' . esc_html( $context['email'] ?? '' ) . '</li>';
		$body .= '<li><strong>' . \esc_html__( 'Téléphone', 'ibc-enrollment-manager' ) . ':</strong> ' . esc_html( $context['phone'] ?? '' ) . '</li>';
		$body .= '</ul>';
		$body .= '<p style="margin:16px 0;"><strong>' . \esc_html__( 'Montant à régler', 'ibc-enrollment-manager' ) . ':</strong> ' . esc_html( $context['price'] ?? '' ) . '</p>';
		$body .= '<p style="margin:16px 0;"><strong>' . \esc_html__( 'Échéance de paiement', 'ibc-enrollment-manager' ) . ':</strong> ' . esc_html( $deadline ) . '</p>';
		$body .= '<div style="border:1px dashed ' . esc_attr( $colors['border'] ) . ';padding:16px;border-radius:8px;margin:24px 0;background:' . esc_attr( $colors['muted'] ) . ';">';
		$body .= '<strong>' . \esc_html__( 'Coordonnées bancaires', 'ibc-enrollment-manager' ) . '</strong>';
		$body .= '<ul style="margin:12px 0;padding-left:18px;">';
		if ( ! empty( $bank['bankName'] ) ) {
			$body .= '<li>' . \esc_html__( 'Banque', 'ibc-enrollment-manager' ) . ': ' . esc_html( $bank['bankName'] ) . '</li>';
		}
		if ( ! empty( $bank['accountHolder'] ) ) {
			$body .= '<li>' . \esc_html__( 'Titulaire', 'ibc-enrollment-manager' ) . ': ' . esc_html( $bank['accountHolder'] ) . '</li>';
		}
		if ( ! empty( $bank['rib'] ) ) {
			$body .= '<li>' . \esc_html__( 'RIB', 'ibc-enrollment-manager' ) . ': ' . esc_html( $bank['rib'] ) . '</li>';
		}
		if ( ! empty( $bank['iban'] ) ) {
			$body .= '<li>' . \esc_html__( 'IBAN', 'ibc-enrollment-manager' ) . ': ' . esc_html( $bank['iban'] ) . '</li>';
		}
		if ( ! empty( $bank['bic'] ) ) {
			$body .= '<li>' . \esc_html__( 'BIC/SWIFT', 'ibc-enrollment-manager' ) . ': ' . esc_html( $bank['bic'] ) . '</li>';
		}
		if ( ! empty( $bank['agency'] ) ) {
			$body .= '<li>' . \esc_html__( 'Agence', 'ibc-enrollment-manager' ) . ': ' . esc_html( $bank['agency'] ) . '</li>';
		}
		$body .= '</ul>';
		if ( ! empty( $bank['paymentNote'] ) ) {
			$body .= '<p style="margin:8px 0 0 0;">' . esc_html( $bank['paymentNote'] ) . '</p>';
		}
		$body .= '</div>';
		$body .= '<p style="margin:16px 0;">' . \esc_html__( 'Merci de préciser la référence dans l’objet de votre virement.', 'ibc-enrollment-manager' ) . '</p>';
		$body .= '<p style="margin:16px 0;">' . \esc_html__( 'Notre équipe reste à votre disposition pour toute question.', 'ibc-enrollment-manager' ) . '</p>';
		$body .= '<div style="margin:24px 0;padding:16px;border-top:1px solid ' . esc_attr( $colors['border'] ) . ';font-size:14px;color:' . esc_attr( $colors['secondary'] ) . ';">';
		if ( ! empty( $contact['address'] ) ) {
			$body .= '<div>' . esc_html( $contact['address'] ) . '</div>';
		}
		if ( ! empty( $contact['email'] ) ) {
			$body .= '<div>' . \esc_html__( 'Email', 'ibc-enrollment-manager' ) . ': ' . esc_html( $contact['email'] ) . '</div>';
		}
		if ( ! empty( $contact['phone'] ) ) {
			$body .= '<div>' . \esc_html__( 'Mobile', 'ibc-enrollment-manager' ) . ': ' . esc_html( $contact['phone'] ) . '</div>';
		}
		if ( ! empty( $contact['landline'] ) ) {
			$body .= '<div>' . \esc_html__( 'Fixe', 'ibc-enrollment-manager' ) . ': ' . esc_html( $contact['landline'] ) . '</div>';
		}
		$body .= '</div>';
		$body .= '<p style="margin:0;">' . \esc_html__( 'À très vite,', 'ibc-enrollment-manager' ) . '<br><strong>IBC Morocco</strong></p>';
		$body .= '</td></tr></table>';
		$body .= '</td></tr></table>';
		$body .= '</body></html>';

		return $body;
	}
}
