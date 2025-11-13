<?php
/**
 * Loader for IBC Enrollment Manager.
 *
 * @package IBC\Enrollment
 */

namespace IBC\Enrollment;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Loader
 */
class Loader {

	/**
	 * Singleton instance.
	 *
	 * @var Loader|null
	 */
	private static ?Loader $instance = null;

	/**
	 * Whether bootstrap already executed.
	 *
	 * @var bool
	 */
	private static bool $bootstrapped = false;

	/**
	 * Settings handler.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Registrations domain service.
	 *
	 * @var Registrations
	 */
	private Registrations $registrations;

	/**
	 * Authentication manager.
	 *
	 * @var Auth
	 */
	private Auth $auth;

	/**
	 * Asset manager.
	 *
	 * @var Assets
	 */
	private Assets $assets;

	/**
	 * Shortcodes handler.
	 *
	 * @var Shortcodes
	 */
	private Shortcodes $shortcodes;

	/**
	 * Form builder service.
	 *
	 * @var FormBuilder
	 */
	private FormBuilder $form_builder;

	/**
	 * REST API handler.
	 *
	 * @var REST
	 */
	private REST $rest;

	/**
	 * Bootstrap plugin.
	 *
	 * @return void
	 */
	public static function bootstrap(): void {
		if ( self::$bootstrapped ) {
			return;
		}

		self::$bootstrapped = true;
		self::load_dependencies();
		self::instance();
	}

	/**
	 * Retrieve singleton.
	 *
	 * @return Loader
	 */
	public static function instance(): Loader {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->form_builder  = new FormBuilder();
		$this->settings      = new Settings( $this->form_builder );
		$db                  = DB::instance();
		$email               = new Email();
		$pdf                 = new PDF();
		$this->registrations = new Registrations( $db, $email, $pdf, $this->form_builder );
		$this->auth          = new Auth();
		$this->assets        = new Assets( $this->form_builder );
		$this->shortcodes    = new Shortcodes( $this->registrations, $this->auth, $this->form_builder );
		$this->rest          = new REST( $this->registrations, $this->auth );

		add_action( 'plugins_loaded', array( $this, 'on_plugins_loaded' ) );
	}

	/**
	 * Load dependencies.
	 *
	 * @return void
	 */
	private static function load_dependencies(): void {
		$files = array(
			'includes/class-activator.php',
			'includes/class-deactivator.php',
			'includes/class-uninstall.php',
			'includes/class-db.php',
			'includes/class-formbuilder.php',
			'includes/class-settings.php',
			'includes/class-auth.php',
			'includes/class-assets.php',
			'includes/class-shortcodes.php',
			'includes/class-email.php',
			'includes/class-pdf.php',
			'includes/class-registrations.php',
			'includes/class-rest.php',
		);

		foreach ( $files as $file ) {
			require_once IBC_ENROLLMENT_PATH . $file;
		}

		require_once IBC_ENROLLMENT_PATH . 'admin/class-admin-page.php';

		$dompdf_autoload = IBC_ENROLLMENT_PATH . 'vendor/dompdf/autoload.inc.php';
		if ( file_exists( $dompdf_autoload ) ) {
			require_once $dompdf_autoload;
		}
	}

	/**
	 * Plugin loaded hook.
	 *
	 * @return void
	 */
	public function on_plugins_loaded(): void {
		load_plugin_textdomain(
			'ibc-enrollment-manager',
			false,
			dirname( IBC_ENROLLMENT_BASENAME ) . '/languages/'
		);

		$this->settings->register_hooks();
		$this->assets->register_hooks();
		$this->shortcodes->register_hooks();
		$this->rest->register_hooks();
	}
}
