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
