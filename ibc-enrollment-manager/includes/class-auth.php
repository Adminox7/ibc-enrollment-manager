<?php
/**
 * Authentication utilities.
 *
 * @package IBC\EnrollmentManager
 */

namespace IBC;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Auth
 */
class Auth {

	/**
	 * Transient TTL for tokens.
	 */
	private const TOKEN_TTL = 7200; // 2 hours.

	/**
	 * Attempt login with password.
	 *
	 * @param string $password Password.
	 *
	 * @return array{success:bool,message?:string,token?:string,ttl?:int}
	 */
	public function login( string $password ): array {
		$password = trim( $password );

		if ( '' === $password ) {
			return array(
				'success' => false,
				'message' => \__( 'Mot de passe manquant.', 'ibc-enrollment-manager' ),
			);
		}

		$hash = (string) get_option( 'ibc_admin_password_hash', '' );
		if ( '' !== $hash && password_verify( $password, $hash ) ) {
			return $this->issue_token();
		}

		$legacy = (string) get_option( 'ibc_admin_password_plain', '' );
		if ( '' !== $legacy && hash_equals( $legacy, $password ) ) {
			return $this->issue_token();
		}

		return array(
			'success' => false,
			'message' => \__( 'Mot de passe invalide.', 'ibc-enrollment-manager' ),
		);
	}

	/**
	 * Validate token.
	 *
	 * @param string $token Token.
	 *
	 * @return bool
	 */
	public function validate( string $token ): bool {
		if ( empty( $token ) ) {
			return false;
		}

		$key = $this->transient_key( $token );

		return false !== get_transient( $key );
	}

	/**
	 * Issue new token.
	 *
	 * @return array
	 */
	private function issue_token(): array {
		$token = wp_generate_password( 64, false, false );
		$key   = $this->transient_key( $token );

		set_transient( $key, 1, self::TOKEN_TTL );
		update_option( 'ibc_last_token_issued', ibc_now() );

		return array(
			'success' => true,
			'token'   => $token,
			'ttl'     => self::TOKEN_TTL,
		);
	}

	/**
	 * Build transient key.
	 *
	 * @param string $token Token.
	 *
	 * @return string
	 */
	private function transient_key( string $token ): string {
		return 'ibc_tok_' . hash( 'sha256', $token );
	}
}
