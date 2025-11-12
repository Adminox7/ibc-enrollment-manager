<?php
/**
 * Plugin Name:       IBC Enrollment Manager
 * Plugin URI:        https://ibc-morocco.com
 * Description:       Gestion complète des sessions et inscriptions pour IBC Morocco.
 * Version:           1.0.0
 * Author:            IBC Morocco
 * Text Domain:       ibc-enrollment
 * Domain Path:       /languages
 *
 * @package IBC\EnrollmentManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ensure the required PHP version is available.
 */
if ( version_compare( PHP_VERSION, '8.1', '<' ) ) {
	wp_die(
		esc_html__(
			'IBC Enrollment Manager requiert PHP 8.1 ou supérieur.',
			'ibc-enrollment'
		)
	);
}

define( 'IBC_ENROLLMENT_VERSION', '1.0.0' );
define( 'IBC_ENROLLMENT_FILE', __FILE__ );
define( 'IBC_ENROLLMENT_DIR', plugin_dir_path( __FILE__ ) );
define( 'IBC_ENROLLMENT_URL', plugin_dir_url( __FILE__ ) );
define( 'IBC_ENROLLMENT_BASENAME', plugin_basename( __FILE__ ) );

require_once IBC_ENROLLMENT_DIR . 'includes/helpers.php';
require_once IBC_ENROLLMENT_DIR . 'includes/class-ibc-db.php';
require_once IBC_ENROLLMENT_DIR . 'includes/class-ibc-capabilities.php';
require_once IBC_ENROLLMENT_DIR . 'includes/class-ibc-seatlock.php';
require_once IBC_ENROLLMENT_DIR . 'includes/class-ibc-emails.php';
require_once IBC_ENROLLMENT_DIR . 'admin/class-ibc-admin-menu.php';
require_once IBC_ENROLLMENT_DIR . 'public/class-ibc-public.php';

/**
 * Main plugin class.
 */
final class IBC_Enrollment_Manager {

	/**
	 * Singleton instance.
	 *
	 * @var IBC_Enrollment_Manager|null
	 */
	private static $instance = null;

	/**
	 * Database handler.
	 *
	 * @var IBC_DB
	 */
	private $db;

	/**
	 * Seat lock handler.
	 *
	 * @var IBC_SeatLock
	 */
	private $seat_lock;

	/**
	 * Emails handler.
	 *
	 * @var IBC_Emails
	 */
	private $emails;

	/**
	 * Admin menu handler.
	 *
	 * @var IBC_Admin_Menu
	 */
	private $admin_menu;

	/**
	 * Public handler.
	 *
	 * @var IBC_Public
	 */
	private $public;

	/**
	 * Get instance.
	 *
	 * @return IBC_Enrollment_Manager
	 */
	public static function get_instance(): IBC_Enrollment_Manager {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->load_textdomain();
		$this->init_components();
		$this->init_hooks();
	}

	/**
	 * Clone prevention.
	 */
	private function __clone() {}

	/**
	 * Wakeup prevention.
	 */
	public function __wakeup() {
		throw new \RuntimeException( 'Unserializing instances of IBC_Enrollment_Manager is forbidden.' );
	}

	/**
	 * Load translations.
	 *
	 * @return void
	 */
	private function load_textdomain(): void {
		load_plugin_textdomain(
			'ibc-enrollment',
			false,
			dirname( IBC_ENROLLMENT_BASENAME ) . '/languages/'
		);
	}

	/**
	 * Initialize components.
	 *
	 * @return void
	 */
	private function init_components(): void {
		$this->db        = IBC_DB::get_instance();
		$this->seat_lock = IBC_SeatLock::get_instance();
		$this->emails    = IBC_Emails::get_instance();
		$this->admin_menu = new IBC_Admin_Menu();
		$this->public     = new IBC_Public();
	}

	/**
	 * Hook registrations.
	 *
	 * @return void
	 */
	private function init_hooks(): void {
		add_action( 'init', array( $this, 'register_post_hooks' ) );
		add_action( 'admin_init', array( $this, 'register_admin_hooks' ) );
	}

	/**
	 * Hooks executed on init.
	 *
	 * @return void
	 */
	public function register_post_hooks(): void {
		$this->public->register_shortcodes();
		$this->seat_lock->maybe_schedule();
	}

	/**
	 * Register admin related hooks.
	 *
	 * @return void
	 */
	public function register_admin_hooks(): void {
		IBC_Capabilities::ensure_capabilities();
	}

	/**
	 * Activation callback.
	 *
	 * @return void
	 */
	public static function activate(): void {
		$instance = self::get_instance();
		$instance->db->create_tables();
		IBC_Capabilities::add_capabilities();
		$instance->seat_lock->schedule();
		flush_rewrite_rules( false );
	}

	/**
	 * Deactivation callback.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		IBC_SeatLock::get_instance()->clear_schedule();
		flush_rewrite_rules( false );
	}
}

/**
 * Bootstrap the plugin.
 *
 * @return IBC_Enrollment_Manager
 */
function ibc_enrollment_manager(): IBC_Enrollment_Manager {
	return IBC_Enrollment_Manager::get_instance();
}

add_action( 'plugins_loaded', 'ibc_enrollment_manager' );

register_activation_hook( __FILE__, array( 'IBC_Enrollment_Manager', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'IBC_Enrollment_Manager', 'deactivate' ) );
