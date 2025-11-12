<?php
/**
 * Admin menu registration.
 *
 * @package IBC\EnrollmentManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once IBC_ENROLLMENT_DIR . 'admin/class-ibc-admin-dashboard.php';
require_once IBC_ENROLLMENT_DIR . 'admin/class-ibc-admin-sessions.php';
require_once IBC_ENROLLMENT_DIR . 'admin/class-ibc-admin-students.php';
require_once IBC_ENROLLMENT_DIR . 'admin/class-ibc-admin-registrations.php';
require_once IBC_ENROLLMENT_DIR . 'admin/class-ibc-admin-settings.php';

/**
 * Class IBC_Admin_Menu
 */
class IBC_Admin_Menu {

	/**
	 * Base slug.
	 */
	private const MENU_SLUG = 'ibc-manager';

	/**
	 * Dashboard handler.
	 *
	 * @var IBC_Admin_Dashboard
	 */
	private $dashboard;

	/**
	 * Sessions handler.
	 *
	 * @var IBC_Admin_Sessions
	 */
	private $sessions;

	/**
	 * Students handler.
	 *
	 * @var IBC_Admin_Students
	 */
	private $students;

	/**
	 * Registrations handler.
	 *
	 * @var IBC_Admin_Registrations
	 */
	private $registrations;

	/**
	 * Settings handler.
	 *
	 * @var IBC_Admin_Settings
	 */
	private $settings;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->dashboard     = new IBC_Admin_Dashboard();
		$this->sessions      = new IBC_Admin_Sessions();
		$this->students      = new IBC_Admin_Students();
		$this->registrations = new IBC_Admin_Registrations();
		$this->settings      = new IBC_Admin_Settings();

		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Register admin menu.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		if ( ! ibc_current_user_can( IBC_Capabilities::CAPABILITY ) ) {
			return;
		}

		add_menu_page(
			__( 'IBC Manager', 'ibc-enrollment' ),
			__( 'IBC Manager', 'ibc-enrollment' ),
			IBC_Capabilities::CAPABILITY,
			self::MENU_SLUG,
			array( $this->dashboard, 'render_page' ),
			'dashicons-welcome-learn-more',
			26
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Tableau de bord', 'ibc-enrollment' ),
			__( 'Tableau de bord', 'ibc-enrollment' ),
			IBC_Capabilities::CAPABILITY,
			self::MENU_SLUG,
			array( $this->dashboard, 'render_page' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Sessions', 'ibc-enrollment' ),
			__( 'Sessions', 'ibc-enrollment' ),
			IBC_Capabilities::CAPABILITY,
			self::MENU_SLUG . '-sessions',
			array( $this->sessions, 'render_page' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Étudiants', 'ibc-enrollment' ),
			__( 'Étudiants', 'ibc-enrollment' ),
			IBC_Capabilities::CAPABILITY,
			self::MENU_SLUG . '-students',
			array( $this->students, 'render_page' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Inscriptions', 'ibc-enrollment' ),
			__( 'Inscriptions', 'ibc-enrollment' ),
			IBC_Capabilities::CAPABILITY,
			self::MENU_SLUG . '-registrations',
			array( $this->registrations, 'render_page' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Paramètres', 'ibc-enrollment' ),
			__( 'Paramètres', 'ibc-enrollment' ),
			IBC_Capabilities::CAPABILITY,
			self::MENU_SLUG . '-settings',
			array( $this->settings, 'render_page' )
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Hook suffix.
	 *
	 * @return void
	 */
	public function enqueue_assets( string $hook ): void {
		if ( false === strpos( $hook, self::MENU_SLUG ) ) {
			return;
		}

		wp_enqueue_style(
			'ibc-admin',
			IBC_ENROLLMENT_URL . 'public/assets/css/ibc.css',
			array(),
			IBC_ENROLLMENT_VERSION
		);

		wp_enqueue_script(
			'ibc-admin',
			IBC_ENROLLMENT_URL . 'public/assets/js/ibc.js',
			array( 'jquery' ),
			IBC_ENROLLMENT_VERSION,
			true
		);

		wp_localize_script(
			'ibc-admin',
			'ibcAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'ibc-admin' ),
			)
		);
	}
}
