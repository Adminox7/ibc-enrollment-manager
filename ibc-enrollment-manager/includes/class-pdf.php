<?php
/**
 * Dompdf-based receipt generator.
 *
 * @package IBC\Enrollment
 */

declare( strict_types=1 );

namespace IBC\Enrollment\Services;

use Dompdf\Dompdf;
use Dompdf\Options;
use WP_Error;
use function IBC\Enrollment\ibc_get_brand_colors_with_legacy;
use function IBC\Enrollment\ibc_get_brand_name;
use function IBC\Enrollment\ibc_get_payment_details;
use function IBC\Enrollment\ibc_get_price_prep;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Immutable value object describing a rendered PDF.
 */
class PdfResult {

	public function __construct(
		public readonly string $path,
		public readonly string $url
	) {}
}

/**
 * Lightweight data wrapper for enrollment payloads.
 */
class EnrollmentData {

	/**
	 * @param array<string,mixed> $payload Raw registration data.
	 */
	public function __construct( private array $payload ) {}

	public function get( string $key, mixed $default = '' ): mixed {
		return $this->payload[ $key ] ?? $default;
	}

	public function all(): array {
		return $this->payload;
	}
}

/**
 * Generates the turquoise receipt PDF using Dompdf.
 */
class PdfService {

	/**
	 * Builds the PDF receipt and returns path + URL pair.
	 *
	 * @param EnrollmentData $data Registration data bag.
	 * @return PdfResult|WP_Error
	 */
	public function generateReceipt( EnrollmentData $data ) {
		if ( ! class_exists( Dompdf::class ) ) {
			return new WP_Error(
				'ibc_pdf_missing',
				__( 'La génération de PDF est indisponible (Dompdf manquant).', 'ibc-enrollment' )
			);
		}

		$context = $this->build_context( $data );
		$html    = $this->render_template( $context );

		$options = new Options();
		$options->set( 'isRemoteEnabled', true );
		$options->set( 'isHtml5ParserEnabled', true );
		$options->set( 'defaultFont', 'Inter' );

		$dompdf = new Dompdf( $options );
		$dompdf->loadHtml( $html );
		$dompdf->setPaper( 'A4', 'portrait' );
		$dompdf->render();

		$pdf_output = $dompdf->output();

		$upload_dir = wp_upload_dir();
		$base_dir   = trailingslashit( $upload_dir['basedir'] ) . 'ibc/receipts/';
		$base_url   = trailingslashit( $upload_dir['baseurl'] ) . 'ibc/receipts/';

		if ( ! wp_mkdir_p( $base_dir ) ) {
			return new WP_Error( 'ibc_pdf_path', __( 'Impossible de créer le dossier des reçus.', 'ibc-enrollment' ) );
		}

		$filename = sprintf( 'recu-prepa-%s.pdf', $context['reference_slug'] );
		$path     = $base_dir . $filename;
		$url      = $base_url . $filename;

		if ( false === file_put_contents( $path, $pdf_output ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents
			return new WP_Error( 'ibc_pdf_write', __( 'Impossible d’enregistrer le reçu PDF.', 'ibc-enrollment' ) );
		}

		return new PdfResult( $path, $url );
	}

	/**
	 * Normalizes template data for the receipt.
	 *
	 * @param EnrollmentData $data Enrollment payload.
	 * @return array<string,mixed>
	 */
	private function build_context( EnrollmentData $data ): array {
		$payload   = $data->all();
		$reference = (string) ( $payload['reference'] ?? $payload['ref'] ?? sprintf( 'IBC-%s', wp_generate_password( 6, false ) ) );
		$ts        = isset( $payload['timestamp'] ) ? strtotime( (string) $payload['timestamp'] ) : time();

		$full_name = trim( sprintf(
			'%s %s',
			$payload['prenom'] ?? '',
			$payload['nom'] ?? ''
		) );

		$default_price = (float) ( $payload['price_prep'] ?? ibc_get_price_prep() );
		$numeric_price = $payload['price_numeric'] ?? $payload['price'] ?? $default_price;
		$price_value   = is_numeric( $numeric_price ) ? (float) $numeric_price : $default_price;
		$price         = is_numeric( $price_value )
			? sprintf( '%s MAD', number_format_i18n( (float) $price_value, 0 ) )
			: (string) $price_value;

		return [
			'brand_name'     => ibc_get_brand_name(),
			'colors'         => ibc_get_brand_colors_with_legacy(),
			'full_name'      => $full_name,
			'email'          => (string) ( $payload['email'] ?? '' ),
			'telephone'      => (string) ( $payload['telephone'] ?? '' ),
			'level'          => (string) ( $payload['niveau'] ?? '' ),
			'date_naissance' => (string) ( $payload['date_naissance'] ?? $payload['dateNaissance'] ?? '' ),
			'lieu_naissance' => (string) ( $payload['lieu_naissance'] ?? $payload['lieuNaissance'] ?? '' ),
			'message'        => (string) ( $payload['message'] ?? '' ),
			'extra'          => is_array( $payload['extraFields'] ?? null ) ? array_values( $payload['extraFields'] ) : [],
			'price'          => $price,
			'created_at'     => wp_date( 'd/m/Y H:i', $ts ),
			'deadline'       => wp_date( 'd/m/Y H:i', strtotime( '+24 hours', $ts ) ),
			'payment'        => ibc_get_payment_details(),
			'contact'        => [
				'address'  => (string) get_option( 'ibc_contact_address', '' ),
				'email'    => (string) get_option( 'ibc_contact_email', '' ),
				'phone'    => (string) get_option( 'ibc_contact_phone', '' ),
				'landline' => (string) get_option( 'ibc_contact_landline', '' ),
			],
			'reference'      => $reference,
			'reference_slug' => sanitize_title( $reference ),
		];
	}

	/**
	 * Renders the dedicated receipt template.
	 *
	 * @param array<string,mixed> $context Template context.
	 * @return string
	 */
	private function render_template( array $context ): string {
		$template_context = $context;
		ob_start();
		include IBC_ENROLLMENT_PLUGIN_DIR . 'templates/pdf-receipt.php';
		return (string) ob_get_clean();
	}
}
