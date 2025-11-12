<?php
/**
 * Registration domain logic.
 *
 * @package IBC\EnrollmentManager
 */

namespace IBC;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Registrations
 */
class Registrations {

	/**
	 * Database layer.
	 *
	 * @var DB
	 */
	private DB $db;

	/**
	 * Email handler.
	 *
	 * @var Email
	 */
	private Email $email;

	/**
	 * PDF generator.
	 *
	 * @var PDF
	 */
	private PDF $pdf;

	/**
	 * Constructor.
	 *
	 * @param DB    $db    Database service.
	 * @param Email $email Email service.
	 * @param PDF   $pdf   PDF service.
	 */
	public function __construct( DB $db, Email $email, PDF $pdf ) {
		$this->db    = $db;
		$this->email = $email;
		$this->pdf   = $pdf;
	}

	/**
	 * Capacity and duplicate info.
	 *
	 * @param string $email Email.
	 * @param string $phone Phone.
	 *
	 * @return array
	 */
	public function get_capacity_info( string $email, string $phone ): array {
		$email  = ibc_normalize_email( $email );
		$phone  = ibc_normalize_phone( $phone );
		$total  = $this->db->count_active();
		$limit  = ibc_get_capacity_limit();
		$exists = $this->find_existing( $email, $phone );

		return array(
			'capacity'    => $limit,
			'total'       => $total,
			'existsEmail' => (bool) $exists['email'],
			'existsPhone' => (bool) $exists['phone'],
		);
	}

	/**
	 * Create a registration record.
	 *
	 * @param array $payload  Data payload.
	 * @param array $files    Uploaded files (cin_recto, cin_verso).
	 *
	 * @return array|WP_Error
	 */
	public function create_registration( array $payload, array $files = array() ) {
		$clean = $this->sanitize_payload( $payload );
		$required = array( 'prenom', 'nom', 'email', 'phone', 'niveau' );

		foreach ( $required as $field ) {
			if ( empty( $clean[ $field ] ) ) {
				return new WP_Error( 'ibc_missing_field', \__( 'Merci de compléter tous les champs obligatoires.', 'ibc-enrollment-manager' ) );
			}
		}

		// Rate limit per email/phone combination.
		$rate_key = 'register_' . md5( $clean['email'] . $clean['phone'] );
		if ( ibc_rate_limit( $rate_key, 10 ) ) {
			return new WP_Error( 'ibc_rate_limit', \__( 'Merci de patienter quelques instants avant de réessayer.', 'ibc-enrollment-manager' ) );
		}

		$capacity = ibc_get_capacity_limit();
		$total    = $this->db->count_active();
		if ( $capacity > 0 && $total >= $capacity ) {
			return new WP_Error( 'ibc_capacity_full', \__( 'Les inscriptions sont complètes pour le moment.', 'ibc-enrollment-manager' ) );
		}

		$duplicates = $this->find_existing( $clean['email'], $clean['phone'] );
		if ( $duplicates['email'] || $duplicates['phone'] ) {
			return new WP_Error(
				'ibc_duplicate',
				\__( 'Une inscription existe déjà avec cet email ou ce numéro de téléphone.', 'ibc-enrollment-manager' )
			);
		}

		$reference = $this->generate_unique_reference();
		$timestamp = ibc_now();
		$full_name = trim( $clean['prenom'] . ' ' . $clean['nom'] );

		$recto_url = '';
		$verso_url = '';

		if ( ! empty( $files['cin_recto'] ) && is_array( $files['cin_recto'] ) ) {
			$upload = $this->handle_upload( $files['cin_recto'] );
			if ( is_wp_error( $upload ) ) {
				return $upload;
			}
			$recto_url = $upload['url'];
		}

		if ( ! empty( $files['cin_verso'] ) && is_array( $files['cin_verso'] ) ) {
			$upload = $this->handle_upload( $files['cin_verso'] );
			if ( is_wp_error( $upload ) ) {
				return $upload;
			}
			$verso_url = $upload['url'];
		}

		$row = array(
			'created_at'    => $timestamp,
			'ref'           => $reference,
			'prenom'        => $clean['prenom'],
			'nom'           => $clean['nom'],
			'full_name'     => $full_name,
			'birth_date'    => $clean['birth_date'],
			'birth_place'   => $clean['birth_place'],
			'email'         => $clean['email'],
			'phone'         => $clean['phone'],
			'niveau'        => $clean['niveau'],
			'message'       => $clean['message'],
			'cin_recto_url' => $recto_url,
			'cin_verso_url' => $verso_url,
			'statut'        => 'Confirme',
		);

		$insert_id = $this->db->insert( $row );
		if ( ! $insert_id ) {
			return new WP_Error( 'ibc_insert_failed', \__( 'Impossible d’enregistrer votre demande. Merci de réessayer.', 'ibc-enrollment-manager' ) );
		}

		$context = array(
			'fullName'    => $full_name,
			'email'       => $clean['email'],
			'phone'       => $clean['phone'],
			'level'       => $clean['niveau'],
			'ref'         => $reference,
			'createdAt'   => wp_date( 'd/m/Y H:i', strtotime( $timestamp ) ),
			'payDeadline' => wp_date( 'd/m/Y H:i', strtotime( '+24 hours' ) ),
			'price'       => ibc_get_price_prep() . ' MAD',
		);

		$pdf_path    = '';
		$pdf_mediaId = 0;
		$pdf_url     = '';

		$pdf_result = $this->pdf->generate_prep_receipt(
			array(
				'fullName'    => $context['fullName'],
				'email'       => $context['email'],
				'phone'       => $context['phone'],
				'level'       => $context['level'],
				'ref'         => $context['ref'],
				'createdAt'   => $context['createdAt'],
				'payDeadline' => $context['payDeadline'],
				'price'       => $context['price'],
			)
		);

		$attachments = array();

		if ( ! is_wp_error( $pdf_result ) ) {
			$pdf_path = $pdf_result;
			$attachments[] = $pdf_path;

			$attachment = $this->register_pdf_attachment( $pdf_path, $reference );
			if ( ! is_wp_error( $attachment ) ) {
				$pdf_mediaId = (int) $attachment['id'];
				$pdf_url     = (string) $attachment['url'];
			}
		}

		$this->email->send_confirmation( $clean['email'], $context, $attachments );

		return array(
			'id'          => $insert_id,
			'ref'         => $reference,
			'createdAt'   => $timestamp,
			'status'      => 'Confirme',
			'receiptPath' => $pdf_path,
			'receiptId'   => $pdf_mediaId,
			'downloadUrl' => $pdf_url,
		);
	}

	/**
	 * Retrieve registrations.
	 *
	 * @param array $args Arguments.
	 *
	 * @return array
	 */
	public function get_registrations( array $args = array() ): array {
		$records = $this->db->query( $args );

		return array_map(
			static function ( array $row ): array {
				return array(
					'row'         => (int) $row['id'],
					'timestamp'   => $row['created_at'],
					'prenom'      => $row['prenom'],
					'nom'         => $row['nom'],
					'fullName'    => $row['full_name'],
					'dateNaissance' => $row['birth_date'],
					'lieuNaissance' => $row['birth_place'],
					'email'       => $row['email'],
					'phone'       => $row['phone'],
					'level'       => $row['niveau'],
					'message'     => $row['message'],
					'cinRectoUrl' => $row['cin_recto_url'],
					'cinVersoUrl' => $row['cin_verso_url'],
					'ref'         => $row['ref'],
					'statut'      => $row['statut'],
				);
			},
			$records
		);
	}

	/**
	 * Update registration.
	 *
	 * @param int   $id     Row id.
	 * @param array $fields Fields.
	 *
	 * @return bool|WP_Error
	 */
	public function update_registration( int $id, array $fields ) {
		$allowed = array( 'prenom', 'nom', 'birth_date', 'birth_place', 'email', 'phone', 'niveau', 'message', 'statut', 'cin_recto_url', 'cin_verso_url' );
		$data    = array();

		foreach ( $allowed as $key ) {
			if ( ! array_key_exists( $key, $fields ) ) {
				continue;
			}

			$value = $fields[ $key ];

			switch ( $key ) {
				case 'email':
					$value = ibc_normalize_email( (string) $value );
					break;
				case 'phone':
					$value = ibc_normalize_phone( (string) $value );
					break;
				case 'message':
					$value = ibc_sanitize_textarea( (string) $value );
					break;
				case 'statut':
					$value = $this->normalize_status( (string) $value );
					if ( ! $value ) {
						return new WP_Error( 'ibc_status', \__( 'Statut invalide.', 'ibc-enrollment-manager' ) );
					}
					break;
				default:
					$value = sanitize_text_field( (string) $value );
					break;
			}

			$data[ $key ] = $value;
		}

		if ( empty( $data ) ) {
			return false;
		}

		if ( isset( $data['prenom'] ) || isset( $data['nom'] ) ) {
			$row = $this->db->get( $id );
			if ( $row ) {
				$prenom = $data['prenom'] ?? $row['prenom'];
				$nom    = $data['nom'] ?? $row['nom'];
				$data['full_name'] = trim( $prenom . ' ' . $nom );
			}
		}

		return $this->db->update( $id, $data );
	}

	/**
	 * Soft delete by reference.
	 *
	 * @param string $reference Reference.
	 *
	 * @return bool
	 */
	public function cancel_by_reference( string $reference ): bool {
		return $this->db->soft_delete_by_ref( sanitize_text_field( $reference ) );
	}

	/**
	 * Sanitize payload.
	 *
	 * @param array $payload Raw payload.
	 *
	 * @return array
	 */
	private function sanitize_payload( array $payload ): array {
		return array(
			'prenom'      => sanitize_text_field( $payload['prenom'] ?? '' ),
			'nom'         => sanitize_text_field( $payload['nom'] ?? '' ),
			'birth_date'  => sanitize_text_field( $payload['birth_date'] ?? '' ),
			'birth_place' => sanitize_text_field( $payload['birth_place'] ?? '' ),
			'email'       => ibc_normalize_email( $payload['email'] ?? '' ),
			'phone'       => ibc_normalize_phone( $payload['phone'] ?? '' ),
			'niveau'      => sanitize_text_field( $payload['niveau'] ?? '' ),
			'message'     => ibc_sanitize_textarea( $payload['message'] ?? '' ),
		);
	}

	/**
	 * Find duplicates.
	 *
	 * @param string $email Email.
	 * @param string $phone Phone.
	 *
	 * @return array{email:array|null,phone:array|null}
	 */
	private function find_existing( string $email, string $phone ): array {
		$email_row = null;
		$phone_row = null;

		if ( $email ) {
			$email_row = $this->db->get_by_email( $email );
			if ( $email_row && 'Annule' === $email_row['statut'] ) {
				$email_row = null;
			}
		}

		if ( $phone ) {
			$phone_row = $this->db->get_by_phone( $phone );
			if ( $phone_row && 'Annule' === $phone_row['statut'] ) {
				$phone_row = null;
			}
		}

		return array(
			'email' => $email_row,
			'phone' => $phone_row,
		);
	}

	/**
	 * Handle upload via wp_handle_upload.
	 *
	 * @param array $file File data.
	 *
	 * @return array|WP_Error
	 */
	private function handle_upload( array $file ) {
		if ( empty( $file['tmp_name'] ) ) {
			return array( 'url' => '' );
		}

		$allowed = array( 'image/jpeg', 'image/png', 'application/pdf' );
		$type    = $file['type'] ?? '';

		if ( ! in_array( $type, $allowed, true ) ) {
			return new WP_Error( 'ibc_upload_type', \__( 'Format de fichier non autorisé.', 'ibc-enrollment-manager' ) );
		}

		$uploaded = wp_handle_upload(
			$file,
			array(
				'test_form' => false,
			)
		);

		if ( isset( $uploaded['error'] ) ) {
			return new WP_Error( 'ibc_upload_error', $uploaded['error'] );
		}

		return $uploaded;
	}

	/**
	 * Generate unique reference.
	 *
	 * @return string
	 */
	private function generate_unique_reference(): string {
		$reference = ibc_generate_reference();

		while ( $this->db->get_by_ref( $reference ) ) {
			$reference = ibc_generate_reference();
		}

		return $reference;
	}

	/**
	 * Normalize status input.
	 *
	 * @param string $status Status.
	 *
	 * @return string
	 */
	private function normalize_status( string $status ): string {
		$status = strtolower( $status );

		if ( in_array( $status, array( 'confirme', 'confirmé', 'confirmed' ), true ) ) {
			return 'Confirme';
		}

		if ( in_array( $status, array( 'annule', 'annulé', 'cancelled', 'canceled' ), true ) ) {
			return 'Annule';
		}

		return '';
	}

	/**
	 * Insert PDF as attachment.
	 *
	 * @param string $path File path.
	 * @param string $reference Reference.
	 *
	 * @return array|WP_Error
	 */
	private function register_pdf_attachment( string $path, string $reference ) {
		$filetype = wp_check_filetype( basename( $path ), null );

		$upload_dir = wp_upload_dir();
		$relative   = str_replace( trailingslashit( $upload_dir['basedir'] ), '', $path );
		$url        = trailingslashit( $upload_dir['baseurl'] ) . $relative;

		$attachment = array(
			'post_mime_type' => $filetype['type'] ?? 'application/pdf',
			'post_title'     => sanitize_text_field( $reference ),
			'post_content'   => '',
			'post_status'    => 'inherit',
			'guid'           => $url,
		);

		$attach_id = wp_insert_attachment( $attachment, $path );
		if ( is_wp_error( $attach_id ) ) {
			return $attach_id;
		}

		require_once ABSPATH . 'wp-admin/includes/image.php';
		wp_update_attachment_metadata( $attach_id, wp_generate_attachment_metadata( $attach_id, $path ) );

		return array(
			'id'  => $attach_id,
			'url' => wp_get_attachment_url( $attach_id ),
		);
	}
}
