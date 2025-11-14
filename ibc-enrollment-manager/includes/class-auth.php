<?php
/**
 * Lightweight token-based authentication for the admin dashboard + REST.
 *
 * @package IBC\Enrollment
 */

declare( strict_types=1 );

namespace IBC\Enrollment\Security;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles password verification and issues ephemeral tokens stored in transients.
 */
class Auth {

	private const OPTION_PASSWORD_HASH = 'ibc_admin_token_hash';
	private const OPTION_LAST_ISSUED   = 'ibc_admin_token_last';
	private const TOKEN_TTL            = 2 * HOUR_IN_SECONDS;

	/**
	 * Attempts to authenticate and returns a signed token on success.
	 *
	 * @param string $password Raw password from the modal.
	 * @return array{success:bool,message?:string,token?:string,ttl?:int}
	 */
	public function login( string $password ): array {
		$password = trim( $password );

		if ( '' === $password ) {
			return [
				'success' => false,
				'message' => __( 'Mot de passe requis.', 'ibc-enrollment' ),
			];
		}

		$hash = (string) get_option( self::OPTION_PASSWORD_HASH, '' );

		if ( '' === $hash ) {
			return [
				'success' => false,
				'message' => __( 'Aucun mot de passe administrateur n’est configuré.', 'ibc-enrollment' ),
			];
		}

		if ( ! wp_check_password( $password, $hash ) ) {
			return [
				'success' => false,
				'message' => __( 'Mot de passe invalide.', 'ibc-enrollment' ),
			];
		}

		return $this->issue_token();
	}

	/**
	 * Validates the token present in the current request.
	 *
	 * @return bool
	 */
	public function validate_current_request(): bool {
		return $this->validate_token( $this->extract_token_from_request() );
	}

	/**
	 * Validates a provided token string.
	 *
	 * @param string|null $token Token string.
	 * @return bool
	 */
	public function validate_token( ?string $token ): bool {
		if ( empty( $token ) ) {
			return false;
		}

		$key = $this->cache_key( $token );
		$hit = get_transient( $key );

		if ( false === $hit ) {
			return false;
		}

		// Sliding expiration.
		set_transient( $key, 1, self::TOKEN_TTL );

		return true;
	}

	/**
	 * Updates the admin password hash.
	 *
	 * @param string $password Raw password.
	 * @return void
	 */
	public function update_password( string $password ): void {
		$password = trim( $password );

		if ( '' === $password ) {
			return;
		}

		update_option( self::OPTION_PASSWORD_HASH, wp_hash_password( $password ) );
		delete_option( self::OPTION_LAST_ISSUED );
	}

	/**
	 * Issues a brand new token and stores it in a transient.
	 *
	 * @return array{success:bool,token:string,ttl:int}
	 */
	private function issue_token(): array {
		$token = bin2hex( random_bytes( 32 ) );
		$key   = $this->cache_key( $token );

		set_transient( $key, 1, self::TOKEN_TTL );
		update_option( self::OPTION_LAST_ISSUED, time() );

		return [
			'success' => true,
			'token'   => $token,
			'ttl'     => self::TOKEN_TTL,
		];
	}

	/**
	 * Computes the cache key associated to a token.
	 *
	 * @param string $token Token value.
	 * @return string
	 */
	private function cache_key( string $token ): string {
		return 'ibc_tok_' . hash( 'sha256', $token );
	}

	/**
	 * Extracts token from headers, GET or POST payloads.
	 *
	 * @return string|null
	 */
	private function extract_token_from_request(): ?string {
		$headers = function_exists( 'getallheaders' ) ? getallheaders() : [];

		if ( isset( $headers['X-IBC-Token'] ) ) {
			return sanitize_text_field( (string) $headers['X-IBC-Token'] );
		}

		if ( isset( $_SERVER['HTTP_X_IBC_TOKEN'] ) ) {
			return sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_X_IBC_TOKEN'] ) );
		}

		if ( isset( $_GET['ibc_token'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return sanitize_text_field( wp_unslash( (string) $_GET['ibc_token'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		if ( isset( $_POST['ibc_token'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return sanitize_text_field( wp_unslash( (string) $_POST['ibc_token'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		}

		return null;
	}
}
