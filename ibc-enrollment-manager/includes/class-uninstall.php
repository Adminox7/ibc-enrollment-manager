<?php
/**
 * Uninstall routines.
 *
 * @package IBC\EnrollmentManager
 */

namespace IBC;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Uninstall
 */
class Uninstall {

	/**
	 * Execute uninstall.
	 *
	 * @return void
	 */
	public static function run(): void {
		$options = array(
			'ibc_capacity_limit',
			'ibc_price_prep',
			'ibc_brand_colors',
			'ibc_brand_bankName',
			'ibc_brand_accountHolder',
			'ibc_brand_rib',
			'ibc_brand_iban',
			'ibc_brand_bic',
			'ibc_brand_agency',
			'ibc_brand_paymentNote',
			'ibc_contact_address',
			'ibc_contact_email',
			'ibc_contact_phone',
			'ibc_contact_landline',
			'ibc_admin_password_hash',
			'ibc_admin_password_plain',
		);

		foreach ( $options as $option ) {
			delete_option( $option );
		}

		DB::instance()->drop_table();
	}
}
