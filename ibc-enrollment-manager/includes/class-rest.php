<?php
/**
 * REST API controller (ibc/v1).
 *
 * @package IBC\Enrollment
 */

declare( strict_types=1 );

namespace IBC\Enrollment\Rest;

use IBC\Enrollment\Security\Auth;
use IBC\Enrollment\Services\Registrations;
use WP_REST_Request;
use WP_REST_Response;
use function IBC\Enrollment\ibc_get_request_ip;
use function IBC\Enrollment\ibc_rate_limit;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers every REST endpoint consumed by the public form and the admin dashboard.
 */
class RestController {

	public const NAMESPACE = 'ibc/v1';

	public function __construct( private Registrations $registrations, private Auth $auth ) {}

	/**
	 * Registers all API routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/login',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'login' ],
				'permission_callback' => '__return_true',
				'args'                => [
					'password' => [
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/check',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'check_capacity' ],
				'permission_callback' => '__return_true',
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/register',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'register' ],
				'permission_callback' => '__return_true',
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/registrations',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'list_registrations' ],
				'permission_callback' => [ $this, 'verify_request_token' ],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/registrations/(?P<id>\d+)',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'update_registration' ],
				'permission_callback' => [ $this, 'verify_request_token' ],
				'args'                => [
					'id' => [
						'required'          => true,
						'sanitize_callback' => 'absint',
					],
				],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/registrations/(?P<reference>[A-Za-z0-9\-]+)/cancel',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'cancel_registration' ],
				'permission_callback' => [ $this, 'verify_request_token' ],
			]
		);
	}

	/**
	 * Issues a token for the admin dashboard.
	 */
	public function login( WP_REST_Request $request ): WP_REST_Response {
		$result = $this->auth->login( (string) $request->get_param( 'password' ) );

		if ( empty( $result['success'] ) ) {
			$message = $result['message'] ?? __( 'Accès refusé.', 'ibc-enrollment' );

			return $this->error( $message, 401 );
		}

		return $this->success(
			[
				'token' => $result['token'],
				'ttl'   => (int) $result['ttl'],
			]
		);
	}

	/**
	 * Public endpoint used to check duplicates/capacity before submitting the form.
	 */
	public function check_capacity( WP_REST_Request $request ): WP_REST_Response {
		$email     = (string) $request->get_param( 'email' );
		$telephone = (string) ( $request->get_param( 'telephone' ) ?: $request->get_param( 'phone' ) );

		$data = $this->registrations->capacitySnapshot( $email, $telephone );

		return $this->success( $data );
	}

	/**
	 * Handles public form submission.
	 */
	public function register( WP_REST_Request $request ): WP_REST_Response {
		$ip_key = 'ibc_register_' . ibc_get_request_ip();

		if ( ibc_rate_limit( $ip_key, 10, false ) ) {
			return $this->error( __( 'Veuillez patienter avant de soumettre à nouveau.', 'ibc-enrollment' ), 429 );
		}

		$result = $this->registrations->create(
			$request->get_params(),
			$request->get_file_params()
		);

		if ( is_wp_error( $result ) ) {
			return $this->error( $result->get_error_message(), 400 );
		}

		// Prime the limiter after a successful attempt to avoid spamming.
		ibc_rate_limit( $ip_key, 10 );

		$payload = [
			'reference'     => $result['reference'],
			'ref'           => $result['reference'],
			'receipt_url'   => $result['pdf_url'],
			'receiptUrl'    => $result['pdf_url'],
			'pdf_available' => ! empty( $result['pdf_url'] ),
			'pdfAvailable'  => ! empty( $result['pdf_url'] ),
			'email'         => $result['email'],
			'telephone'     => $result['telephone'],
			'created_at'    => $result['created_at'],
			'createdAt'     => $result['created_at'],
			'extraFields'   => $result['extraFields'] ?? [],
		];

		return $this->success( $payload, 201 );
	}

	/**
	 * Lists registrations for the dashboard table.
	 */
	public function list_registrations( WP_REST_Request $request ): WP_REST_Response {
		$per_page = (int) max( 1, min( 200, $request->get_param( 'per_page' ) ?: 50 ) );
		$page     = (int) max( 1, $request->get_param( 'page' ) ?: 1 );

		$args = [
			'search' => sanitize_text_field( (string) $request->get_param( 'search' ) ),
			'niveau' => sanitize_text_field( (string) $request->get_param( 'niveau' ) ),
			'statut' => sanitize_text_field( (string) $request->get_param( 'statut' ) ),
			'limit'  => $per_page,
			'offset' => ( $page - 1 ) * $per_page,
		];

		$list  = $this->registrations->list( $args );
		$items = $list['items'] ?? [];
		$total = isset( $list['total'] ) ? (int) $list['total'] : count( $items );

		return $this->success(
			[
				'items' => $items,
				'total' => $total,
				'page'  => $page,
				'limit' => $per_page,
			]
		);
	}

	/**
	 * Updates a record from the admin modal.
	 */
	public function update_registration( WP_REST_Request $request ): WP_REST_Response {
		$id = (int) $request->get_param( 'id' );

		if ( $id <= 0 ) {
			return $this->error( __( 'Identifiant manquant.', 'ibc-enrollment' ) );
		}

		$fields = $this->extract_fields_from_request( $request );

		if ( empty( $fields ) ) {
			return $this->error( __( 'Aucune donnée à mettre à jour.', 'ibc-enrollment' ) );
		}

		$result = $this->registrations->update( $id, $fields );

		if ( is_wp_error( $result ) ) {
			return $this->error( $result->get_error_message() );
		}

		return $this->success(
			[
				'updated' => (bool) $result,
			]
		);
	}

	/**
	 * Soft-cancels a registration (statut → Annule).
	 */
	public function cancel_registration( WP_REST_Request $request ): WP_REST_Response {
		$reference = sanitize_text_field(
			(string) ( $request->get_param( 'reference' ) ?: $request->get_param( 'ref' ) )
		);

		if ( '' === $reference ) {
			return $this->error( __( 'Référence manquante.', 'ibc-enrollment' ) );
		}

		$result = $this->registrations->cancelByReference( $reference );

		return $this->success(
			[
				'deleted' => (bool) $result,
			]
		);
	}

	/**
	 * Ensures a valid token is present for private routes.
	 */
	public function verify_request_token( WP_REST_Request $request ): bool {
		return $this->auth->validate_token( $this->extract_token( $request ) );
	}

	/**
	 * Standard success payload.
	 */
	private function success( array $data, int $status = 200 ): WP_REST_Response {
		return new WP_REST_Response(
			[
				'success' => true,
				'data'    => $data,
			],
			$status
		);
	}

	/**
	 * Standard error payload.
	 */
	private function error( string $message, int $status = 400, array $extra = [] ): WP_REST_Response {
		return new WP_REST_Response(
			array_merge(
				[
					'success' => false,
					'message' => $message,
				],
				$extra
			),
			$status
		);
	}

	/**
	 * Normalizes update payloads whether they come as JSON or form-data.
	 */
	private function extract_fields_from_request( WP_REST_Request $request ): array {
		$json = $request->get_json_params();

		if ( isset( $json['fields'] ) && is_array( $json['fields'] ) ) {
			return $json['fields'];
		}

		if ( ! empty( $json ) ) {
			unset( $json['id'], $json['reference'], $json['ref'], $json['action'] );

			if ( ! empty( $json ) ) {
				return $json;
			}
		}

		$params = $request->get_body_params();

		if ( isset( $params['fields'] ) && is_array( $params['fields'] ) ) {
			return $params['fields'];
		}

		unset( $params['id'], $params['reference'], $params['ref'], $params['action'] );

		return $params;
	}

	/**
	 * Extracts the bearer token from headers or query parameters.
	 */
	private function extract_token( WP_REST_Request $request ): ?string {
		$header = $request->get_header( 'X-IBC-Token' );

		if ( $header ) {
			return sanitize_text_field( $header );
		}

		$param = $request->get_param( 'token' );

		if ( $param ) {
			return sanitize_text_field( (string) $param );
		}

		return null;
	}
}
