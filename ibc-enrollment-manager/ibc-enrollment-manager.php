<?php
/**
 * Plugin Name:       IBC Enrollment Manager
 * Plugin URI:        https://ibc-morocco.com
 * Description:       Gestion complète des inscriptions IBC avec formulaires front, dashboard, PDF, email et API REST sécurisée.
 * Version:           1.0.0
 * Author:            IBC Morocco
 * Requires at least: 6.2
 * Requires PHP:      8.1
 * Text Domain:       ibc-enrollment-manager
 * Domain Path:       /languages
 *
 * @package IBC\Enrollment
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( version_compare( PHP_VERSION, '8.1', '<' ) ) {
	wp_die(
		sprintf(
			/* translators: %s: PHP version */
			esc_html__( 'IBC Enrollment Manager requiert PHP 8.1 ou supérieur. Version détectée : %s', 'ibc-enrollment-manager' ),
			esc_html( PHP_VERSION )
		)
	);
}

define( 'IBC_ENROLLMENT_VERSION', '1.0.0' );
define( 'IBC_ENROLLMENT_FILE', __FILE__ );
define( 'IBC_ENROLLMENT_BASENAME', plugin_basename( __FILE__ ) );
define( 'IBC_ENROLLMENT_PATH', plugin_dir_path( __FILE__ ) );
define( 'IBC_ENROLLMENT_URL', plugin_dir_url( __FILE__ ) );

require_once IBC_ENROLLMENT_PATH . 'includes/helpers.php';
require_once IBC_ENROLLMENT_PATH . 'includes/class-loader.php';

\IBC\Enrollment\Loader::bootstrap();

register_activation_hook( __FILE__, array( '\IBC\Enrollment\Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( '\IBC\Enrollment\Deactivator', 'deactivate' ) );
register_uninstall_hook( __FILE__, array( '\IBC\Enrollment\Uninstall', 'run' ) );
