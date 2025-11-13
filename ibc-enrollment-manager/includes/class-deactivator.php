<?php
/**
 * Deactivation callbacks.
 *
 * @package IBC\Enrollment
 */

namespace IBC\Enrollment;

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
