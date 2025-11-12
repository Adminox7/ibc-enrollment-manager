<?php
/**
 * Deactivation callbacks.
 *
 * @package IBC\EnrollmentManager
 */

namespace IBC;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Deactivator
 */
class Deactivator {

	/**
	 * Deactivation handler.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		flush_rewrite_rules();
	}
}
