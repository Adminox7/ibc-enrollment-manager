<?php
/**
 * Plugin runtime loader.
 *
 * @package IBC\Enrollment
 */

declare( strict_types=1 );

namespace IBC\Enrollment\Core;

use IBC\Enrollment\Admin\Dashboard;
use IBC\Enrollment\Database\DB;
use IBC\Enrollment\REST\RestController;
use IBC\Enrollment\Services\EmailService;
use IBC\Enrollment\Services\PdfService;
use IBC\Enrollment\Services\Registrations;
use IBC\Enrollment\Support\Assets;
use IBC\Enrollment\Support\Shortcodes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bootstraps and wires every plugin service.
 */
class Loader {

	/**
	 * Tracks if boot() already ran.
	 */
	private bool $booted = false;

	/**
	 * Database gateway singleton.
	 */
	private DB $db;

	/**
	 * Registration domain service.
	 */
	private Registrations $registrations;

	/**
	 * REST controller (ibc/v1).
	 */
	private RestController $rest;

	/**
	 * Asset manager (admin + public).
	 */
	private Assets $assets;

	/**
	 * Shortcode handler (front form).
	 */
	private Shortcodes $shortcodes;

	/**
	 * Admin dashboard controller.
	 */
	private Dashboard $dashboard;

	/**
	 * Bootstraps the plugin (idempotent).
	 *
	 * @return void
	 */
	public function boot(): void {
		if ( $this->booted ) {
			return;
		}

		$this->booted = true;

		$this->db            = DB::instance();
		$email_service       = new EmailService();
		$pdf_service         = new PdfService();
		$this->registrations = new Registrations( $this->db, $email_service, $pdf_service );
		$this->rest          = new RestController( $this->registrations );
		$this->assets        = new Assets();
		$this->shortcodes    = new Shortcodes( $this->registrations );
		$this->dashboard     = new Dashboard( $this->registrations );

		$this->register_hooks();

		/**
		 * Fires after the loader fully boots.
		 *
		 * @param Loader $this Loader instance.
		 */
		do_action( 'ibc_enrollment_booted', $this );
	}

	/**
	 * Registers WP hooks for every service.
	 *
	 * @return void
	 */
	private function register_hooks(): void {
		add_action( 'init', [ $this, 'init' ] );
		add_action( 'rest_api_init', [ $this->rest, 'register_routes' ] );
		add_action( 'admin_init', [ $this->dashboard, 'handle_token_reset' ] );
		add_action( 'admin_menu', [ $this->dashboard, 'register_page' ] );
		add_action( 'admin_enqueue_scripts', [ $this->assets, 'enqueue_admin' ] );
		add_action( 'wp_enqueue_scripts', [ $this->assets, 'enqueue_public' ] );
		add_action( 'wp_ajax_ibc_download_receipt', [ $this->registrations, 'handle_ajax_receipt' ] );
		add_action( 'wp_ajax_nopriv_ibc_download_receipt', [ $this->registrations, 'handle_ajax_receipt' ] );
	}

	/**
	 * Initializes run-time features hooked to `init`.
	 *
	 * @return void
	 */
	public function init(): void {
		$this->shortcodes->register();
		$this->assets->register_shared_theme_variables();
	}

	/**
	 * Exposes the registrations service for other components.
	 *
	 * @return Registrations
	 */
	public function registrations(): Registrations {
		return $this->registrations;
	}
}
