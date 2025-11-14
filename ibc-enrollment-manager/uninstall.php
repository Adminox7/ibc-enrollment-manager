<?php
/**
 * Uninstall script.
 *
 * @package IBC\Enrollment
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

define( 'IBC_ENROLLMENT_PATH', plugin_dir_path( __FILE__ ) );

require_once IBC_ENROLLMENT_PATH . 'includes/helpers.php';
require_once __DIR__ . '/includes/class-uninstall.php';

\IBC\Enrollment\Uninstall::run();
