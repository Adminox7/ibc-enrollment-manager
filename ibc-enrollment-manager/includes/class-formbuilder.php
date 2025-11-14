<?php
/**
 * Minimal form schema service (fixed fields matching design).
 *
 * @package IBC\Enrollment
 */

declare( strict_types=1 );

namespace IBC\Enrollment\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides the canonical schema used by the shortcode + REST validation.
 */
class FormBuilder {

	/**
	 * Returns the fixed schema describing every field.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function getSchema(): array {
		return [
			$this->field( 'prenom', __( 'Prénom', 'ibc-enrollment' ), 'text', required: true, width: 'half', placeholder: __( 'Votre prénom', 'ibc-enrollment' ), map: 'prenom', locked: true ),
			$this->field( 'nom', __( 'Nom', 'ibc-enrollment' ), 'text', required: true, width: 'half', placeholder: __( 'Votre nom', 'ibc-enrollment' ), map: 'nom', locked: true ),
			$this->field( 'date_naissance', __( 'Date de naissance', 'ibc-enrollment' ), 'date', required: true, width: 'half', map: 'date_naissance', locked: true ),
			$this->field( 'lieu_naissance', __( 'Lieu de naissance', 'ibc-enrollment' ), 'text', required: true, width: 'half', placeholder: __( 'Ville, pays', 'ibc-enrollment' ), map: 'lieu_naissance', locked: true ),
			$this->field( 'email', __( 'Adresse email', 'ibc-enrollment' ), 'email', required: true, width: 'half', placeholder: __( 'exemple@email.com', 'ibc-enrollment' ), map: 'email', locked: true ),
			$this->field( 'telephone', __( 'Téléphone', 'ibc-enrollment' ), 'tel', required: true, width: 'half', placeholder: __( '+212 6 XX XX XX XX', 'ibc-enrollment' ), map: 'telephone', locked: true ),
			[
				'id'          => 'niveau',
				'label'       => __( 'Niveau souhaité', 'ibc-enrollment' ),
				'type'        => 'select',
				'choices'     => array_map(
					static fn ( string $level ): array => [
						'value' => $level,
						'label' => $level,
					],
					[ 'A1', 'A2', 'B1', 'B2', 'C1' ]
				),
				'placeholder' => __( 'Choisissez votre niveau', 'ibc-enrollment' ),
				'required'    => true,
				'width'       => 'full',
				'map'         => 'niveau',
				'locked'      => true,
			],
			$this->field( 'message', __( 'Message (optionnel)', 'ibc-enrollment' ), 'textarea', required: false, width: 'full', placeholder: __( 'Ajoutez une précision si nécessaire', 'ibc-enrollment' ), map: 'message' ),
			$this->fileField( 'cin_recto', __( 'CIN Recto', 'ibc-enrollment' ), map: 'cin_recto' ),
			$this->fileField( 'cin_verso', __( 'CIN Verso', 'ibc-enrollment' ), map: 'cin_verso' ),
		];
	}

	/**
	 * Schema tailored for front-end consumption (only relevant keys).
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function getPublicSchema(): array {
		return array_map(
			static fn ( array $field ): array => [
				'id'          => $field['id'],
				'label'       => $field['label'],
				'type'        => $field['type'],
				'placeholder' => $field['placeholder'] ?? '',
				'required'    => ! empty( $field['required'] ),
				'width'       => $field['width'],
				'choices'     => $field['choices'] ?? [],
				'accept'      => $field['accept'] ?? '',
				'help'        => $field['help'] ?? '',
			],
			$this->getSchema()
		);
	}

	/**
	 * Returns normalized field definition.
	 *
	 * @param string $id          Field identifier.
	 * @param string $label       Display label.
	 * @param string $type        Field type.
	 * @param bool   $required    Whether field is mandatory.
	 * @param string $width       Layout width.
	 * @param string $placeholder Optional placeholder.
	 * @param string $map         Optional data map.
	 * @param bool   $locked      Whether field can be edited from UI.
	 * @return array<string,mixed>
	 */
	private function field(
		string $id,
		string $label,
		string $type,
		bool $required = true,
		string $width = 'full',
		string $placeholder = '',
		string $map = '',
		bool $locked = false
	): array {
		return [
			'id'          => $id,
			'label'       => $label,
			'type'        => $type,
			'required'    => $required,
			'width'       => $width,
			'placeholder' => $placeholder,
			'choices'     => [],
			'accept'      => '',
			'map'         => $map ?: $id,
			'locked'      => $locked,
		];
	}

	/**
	 * Helper for file inputs.
	 *
	 * @param string $id    Identifier.
	 * @param string $label Label.
	 * @param string $map   Map key.
	 * @return array<string,mixed>
	 */
	private function fileField( string $id, string $label, string $map ): array {
		return [
			'id'          => $id,
			'label'       => $label,
			'type'        => 'file',
			'required'    => true,
			'width'       => 'half',
			'accept'      => '.jpg,.jpeg,.png,.pdf',
			'help'        => __( 'Formats acceptés : JPG, PNG, PDF', 'ibc-enrollment' ),
			'map'         => $map,
			'locked'      => true,
		];
	}
}
