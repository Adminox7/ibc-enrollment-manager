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
		$schema           = $this->form_builder->get_active_schema();
		$allowed_columns  = array( 'prenom', 'nom', 'birth_date', 'birth_place', 'email', 'phone', 'niveau', 'message', 'cin_recto_url', 'cin_verso_url' );
		$mapped           = array_fill_keys( $allowed_columns, '' );
		$notes            = '';
		$extra_fields     = array();
		$email_value      = '';
		$phone_value      = '';
		$required_missing = array();

		foreach ( $schema as $field ) {
			$field_id = $field['id'];
			$type     = $field['type'] ?? 'text';
			$map      = sanitize_key( $field['map'] ?? '' );
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

			if ( $map && in_array( $map, $allowed_columns, true ) ) {
				if ( 'message' === $map ) {
					$notes = ibc_sanitize_textarea( $value );
				} else {
					$mapped[ $map ] = $value;
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

			if ( 'email' === $map || 'email' === $field_id ) {
				$email_value = $mapped['email'] ?: $value;
			}

			if ( 'phone' === $map || 'phone' === $field_id ) {
				$phone_value = $mapped['phone'] ?: $value;
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
		if ( empty( $mapped['prenom'] ) || empty( $mapped['nom'] ) || empty( $mapped['niveau'] ) || empty( $email_value ) || empty( $phone_value ) ) {
			return new WP_Error( 'ibc_missing_core', \__( 'Les informations principales sont incomplètes.', 'ibc-enrollment-manager' ) );
		}

		// Rate limit per email/phone combination.
		$rate_key = 'register_' . md5( $email_value . $phone_value );
		if ( ibc_rate_limit( $rate_key, 10, false ) ) {
			return new WP_Error( 'ibc_rate_limit', \__( 'Merci de patienter quelques instants avant de réessayer.', 'ibc-enrollment-manager' ) );
		}

		$capacity = ibc_get_capacity_limit();
		$total    = $this->db->count_active();
		if ( $capacity > 0 && $total >= $capacity ) {
			return new WP_Error( 'ibc_capacity_full', \__( 'Les inscriptions sont complètes pour le moment.', 'ibc-enrollment-manager' ) );
		}

		$duplicates = $this->find_existing( $email_value, $phone_value );
		if ( $duplicates['email'] || $duplicates['phone'] ) {
			return new WP_Error(
				'ibc_duplicate',
				\__( 'Une inscription existe déjà avec cet email ou ce numéro de téléphone.', 'ibc-enrollment-manager' )
			);
		}

		$reference = $this->generate_unique_reference();
		$timestamp = ibc_now();
		$full_name = trim( $mapped['prenom'] . ' ' . $mapped['nom'] );

		$row = array(
			'created_at'    => $timestamp,
			'ref'           => $reference,
			'prenom'        => $mapped['prenom'],
			'nom'           => $mapped['nom'],
			'full_name'     => $full_name,
			'birth_date'    => $mapped['birth_date'],
			'birth_place'   => $mapped['birth_place'],
			'email'         => $email_value,
			'phone'         => $phone_value,
			'niveau'        => $mapped['niveau'],
			'message'       => $this->encode_message_payload( $notes, $extra_fields ),
			'cin_recto_url' => $mapped['cin_recto_url'],
			'cin_verso_url' => $mapped['cin_verso_url'],
			'statut'        => 'Confirme',
		);

		$insert_id = $this->db->insert( $row );
		if ( ! $insert_id ) {
			return new WP_Error( 'ibc_insert_failed', \__( 'Impossible d’enregistrer votre demande. Merci de réessayer.', 'ibc-enrollment-manager' ) );
		}

		$context = array(
			'fullName'    => $full_name,
			'email'       => $email_value,
			'phone'       => $phone_value,
			'level'       => $mapped['niveau'],
			'ref'         => $reference,
			'createdAt'   => wp_date( 'd/m/Y H:i', strtotime( $timestamp ) ),
			'payDeadline' => wp_date( 'd/m/Y H:i', strtotime( '+24 hours' ) ),
			'price'       => ibc_get_price_prep() . ' MAD',
			'notes'       => $notes,
			'extra'       => $extra_fields,
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
				'notes'       => $notes,
				'extra'       => $extra_fields,
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

		$this->email->send_confirmation( $email_value, $context, $attachments );

		ibc_rate_limit( $rate_key, 10 );

		return array(
			'id'           => $insert_id,
			'ref'          => $reference,
			'createdAt'    => $timestamp,
			'status'       => 'Confirme',
			'receiptPath'  => $pdf_path,
			'receiptId'    => $pdf_mediaId,
			'downloadUrl'  => $pdf_url,
			'extraFields'  => $extra_fields,
			'messageNotes' => $notes,
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
		$output  = array();

		foreach ( $records as $row ) {
			$message = $this->decode_message_payload( $row['message'] );

			$output[] = array(
				'row'           => (int) $row['id'],
				'timestamp'     => $row['created_at'],
				'prenom'        => $row['prenom'],
				'nom'           => $row['nom'],
				'fullName'      => $row['full_name'],
				'dateNaissance' => $row['birth_date'],
				'lieuNaissance' => $row['birth_place'],
				'email'         => $row['email'],
				'phone'         => $row['phone'],
				'level'         => $row['niveau'],
				'message'       => $message['notes'],
				'extraFields'   => $message['extra'],
				'cinRectoUrl'   => $row['cin_recto_url'],
				'cinVersoUrl'   => $row['cin_verso_url'],
				'ref'           => $row['ref'],
				'statut'        => $row['statut'],
			);
		}

		return $output;
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
		$allowed   = array( 'prenom', 'nom', 'birth_date', 'birth_place', 'email', 'phone', 'niveau', 'message', 'statut', 'cin_recto_url', 'cin_verso_url' );
		$data      = array();
		$new_notes = null;

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
					$new_notes = ibc_sanitize_textarea( (string) $value );
					continue 2;
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

		if ( null !== $new_notes ) {
			$row = $row ?? $this->db->get( $id );
			if ( $row ) {
				$decoded         = $this->decode_message_payload( $row['message'] );
				$decoded['notes'] = $new_notes;
				$data['message']  = $this->encode_message_payload( $decoded['notes'], $decoded['extra'] );
			} else {
				$data['message'] = $this->encode_message_payload( $new_notes, array() );
			}
		}

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
			case 'textarea':
				return ibc_sanitize_textarea( $value );
			case 'select':
			case 'date':
			case 'text':
			default:
				return sanitize_text_field( $value );
		}
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
