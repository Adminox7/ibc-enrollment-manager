<?php
/**
 * Email utilities.
 *
 * @package IBC\Enrollment
 */

namespace IBC\Enrollment;

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
	 * @param array  $registration Registration data.
	 * @param string $pdf_path     Absolute PDF path.
	 *
	 * @return bool
	 */
	public function send_confirmation( array $registration, string $pdf_path ): bool {
		$to = isset( $registration['email'] ) ? ibc_normalize_email( (string) $registration['email'] ) : '';

		if ( empty( $to ) || ! is_email( $to ) ) {
			return false;
		}

		$brand_name = ibc_get_brand_name();
		$subject    = sprintf( \__( 'Préinscription reçue – %s', 'ibc-enrollment-manager' ), $brand_name );

		$headers   = array( 'Content-Type: text/html; charset=UTF-8' );
		$from_mail = (string) get_option( 'ibc_contact_email', '' );
		if ( is_email( $from_mail ) ) {
			$headers[] = sprintf( 'From: %s <%s>', $brand_name, $from_mail );
			$headers[] = sprintf( 'Reply-To: %s <%s>', $brand_name, $from_mail );
		}

		$context = $this->prepare_email_context( $registration, $brand_name, $pdf_path );
		$html    = $this->build_email_html( $context );

		$attachments = array();
		if ( $pdf_path && file_exists( $pdf_path ) ) {
			$attachments[] = $pdf_path;
		}

		return wp_mail( $to, $subject, $html, $headers, $attachments );
	}

	/**
	 * Prepare context for email rendering.
	 *
	 * @param array  $registration Registration data.
	 * @param string $brand_name   Brand name.
	 * @param string $pdf_path     PDF path.
	 *
	 * @return array<string,mixed>
	 */
	private function prepare_email_context( array $registration, string $brand_name, string $pdf_path ): array {
		$context = isset( $registration['context'] ) && is_array( $registration['context'] )
			? $registration['context']
			: array();

		$email       = ibc_normalize_email( (string) ( $registration['email'] ?? $context['email'] ?? '' ) );
		$full_name   = trim( (string) ( $context['fullName'] ?? ( ( $registration['prenom'] ?? '' ) . ' ' . ( $registration['nom'] ?? '' ) ) ) );
		$reference   = (string) ( $context['reference'] ?? $context['ref'] ?? $registration['reference'] ?? $registration['ref'] ?? '' );
		$telephone   = (string) ( $context['telephone'] ?? $context['phone'] ?? $registration['telephone'] ?? '' );
		$level       = (string) ( $context['level'] ?? $registration['niveau'] ?? '' );
		$created_raw = (string) ( $context['created_at'] ?? $registration['created_at'] ?? ibc_now() );
		$created_ts  = strtotime( $created_raw ) ?: time();
		$created_human = (string) ( $context['createdAt'] ?? wp_date( 'd/m/Y H:i', $created_ts ) );
		$deadline_human = (string) ( $context['payDeadline'] ?? wp_date( 'd/m/Y H:i', strtotime( '+24 hours', $created_ts ) ) );
		$price_display  = (string) ( $context['price'] ?? ( ibc_get_price_prep() . ' MAD' ) );
		$price_numeric  = (float) ( $context['price_numeric'] ?? ibc_get_price_prep() );
		$receipt_url    = (string) ( $registration['receipt_url'] ?? $registration['receiptUrl'] ?? $context['receiptUrl'] ?? '' );
		$notes          = ibc_sanitize_textarea( (string) ( $context['notes'] ?? $registration['notes'] ?? $registration['messageNotes'] ?? '' ) );
		$extra_fields   = $context['extra'] ?? $context['extraFields'] ?? $registration['extraFields'] ?? array();
		$extra_fields   = is_array( $extra_fields ) ? array_values( $extra_fields ) : array();
		$date_source    = $context['dateNaissance'] ?? $registration['date_naissance'] ?? '';
		$date_naissance = ibc_format_date_human( $date_source );
		if ( '' === $date_naissance && ! empty( $registration['date_naissance'] ) ) {
			$date_naissance = ibc_format_date_human( $registration['date_naissance'] );
		}
		$lieu_naissance = (string) ( $context['lieuNaissance'] ?? $registration['lieu_naissance'] ?? '' );

		$payment_details = ibc_get_payment_details();
		$contact_details = array(
			'address'  => (string) get_option( 'ibc_contact_address', '' ),
			'email'    => (string) get_option( 'ibc_contact_email', '' ),
			'phone'    => (string) get_option( 'ibc_contact_phone', '' ),
			'landline' => (string) get_option( 'ibc_contact_landline', '' ),
		);

		return array(
			'brand_name'      => $brand_name,
			'email'           => $email,
			'full_name'       => $full_name,
			'telephone'       => $telephone,
			'level'           => $level,
			'reference'       => $reference,
			'created_human'   => $created_human,
			'deadline_human'  => $deadline_human,
			'price_display'   => $price_display,
			'price_numeric'   => $price_numeric,
			'date_naissance'  => $date_naissance,
			'lieu_naissance'  => $lieu_naissance,
			'notes'           => $notes,
			'extra'           => $extra_fields,
			'payment'         => $payment_details,
			'contact'         => $contact_details,
			'receipt_url'     => $receipt_url,
			'has_receipt'     => ( $receipt_url || ( $pdf_path && file_exists( $pdf_path ) ) ),
		);
	}

	/**
	 * Build HTML content for confirmation email.
	 *
	 * @param array $context Context array.
	 *
	 * @return string
	 */
	private function build_email_html( array $context ): string {
		$colors    = ibc_get_brand_colors_with_legacy();
		$primary   = $colors['primary'] ?? '#4CB4B4';
		$secondary = $colors['secondary'] ?? '#2A8E8E';
		$muted     = $colors['muted'] ?? '#E0F5F5';
		$border    = $colors['border'] ?? '#E5E7EB';
		$text      = $colors['text'] ?? '#1F2937';
		$danger    = $colors['error_text'] ?? '#B91C1C';

		$brand_name = $context['brand_name'] ?? ibc_get_brand_name();
		$payment    = $context['payment'] ?? array();
		$contact    = $context['contact'] ?? array();

		$payment_rows = array(
			__( 'Banque', 'ibc-enrollment-manager' )        => $payment['bank_name'] ?? '',
			__( 'Titulaire', 'ibc-enrollment-manager' )     => $payment['account_holder'] ?? '',
			__( 'RIB', 'ibc-enrollment-manager' )           => $payment['rib'] ?? '',
			__( 'IBAN', 'ibc-enrollment-manager' )          => $payment['iban'] ?? '',
			__( 'BIC / SWIFT', 'ibc-enrollment-manager' )   => $payment['bic'] ?? '',
			__( 'Agence', 'ibc-enrollment-manager' )        => $payment['agency'] ?? '',
		);

		ob_start();
		?>
		<!DOCTYPE html>
		<html lang="fr">
		<head>
			<meta charset="utf-8">
			<title><?php echo esc_html( $brand_name ); ?></title>
		</head>
		<body style="margin:0;padding:0;background:<?php echo esc_attr( $muted ); ?>;font-family:'Inter','Segoe UI',Arial,sans-serif;color:<?php echo esc_attr( $text ); ?>;line-height:1.6;">
			<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding:32px 0;">
				<tr>
					<td align="center">
						<table role="presentation" width="640" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:18px;border:1px solid <?php echo esc_attr( $border ); ?>;box-shadow:0 24px 48px -32px rgba(31,41,55,0.25);overflow:hidden;">
							<tr>
								<td style="padding:32px;border-bottom:4px solid <?php echo esc_attr( $primary ); ?>;background:linear-gradient(135deg, <?php echo esc_attr( $primary ); ?>, rgba(42,142,142,0.92));color:#ffffff;">
									<div style="font-size:14px;letter-spacing:0.12em;text-transform:uppercase;font-weight:600;opacity:0.9;"><?php echo esc_html( $brand_name ); ?></div>
									<h1 style="margin:12px 0 6px;font-size:24px;font-weight:700;line-height:1.35;"><?php esc_html_e( 'Préinscription reçue – Préparation d’examen', 'ibc-enrollment-manager' ); ?></h1>
									<p style="margin:0;font-size:14px;max-width:520px;"><?php esc_html_e( 'Merci pour votre confiance. Ce message confirme la bonne réception de votre demande de préinscription à la préparation IBC.', 'ibc-enrollment-manager' ); ?></p>
								</td>
							</tr>
							<tr>
								<td style="padding:34px 40px;">
									<p style="margin:0 0 18px;font-size:15px;"><?php printf( esc_html__( 'Bonjour %s,', 'ibc-enrollment-manager' ), esc_html( $context['full_name'] ) ); ?></p>
									<p style="margin:0 0 24px;font-size:14px;color:rgba(31,41,55,0.72);"><?php esc_html_e( 'Votre préinscription est enregistrée. Merci d’effectuer le paiement sous 24 heures en rappelant la référence ci-dessous.', 'ibc-enrollment-manager' ); ?></p>

									<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:separate;border-spacing:0 8px;margin-bottom:28px;">
										<tr>
											<td colspan="2" style="padding-bottom:6px;font-size:12px;font-weight:600;letter-spacing:0.12em;text-transform:uppercase;color:<?php echo esc_attr( $secondary ); ?>;"><?php esc_html_e( 'Récapitulatif', 'ibc-enrollment-manager' ); ?></td>
										</tr>
										<tr>
											<td style="padding:14px 16px;background:<?php echo esc_attr( $muted ); ?>;border-radius:12px 0 0 12px;"><strong><?php esc_html_e( 'Référence', 'ibc-enrollment-manager' ); ?></strong><br><?php echo esc_html( $context['reference'] ); ?></td>
											<td style="padding:14px 16px;background:<?php echo esc_attr( $muted ); ?>;border-radius:0 12px 12px 0;"><strong><?php esc_html_e( 'Date de préinscription', 'ibc-enrollment-manager' ); ?></strong><br><?php echo esc_html( $context['created_human'] ); ?></td>
										</tr>
										<tr>
											<td style="padding:14px 16px;background:<?php echo esc_attr( $muted ); ?>;border-radius:12px 0 0 12px;"><strong><?php esc_html_e( 'Échéance de paiement', 'ibc-enrollment-manager' ); ?></strong><br><?php echo esc_html( $context['deadline_human'] ); ?></td>
											<td style="padding:14px 16px;background:<?php echo esc_attr( $muted ); ?>;border-radius:0 12px 12px 0;"><strong><?php esc_html_e( 'Frais de préparation', 'ibc-enrollment-manager' ); ?></strong><br><?php echo esc_html( $context['price_display'] ); ?></td>
										</tr>
									</table>

									<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid <?php echo esc_attr( $border ); ?>;border-radius:16px;overflow:hidden;margin-bottom:24px;">
										<tr>
											<td style="background:rgba(76,180,180,0.12);padding:14px 20px;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:0.08em;color:<?php echo esc_attr( $secondary ); ?>;"><?php esc_html_e( 'Informations personnelles', 'ibc-enrollment-manager' ); ?></td>
										</tr>
										<tr>
											<td style="padding:18px 20px;">
												<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;line-height:1.6;">
													<tr>
														<td width="50%"><strong><?php esc_html_e( 'Nom complet', 'ibc-enrollment-manager' ); ?></strong><br><?php echo esc_html( $context['full_name'] ); ?></td>
														<td width="50%"><strong><?php esc_html_e( 'Téléphone', 'ibc-enrollment-manager' ); ?></strong><br><?php echo esc_html( $context['telephone'] ); ?></td>
													</tr>
													<tr>
														<td width="50%" style="padding-top:12px;"><strong><?php esc_html_e( 'Email', 'ibc-enrollment-manager' ); ?></strong><br><?php echo esc_html( $context['email'] ); ?></td>
														<td width="50%" style="padding-top:12px;"><strong><?php esc_html_e( 'Niveau', 'ibc-enrollment-manager' ); ?></strong><br><?php echo esc_html( $context['level'] ); ?></td>
													</tr>
													<?php if ( ! empty( $context['date_naissance'] ) || ! empty( $context['lieu_naissance'] ) ) : ?>
														<tr>
															<td width="50%" style="padding-top:12px;"><strong><?php esc_html_e( 'Date de naissance', 'ibc-enrollment-manager' ); ?></strong><br><?php echo esc_html( $context['date_naissance'] ); ?></td>
															<td width="50%" style="padding-top:12px;"><strong><?php esc_html_e( 'Lieu de naissance', 'ibc-enrollment-manager' ); ?></strong><br><?php echo esc_html( $context['lieu_naissance'] ); ?></td>
														</tr>
													<?php endif; ?>
												</table>
											</td>
										</tr>
									</table>

									<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid rgba(76,180,180,0.35);border-radius:16px;overflow:hidden;margin-bottom:24px;">
										<tr>
											<td style="background:<?php echo esc_attr( $primary ); ?>;color:#ffffff;padding:14px 20px;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:0.08em;"><?php esc_html_e( 'Coordonnées bancaires (paiement sous 24 h)', 'ibc-enrollment-manager' ); ?></td>
										</tr>
										<tr>
											<td style="padding:20px;">
												<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;line-height:1.6;">
													<?php foreach ( $payment_rows as $label => $value ) : ?>
														<?php if ( ! empty( $value ) ) : ?>
															<tr>
																<td style="padding:4px 0;"><strong><?php echo esc_html( $label ); ?> :</strong> <?php echo esc_html( $value ); ?></td>
															</tr>
														<?php endif; ?>
													<?php endforeach; ?>
													<?php if ( ! empty( $payment['payment_note'] ) ) : ?>
														<tr>
															<td style="padding-top:12px;font-style:italic;color:rgba(31,41,55,0.72);"><?php echo esc_html( $payment['payment_note'] ); ?></td>
														</tr>
													<?php endif; ?>
												</table>
												<div style="margin-top:18px;padding:14px 16px;border:1px dashed rgba(42,142,142,0.45);border-radius:12px;font-weight:600;color:<?php echo esc_attr( $secondary ); ?>;text-align:center;">
													<?php printf( esc_html__( 'Mentionnez impérativement la référence %s dans l’objet de votre virement.', 'ibc-enrollment-manager' ), esc_html( $context['reference'] ) ); ?>
												</div>
											</td>
										</tr>
									</table>

									<div style="margin-bottom:18px;padding:16px 18px;border-radius:14px;background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.22);color:<?php echo esc_attr( $danger ); ?>;font-weight:600;">
										<?php esc_html_e( 'Une fois votre inscription validée, elle est définitive et non remboursable.', 'ibc-enrollment-manager' ); ?>
									</div>

									<div style="margin-bottom:24px;padding:16px 18px;border-radius:14px;background:rgba(250,204,21,0.12);border:1px solid rgba(217,119,6,0.22);color:#92400e;font-weight:600;font-size:13px;">
										<?php esc_html_e( 'Le paiement doit être réalisé dans les 24 heures suivant la réception de cet e-mail. Au-delà, la préinscription sera automatiquement annulée.', 'ibc-enrollment-manager' ); ?>
									</div>

									<?php if ( ! empty( $context['notes'] ) ) : ?>
										<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid <?php echo esc_attr( $border ); ?>;border-radius:14px;overflow:hidden;margin-bottom:24px;">
											<tr>
												<td style="background:rgba(76,180,180,0.12);padding:12px 16px;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:0.08em;color:<?php echo esc_attr( $secondary ); ?>;"><?php esc_html_e( 'Message du candidat', 'ibc-enrollment-manager' ); ?></td>
											</tr>
											<tr>
												<td style="padding:18px;font-size:14px;color:rgba(31,41,55,0.86);"><?php echo nl2br( esc_html( $context['notes'] ) ); ?></td>
											</tr>
										</table>
									<?php endif; ?>

									<?php if ( ! empty( $context['extra'] ) ) : ?>
										<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid rgba(76,180,180,0.25);border-radius:14px;overflow:hidden;margin-bottom:24px;">
											<tr>
												<td style="background:rgba(76,180,180,0.12);padding:12px 16px;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:0.08em;color:<?php echo esc_attr( $secondary ); ?>;"><?php esc_html_e( 'Informations complémentaires', 'ibc-enrollment-manager' ); ?></td>
											</tr>
											<tr>
												<td style="padding:18px;">
													<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:13px;line-height:1.6;">
														<?php foreach ( $context['extra'] as $entry ) : ?>
															<?php
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
															?>
															<tr>
																<td style="padding:3px 0;"><strong><?php echo $label; ?> :</strong> <?php echo $display; ?></td>
															</tr>
														<?php endforeach; ?>
													</table>
												</td>
											</tr>
										</table>
									<?php endif; ?>

									<?php if ( $context['has_receipt'] ) : ?>
										<p style="margin:0 0 18px;font-size:13px;color:rgba(31,41,55,0.7);">
											<?php esc_html_e( 'Le reçu de préinscription est joint à cet e-mail. Conservez-le précieusement pour votre dossier.', 'ibc-enrollment-manager' ); ?>
											<?php if ( ! empty( $context['receipt_url'] ) ) : ?>
												<br><a href="<?php echo esc_url( $context['receipt_url'] ); ?>" style="color:<?php echo esc_attr( $secondary ); ?>;font-weight:600;text-decoration:none;" target="_blank" rel="noopener noreferrer">
													<?php esc_html_e( 'Télécharger le reçu', 'ibc-enrollment-manager' ); ?>
												</a>
											<?php endif; ?>
										</p>
									<?php endif; ?>

									<p style="margin:0 0 16px;font-size:14px;"><?php esc_html_e( 'Nous restons à votre disposition pour toute question.', 'ibc-enrollment-manager' ); ?></p>
									<p style="margin:0 0 24px;font-size:14px;"><?php esc_html_e( 'À très vite,', 'ibc-enrollment-manager' ); ?><br><strong><?php echo esc_html( $brand_name ); ?></strong></p>

									<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:12px;color:rgba(31,41,55,0.65);border-top:1px solid <?php echo esc_attr( $border ); ?>;padding-top:18px;">
										<?php if ( ! empty( $contact['address'] ) ) : ?>
											<tr><td style="padding:2px 0;"><?php echo esc_html( $contact['address'] ); ?></td></tr>
										<?php endif; ?>
										<?php if ( ! empty( $contact['email'] ) ) : ?>
											<tr><td style="padding:2px 0;"><?php esc_html_e( 'Email :', 'ibc-enrollment-manager' ); ?> <?php echo esc_html( $contact['email'] ); ?></td></tr>
										<?php endif; ?>
										<?php if ( ! empty( $contact['phone'] ) ) : ?>
											<tr><td style="padding:2px 0;"><?php esc_html_e( 'Mobile :', 'ibc-enrollment-manager' ); ?> <?php echo esc_html( $contact['phone'] ); ?></td></tr>
										<?php endif; ?>
										<?php if ( ! empty( $contact['landline'] ) ) : ?>
											<tr><td style="padding:2px 0;"><?php esc_html_e( 'Fixe :', 'ibc-enrollment-manager' ); ?> <?php echo esc_html( $contact['landline'] ); ?></td></tr>
										<?php endif; ?>
									</table>
								</td>
							</tr>
						</table>
					</td>
				</tr>
			</table>
		</body>
		</html>
		<?php

		return (string) ob_get_clean();
	}
}
