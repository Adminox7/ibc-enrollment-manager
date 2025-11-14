<?php
/**
 * Front-end shortcode renderer.
 *
 * @package IBC\Enrollment
 */

declare( strict_types=1 );

namespace IBC\Enrollment\Support;

use IBC\Enrollment\Services\Registrations;
use function IBC\Enrollment\ibc_get_brand_colors_with_legacy;
use function IBC\Enrollment\ibc_get_brand_name;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Outputs the public registration form shortcode.
 */
class Shortcodes {

	public const FORM_TAG = 'ibc_enrollment_form';

	private FormBuilder $formBuilder;

	public function __construct( private Registrations $registrations ) {
		$this->formBuilder = new FormBuilder();
	}

	/**
	 * Registers the shortcode tag(s).
	 */
	public function register(): void {
		add_shortcode( self::FORM_TAG, [ $this, 'render_form' ] );
	}

	/**
	 * Callback executed by [ibc_enrollment_form].
	 *
	 * @param array $atts Shortcode attributes.
	 */
	public function render_form( array $atts = [] ): string {
		$fields = $this->formBuilder->getSchema();
		$colors = ibc_get_brand_colors_with_legacy();
		$atts   = shortcode_atts(
			[
				'title'       => sprintf( __( 'Préinscription %s', 'ibc-enrollment' ), ibc_get_brand_name() ),
				'description' => __( 'Complétez le formulaire pour réserver votre place au prochain examen.', 'ibc-enrollment' ),
				'submit_text' => __( 'Envoyer mon inscription', 'ibc-enrollment' ),
			],
			$atts,
			self::FORM_TAG
		);

		ob_start();
		?>
		<div class="ibc-form-wrapper" data-ibc-form style="<?php echo esc_attr( $this->palette_style( $colors ) ); ?>">
			<div class="ibc-form-card">
				<h2 class="ibc-form-title"><?php echo esc_html( $atts['title'] ); ?></h2>
				<p class="ibc-form-subtitle"><?php echo esc_html( $atts['description'] ); ?></p>

				<form class="ibc-form" enctype="multipart/form-data" novalidate>
					<div class="ibc-form-grid">
						<?php foreach ( $fields as $field ) : ?>
							<?php echo $this->render_field( $field ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php endforeach; ?>
					</div>

					<div class="ibc-form-actions">
						<button type="submit" class="ibc-button-primary">
							<?php echo esc_html( $atts['submit_text'] ); ?>
						</button>
					</div>

					<div class="ibc-form-feedback" hidden role="status"></div>
				</form>
			</div>

			<div class="ibc-popup" data-ibc-success hidden role="dialog" aria-modal="true" aria-labelledby="ibc-success-title">
				<div class="ibc-popup-card ibc-popup-card--success">
					<div class="ibc-popup-icon" aria-hidden="true">✅</div>
					<h3 id="ibc-success-title"><?php esc_html_e( 'Inscription réussie', 'ibc-enrollment' ); ?></h3>
					<p data-ibc-success-text><?php esc_html_e( 'Votre préinscription est enregistrée. Téléchargez votre reçu ou vérifiez vos e-mails.', 'ibc-enrollment' ); ?></p>
					<div class="ibc-popup-actions">
						<a href="#" class="ibc-button-primary is-disabled" data-ibc-download aria-disabled="true" download>
							<?php esc_html_e( 'Télécharger le reçu PDF', 'ibc-enrollment' ); ?>
						</a>
						<button type="button" class="ibc-button-secondary" data-ibc-close><?php esc_html_e( 'Fermer', 'ibc-enrollment' ); ?></button>
					</div>
				</div>
			</div>

			<div class="ibc-popup" data-ibc-closed hidden role="dialog" aria-modal="true" aria-labelledby="ibc-closed-title">
				<div class="ibc-popup-card">
					<h3 id="ibc-closed-title"><?php esc_html_e( 'Capacité atteinte', 'ibc-enrollment' ); ?></h3>
					<p><?php esc_html_e( 'Le quota est atteint pour cette session. Contactez-nous pour connaître la prochaine ouverture.', 'ibc-enrollment' ); ?></p>
					<button type="button" class="ibc-button-secondary" data-ibc-close><?php esc_html_e( 'Fermer', 'ibc-enrollment' ); ?></button>
				</div>
			</div>
		</div>
		<?php

		return (string) ob_get_clean();
	}

	/**
	 * Converts the palette into inline CSS custom properties.
	 */
	private function palette_style( array $colors ): string {
		$pairs = [
			'--ibc-form-primary'      => $colors['primary'] ?? '#4CB4B4',
			'--ibc-form-secondary'    => $colors['secondary'] ?? '#2A8E8E',
			'--ibc-form-text'         => $colors['text_dark'] ?? '#1F2937',
			'--ibc-form-muted'        => $colors['muted'] ?? '#ffffff',
			'--ibc-form-border'       => $colors['border'] ?? '#E5E7EB',
			'--ibc-form-button'       => $colors['button'] ?? '#4CB4B4',
			'--ibc-form-button-text'  => $colors['button_text'] ?? '#ffffff',
			'--ibc-form-success-bg'   => $colors['success_bg'] ?? '#D1FAE5',
			'--ibc-form-success-text' => $colors['success'] ?? '#15803D',
			'--ibc-form-error-bg'     => $colors['danger_bg'] ?? '#FEE2E2',
			'--ibc-form-error-text'   => $colors['danger'] ?? '#B91C1C',
		];

		$style = '';
		foreach ( $pairs as $var => $value ) {
			$style .= sprintf( '%s:%s;', $var, $value );
		}

		return $style;
	}

	/**
	 * Outputs a single field block.
	 *
	 * @param array<string,mixed> $field Field definition.
	 */
	private function render_field( array $field ): string {
		$id          = sanitize_key( $field['id'] ?? '' );
		$type        = $field['type'] ?? 'text';
		$required    = ! empty( $field['required'] );
		$placeholder = $field['placeholder'] ?? '';
		$help        = $field['help'] ?? '';
		$width       = 'half' === ( $field['width'] ?? 'full' ) ? 'is-half' : 'is-full';
		$map         = sanitize_key( $field['map'] ?? $id );
		$accept      = $field['accept'] ?? '';
		$help_id     = $help ? $id . '_help' : '';

		ob_start();
		?>
		<div
			class="ibc-form-field <?php echo esc_attr( $width ); ?>"
			data-ibc-field-id="<?php echo esc_attr( $id ); ?>"
			data-ibc-field-type="<?php echo esc_attr( $type ); ?>"
			data-ibc-field-required="<?php echo $required ? '1' : '0'; ?>"
			<?php echo $map ? 'data-ibc-field-map="' . esc_attr( $map ) . '"' : ''; ?>
		>
			<label for="ibc_field_<?php echo esc_attr( $id ); ?>">
				<?php echo esc_html( $field['label'] ?? ucfirst( $id ) ); ?>
				<?php if ( $required ) : ?>
					<span class="ibc-form-required" aria-hidden="true">*</span>
				<?php endif; ?>
			</label>

			<?php if ( 'textarea' === $type ) : ?>
				<textarea
					id="ibc_field_<?php echo esc_attr( $id ); ?>"
					name="<?php echo esc_attr( $id ); ?>"
					rows="4"
					<?php echo $required ? 'required' : ''; ?>
					<?php echo $help_id ? 'aria-describedby="' . esc_attr( $help_id ) . '"' : ''; ?>
					placeholder="<?php echo esc_attr( $placeholder ); ?>"
				></textarea>
			<?php elseif ( 'select' === $type ) : ?>
				<select
					id="ibc_field_<?php echo esc_attr( $id ); ?>"
					name="<?php echo esc_attr( $id ); ?>"
					<?php echo $required ? 'required' : ''; ?>
					<?php echo $help_id ? 'aria-describedby="' . esc_attr( $help_id ) . '"' : ''; ?>
				>
					<option value=""><?php echo esc_html( $placeholder ?: __( 'Sélectionnez…', 'ibc-enrollment' ) ); ?></option>
					<?php foreach ( (array) ( $field['choices'] ?? [] ) as $choice ) : ?>
						<option value="<?php echo esc_attr( (string) ( $choice['value'] ?? '' ) ); ?>">
							<?php echo esc_html( (string) ( $choice['label'] ?? $choice['value'] ?? '' ) ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			<?php elseif ( 'file' === $type ) : ?>
				<input
					type="file"
					id="ibc_field_<?php echo esc_attr( $id ); ?>"
					name="<?php echo esc_attr( $id ); ?>"
					<?php echo $required ? 'required' : ''; ?>
					<?php echo $accept ? 'accept="' . esc_attr( $accept ) . '"' : ''; ?>
					<?php echo $help_id ? 'aria-describedby="' . esc_attr( $help_id ) . '"' : ''; ?>
				>
			<?php else : ?>
				<input
					type="<?php echo esc_attr( $this->input_type( $type ) ); ?>"
					id="ibc_field_<?php echo esc_attr( $id ); ?>"
					name="<?php echo esc_attr( $id ); ?>"
					<?php echo $required ? 'required' : ''; ?>
					placeholder="<?php echo esc_attr( $placeholder ); ?>"
					<?php echo $help_id ? 'aria-describedby="' . esc_attr( $help_id ) . '"' : ''; ?>
				>
			<?php endif; ?>

			<?php if ( $help ) : ?>
				<p class="ibc-form-help" id="<?php echo esc_attr( $help_id ); ?>"><?php echo esc_html( $help ); ?></p>
			<?php endif; ?>
		</div>
		<?php

		return (string) ob_get_clean();
	}

	/**
	 * Maps schema type to HTML input type.
	 */
	private function input_type( string $type ): string {
		return match ( $type ) {
			'email' => 'email',
			'tel'   => 'tel',
			'date'  => 'date',
			default => 'text',
		};
	}
}
