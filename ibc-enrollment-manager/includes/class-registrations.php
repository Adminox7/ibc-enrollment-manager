<?php
/**
 * Registration domain logic.
 *
 * @package IBC\Enrollment
 */

namespace IBC\Enrollment;

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
	 * Form builder service.
	 *
	 * @var FormBuilder
	 */
	private FormBuilder $form_builder;

	/**
	 * Constructor.
	 *
	 * @param DB    $db    Database service.
	 * @param Email $email Email service.
	 * @param PDF   $pdf   PDF service.
	 */
	public function __construct( DB $db, Email $email, PDF $pdf, FormBuilder $form_builder ) {
		$this->db           = $db;
		$this->email        = $email;
		$this->pdf          = $pdf;
		$this->form_builder = $form_builder;
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
			'existsPhone' => (bool) $exists['telephone'],
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
		$schema           = $this->form_builder->get_active_schema();
		$allowed_columns  = array(
			'prenom',
			'nom',
			'date_naissance',
			'lieu_naissance',
			'email',
			'telephone',
			'niveau',
			'message',
			'cin_recto_url',
			'cin_verso_url',
		);
		$mapped           = array_fill_keys( $allowed_columns, '' );
		$notes             = '';
		$extra_fields      = array();
		$email_value       = '';
		$telephone_value   = '';
		$required_missing  = array();

		foreach ( $schema as $field ) {
			$field_id = $field['id'];
			$type     = $field['type'] ?? 'text';
			$map      = sanitize_key( $field['map'] ?? '' );
			$normalized_map = $map;
			if ( 'phone' === $normalized_map ) {
				$normalized_map = 'telephone';
			}
			$required = ! empty( $field['required'] );
			$value    = '';

			if ( 'file' === $type ) {
				$file = $files[ $field_id ] ?? null;
				$has_file = $file && isset( $file['tmp_name'] ) && '' !== $file['tmp_name'] && ( $file['error'] ?? UPLOAD_ERR_OK ) === UPLOAD_ERR_OK;

				if ( ! $has_file ) {
					if ( $required ) {
						$required_missing[] = $field['label'] ?? $field_id;
					}
				} else {
					$upload = $this->handle_upload( $file );
					if ( is_wp_error( $upload ) ) {
						return $upload;
					}
					$value = $upload['url'];
				}
			} else {
				$raw   = isset( $payload[ $field_id ] ) ? wp_unslash( $payload[ $field_id ] ) : '';
				$raw   = is_array( $raw ) ? '' : (string) $raw;
				$value = $this->sanitize_field_value( $type, $raw );

				if ( 'select' === $type && ! $this->choice_exists( $value, (array) ( $field['choices'] ?? array() ) ) ) {
					return new WP_Error(
						'ibc_invalid_field',
						sprintf(
							/* translators: %s field label */
							\__( 'Valeur invalide pour « %s ».', 'ibc-enrollment-manager' ),
							$field['label'] ?? $field_id
						)
					);
				}
			}

			if ( $required && '' === $value ) {
				$required_missing[] = $field['label'] ?? $field_id;
			}

			if ( $normalized_map && in_array( $normalized_map, $allowed_columns, true ) ) {
				if ( 'message' === $normalized_map ) {
					$notes = ibc_sanitize_textarea( $value );
				} else {
					$mapped[ $normalized_map ] = $value;
				}
			} else {
				if ( '' === $value ) {
					continue;
				}

				$extra_fields[] = array(
					'id'      => $field_id,
					'label'   => $field['label'] ?? ucfirst( $field_id ),
					'type'    => $type,
					'value'   => $value,
					'display' => 'select' === $type ? $this->choice_label( $value, (array) ( $field['choices'] ?? array() ) ) : $value,
				);
			}

			if ( 'email' === $normalized_map || 'email' === $field_id ) {
				$email_value = $mapped['email'] ?: $value;
			}

			if ( 'telephone' === $normalized_map || 'telephone' === $field_id || 'phone' === $map || 'phone' === $field_id ) {
				$telephone_value = $mapped['telephone'] ?: $value;
			}
		}

		if ( ! empty( $required_missing ) ) {
			return new WP_Error(
				'ibc_missing_field',
				sprintf(
					/* translators: %s list of fields */
					\__( 'Merci de compléter les champs obligatoires : %s', 'ibc-enrollment-manager' ),
					implode( ', ', array_map( 'esc_html', $required_missing ) )
				)
			);
		}

		// Ensure essential fields exist.
		if ( empty( $mapped['prenom'] ) || empty( $mapped['nom'] ) || empty( $mapped['niveau'] ) || empty( $email_value ) || empty( $telephone_value ) ) {
			return new WP_Error( 'ibc_missing_core', \__( 'Les informations principales sont incomplètes.', 'ibc-enrollment-manager' ) );
		}

		// Rate limit per email/phone combination.
		$rate_key = 'register_' . md5( $email_value . $telephone_value );
		if ( ibc_rate_limit( $rate_key, 10, false ) ) {
			return new WP_Error( 'ibc_rate_limit', \__( 'Merci de patienter quelques instants avant de réessayer.', 'ibc-enrollment-manager' ) );
		}

		$capacity = ibc_get_capacity_limit();
		$total    = $this->db->count_active();
		if ( $capacity > 0 && $total >= $capacity ) {
			return new WP_Error( 'ibc_capacity_full', \__( 'Les inscriptions sont complètes pour le moment.', 'ibc-enrollment-manager' ) );
		}

		$duplicates = $this->find_existing( $email_value, $telephone_value );
		if ( $duplicates['email'] || $duplicates['telephone'] ) {
			return new WP_Error(
				'ibc_duplicate',
				\__( 'Une inscription existe déjà avec cet email ou ce numéro de téléphone.', 'ibc-enrollment-manager' )
			);
		}

		$reference = $this->generate_unique_reference();
		$timestamp = ibc_now();
		$full_name = trim( $mapped['prenom'] . ' ' . $mapped['nom'] );

		$row = array(
			'created_at'     => $timestamp,
			'updated_at'     => $timestamp,
			'reference'      => $reference,
			'prenom'         => $mapped['prenom'],
			'nom'            => $mapped['nom'],
			'date_naissance' => '' !== $mapped['date_naissance'] ? $mapped['date_naissance'] : null,
			'lieu_naissance' => '' !== $mapped['lieu_naissance'] ? $mapped['lieu_naissance'] : null,
			'email'          => $email_value,
			'telephone'      => $telephone_value,
			'niveau'         => $mapped['niveau'],
			'message'        => $this->encode_message_payload( $notes, $extra_fields ),
			'cin_recto_url'  => '' !== $mapped['cin_recto_url'] ? $mapped['cin_recto_url'] : null,
			'cin_verso_url'  => '' !== $mapped['cin_verso_url'] ? $mapped['cin_verso_url'] : null,
			'statut'         => 'Confirme',
		);

		$insert_id = $this->db->insert( $row );
		if ( ! $insert_id ) {
			return new WP_Error( 'ibc_insert_failed', \__( 'Impossible d’enregistrer votre demande. Merci de réessayer.', 'ibc-enrollment-manager' ) );
		}

		$context = array(
			'fullName'       => $full_name,
			'email'          => $email_value,
			'telephone'      => $telephone_value,
			'phone'          => $telephone_value,
			'level'          => $mapped['niveau'],
			'reference'      => $reference,
			'ref'            => $reference,
			'createdAt'      => wp_date( 'd/m/Y H:i', strtotime( $timestamp ) ),
			'created_at'     => $timestamp,
			'payDeadline'    => wp_date( 'd/m/Y H:i', strtotime( '+24 hours' ) ),
			'price'          => ibc_get_price_prep() . ' MAD',
			'price_numeric'  => ibc_get_price_prep(),
			'notes'          => $notes,
			'extra'          => $extra_fields,
			'dateNaissance'  => ibc_format_date_human( $mapped['date_naissance'] ),
			'lieuNaissance'  => $mapped['lieu_naissance'],
			'date_naissance' => $mapped['date_naissance'],
			'lieu_naissance' => $mapped['lieu_naissance'],
		);

		$pdf_path    = '';
		$pdf_mediaId = 0;
		$pdf_url     = '';

		$pdf_result = $this->pdf->generate_prep_receipt(
			array(
				'fullName'       => $context['fullName'],
				'email'          => $context['email'],
				'telephone'      => $context['telephone'],
				'level'          => $context['level'],
				'reference'      => $context['reference'],
				'createdAt'      => $context['created_at'],
				'createdAtHuman' => $context['createdAt'],
				'payDeadline'    => $context['payDeadline'],
				'price'          => $context['price'],
				'price_numeric'  => $context['price_numeric'],
				'notes'          => $notes,
				'extra'          => $extra_fields,
				'dateNaissance'  => $context['dateNaissance'],
				'lieuNaissance'  => $context['lieuNaissance'],
			)
		);

		if ( ! is_wp_error( $pdf_result ) ) {
			if ( is_array( $pdf_result ) ) {
				$pdf_path = isset( $pdf_result['path'] ) ? (string) $pdf_result['path'] : '';
				$pdf_url  = isset( $pdf_result['url'] ) ? (string) $pdf_result['url'] : $pdf_url;
			} else {
				$pdf_path = (string) $pdf_result;
			}

			if ( $pdf_path ) {
				$attachment = $this->register_pdf_attachment( $pdf_path, $reference );
				if ( ! is_wp_error( $attachment ) ) {
					$pdf_mediaId = (int) $attachment['id'];
					$pdf_url     = (string) $attachment['url'];
				}
			}
		}

		if ( ! empty( $pdf_url ) ) {
			$context['receiptUrl'] = $pdf_url;
		}

		$email_payload = array(
			'email'          => $email_value,
			'telephone'      => $telephone_value,
			'reference'      => $reference,
			'niveau'         => $mapped['niveau'],
			'date_naissance' => $mapped['date_naissance'],
			'lieu_naissance' => $mapped['lieu_naissance'],
			'context'        => $context,
			'receipt_url'    => $pdf_url,
		);

		$this->email->send_confirmation( $email_payload, $pdf_path );

		ibc_rate_limit( $rate_key, 10 );

		return array(
			'id'             => $insert_id,
			'reference'      => $reference,
			'ref'            => $reference,
			'email'          => $email_value,
			'telephone'      => $telephone_value,
			'created_at'     => $timestamp,
			'updated_at'     => $timestamp,
			'status'         => 'Confirme',
			'receipt_path'   => $pdf_path,
			'receiptPath'    => $pdf_path,
			'receipt_id'     => $pdf_mediaId,
			'receiptId'      => $pdf_mediaId,
			'receipt_url'    => $pdf_url,
			'receiptUrl'     => $pdf_url,
			'downloadUrl'    => $pdf_url,
			'pdf_available'  => ! empty( $pdf_url ),
			'pdfAvailable'   => ! empty( $pdf_url ),
			'extraFields'    => $extra_fields,
			'messageNotes'   => $notes,
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
		$total   = $this->db->count_filtered(
			array(
				'search' => $args['search'] ?? '',
				'niveau' => $args['niveau'] ?? '',
				'statut' => $args['statut'] ?? '',
			)
		);
		$output  = array();

		foreach ( $records as $row ) {
			$message = $this->decode_message_payload( $row['message'] );

			$output[] = array(
				'row'            => (int) $row['id'],
				'created_at'     => $row['created_at'],
				'timestamp'      => $row['created_at'],
				'updated_at'     => $row['updated_at'],
				'prenom'         => $row['prenom'],
				'nom'            => $row['nom'],
				'fullName'       => trim( $row['prenom'] . ' ' . $row['nom'] ),
				'dateNaissance'  => ibc_format_date_human( $row['date_naissance'] ),
				'date_naissance' => $row['date_naissance'],
				'lieuNaissance'  => $row['lieu_naissance'],
				'email'          => $row['email'],
				'telephone'      => $row['telephone'],
				'phone'          => $row['telephone'],
				'level'          => $row['niveau'],
				'message'        => $message['notes'],
				'extraFields'    => $message['extra'],
				'cinRectoUrl'    => $row['cin_recto_url'],
				'cinVersoUrl'    => $row['cin_verso_url'],
				'reference'      => $row['reference'],
				'ref'            => $row['reference'],
				'statut'         => $row['statut'],
			);
		}

		return array(
			'items' => $output,
			'total' => $total,
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
		$allowed     = array( 'prenom', 'nom', 'date_naissance', 'lieu_naissance', 'email', 'telephone', 'niveau', 'message', 'statut', 'cin_recto_url', 'cin_verso_url' );
		$data        = array();
		$new_notes   = null;
		$current_row = null;

		foreach ( $allowed as $key ) {
			if ( ! array_key_exists( $key, $fields ) ) {
				continue;
			}

			$value = $fields[ $key ];

			switch ( $key ) {
				case 'email':
					$value = ibc_normalize_email( (string) $value );
					break;
				case 'telephone':
					$value = ibc_normalize_phone( (string) $value );
					break;
				case 'date_naissance':
					$value = $this->sanitize_date_for_storage( (string) $value );
					break;
				case 'message':
					$new_notes = ibc_sanitize_textarea( (string) $value );
					continue 2;
				case 'statut':
					$value = $this->normalize_status( (string) $value );
					if ( ! $value ) {
						return new WP_Error( 'ibc_status', \__( 'Statut invalide.', 'ibc-enrollment-manager' ) );
					}
					break;
				case 'cin_recto_url':
				case 'cin_verso_url':
					$value = esc_url_raw( (string) $value );
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

		if ( null !== $new_notes ) {
			$current_row = $current_row ?? $this->db->get( $id );
			if ( $current_row ) {
				$decoded         = $this->decode_message_payload( $current_row['message'] );
				$decoded['notes'] = $new_notes;
				$data['message']  = $this->encode_message_payload( $decoded['notes'], $decoded['extra'] );
			} else {
				$data['message'] = $this->encode_message_payload( $new_notes, array() );
			}
		}

		$data['updated_at'] = ibc_now();

		return $this->db->update( $id, $data );
	}

	/**
	 * Sanitize a field value based on type.
	 *
	 * @param string $type  Field type.
	 * @param string $value Raw value.
	 *
	 * @return string
	 */
	private function sanitize_field_value( string $type, string $value ): string {
		switch ( $type ) {
			case 'email':
				return ibc_normalize_email( $value );
			case 'tel':
				return ibc_normalize_phone( $value );
			case 'date':
				$sanitized = $this->sanitize_date_for_storage( $value );
				return $sanitized ?? '';
			case 'textarea':
				return ibc_sanitize_textarea( $value );
			default:
				return sanitize_text_field( $value );
		}
	}

	/**
	 * Normalize date input to Y-m-d or null.
	 *
	 * @param string $value Raw value.
	 *
	 * @return string|null
	 */
	private function sanitize_date_for_storage( string $value ): ?string {
		$value = trim( $value );

		if ( '' === $value ) {
			return null;
		}

		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
			return $value;
		}

		if ( preg_match( '/^\d{2}\/\d{2}\/\d{4}$/', $value ) ) {
			$parts = explode( '/', $value );

			return sprintf( '%04d-%02d-%02d', (int) $parts[2], (int) $parts[1], (int) $parts[0] );
		}

		$timestamp = strtotime( $value );

		if ( false === $timestamp ) {
			return null;
		}

		return gmdate( 'Y-m-d', $timestamp );
	}

	/**
	 * Check if a select choice exists.
	 *
	 * @param string $value   Value.
	 * @param array  $choices Choices.
	 *
	 * @return bool
	 */
	private function choice_exists( string $value, array $choices ): bool {
		if ( '' === $value ) {
			return true;
		}

		foreach ( $choices as $choice ) {
			if ( ! is_array( $choice ) ) {
				$choice = array(
					'value' => (string) $choice,
					'label' => (string) $choice,
				);
			}
			if ( isset( $choice['value'] ) && (string) $choice['value'] === $value ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Retrieve the display label for a choice value.
	 *
	 * @param string $value   Value.
	 * @param array  $choices Choices.
	 *
	 * @return string
	 */
	private function choice_label( string $value, array $choices ): string {
		foreach ( $choices as $choice ) {
			if ( ! is_array( $choice ) ) {
				$choice = array(
					'value' => (string) $choice,
					'label' => (string) $choice,
				);
			}
			if ( isset( $choice['value'] ) && (string) $choice['value'] === $value ) {
				return (string) ( $choice['label'] ?? $value );
			}
		}

		return $value;
	}

	/**
	 * Encode notes and extra fields into message column.
	 *
	 * @param string $notes Notes.
	 * @param array  $extra Extra fields.
	 *
	 * @return string
	 */
	private function encode_message_payload( string $notes, array $extra ): string {
		$payload = array(
			'notes' => $notes,
			'extra' => array_values( $extra ),
		);

		return wp_json_encode( $payload );
	}

	/**
	 * Decode message column payload.
	 *
	 * @param string $raw Raw message.
	 *
	 * @return array{notes:string,extra:array}
	 */
	private function decode_message_payload( string $raw ): array {
		$data = json_decode( $raw, true );

		if ( is_array( $data ) && array_key_exists( 'extra', $data ) ) {
			return array(
				'notes' => isset( $data['notes'] ) ? ibc_sanitize_textarea( (string) $data['notes'] ) : '',
				'extra' => is_array( $data['extra'] ) ? $data['extra'] : array(),
			);
		}

		return array(
			'notes' => ibc_sanitize_textarea( $raw ),
			'extra' => array(),
		);
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
	 * Find duplicates.
	 *
	 * @param string $email Email.
	 * @param string $phone Phone.
	 *
	 * @return array{email:array|null,telephone:array|null}
	 */
	private function find_existing( string $email, string $phone ): array {
		$email_row     = null;
		$telephone_row = null;

		if ( $email ) {
			$email_row = $this->db->get_by_email( $email );
			if ( $email_row && 'Annule' === $email_row['statut'] ) {
				$email_row = null;
			}
		}

		if ( $phone ) {
			$telephone_row = $this->db->get_by_phone( $phone );
			if ( $telephone_row && 'Annule' === $telephone_row['statut'] ) {
				$telephone_row = null;
			}
		}

		return array(
			'email'      => $email_row,
			'telephone'  => $telephone_row,
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

		$allowed_extensions = array( 'jpg', 'jpeg', 'png', 'pdf' );
		$checked            = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'], array(
			'jpg'  => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'png'  => 'image/png',
			'pdf'  => 'application/pdf',
		) );

		$ext  = strtolower( $checked['ext'] ?? '' );
		$type = $checked['type'] ?? '';

		if ( empty( $ext ) || ! in_array( $ext, $allowed_extensions, true ) ) {
			return new WP_Error( 'ibc_upload_type', \__( 'Format de fichier non autorisé.', 'ibc-enrollment-manager' ) );
		}

		if ( empty( $type ) ) {
			$mime_map = array(
				'jpg'  => 'image/jpeg',
				'jpeg' => 'image/jpeg',
				'png'  => 'image/png',
				'pdf'  => 'application/pdf',
			);
			$type = $mime_map[ $ext ] ?? 'application/octet-stream';
		}

		if ( ! function_exists( 'wp_handle_upload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$uploaded = wp_handle_upload(
			$file,
			array(
				'test_form' => false,
				'mimes'     => array(
					'jpg'  => 'image/jpeg',
					'jpeg' => 'image/jpeg',
					'png'  => 'image/png',
					'pdf'  => 'application/pdf',
				),
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
