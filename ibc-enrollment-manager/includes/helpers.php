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
 * Retrieve an option value with default.
 *
 * @param string $key     Option key.
 * @param mixed  $default Default value.
 *
 * @return mixed
 */
function ibc_get_option( string $key, $default = null ) {
	$value = get_option( $key, $default );

	return null === $value ? $default : $value;
}

/**
 * Update a simple option value.
 *
 * @param string $key   Option key.
 * @param mixed  $value Value.
 *
 * @return void
 */
function ibc_update_option( string $key, $value ): void {
	update_option( $key, $value );
}

/**
 * Retrieve capacity limit option.
 *
 * @return int
 */
function ibc_get_capacity_limit(): int {
	return (int) ibc_get_option( 'ibc_capacity_limit', 1066 );
}

/**
 * Retrieve price option.
 *
 * @return int
 */
function ibc_get_price_prep(): int {
	return (int) ibc_get_option( 'ibc_price_prep', 1000 );
}

/**
 * Retrieve configured brand colors.
 *
 * @return array{primary:string,secondary:string,text:string,muted:string,border:string,button:string,button_text:string,success_bg:string,success_text:string,error_bg:string,error_text:string}
 */
function ibc_get_brand_colors(): array {
	$defaults = array(
		'primary'      => '#e94162',
		'secondary'    => '#0f172a',
		'text'         => '#1f2937',
		'muted'        => '#f8fafc',
		'border'       => '#e2e8f0',
		'button'       => '#e94162',
		'button_text'  => '#ffffff',
		'success_bg'   => '#dcfce7',
		'success_text' => '#166534',
		'error_bg'     => '#fee2e2',
		'error_text'   => '#991b1b',
	);

	$keys = array_keys( $defaults );
	$colors = array();

	foreach ( $keys as $key ) {
		$option_key      = 'ibc_brand_' . $key;
		$value           = get_option( $option_key, $defaults[ $key ] );
		$sanitized       = sanitize_hex_color( $value );
		$colors[ $key ]  = $sanitized ? $sanitized : (string) $value;

		if ( empty( $colors[ $key ] ) ) {
			$colors[ $key ] = $defaults[ $key ];
		}
	}

	return array_merge( $defaults, $colors );
}

/**
 * Merge legacy brand colors array into new options if available.
 *
 * @return array
 */
function ibc_get_brand_colors_with_legacy(): array {
	$colors = ibc_get_brand_colors();
	$legacy = get_option( 'ibc_brand_colors', array() );

	if ( is_array( $legacy ) ) {
		$map = array(
			'primary'   => 'primary',
			'secondary' => 'secondary',
			'text'      => 'text',
			'muted'     => 'muted',
			'border'    => 'border',
		);

		foreach ( $map as $legacy_key => $new_key ) {
			if ( ! empty( $legacy[ $legacy_key ] ) && sanitize_hex_color( $legacy[ $legacy_key ] ) ) {
				$colors[ $new_key ] = sanitize_hex_color( $legacy[ $legacy_key ] );
			}
		}
	}

	return $colors;
}

/**
 * Retrieve a specific brand color.
 *
 * @param string $key Color key.
 *
 * @return string
 */
function ibc_get_brand_color( string $key ): string {
	$colors = ibc_get_brand_colors_with_legacy();

	return $colors[ $key ] ?? '';
}

/**
 * Retrieve payment details.
 *
 * @return array<string,string>
 */
function ibc_get_payment_details(): array {
	$fields = array(
		'bank_name'      => '',
		'account_holder' => '',
		'rib'            => '',
		'iban'           => '',
		'bic'            => '',
		'agency'         => '',
		'payment_note'   => __( 'Paiement non remboursable, à effectuer sous 24h.', 'ibc-enrollment-manager' ),
	);

	foreach ( $fields as $key => $default ) {
		$option_key         = 'ibc_' . $key;
		$fields[ $key ]     = (string) get_option( $option_key, $default );
	}

	$legacy_map = array(
		'bank_name'      => 'ibc_brand_bankName',
		'account_holder' => 'ibc_brand_accountHolder',
		'rib'            => 'ibc_brand_rib',
		'iban'           => 'ibc_brand_iban',
		'bic'            => 'ibc_brand_bic',
		'agency'         => 'ibc_brand_agency',
		'payment_note'   => 'ibc_brand_paymentNote',
	);

	foreach ( $legacy_map as $key => $legacy_option ) {
		if ( empty( $fields[ $key ] ) ) {
			$fields[ $key ] = (string) get_option( $legacy_option, $fields[ $key ] );
		}
	}

	return $fields;
}

/**
 * Sanitize textarea input.
 *
 * @param string $text Raw text.
 *
 * @return string
 */
function ibc_sanitize_textarea( string $text ): string {
	return trim( wp_kses_post( $text ) );
}

/**
 * Normalize email value.
 *
 * @param string $email Email value.
 *
 * @return string
 */
function ibc_normalize_email( string $email ): string {
	return strtolower( sanitize_email( $email ) );
}

/**
 * Normalize phone to +212 pattern when possible.
 *
 * @param string $phone Phone input.
 *
 * @return string
 */
function ibc_normalize_phone( string $phone ): string {
	$digits = preg_replace( '/[^0-9\+]/', '', $phone );

	if ( empty( $digits ) ) {
		return '';
	}

	if ( str_starts_with( $digits, '+212' ) ) {
		return '+212' . substr( $digits, 4 );
	}

	if ( str_starts_with( $digits, '0' ) && preg_match( '/^0[5-7][0-9]{8}$/', $digits ) ) {
		return '+212' . substr( $digits, 1 );
	}

	if ( str_starts_with( $digits, '212' ) && strlen( $digits ) >= 11 ) {
		return '+' . $digits;
	}

	return $digits;
}

/**
 * Generate registration reference.
 *
 * @return string
 */
function ibc_generate_reference(): string {
	$date  = wp_date( 'Ymd' );
	$token = strtoupper( substr( wp_generate_password( 8, false, false ), 0, 4 ) );

	return sprintf( 'IBC-%s-%s', $date, $token );
}

/**
 * Ensure plugin uploads directory exists.
 *
 * @return array{path:string,url:string}
 */
function ibc_get_upload_dir(): array {
	$upload_dir = wp_upload_dir();
	$path       = trailingslashit( $upload_dir['basedir'] ) . 'ibc-enrollment';
	$url        = trailingslashit( $upload_dir['baseurl'] ) . 'ibc-enrollment';

	if ( ! file_exists( $path ) ) {
		wp_mkdir_p( $path );
	}

	return array(
		'path' => $path,
		'url'  => $url,
	);
}

/**
 * Detect shortcode on post content.
 *
 * @param string          $shortcode Shortcode tag.
 * @param int|\WP_Post|null $post Optional post.
 *
 * @return bool
 */
function ibc_has_shortcode( string $shortcode, $post = null ): bool {
	$post = $post ? get_post( $post ) : get_post();

	if ( ! $post instanceof \WP_Post ) {
		return false;
	}

	if ( has_shortcode( (string) $post->post_content, $shortcode ) ) {
		return true;
	}

	return (bool) apply_filters( 'ibc_has_shortcode', false, $shortcode, $post );
}

/**
 * Simple rate limiting helper.
 *
 * @param string $key     Rate key.
 * @param int    $seconds Seconds.
 *
 * @return bool True when blocked.
 */
function ibc_rate_limit( string $key, int $seconds ): bool {
	$cache_key = 'ibc_rl_' . md5( $key );

	if ( get_transient( $cache_key ) ) {
		return true;
	}

	set_transient( $cache_key, 1, $seconds );

	return false;
}

/**
 * Requester IP.
 *
 * @return string
 */
function ibc_get_request_ip(): string {
	$keys = array( 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' );

	foreach ( $keys as $key ) {
		if ( ! empty( $_SERVER[ $key ] ) ) {
			$value = sanitize_text_field( wp_unslash( (string) $_SERVER[ $key ] ) );
			$ips   = explode( ',', $value );

			return trim( $ips[0] );
		}
	}

	return '';
}

/**
 * Current datetime in MySQL format.
 *
 * @return string
 */
function ibc_now(): string {
	return current_time( 'mysql' );
}
