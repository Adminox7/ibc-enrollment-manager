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
			$headers[] = 'Reply-To: IBC Morocco <' . $from_mail . '>';
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
		$colors     = ibc_get_brand_colors_with_legacy();
		$primary    = $colors['primary'] ?? '#16a085';
		$secondary  = $colors['secondary'] ?? '#0f172a';
		$muted      = $colors['muted'] ?? '#f8fafc';
		$border     = $colors['border'] ?? '#e2e8f0';
		$brand_name = get_bloginfo( 'name' );

		$details = ibc_get_payment_details();
		$contact = array(
			'address'  => (string) get_option( 'ibc_contact_address', '' ),
			'email'    => (string) get_option( 'ibc_contact_email', '' ),
			'phone'    => (string) get_option( 'ibc_contact_phone', '' ),
			'landline' => (string) get_option( 'ibc_contact_landline', '' ),
		);

		$full_name = esc_html( $context['fullName'] ?? '' );
		$reference = esc_html( $context['ref'] ?? '' );
		$level     = esc_html( $context['level'] ?? '' );
		$email     = esc_html( $context['email'] ?? '' );
		$phone     = esc_html( $context['phone'] ?? '' );
		$price     = esc_html( $context['price'] ?? ( ibc_get_price_prep() . ' MAD' ) );
		$created   = esc_html( $context['createdAt'] ?? '' );
		$deadline  = esc_html( $context['payDeadline'] ?? __( 'Sous 24 heures', 'ibc-enrollment-manager' ) );

		$bank_rows = array(
			__( 'Banque', 'ibc-enrollment-manager' )                  => esc_html( $details['bank_name'] ?? '' ),
			__( 'Titulaire', 'ibc-enrollment-manager' )               => esc_html( $details['account_holder'] ?? '' ),
			__( 'RIB', 'ibc-enrollment-manager' )                     => esc_html( $details['rib'] ?? '' ),
			__( 'IBAN', 'ibc-enrollment-manager' )                    => esc_html( $details['iban'] ?? '' ),
			__( 'BIC / SWIFT', 'ibc-enrollment-manager' )             => esc_html( $details['bic'] ?? '' ),
			__( 'Agence', 'ibc-enrollment-manager' )                  => esc_html( $details['agency'] ?? '' ),
		);

		$body  = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>' . esc_html( $brand_name ) . '</title></head>';
		$body .= '<body style="margin:0;padding:0;background:' . esc_attr( $muted ) . ';font-family:\'Inter\',Arial,sans-serif;color:' . esc_attr( $secondary ) . ';line-height:1.6;">';
		$body .= '<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="margin:0;padding:32px 0;">';
		$body .= '<tr><td align="center">';
		$body .= '<table role="presentation" cellpadding="0" cellspacing="0" width="620" style="background:#ffffff;border-radius:20px;overflow:hidden;border:1px solid rgba(22,160,133,0.12);box-shadow:0 30px 80px -44px rgba(15,118,110,0.25);">';
		$body .= '<tr><td style="padding:36px 40px;background:linear-gradient(135deg,' . esc_attr( $primary ) . ', rgba(15,118,110,0.92));color:#ffffff;">';
		$body .= '<div style="font-size:13px;letter-spacing:0.12em;text-transform:uppercase;font-weight:600;opacity:0.9;">' . esc_html( $brand_name ) . '</div>';
		$body .= '<h1 style="margin:12px 0 6px;font-size:24px;font-weight:700;line-height:1.3;">' . esc_html__( 'Confirmation de préinscription à la préparation d’examen', 'ibc-enrollment-manager' ) . '</h1>';
		$body .= '<p style="margin:0;font-size:14px;max-width:480px;">' . esc_html__( 'Votre dossier est bien enregistré. Retrouvez ci-dessous le récapitulatif de votre préinscription ainsi que les prochaines étapes.', 'ibc-enrollment-manager' ) . '</p>';
		$body .= '</td></tr>';
		$body .= '<tr><td style="padding:34px 40px;">';
		$body .= '<p style="margin:0 0 16px;font-size:15px;">' . sprintf( esc_html__( 'Bonjour %s,', 'ibc-enrollment-manager' ), $full_name ) . '</p>';
		$body .= '<p style="margin:0 0 24px;font-size:14px;color:rgba(15,23,42,0.72);">' . esc_html__( 'Nous confirmons la réception de votre préinscription à la préparation IBC. Pour finaliser votre inscription, merci d’effectuer le paiement sous 24 heures avec la référence ci-dessous.', 'ibc-enrollment-manager' ) . '</p>';

		$body .= '<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="border-collapse:separate;border-spacing:0 8px;font-size:14px;">';
		$body .= '<tr><td colspan="2" style="padding:0 0 4px;font-size:12px;font-weight:600;letter-spacing:0.1em;text-transform:uppercase;color:' . esc_attr( $primary ) . ';">' . esc_html__( 'Récapitulatif', 'ibc-enrollment-manager' ) . '</td></tr>';
		$body .= '<tr><td style="padding:12px 16px;background:' . esc_attr( $muted ) . ';border-radius:12px 0 0 12px;width:50%;"><strong>' . esc_html__( 'Référence', 'ibc-enrollment-manager' ) . '</strong><br>' . $reference . '</td>';
		$body .= '<td style="padding:12px 16px;background:' . esc_attr( $muted ) . ';border-radius:0 12px 12px 0;width:50%;"><strong>' . esc_html__( 'Date de préinscription', 'ibc-enrollment-manager' ) . '</strong><br>' . $created . '</td></tr>';
		$body .= '<tr><td style="padding:12px 16px;background:' . esc_attr( $muted ) . ';border-radius:12px 0 0 12px;width:50%;"><strong>' . esc_html__( 'Échéance de paiement', 'ibc-enrollment-manager' ) . '</strong><br>' . $deadline . '</td>';
		$body .= '<td style="padding:12px 16px;background:' . esc_attr( $muted ) . ';border-radius:0 12px 12px 0;width:50%;"><strong>' . esc_html__( 'Frais de préparation', 'ibc-enrollment-manager' ) . '</strong><br>' . $price . '</td></tr>';
		$body .= '</table>';

		$body .= '<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="margin:28px 0 24px;border:1px solid ' . esc_attr( $border ) . ';border-radius:16px;overflow:hidden;">';
		$body .= '<tr><td style="background:rgba(22,160,133,0.1);padding:14px 20px;font-weight:600;text-transform:uppercase;font-size:12px;letter-spacing:0.1em;color:' . esc_attr( $secondary ) . ';">' . esc_html__( 'Informations personnelles', 'ibc-enrollment-manager' ) . '</td></tr>';
		$body .= '<tr><td style="padding:18px 20px;"><table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="font-size:14px;line-height:1.6;">';
		$body .= '<tr><td width="33%"><strong>' . esc_html__( 'Nom complet', 'ibc-enrollment-manager' ) . '</strong><br>' . $full_name . '</td>';
		$body .= '<td width="33%"><strong>' . esc_html__( 'Téléphone', 'ibc-enrollment-manager' ) . '</strong><br>' . $phone . '</td>';
		$body .= '<td width="34%"><strong>' . esc_html__( 'Email', 'ibc-enrollment-manager' ) . '</strong><br>' . $email . '</td></tr>';
		$body .= '<tr><td colspan="3" style="padding-top:12px;"><strong>' . esc_html__( 'Niveau souhaité', 'ibc-enrollment-manager' ) . '</strong><br>' . $level . '</td></tr>';
		$body .= '</table></td></tr></table>';

		$body .= '<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:24px;border:1px solid rgba(22,160,133,0.25);border-radius:16px;overflow:hidden;">';
		$body .= '<tr><td style="background:' . esc_attr( $primary ) . ';color:#ffffff;padding:14px 20px;font-weight:600;text-transform:uppercase;font-size:12px;letter-spacing:0.1em;">' . esc_html__( 'Coordonnées bancaires (paiement sous 24 h)', 'ibc-enrollment-manager' ) . '</td></tr>';
		$body .= '<tr><td style="padding:20px;">';
		$body .= '<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="font-size:14px;line-height:1.6;">';
		foreach ( $bank_rows as $label => $value ) {
			if ( ! $value ) {
				continue;
			}
			$body .= '<tr><td style="padding:4px 0;"><strong>' . esc_html( $label ) . ' :</strong> ' . $value . '</td></tr>';
		}
		if ( ! empty( $details['payment_note'] ) ) {
			$body .= '<tr><td style="padding:10px 0 0;font-style:italic;color:rgba(15,23,42,0.7);">' . esc_html( $details['payment_note'] ) . '</td></tr>';
		}
		$body .= '</table>';
		$body .= '<div style="margin-top:16px;padding:14px 16px;background:#ffffff;border-radius:12px;border:1px dashed rgba(22,160,133,0.45);font-weight:600;color:' . esc_attr( $secondary ) . ';font-size:13px;text-align:center;">' . sprintf( esc_html__( 'Référence à mentionner : %s', 'ibc-enrollment-manager' ), $reference ) . '</div>';
		$body .= '</td></tr></table>';

		$body .= '<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="border-radius:14px;border:1px solid rgba(239,68,68,0.18);background:rgba(239,68,68,0.08);margin-bottom:16px;">';
		$body .= '<tr><td style="padding:16px 20px;font-size:13px;color:#991b1b;font-weight:600;">' . esc_html__( 'Une fois votre inscription validée, elle est définitive et non remboursable.', 'ibc-enrollment-manager' ) . '</td></tr>';
		$body .= '</table>';

		if ( ! empty( $context['notes'] ) ) {
			$body .= '<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="margin:0 0 24px;border:1px solid ' . esc_attr( $border ) . ';border-radius:14px;">';
			$body .= '<tr><td style="background:rgba(15,118,110,0.08);padding:12px 18px;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:0.08em;color:' . esc_attr( $secondary ) . ';">' . esc_html__( 'Message du candidat', 'ibc-enrollment-manager' ) . '</td></tr>';
			$body .= '<tr><td style="padding:18px;font-size:14px;color:rgba(15,23,42,0.78);">' . nl2br( esc_html( $context['notes'] ) ) . '</td></tr>';
			$body .= '</table>';
		}

		if ( ! empty( $context['extra'] ) && is_array( $context['extra'] ) ) {
			$body .= '<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="margin:0 0 24px;border:1px solid rgba(22,160,133,0.14);border-radius:14px;">';
			$body .= '<tr><td style="background:rgba(22,160,133,0.12);padding:12px 18px;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:0.08em;color:' . esc_attr( $secondary ) . ';">' . esc_html__( 'Informations complémentaires', 'ibc-enrollment-manager' ) . '</td></tr>';
			$body .= '<tr><td style="padding:18px;"><table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="font-size:13px;">';
			foreach ( $context['extra'] as $entry ) {
				if ( empty( $entry['value'] ) ) {
					continue;
				}
				$label = esc_html( $entry['label'] ?? $entry['id'] ?? '' );
				$value = $entry['value'];
				if ( ( $entry['type'] ?? '' ) === 'file' && filter_var( $value, FILTER_VALIDATE_URL ) ) {
					$display = '<a href="' . esc_url( $value ) . '" style="color:' . esc_attr( $primary ) . ';text-decoration:none;" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Télécharger', 'ibc-enrollment-manager' ) . '</a>';
				} else {
					$display = esc_html( (string) ( $entry['display'] ?? $value ) );
				}
				$body .= '<tr><td style="padding:4px 0;"><strong>' . $label . ' :</strong> ' . $display . '</td></tr>';
			}
			$body .= '</table></td></tr></table>';
		}

		$body .= '<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="border-radius:14px;border:1px solid rgba(251,191,36,0.26);background:rgba(251,191,36,0.12);margin-bottom:28px;">';
		$body .= '<tr><td style="padding:16px 20px;font-size:13px;color:#92400e;font-weight:600;">' . esc_html__( 'Le règlement doit être effectué dans les 24 heures suivant la réception de cet e-mail. Passé ce délai, la préinscription sera automatiquement annulée.', 'ibc-enrollment-manager' ) . '</td></tr>';
		$body .= '</table>';

		if ( ! empty( $context['receiptUrl'] ) ) {
			$body .= '<p style="margin:0 0 16px;font-size:13px;color:rgba(15,23,42,0.7);">' . esc_html__( 'Le reçu de préinscription est disponible en pièce jointe et téléchargeable à tout moment via votre espace.', 'ibc-enrollment-manager' ) . '</p>';
		}

		$body .= '<p style="margin:0 0 16px;font-size:14px;">' . esc_html__( 'Nous restons à votre disposition pour toute question.', 'ibc-enrollment-manager' ) . '</p>';
		$body .= '<p style="margin:0 0 24px;font-size:14px;">' . esc_html__( 'À très vite,', 'ibc-enrollment-manager' ) . '<br><strong>' . esc_html( $brand_name ) . '</strong></p>';

		$body .= '<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="font-size:12px;color:rgba(15,23,42,0.65);border-top:1px solid ' . esc_attr( $border ) . ';padding-top:18px;">';
		if ( ! empty( $contact['address'] ) ) {
			$body .= '<tr><td style="padding:2px 0;">' . esc_html( $contact['address'] ) . '</td></tr>';
		}
		if ( ! empty( $contact['email'] ) ) {
			$body .= '<tr><td style="padding:2px 0;">' . esc_html__( 'Email :', 'ibc-enrollment-manager' ) . ' ' . esc_html( $contact['email'] ) . '</td></tr>';
		}
		if ( ! empty( $contact['phone'] ) ) {
			$body .= '<tr><td style="padding:2px 0;">' . esc_html__( 'Mobile :', 'ibc-enrollment-manager' ) . ' ' . esc_html( $contact['phone'] ) . '</td></tr>';
		}
		if ( ! empty( $contact['landline'] ) ) {
			$body .= '<tr><td style="padding:2px 0;">' . esc_html__( 'Fixe :', 'ibc-enrollment-manager' ) . ' ' . esc_html( $contact['landline'] ) . '</td></tr>';
		}
		$body .= '</table>';

		$body .= '</td></tr>';
		$body .= '</table>';
		$body .= '</td></tr></table>';
		$body .= '</body></html>';

		return $body;
	}
}
