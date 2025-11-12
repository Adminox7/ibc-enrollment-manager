<?php
/**
 * Public bootstrap.
 *
 * @package IBC\EnrollmentManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once IBC_ENROLLMENT_DIR . 'public/shortcodes/class-sc-sessions.php';
require_once IBC_ENROLLMENT_DIR . 'public/shortcodes/class-sc-register.php';

/**
 * Class IBC_Public
 */
class IBC_Public {

	/**
	 * Sessions shortcode handler.
	 *
	 * @var IBC_SC_Sessions
	 */
	private $sessions_shortcode;

	/**
	 * Register shortcode handler.
	 *
	 * @var IBC_SC_Register
	 */
	private $register_shortcode;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->sessions_shortcode = new IBC_SC_Sessions();
		$this->register_shortcode = new IBC_SC_Register();

		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
	}

	/**
	 * Register shortcodes.
	 *
	 * @return void
	 */
	public function register_shortcodes(): void {
		$this->sessions_shortcode->register();
		$this->register_shortcode->register();
	}

	/**
	 * Register public assets.
	 *
	 * @return void
	 */
	public function register_assets(): void {
		wp_register_style(
			'ibc-public',
			IBC_ENROLLMENT_URL . 'public/assets/css/ibc.css',
			array(),
			IBC_ENROLLMENT_VERSION
		);

		wp_register_script(
			'ibc-public',
			IBC_ENROLLMENT_URL . 'public/assets/js/ibc.js',
			array( 'jquery' ),
			IBC_ENROLLMENT_VERSION,
			true
		);

		wp_register_script(
			'intl-tel-input',
			'https://cdn.jsdelivr.net/npm/intl-tel-input@18/build/js/intlTelInput.min.js',
			array(),
			'18.2.1',
			true
		);

		wp_register_style(
			'intl-tel-input',
			'https://cdn.jsdelivr.net/npm/intl-tel-input@18/build/css/intlTelInput.min.css',
			array(),
			'18.2.1'
		);
	}
}
