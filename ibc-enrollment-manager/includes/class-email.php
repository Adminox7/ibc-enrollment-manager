<?php
/**
 * Confirmation email service (HTML receipt summary).
 *
 * @package IBC\Enrollment
 */

declare( strict_types=1 );

namespace IBC\Enrollment\Services;

use function IBC\Enrollment\ibc_get_brand_colors_with_legacy;
use function IBC\Enrollment\ibc_get_brand_name;
use function IBC\Enrollment\ibc_get_payment_details;
use function IBC\Enrollment\ibc_get_price_prep;
use function IBC\Enrollment\ibc_normalize_email;
use function IBC\Enrollment\ibc_sanitize_textarea;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sends the turquoise confirmation email with PDF receipt attachment.
 */
class EmailService {

	/**
	 * Sends the confirmation email to the applicant.
	 *
	 * @param array       $payload   Registration data/context.
	 * @param string|null $pdf_path  Absolute path to generated PDF (optional).
	 * @param string|null $pdf_name  Download name.
	 * @return bool
	 */
	public function send_confirmation( array $payload, ?string $pdf_path = null, ?string $pdf_name = null ): bool {
		$to = isset( $payload['email'] ) ? ibc_normalize_email( (string) $payload['email'] ) : '';

		if ( empty( $to ) || ! is_email( $to ) ) {
			return false;
		}

		$brand   = ibc_get_brand_name();
		$subject = sprintf( __( 'Préinscription reçue – %s', 'ibc-enrollment' ), $brand );

		$headers = [
			'Content-Type: text/html; charset=UTF-8',
		];

		$from = (string) get_option( 'ibc_contact_email', '' );
		if ( is_email( $from ) ) {
			$headers[] = sprintf( 'From: %s <%s>', $brand, $from );
			$headers[] = sprintf( 'Reply-To: %s <%s>', $brand, $from );
		}

		$context = $this->build_context( $payload, $pdf_path );
		$html    = $this->render_email( $context );

		$attachments = [];
		if ( $pdf_path && file_exists( $pdf_path ) ) {
			if ( $pdf_name ) {
				$attachments[] = [ $pdf_path, $pdf_name ];
			} else {
				$attachments[] = $pdf_path;
			}
		}

		return wp_mail( $to, $subject, $html, $headers, $attachments );
	}

	/**
	 * Normalizes the context consumed by the template.
	 *
	 * @param array       $registration Registration data.
	 * @param string|null $pdf_path     PDF path.
	 * @return array<string,mixed>
	 */
	private function build_context( array $registration, ?string $pdf_path ): array {
		$full_name  = trim( sprintf( '%s %s', $registration['prenom'] ?? '', $registration['nom'] ?? '' ) );
		$reference  = (string) ( $registration['reference'] ?? '' );
		$creation   = isset( $registration['timestamp'] ) ? strtotime( (string) $registration['timestamp'] ) : time();
		$deadline   = strtotime( '+24 hours', $creation );
		$level      = (string) ( $registration['niveau'] ?? '' );
		$email      = (string) ( $registration['email'] ?? '' );
		$phone      = (string) ( $registration['telephone'] ?? '' );
		$message    = ibc_sanitize_textarea( (string) ( $registration['message'] ?? '' ) );
		$price      = ibc_get_price_prep();
		$payment    = ibc_get_payment_details();
		$contact    = [
			'address'  = (string) get_option( 'ibc_contact_address', '' ),
			'email'    = (string) get_option( 'ibc_contact_email', '' ),
			'phone'    = (string) get_option( 'ibc_contact_phone', '' ),
			'landline' = (string) get_option( 'ibc_contact_landline', '' ),
		];

		return [
			'brand'        => ibc_get_brand_name(),
			'full_name'    => $full_name,
			'email'        => $email,
			'phone'        => $phone,
			'level'        => $level,
			'reference'    => $reference,
			'created_at'   => wp_date( 'd/m/Y H:i', $creation ),
			'deadline'     => wp_date( 'd/m/Y H:i', $deadline ),
			'price'        => sprintf( '%s MAD', number_format_i18n( $price, 0 ) ),
			'message'      => $message,
			'payment'      => $payment,
			'contact'      => $contact,
			'has_receipt'  => (bool) $pdf_path,
			'receipt_url'  => $registration['pdf_url'] ?? '',
		];
	}

	/**
	 * Renders the HTML email template.
	 *
	 * @param array $context Template context.
	 * @return string
	 */
	private function render_email( array $context ): string {
		$colors = ibc_get_brand_colors_with_legacy();
		ob_start();
		include __DIR__ . '/../templates/email-confirmation.php';
		return (string) ob_get_clean();
	}
}
