<?php
/**
 * REST API endpoints.
 *
 * @package IBC\EnrollmentManager
 */

namespace IBC;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class REST
 */
class REST {

	/**
	 * Namespace.
	 */
	public const ROUTE_NAMESPACE = 'ibc/v1';

	/**
	 * Registrations service.
	 *
	 * @var Registrations
	 */
	private Registrations $registrations;

	/**
	 * Auth service.
	 *
	 * @var Auth
	 */
	private Auth $auth;

	/**
	 * Constructor.
	 *
	 * @param Registrations $registrations Registrations domain.
	 * @param Auth          $auth          Auth service.
	 */
	public function __construct( Registrations $registrations, Auth $auth ) {
		$this->registrations = $registrations;
		$this->auth          = $auth;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register API routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			self::ROUTE_NAMESPACE,
			'/login',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_login' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'password' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);

		register_rest_route(
			self::ROUTE_NAMESPACE,
			'/check',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_check' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::ROUTE_NAMESPACE,
			'/register',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_register' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'prenom' => array( 'required' => true ),
					'nom'    => array( 'required' => true ),
					'email'  => array( 'required' => true ),
					'phone'  => array( 'required' => true ),
					'niveau' => array( 'required' => true ),
				),
			)
		);

		register_rest_route(
			self::ROUTE_NAMESPACE,
			'/regs',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_regs' ),
				'permission_callback' => array( $this, 'check_token' ),
			)
		);

		register_rest_route(
			self::ROUTE_NAMESPACE,
			'/reg/update',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_update' ),
				'permission_callback' => array( $this, 'check_token' ),
			)
		);

		register_rest_route(
			self::ROUTE_NAMESPACE,
			'/reg/delete',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_delete' ),
				'permission_callback' => array( $this, 'check_token' ),
			)
		);
	}

	/**
	 * Handle login.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return WP_REST_Response
	 */
	public function handle_login( WP_REST_Request $request ): WP_REST_Response {
		$password = (string) $request->get_param( 'password' );
		$result   = $this->auth->login( $password );

		if ( empty( $result['success'] ) ) {
			return $this->error_response( $result['message'] ?? \__( 'Accès refusé.', 'ibc-enrollment-manager' ), 401 );
		}

		return $this->success_response(
			array(
				'token' => $result['token'],
				'ttl'   => $result['ttl'],
			)
		);
	}

	/**
	 * Handle capacity check.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return WP_REST_Response
	 */
	public function handle_check( WP_REST_Request $request ): WP_REST_Response {
		$email = (string) $request->get_param( 'email' );
		$phone = (string) $request->get_param( 'phone' );

		$data = $this->registrations->get_capacity_info( $email, $phone );

		return $this->success_response( $data );
	}

	/**
	 * Handle registration creation.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return WP_REST_Response
	 */
	public function handle_register( WP_REST_Request $request ): WP_REST_Response {
		$ip_key = 'register_ip_' . ibc_get_request_ip();
		if ( ibc_rate_limit( $ip_key, 10 ) ) {
			return $this->error_response( \__( 'Veuillez patienter avant de soumettre à nouveau.', 'ibc-enrollment-manager' ), 429 );
		}

		$params = $request->get_params();
		$files  = $request->get_file_params();

		$result = $this->registrations->create_registration( $params, $files );

		if ( is_wp_error( $result ) ) {
			return $this->error_response( $result->get_error_message(), 400 );
		}

		return $this->success_response(
			array(
				'ref'         => $result['ref'],
				'receiptUrl'  => $result['downloadUrl'],
				'receiptId'   => $result['receiptId'],
				'createdAt'   => $result['createdAt'],
			),
			201
		);
	}

	/**
	 * Handle admin list.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return WP_REST_Response
	 */
	public function handle_regs( WP_REST_Request $request ): WP_REST_Response {
		$per_page = max( 1, min( 200, (int) $request->get_param( 'per_page' ) ?: 50 ) );
		$page     = max( 1, (int) $request->get_param( 'page' ) ?: 1 );
		$offset   = ( $page - 1 ) * $per_page;

		$args = array(
			'search' => sanitize_text_field( (string) $request->get_param( 'search' ) ),
			'niveau' => sanitize_text_field( (string) $request->get_param( 'niveau' ) ),
			'statut' => sanitize_text_field( (string) $request->get_param( 'statut' ) ),
			'limit'  => $per_page,
			'offset' => $offset,
		);

		$data = $this->registrations->get_registrations( $args );

		return $this->success_response(
			array(
				'items' => $data,
				'page'  => $page,
				'limit' => $per_page,
			)
		);
	}

	/**
	 * Handle admin update.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return WP_REST_Response
	 */
	public function handle_update( WP_REST_Request $request ): WP_REST_Response {
		$body   = $request->get_json_params();
		$id     = isset( $body['id'] ) ? (int) $body['id'] : (int) ( $body['row'] ?? 0 );
		$fields = isset( $body['fields'] ) && is_array( $body['fields'] ) ? $body['fields'] : array();

		if ( $id <= 0 || empty( $fields ) ) {
			return $this->error_response( \__( 'Requête invalide.', 'ibc-enrollment-manager' ), 400 );
		}

		$result = $this->registrations->update_registration( $id, $fields );

		if ( is_wp_error( $result ) ) {
			return $this->error_response( $result->get_error_message(), 400 );
		}

		return $this->success_response(
			array(
				'updated' => (bool) $result,
			)
		);
	}

	/**
	 * Handle admin delete (soft).
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return WP_REST_Response
	 */
	public function handle_delete( WP_REST_Request $request ): WP_REST_Response {
		$body = $request->get_json_params();
		$ref  = sanitize_text_field( $body['ref'] ?? '' );

		if ( empty( $ref ) ) {
			return $this->error_response( \__( 'Référence manquante.', 'ibc-enrollment-manager' ), 400 );
		}

		$result = $this->registrations->cancel_by_reference( $ref );

		return $this->success_response(
			array(
				'deleted' => (bool) $result,
			)
		);
	}

	/**
	 * Permission callback for token protected routes.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return bool
	 */
	public function check_token( WP_REST_Request $request ): bool {
		$token = $this->extract_token( $request );

		return $this->auth->validate( $token );
	}

	/**
	 * Build success response.
	 *
	 * @param array $data   Payload.
	 * @param int   $status Status.
	 *
	 * @return WP_REST_Response
	 */
	private function success_response( array $data, int $status = 200 ): WP_REST_Response {
		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => $data,
			),
			$status
		);
	}

	/**
	 * Build error response.
	 *
	 * @param string $message Error message.
	 * @param int    $status  Status code.
	 *
	 * @return WP_REST_Response
	 */
	private function error_response( string $message, int $status = 400 ): WP_REST_Response {
		return new WP_REST_Response(
			array(
				'success' => false,
				'message' => $message,
			),
			$status
		);
	}

	/**
	 * Extract token from request.
	 *
	 * @param WP_REST_Request $request Request.
	 *
	 * @return string
	 */
	private function extract_token( WP_REST_Request $request ): string {
		$header = $request->get_header( 'X-IBC-Token' );
		if ( ! empty( $header ) ) {
			return sanitize_text_field( $header );
		}

		$token = $request->get_param( 'token' );
		if ( ! empty( $token ) ) {
			return sanitize_text_field( (string) $token );
		}

		return '';
	}
}
