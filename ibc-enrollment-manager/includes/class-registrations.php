<?php
/**
 * Registration domain service.
 *
 * @package IBC\Enrollment
 */

declare( strict_types=1 );

namespace IBC\Enrollment\Services;

use IBC\Enrollment\Database\DB;
use IBC\Enrollment\Support\FormBuilder;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles creation, retrieval and updates of enrollment records.
 */
class Registrations {

	public function __construct(
		private DB $db,
		private EmailService $email,
		private PdfService $pdf,
		private FormBuilder $formBuilder,
	) {}

	/**
	 * Returns capacity/duplicate snapshot for the front form.
	 */
	public function capacitySnapshot( string $email, string $phone ): array {
		$email     = ibc_normalize_email( $email );
		$telephone = ibc_normalize_phone( $phone );
		$total     = $this->db->count_active();
		$limit     = ibc_get_capacity_limit();
		$existing  = $this->findDuplicates( $email, $telephone );

		return [
			'capacity'    => $limit,
			'total'       => $total,
			'existsEmail' => (bool) $existing['email'],
			'existsPhone' => (bool) $existing['telephone'],
		];
	}

	/**
	 * Creates a new registration entry.
	 *
	 * @param array $payload Request body.
	 * @param array $files   Uploaded files (CIN recto/verso).
	 * @return array|WP_Error
	 */
	public function create( array $payload, array $files = [] ) {
		$schema     = $this->formBuilder->getSchema();
		$mapped     = [];
		$extras     = [];
		$notes      = '';
		$emailValue = '';
		$phoneValue = '';
		$missing    = [];

		foreach ( $schema as $field ) {
			$id       = $field['id'];
			$type     = $field['type'];
			$required = ! empty( $field['required'] );
			$map      = $field['map'] ?? $id;

			if ( 'file' === $type ) {
				$value = $this->processUpload( $files[ $id ] ?? null, $required, $field['label'] ?? $id );
				if ( is_wp_error( $value ) ) {
					return $value;
				}
			} else {
				$value = $this->sanitizeValue( $type, (string) ( $payload[ $id ] ?? '' ) );
				if ( $required && '' === $value ) {
					$missing[] = $field['label'] ?? $id;
				}
			}

			if ( '' === $value ) {
				continue;
			}

			switch ( $map ) {
				case 'message':
					$notes = $value;
					break;
				case 'email':
					$emailValue = $value;
					$mapped['email'] = $value;
					break;
				case 'telephone':
				case 'phone':
					$phoneValue = $value;
					$mapped['telephone'] = $value;
					break;
				default:
					$mapped[ $map ] = $value;
					break;
			}

			if ( ! in_array( $map, [ 'prenom', 'nom', 'date_naissance', 'lieu_naissance', 'email', 'telephone', 'niveau', 'message', 'cin_recto', 'cin_verso' ], true ) ) {
				$extras[] = [
					'id'      => $id,
					'label'   => $field['label'] ?? ucfirst( $id ),
					'type'    => $type,
					'value'   => $value,
					'display' => $value,
				];
			}
		}

		if ( $missing ) {
			return new WP_Error(
				'ibc_missing_fields',
				sprintf(
					/* translators: %s missing fields */
					__( 'Merci de compléter les champs obligatoires : %s.', 'ibc-enrollment' ),
					implode( ', ', $missing )
				)
			);
		}

		if ( empty( $mapped['prenom'] ) || empty( $mapped['nom'] ) || empty( $mapped['niveau'] ) || empty( $emailValue ) || empty( $phoneValue ) ) {
			return new WP_Error( 'ibc_core_missing', __( 'Les informations principales sont incomplètes.', 'ibc-enrollment' ) );
		}

		if ( $this->isCapacityReached() ) {
			return new WP_Error( 'ibc_capacity_full', __( 'Les inscriptions sont désormais closes.', 'ibc-enrollment' ) );
		}

		$duplicates = $this->findDuplicates( $emailValue, $phoneValue );
		if ( $duplicates['email'] || $duplicates['telephone'] ) {
			return new WP_Error( 'ibc_duplicate', __( 'Une inscription existe déjà avec ces coordonnées.', 'ibc-enrollment' ) );
		}

		$reference = $this->uniqueReference();
		$timestamp = ibc_now();
		$fullName  = trim( sprintf( '%s %s', $mapped['prenom'], $mapped['nom'] ) );

		$row = [
			'timestamp'      => $timestamp,
			'prenom'         => $mapped['prenom'],
			'nom'            => $mapped['nom'],
			'date_naissance' => $this->normalizeDate( $mapped['date_naissance'] ?? '' ),
			'lieu_naissance' => $mapped['lieu_naissance'] ?? '',
			'email'          => $emailValue,
			'telephone'      => $phoneValue,
			'niveau'         => $mapped['niveau'],
			'message'        => $this->encodeMessage( $notes, $extras ),
			'cin_recto'      => $mapped['cin_recto'] ?? '',
			'cin_verso'      => $mapped['cin_verso'] ?? '',
			'pdf_url'        => '',
			'statut'         => 'Confirme',
			'reference'      => $reference,
		];

		$insertId = $this->db->insert( $row );
		if ( ! $insertId ) {
			return new WP_Error( 'ibc_insert_failed', __( 'Impossible d’enregistrer votre demande. Merci de réessayer.', 'ibc-enrollment' ) );
		}

		$context = [
			'full_name'      => $fullName,
			'email'          => $emailValue,
			'telephone'      => $phoneValue,
			'level'          => $mapped['niveau'],
			'reference'      => $reference,
			'created_at'     => $timestamp,
			'deadline'       => wp_date( 'd/m/Y H:i', strtotime( '+24 hours', strtotime( $timestamp ) ) ),
			'price_numeric'  => ibc_get_price_prep(),
			'price'          => ibc_get_price_prep() . ' MAD',
			'message'        => $notes,
			'extra'          => $extras,
			'date_naissance' => $mapped['date_naissance'] ?? '',
			'lieu_naissance' => $mapped['lieu_naissance'] ?? '',
		];

		$pdfResult = $this->pdf->generateReceipt( new EnrollmentData( $context ) );
		if ( ! is_wp_error( $pdfResult ) ) {
			$row['pdf_url'] = $pdfResult->url;
			$this->db->update_by_reference( $reference, [ 'pdf_url' => $pdfResult->url ] );
		}

		$this->email->send_confirmation(
			array_merge( $context, [ 'pdf_url' => $row['pdf_url'] ] ),
			! is_wp_error( $pdfResult ) ? $pdfResult->path : null,
			sprintf( 'recu-prepa-%s.pdf', sanitize_title( $reference ) )
		);

		return [
			'id'          => $insertId,
			'reference'   => $reference,
			'created_at'  => $timestamp,
			'pdf_url'     => $row['pdf_url'],
			'email'       => $emailValue,
			'telephone'   => $phoneValue,
			'status'      => 'Confirme',
			'extraFields' => $extras,
		];
	}

	/**
	 * Lists registrations for the admin dashboard.
	 */
	public function list( array $args = [] ): array {
		$items = $this->db->query( $args );
		$total = $this->db->count_filtered(
			[
				'search' => $args['search'] ?? '',
				'niveau' => $args['niveau'] ?? '',
				'statut' => $args['statut'] ?? '',
			]
		);

		$rows = array_map(
			function ( array $row ): array {
				$message = $this->decodeMessage( $row['message'] ?? '' );

				return [
					'row'           => (int) $row['id'],
					'timestamp'     => $row['timestamp'],
					'prenom'        => $row['prenom'],
					'nom'           => $row['nom'],
					'fullName'      => trim( $row['prenom'] . ' ' . $row['nom'] ),
					'dateNaissance' => ibc_format_date_human( $row['date_naissance'] ),
					'lieuNaissance' => $row['lieu_naissance'],
					'email'         => $row['email'],
					'telephone'     => $row['telephone'],
					'niveau'        => $row['niveau'],
					'message'       => $message['notes'],
					'extraFields'   => $message['extra'],
					'cinRectoUrl'   => $row['cin_recto'],
					'cinVersoUrl'   => $row['cin_verso'],
					'ref'           => $row['reference'],
					'statut'        => $row['statut'],
					'pdfUrl'        => $row['pdf_url'],
				];
			},
			$items
		);

		return [
			'items' => $rows,
			'total' => $total,
		];
	}

	/**
	 * Updates editable fields.
	 */
	public function update( int $id, array $fields ) {
		$allowed = [ 'prenom', 'nom', 'date_naissance', 'lieu_naissance', 'email', 'telephone', 'niveau', 'message', 'statut' ];
		$data    = [];

		foreach ( $allowed as $key ) {
			if ( ! array_key_exists( $key, $fields ) ) {
				continue;
			}

			switch ( $key ) {
				case 'email':
					$data['email'] = ibc_normalize_email( (string) $fields[ $key ] );
					break;
				case 'telephone':
					$data['telephone'] = ibc_normalize_phone( (string) $fields[ $key ] );
					break;
				case 'date_naissance':
					$data['date_naissance'] = $this->normalizeDate( (string) $fields[ $key ] );
					break;
				case 'message':
					$current = $this->db->get( $id );
					if ( ! $current ) {
						return new WP_Error( 'ibc_not_found', __( 'Inscription introuvable.', 'ibc-enrollment' ) );
					}
					$decoded             = $this->decodeMessage( $current['message'] ?? '' );
					$decoded['notes']    = ibc_sanitize_textarea( (string) $fields[ $key ] );
					$data['message']     = $this->encodeMessage( $decoded['notes'], $decoded['extra'] );
					break;
				case 'statut':
					$status = $this->normalizeStatus( (string) $fields[ $key ] );
					if ( ! $status ) {
						return new WP_Error( 'ibc_status', __( 'Statut invalide.', 'ibc-enrollment' ) );
					}
					$data['statut'] = $status;
					break;
				default:
					$data[ $key ] = sanitize_text_field( (string) $fields[ $key ] );
					break;
			}
		}

		if ( empty( $data ) ) {
			return false;
		}

		return $this->db->update( $id, $data );
	}

	/**
	 * Soft delete via reference.
	 */
	public function cancelByReference( string $reference ): bool {
		return $this->db->soft_cancel( sanitize_text_field( $reference ) );
	}

	/* -------------------------------------------------------------------------
	 * Internals
	 * ---------------------------------------------------------------------- */

	private function sanitizeValue( string $type, string $value ): string {
		return match ( $type ) {
			'email'    => ibc_normalize_email( $value ),
			'tel'      => ibc_normalize_phone( $value ),
			'textarea' => ibc_sanitize_textarea( $value ),
			'date'     => $this->normalizeDate( $value ) ?? '',
			default    => sanitize_text_field( $value ),
		};
	}

	private function normalizeDate( string $value ): ?string {
		$value = trim( $value );
		if ( '' === $value ) {
			return null;
		}
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
			return $value;
		}
		if ( preg_match( '/^\d{2}\/\d{2}\/\d{4}$/', $value ) ) {
			[ $d, $m, $y ] = array_map( 'intval', explode( '/', $value ) );
			return sprintf( '%04d-%02d-%02d', $y, $m, $d );
		}
		$timestamp = strtotime( $value );
		return $timestamp ? gmdate( 'Y-m-d', $timestamp ) : null;
	}

	private function processUpload( ?array $file, bool $required, string $label ) {
		if ( empty( $file ) || empty( $file['tmp_name'] ) ) {
			return $required ? new WP_Error( 'ibc_upload_missing', sprintf( __( 'Merci de fournir %s.', 'ibc-enrollment' ), $label ) ) : '';
		}

		if ( ! function_exists( 'wp_handle_upload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$upload = wp_handle_upload(
			$file,
			[
				'test_form' => false,
				'mimes'     => [
					'jpg'  => 'image/jpeg',
					'jpeg' => 'image/jpeg',
					'png'  => 'image/png',
					'pdf'  => 'application/pdf',
				],
			]
		);

		if ( isset( $upload['error'] ) ) {
			return new WP_Error( 'ibc_upload_error', $upload['error'] );
		}

		return $upload['url'] ?? '';
	}

	private function encodeMessage( string $notes, array $extra ): string {
		return wp_json_encode(
			[
				'notes' => ibc_sanitize_textarea( $notes ),
				'extra' => array_values( $extra ),
			]
		);
	}

	private function decodeMessage( string $raw ): array {
		$data = json_decode( $raw, true );

		if ( is_array( $data ) && isset( $data['extra'] ) ) {
			return [
				'notes' => ibc_sanitize_textarea( (string) ( $data['notes'] ?? '' ) ),
				'extra' => is_array( $data['extra'] ) ? $data['extra'] : [],
			];
		}

		return [
			'notes' => ibc_sanitize_textarea( $raw ),
			'extra' => [],
		];
	}

	private function uniqueReference(): string {
		do {
			$reference = ibc_generate_reference();
		} while ( $this->db->get_by_reference( $reference ) );

		return $reference;
	}

	private function normalizeStatus( string $status ): string {
		$status = strtolower( $status );
		return match ( true ) {
			in_array( $status, [ 'confirme', 'confirmé', 'confirmed' ], true ) => 'Confirme',
			in_array( $status, [ 'annule', 'annulé', 'cancelled', 'canceled' ], true ) => 'Annule',
			default => '',
		};
	}

	private function findDuplicates( string $email, string $phone ): array {
		return [
			'email'     => $email ? $this->db->get_by_email( $email ) : null,
			'telephone' => $phone ? $this->db->get_by_phone( $phone ) : null,
		];
	}

	private function isCapacityReached(): bool {
		$limit = ibc_get_capacity_limit();
		return $limit > 0 && $this->db->count_active() >= $limit;
	}
}
