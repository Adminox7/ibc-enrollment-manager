<?php
/**
 * Uninstall script.
 *
 * @package IBC\EnrollmentManager
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/class-ibc-db.php';
require_once __DIR__ . '/includes/class-ibc-capabilities.php';

IBC_Capabilities::remove_capabilities();

$settings = ibc_get_settings();

wp_clear_scheduled_hook( 'ibc_purge_expired_locks' );

if ( isset( $settings['delete_on_uninstall'] ) && 'yes' === $settings['delete_on_uninstall'] ) {
	IBC_DB::get_instance()->drop_tables();
	delete_option( 'ibc_enrollment_settings' );
}
