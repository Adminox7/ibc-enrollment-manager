<?php
/**
 * Form Builder service.
 *
 * @package IBC\EnrollmentManager
 */

namespace IBC;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FormBuilder
 *
 * Manages the dynamic form schema used by the public shortcode and admin tools.
 */
class FormBuilder {

	/**
	 * Option key storing the schema.
	 *
	 * @var string
	 */
	private const OPTION_SCHEMA = 'ibc_form_schema';

	/**
	 * Allowed field types.
	 *
	 * @var array<string,array<string,mixed>>
	 */
	private array $field_types = array(
		'text' => array(
			'label'          => 'Texte',
			'supports_placeholder' => true,
		),
		'email' => array(
			'label'          => 'Email',
			'supports_placeholder' => true,
		),
		'tel' => array(
			'label'          => 'Téléphone',
			'supports_placeholder' => true,
		),
		'date' => array(
			'label'          => 'Date',
			'supports_placeholder' => false,
		),
		'textarea' => array(
			'label'          => 'Zone de texte',
			'supports_placeholder' => true,
		),
		'select' => array(
			'label'          => 'Liste déroulante',
			'supports_placeholder' => false,
			'supports_choices' => true,
		),
		'file' => array(
			'label'          => 'Fichier',
			'supports_placeholder' => false,
			'supports_accept' => true,
		),
	);

	/**
	 * Retrieve the persisted schema (fallback to default when empty).
	 *
	 * @return array
	 */
	public function get_schema(): array {
		$schema = get_option( self::OPTION_SCHEMA, array() );

		if ( is_string( $schema ) ) {
			$decoded = json_decode( $schema, true );
			$schema  = is_array( $decoded ) ? $decoded : array();
		}

		if ( ! is_array( $schema ) || empty( $schema ) ) {
			return $this->get_default_schema();
		}

		$sanitized = $this->sanitize_schema( $schema );

		return empty( $sanitized ) ? $this->get_default_schema() : $sanitized;
	}

	/**
	 * Persist the schema.
	 *
	 * @param array $schema Schema array.
	 *
	 * @return void
	 */
	public function save_schema( array $schema ): void {
		$sanitized = $this->sanitize_schema( $schema );
		update_option( self::OPTION_SCHEMA, $sanitized );
	}

	/**
	 * Sanitize raw schema.
	 *
	 * @param array $fields Raw fields array.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function sanitize_schema( array $fields ): array {
		$allowed_types = array_keys( $this->field_types );
		$sanitized     = array();
		$used_ids      = array();
		$order_step    = 10;
		$order         = $order_step;

		foreach ( $fields as $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}

			$id = isset( $field['id'] ) ? $this->normalize_id( (string) $field['id'] ) : '';
			if ( '' === $id ) {
				continue;
			}

			if ( isset( $used_ids[ $id ] ) ) {
				$increment = 2;
				$base      = $id;
				while ( isset( $used_ids[ $base . '_' . $increment ] ) ) {
					$increment++;
				}
				$id = $base . '_' . $increment;
			}

			$type = isset( $field['type'] ) ? strtolower( (string) $field['type'] ) : 'text';
			if ( ! in_array( $type, $allowed_types, true ) ) {
				$type = 'text';
			}

			$choices = array();
			if ( 'select' === $type ) {
				$choices = $this->sanitize_choices( $field['choices'] ?? array() );
			}

			$accept = '';
			if ( 'file' === $type ) {
				$accept = sanitize_text_field( $field['accept'] ?? '.jpg,.jpeg,.png,.pdf' );
			}

			$sanitized_field = array(
				'id'          => $id,
				'label'       => $this->sanitize_label( $field['label'] ?? '' ),
				'placeholder' => sanitize_text_field( $field['placeholder'] ?? '' ),
				'type'        => $type,
				'width'       => $this->sanitize_width( $field['width'] ?? 'full' ),
				'required'    => ! empty( $field['required'] ),
				'active'      => array_key_exists( 'active', $field ) ? (bool) $field['active'] : true,
				'help'        => sanitize_text_field( $field['help'] ?? '' ),
				'choices'     => $choices,
				'accept'      => $accept,
				'order'       => $order,
				'locked'      => ! empty( $field['locked'] ),
				'map'         => $this->sanitize_map( $field['map'] ?? '' ),
				'default'     => sanitize_text_field( $field['default'] ?? '' ),
			);

			if ( '' === $sanitized_field['label'] ) {
				$sanitized_field['label'] = ucwords( str_replace( array( '_', '-' ), ' ', $id ) );
			}

			$sanitized[]       = $sanitized_field;
			$used_ids[ $id ]   = true;
			$order            += $order_step;
		}

		usort(
			$sanitized,
			static function ( array $a, array $b ): int {
				return ( $a['order'] ?? 0 ) <=> ( $b['order'] ?? 0 );
			}
		);

		// Normalize order values after sorting.
		$order = $order_step;
		foreach ( $sanitized as $index => $field ) {
			$sanitized[ $index ]['order'] = $order;
			$order                       += $order_step;
		}

		return $sanitized;
	}

	/**
	 * Retrieve metadata for field types.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function get_field_types(): array {
		$types = $this->field_types;

		$types['text']['label']     = __( 'Texte', 'ibc-enrollment-manager' );
		$types['email']['label']    = __( 'Email', 'ibc-enrollment-manager' );
		$types['tel']['label']      = __( 'Téléphone', 'ibc-enrollment-manager' );
		$types['date']['label']     = __( 'Date', 'ibc-enrollment-manager' );
		$types['textarea']['label'] = __( 'Zone de texte', 'ibc-enrollment-manager' );
		$types['select']['label']   = __( 'Liste déroulante', 'ibc-enrollment-manager' );
		$types['file']['label']     = __( 'Fichier', 'ibc-enrollment-manager' );

		return $types;
	}

	/**
	 * Retrieve only active fields ordered for frontend usage.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function get_active_schema(): array {
		$schema = $this->get_schema();

		return array_values(
			array_filter(
				$schema,
				static function ( array $field ): bool {
					return ! empty( $field['active'] );
				}
			)
		);
	}

	/**
	 * Schema exposed to the frontend (only useful keys).
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function get_public_schema(): array {
		return array_map(
			static function ( array $field ): array {
				return array(
					'id'          => $field['id'],
					'label'       => $field['label'],
					'type'        => $field['type'],
					'placeholder' => $field['placeholder'],
					'required'    => (bool) $field['required'],
					'width'       => $field['width'],
					'choices'     => $field['choices'],
					'accept'      => $field['accept'],
					'help'        => $field['help'],
					'map'         => $field['map'],
					'default'     => $field['default'],
				);
			},
			$this->get_active_schema()
		);
	}

	/**
	 * Provide default schema replicating historical form.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function get_default_schema(): array {
		return array(
			array(
				'id'          => 'prenom',
				'label'       => __( 'Prénom', 'ibc-enrollment-manager' ),
				'placeholder' => __( 'Votre prénom', 'ibc-enrollment-manager' ),
				'type'        => 'text',
				'width'       => 'half',
				'required'    => true,
				'active'      => true,
				'help'        => '',
				'choices'     => array(),
				'accept'      => '',
				'order'       => 10,
				'locked'      => true,
				'map'         => 'prenom',
				'default'     => '',
			),
			array(
				'id'          => 'nom',
				'label'       => __( 'Nom', 'ibc-enrollment-manager' ),
				'placeholder' => __( 'Votre nom', 'ibc-enrollment-manager' ),
				'type'        => 'text',
				'width'       => 'half',
				'required'    => true,
				'active'      => true,
				'help'        => '',
				'choices'     => array(),
				'accept'      => '',
				'order'       => 20,
				'locked'      => true,
				'map'         => 'nom',
				'default'     => '',
			),
			array(
				'id'          => 'email',
				'label'       => __( 'Email', 'ibc-enrollment-manager' ),
				'placeholder' => __( 'adresse@email.com', 'ibc-enrollment-manager' ),
				'type'        => 'email',
				'width'       => 'half',
				'required'    => true,
				'active'      => true,
				'help'        => '',
				'choices'     => array(),
				'accept'      => '',
				'order'       => 30,
				'locked'      => true,
				'map'         => 'email',
				'default'     => '',
			),
			array(
				'id'          => 'phone',
				'label'       => __( 'Téléphone', 'ibc-enrollment-manager' ),
				'placeholder' => __( 'Ex: +212 612-345678', 'ibc-enrollment-manager' ),
				'type'        => 'tel',
				'width'       => 'half',
				'required'    => true,
				'active'      => true,
				'help'        => '',
				'choices'     => array(),
				'accept'      => '',
				'order'       => 40,
				'locked'      => true,
				'map'         => 'phone',
				'default'     => '',
			),
			array(
				'id'          => 'birth_date',
				'label'       => __( 'Date de naissance', 'ibc-enrollment-manager' ),
				'placeholder' => '',
				'type'        => 'date',
				'width'       => 'half',
				'required'    => false,
				'active'      => true,
				'help'        => '',
				'choices'     => array(),
				'accept'      => '',
				'order'       => 50,
				'locked'      => true,
				'map'         => 'birth_date',
				'default'     => '',
			),
			array(
				'id'          => 'birth_place',
				'label'       => __( 'Lieu de naissance', 'ibc-enrollment-manager' ),
				'placeholder' => __( 'Ville, pays', 'ibc-enrollment-manager' ),
				'type'        => 'text',
				'width'       => 'half',
				'required'    => false,
				'active'      => true,
				'help'        => '',
				'choices'     => array(),
				'accept'      => '',
				'order'       => 60,
				'locked'      => true,
				'map'         => 'birth_place',
				'default'     => '',
			),
			array(
				'id'          => 'niveau',
				'label'       => __( 'Niveau souhaité', 'ibc-enrollment-manager' ),
				'placeholder' => '',
				'type'        => 'select',
				'width'       => 'full',
				'required'    => true,
				'active'      => true,
				'help'        => '',
				'choices'     => array_map(
					static function ( string $level ): array {
						return array(
							'value' => $level,
							'label' => $level,
						);
					},
					array( 'A1', 'A2', 'B1', 'B2', 'C1', 'C2' )
				),
				'accept'      => '',
				'order'       => 70,
				'locked'      => true,
				'map'         => 'niveau',
				'default'     => '',
			),
			array(
				'id'          => 'message',
				'label'       => __( 'Message', 'ibc-enrollment-manager' ),
				'placeholder' => __( 'Précisions éventuelles…', 'ibc-enrollment-manager' ),
				'type'        => 'textarea',
				'width'       => 'full',
				'required'    => false,
				'active'      => true,
				'help'        => '',
				'choices'     => array(),
				'accept'      => '',
				'order'       => 80,
				'locked'      => false,
				'map'         => 'message',
				'default'     => '',
			),
			array(
				'id'          => 'cin_recto',
				'label'       => __( 'CIN / Passeport (recto)', 'ibc-enrollment-manager' ),
				'placeholder' => '',
				'type'        => 'file',
				'width'       => 'half',
				'required'    => false,
				'active'      => true,
				'help'        => __( 'Formats acceptés : JPG, PNG, PDF', 'ibc-enrollment-manager' ),
				'choices'     => array(),
				'accept'      => '.jpg,.jpeg,.png,.pdf',
				'order'       => 90,
				'locked'      => false,
				'map'         => 'cin_recto_url',
				'default'     => '',
			),
			array(
				'id'          => 'cin_verso',
				'label'       => __( 'CIN / Passeport (verso)', 'ibc-enrollment-manager' ),
				'placeholder' => '',
				'type'        => 'file',
				'width'       => 'half',
				'required'    => false,
				'active'      => true,
				'help'        => __( 'Formats acceptés : JPG, PNG, PDF', 'ibc-enrollment-manager' ),
				'choices'     => array(),
				'accept'      => '.jpg,.jpeg,.png,.pdf',
				'order'       => 100,
				'locked'      => false,
				'map'         => 'cin_verso_url',
				'default'     => '',
			),
		);
	}

	/**
	 * Normalize field identifier.
	 *
	 * @param string $value Raw value.
	 *
	 * @return string
	 */
	private function normalize_id( string $value ): string {
		$value = strtolower( $value );
		$value = preg_replace( '/[^a-z0-9_\-]/', '_', $value );
		$value = preg_replace( '/_{2,}/', '_', $value );

		return trim( $value, '_-' );
	}

	/**
	 * Sanitize label (preserve accents).
	 *
	 * @param string $label Label.
	 *
	 * @return string
	 */
	private function sanitize_label( string $label ): string {
		$label = wp_strip_all_tags( $label );

		return trim( $label );
	}

	/**
	 * Sanitize layout width property.
	 *
	 * @param string $width Width value.
	 *
	 * @return string
	 */
	private function sanitize_width( string $width ): string {
		$width = strtolower( $width );

		if ( ! in_array( $width, array( 'full', 'half' ), true ) ) {
			$width = 'full';
		}

		return $width;
	}

	/**
	 * Sanitize select choices.
	 *
	 * @param mixed $raw Raw value.
	 *
	 * @return array<int,array<string,string>>
	 */
	private function sanitize_choices( $raw ): array {
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$choices = array();

		foreach ( $raw as $entry ) {
			if ( is_string( $entry ) ) {
				$value = trim( $entry );
				if ( '' === $value ) {
					continue;
				}
				$choices[] = array(
					'value' => $value,
					'label' => $value,
				);
				continue;
			}

			if ( is_array( $entry ) ) {
				$value = sanitize_text_field( $entry['value'] ?? '' );
				$label = sanitize_text_field( $entry['label'] ?? $value );

				if ( '' === $value ) {
					continue;
				}

				$choices[] = array(
					'value' => $value,
					'label' => $label ?: $value,
				);
			}
		}

		return $choices;
	}

	/**
	 * Sanitize map (db column).
	 *
	 * @param string $map Raw map.
	 *
	 * @return string
	 */
	private function sanitize_map( string $map ): string {
		$map = $this->normalize_id( $map );

		return str_replace( '-', '_', $map );
	}
}
