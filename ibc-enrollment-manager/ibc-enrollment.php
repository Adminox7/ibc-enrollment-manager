<?php
/**
 * Plugin Name:       IBC Enrollment Manager
 * Plugin URI:        https://ibcmorocco.com
 * Description:       Gestion complète des inscriptions Préparation d’examen pour IBC Morocco (formulaire public + dashboard admin + reçus PDF + e-mails).
 * Version:           1.0.0
 * Author:            IBC Morocco
 * Author URI:        https://ibcmorocco.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ibc-enrollment
 * Domain Path:       /languages
 *
 * @package IBC\Enrollment
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'IBC_ENROLLMENT_VERSION' ) ) {
	define( 'IBC_ENROLLMENT_VERSION', '1.0.0' );
}

if ( ! defined( 'IBC_ENROLLMENT_PLUGIN_FILE' ) ) {
	define( 'IBC_ENROLLMENT_PLUGIN_FILE', __FILE__ );
}

if ( ! defined( 'IBC_ENROLLMENT_PLUGIN_BASENAME' ) ) {
	define( 'IBC_ENROLLMENT_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
}

if ( ! defined( 'IBC_ENROLLMENT_PLUGIN_DIR' ) ) {
	define( 'IBC_ENROLLMENT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'IBC_ENROLLMENT_PLUGIN_URL' ) ) {
	define( 'IBC_ENROLLMENT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

if ( ! defined( 'IBC_ENROLLMENT_UPLOAD_SUBDIR' ) ) {
	define( 'IBC_ENROLLMENT_UPLOAD_SUBDIR', 'ibc' );
}

// Composer/Dompdf autoloader (optional – only if vendor assets are present).
$ibc_enrollment_dompdf = IBC_ENROLLMENT_PLUGIN_DIR . 'vendor/dompdf/autoload.inc.php';
if ( file_exists( $ibc_enrollment_dompdf ) ) {
	require_once $ibc_enrollment_dompdf;
}

/**
 * Internal registry holding missing dependencies.
 *
 * @var string[]
 */
$ibc_enrollment_missing_dependencies = [];

/**
 * Safely requires a plugin file and logs a notice if it is missing.
 *
 * @param string $relative_path Relative path from the plugin root.
 * @return bool
 */
function ibc_enrollment_require( string $relative_path ): bool {
	$absolute = IBC_ENROLLMENT_PLUGIN_DIR . ltrim( $relative_path, '/' );

	if ( file_exists( $absolute ) ) {
		require_once $absolute;
		return true;
	}

	global $ibc_enrollment_missing_dependencies;
	$ibc_enrollment_missing_dependencies[] = $relative_path;

	if ( ! has_action( 'admin_notices', 'ibc_enrollment_dependency_notice' ) ) {
		add_action( 'admin_notices', 'ibc_enrollment_dependency_notice' );
	}

	return false;
}

/**
 * Displays an admin notice when required files are missing.
 *
 * @return void
 */
function ibc_enrollment_dependency_notice(): void {
	global $ibc_enrollment_missing_dependencies;

	if ( empty( $ibc_enrollment_missing_dependencies ) ) {
		return;
	}

	$paths = implode( ', ', array_map( 'esc_html', $ibc_enrollment_missing_dependencies ) );

	printf(
		'<div class="notice notice-error"><p>%s</p></div>',
		sprintf(
			/* translators: %s list of files. */
			esc_html__( 'IBC Enrollment Manager is missing required files: %s', 'ibc-enrollment' ),
			$paths
		)
	);
}

// Load plugin dependencies (will be filled/overwritten file-by-file).
$ibc_enrollment_required_files = [
	'includes/helpers.php',
	'includes/class-loader.php',
	'includes/class-activator.php',
	'includes/class-deactivator.php',
	'includes/class-db.php',
	'includes/class-rest.php',
	'includes/class-registrations.php',
	'includes/class-email.php',
	'includes/class-pdf.php',
];

foreach ( $ibc_enrollment_required_files as $relative_file ) {
	ibc_enrollment_require( $relative_file );
}

if ( ! empty( $ibc_enrollment_missing_dependencies ) ) {
	return;
}

if ( class_exists( '\IBC\Enrollment\Core\Activator' ) ) {
	register_activation_hook(
		IBC_ENROLLMENT_PLUGIN_FILE,
		[ '\IBC\Enrollment\Core\Activator', 'activate' ]
	);
}

if ( class_exists( '\IBC\Enrollment\Core\Deactivator' ) ) {
	register_deactivation_hook(
		IBC_ENROLLMENT_PLUGIN_FILE,
		[ '\IBC\Enrollment\Core\Deactivator', 'deactivate' ]
	);
}

if ( ! function_exists( 'ibc_enrollment_load_textdomain' ) ) {
	/**
	 * Loads the plugin text domain.
	 *
	 * @return void
	 */
	function ibc_enrollment_load_textdomain(): void {
		load_plugin_textdomain(
			'ibc-enrollment',
			false,
			dirname( IBC_ENROLLMENT_PLUGIN_BASENAME ) . '/languages/'
		);
	}
}

add_action( 'plugins_loaded', 'ibc_enrollment_load_textdomain' );

if ( ! function_exists( 'ibc_enrollment' ) ) {
	/**
	 * Retrieves the main plugin loader instance.
	 *
	 * @return \IBC\Enrollment\Core\Loader|null
	 */
	function ibc_enrollment() {
		static $instance = null;

		if ( null === $instance && class_exists( '\IBC\Enrollment\Core\Loader' ) ) {
			$instance = new \IBC\Enrollment\Core\Loader();
			$instance->boot();
		}

		return $instance;
	}
}

ibc_enrollment();
