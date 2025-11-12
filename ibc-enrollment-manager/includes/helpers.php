<?php
/**
 * Helper functions for IBC Enrollment Manager.
 *
 * @package IBC\EnrollmentManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Retrieve plugin options.
 *
 * @return array
 */
function ibc_get_settings(): array {
	$defaults = array(
		'smtp_host'            => '',
		'smtp_port'            => '',
		'smtp_username'        => '',
		'smtp_password'        => '',
		'smtp_secure'          => '',
		'email_from_name'      => '',
		'email_from_address'   => '',
		'recaptcha_site_key'   => '',
		'recaptcha_secret_key' => '',
		'whatsapp_business_id' => '',
		'whatsapp_token'       => '',
		'whatsapp_template'    => '',
		'stripe_public_key'    => '',
		'stripe_secret_key'    => '',
		'cmi_merchant_id'      => '',
		'cmi_secret'           => '',
		'delete_on_uninstall'  => 'no',
	);

	$settings = get_option( 'ibc_enrollment_settings', array() );

	if ( ! is_array( $settings ) ) {
		$settings = array();
	}

	return wp_parse_args( $settings, $defaults );
}

/**
 * Get single setting value.
 *
 * @param string $key     Setting key.
 * @param mixed  $default Default value.
 *
 * @return mixed
 */
function ibc_get_setting( string $key, $default = '' ) {
	$settings = ibc_get_settings();

	return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
}

/**
 * Update plugin settings.
 *
 * @param array $values Values.
 *
 * @return void
 */
function ibc_update_settings( array $values ): void {
	$settings = ibc_get_settings();
	update_option( 'ibc_enrollment_settings', array_merge( $settings, $values ) );
}

/**
 * Sanitize phone numbers.
 *
 * @param string $phone Raw phone.
 *
 * @return string
 */
function ibc_sanitize_phone( string $phone ): string {
	$phone = preg_replace( '/[^\d\+]/', '', $phone );

	return substr( $phone, 0, 25 );
}

/**
 * Sanitize textarea content.
 *
 * @param string $value Raw value.
 *
 * @return string
 */
function ibc_sanitize_textarea( string $value ): string {
	return wp_strip_all_tags( $value );
}

/**
 * Format currency amount.
 *
 * @param float  $amount  Amount.
 * @param string $currency Currency code.
 *
 * @return string
 */
function ibc_format_currency( float $amount, string $currency = 'MAD' ): string {
	$currency = strtoupper( $currency );

	return sprintf( '%s %s', number_format_i18n( $amount, 2 ), $currency );
}

/**
 * Get formatted datetime.
 *
 * @param string      $datetime Datetime string.
 * @param string|null $format   Format.
 *
 * @return string
 */
function ibc_format_datetime( string $datetime, ?string $format = null ): string {
	if ( empty( $datetime ) || '0000-00-00 00:00:00' === $datetime ) {
		return '';
	}

	$timestamp = strtotime( $datetime );
	if ( ! $timestamp ) {
		return '';
	}

	if ( empty( $format ) ) {
		$format = get_option( 'date_format', 'd/m/Y' ) . ' ' . get_option( 'time_format', 'H:i' );
	}

	return esc_html( wp_date( $format, $timestamp ) );
}

/**
 * Get the current timestamp in MySQL format.
 *
 * @return string
 */
function ibc_current_time(): string {
	return current_time( 'mysql' );
}

/**
 * Verify capability.
 *
 * @param string $cap Capability.
 *
 * @return bool
 */
function ibc_current_user_can( string $cap ): bool {
	return current_user_can( $cap );
}

/**
 * Verify Google reCAPTCHA v3 token.
 *
 * @param string $token Token from front-end.
 *
 * @return bool
 */
function ibc_verify_recaptcha( string $token ): bool {
	$secret = ibc_get_setting( 'recaptcha_secret_key', '' );
	if ( empty( $secret ) ) {
		return true;
	}

	$response = wp_remote_post(
		'https://www.google.com/recaptcha/api/siteverify',
		array(
			'timeout' => 10,
			'body'    => array(
				'secret'   => $secret,
				'response' => sanitize_text_field( $token ),
				'remoteip' => rest_is_ip_address( $_SERVER['REMOTE_ADDR'] ?? '' ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
			),
		)
	);

	if ( is_wp_error( $response ) ) {
		return false;
	}

	$body = wp_remote_retrieve_body( $response );
	if ( empty( $body ) ) {
		return false;
	}

	$data = json_decode( $body, true );
	if ( empty( $data ) || ! isset( $data['success'] ) ) {
		return false;
	}

	return (bool) $data['success'];
}

/**
 * Prepare array value.
 *
 * @param array  $array   Array.
 * @param string $key     Key.
 * @param mixed  $default Default value.
 *
 * @return mixed
 */
function ibc_array_get( array $array, string $key, $default = null ) {
	return isset( $array[ $key ] ) ? $array[ $key ] : $default;
}

/**
 * Normalize phone number for WhatsApp (E.164).
 *
 * @param string $phone Phone.
 *
 * @return string
 */
function ibc_format_phone_for_whatsapp( string $phone ): string {
	$phone = preg_replace( '/\D+/', '', $phone );

	if ( empty( $phone ) ) {
		return '';
	}

	if ( 0 === strpos( $phone, '0' ) ) {
		$phone = '212' . substr( $phone, 1 );
	}

	if ( 0 !== strpos( $phone, '212' ) && 0 !== strpos( $phone, '33' ) && 0 !== strpos( $phone, '1' ) ) {
		$phone = '212' . ltrim( $phone, '0' );
	}

	return '+' . $phone;
}

/**
 * Send WhatsApp template message via Cloud API.
 *
 * @param string $phone       Recipient phone (E.164 or raw).
 * @param array  $parameters  Template parameters.
 *
 * @return bool
 */
function ibc_send_whatsapp_template( string $phone, array $parameters = array() ): bool {
	$settings = ibc_get_settings();

	if (
		empty( $settings['whatsapp_business_id'] ) ||
		empty( $settings['whatsapp_token'] ) ||
		empty( $settings['whatsapp_template'] )
	) {
		return false;
	}

	$recipient = ibc_format_phone_for_whatsapp( $phone );
	if ( empty( $recipient ) ) {
		return false;
	}

	$endpoint = sprintf(
		'https://graph.facebook.com/v17.0/%s/messages',
		rawurlencode( $settings['whatsapp_business_id'] )
	);

	$components = array(
		array(
			'type'       => 'body',
			'parameters' => array(),
		),
	);

	foreach ( $parameters as $value ) {
		$components[0]['parameters'][] = array(
			'type' => 'text',
			'text' => (string) $value,
		);
	}

	$body = array(
		'messaging_product' => 'whatsapp',
		'to'                => $recipient,
		'type'              => 'template',
		'template'          => array(
			'name'      => $settings['whatsapp_template'],
			'language'  => array(
				'code' => 'fr',
			),
			'components'=> $components,
		),
	);

	$response = wp_remote_post(
		$endpoint,
		array(
			'timeout' => 15,
			'headers' => array(
				'Authorization' => 'Bearer ' . $settings['whatsapp_token'],
				'Content-Type'  => 'application/json',
			),
			'body'    => wp_json_encode( $body ),
		)
	);

	if ( is_wp_error( $response ) ) {
		return false;
	}

	$code = wp_remote_retrieve_response_code( $response );

	return $code >= 200 && $code < 300;
}
