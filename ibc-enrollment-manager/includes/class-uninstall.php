<?php
/**
 * Uninstall routines.
 *
 * @package IBC\Enrollment
 */

namespace IBC\Enrollment;

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
			'ibc_brand_primary',
			'ibc_brand_name',
			'ibc_brand_secondary',
			'ibc_brand_text',
			'ibc_brand_muted',
			'ibc_brand_border',
			'ibc_brand_button',
			'ibc_brand_button_text',
			'ibc_brand_success_bg',
			'ibc_brand_success_text',
			'ibc_brand_error_bg',
			'ibc_brand_error_text',
			'ibc_bank_name',
			'ibc_account_holder',
			'ibc_rib',
			'ibc_iban',
			'ibc_bic',
			'ibc_agency',
			'ibc_payment_note',
			'ibc_contact_address',
			'ibc_contact_email',
			'ibc_contact_phone',
			'ibc_contact_landline',
			'ibc_admin_password_hash',
			'ibc_admin_password_plain',
			'ibc_active_tokens',
			'ibc_last_token_issued',
			'ibc_form_schema',
			'ibc_form_theme',
		);

		foreach ( $options as $option ) {
			delete_option( $option );
		}

		DB::instance()->drop_table();
	}
}
