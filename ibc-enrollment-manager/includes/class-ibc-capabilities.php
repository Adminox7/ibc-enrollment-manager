<?php
/**
 * Manage plugin capabilities.
 *
 * @package IBC\EnrollmentManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class IBC_Capabilities
 */
class IBC_Capabilities {

	/**
	 * Primary capability.
	 */
	public const CAPABILITY = 'manage_ibc';

	/**
	 * Grant capability to administrators.
	 *
	 * @return void
	 */
	public static function add_capabilities(): void {
		$roles = array( 'administrator' );

		foreach ( $roles as $role_name ) {
			$role = get_role( $role_name );
			if ( $role && ! $role->has_cap( self::CAPABILITY ) ) {
				$role->add_cap( self::CAPABILITY );
			}
		}
	}

	/**
	 * Ensure capability exists for administrators.
	 *
	 * @return void
	 */
	public static function ensure_capabilities(): void {
		self::add_capabilities();
	}

	/**
	 * Remove capability.
	 *
	 * @return void
	 */
	public static function remove_capabilities(): void {
		$roles = array( 'administrator' );

		foreach ( $roles as $role_name ) {
			$role = get_role( $role_name );
			if ( $role && $role->has_cap( self::CAPABILITY ) ) {
				$role->remove_cap( self::CAPABILITY );
			}
		}
	}
}
