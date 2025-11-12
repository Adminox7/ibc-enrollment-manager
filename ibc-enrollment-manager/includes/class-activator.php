<?php
/**
 * Activation callbacks.
 *
 * @package IBC\EnrollmentManager
 */

namespace IBC;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Activator
 */
class Activator {

	/**
	 * Activation handler.
	 *
	 * @return void
	 */
	public static function activate(): void {
		DB::instance()->create_table();
		self::seed_options();
		flush_rewrite_rules();
	}

	/**
	 * Register default options.
	 *
	 * @return void
	 */
	private static function seed_options(): void {
		if ( ! get_option( 'ibc_capacity_limit', false ) ) {
			update_option( 'ibc_capacity_limit', 1066 );
		}

		if ( ! get_option( 'ibc_price_prep', false ) ) {
			update_option( 'ibc_price_prep', 1000 );
		}

		if ( ! get_option( 'ibc_brand_colors', false ) ) {
			update_option(
				'ibc_brand_colors',
				array(
					'primary'   => '#e94162',
					'secondary' => '#0f172a',
					'text'      => '#1f2937',
					'muted'     => '#f8fafc',
					'border'    => '#e2e8f0',
				)
			);
		}

		$brand_defaults = array(
			'ibc_brand_bankName'    => 'Attijariwafa Bank',
			'ibc_brand_accountHolder' => 'IBC Morocco',
			'ibc_brand_rib'         => '',
			'ibc_brand_iban'        => '',
			'ibc_brand_bic'         => '',
			'ibc_brand_agency'      => '',
			'ibc_brand_paymentNote' => \__( 'Paiement non remboursable, à effectuer sous 24h.', 'ibc-enrollment-manager' ),
		);

		foreach ( $brand_defaults as $key => $value ) {
			if ( ! get_option( $key, false ) ) {
				update_option( $key, $value );
			}
		}

		$contact_defaults = array(
			'ibc_contact_address' => '',
			'ibc_contact_email'   => get_option( 'admin_email' ),
			'ibc_contact_phone'   => '',
			'ibc_contact_landline'=> '',
		);

		foreach ( $contact_defaults as $key => $value ) {
			if ( ! get_option( $key, false ) ) {
				update_option( $key, $value );
			}
		}

		if ( false === get_option( 'ibc_admin_password_hash', false ) ) {
			update_option( 'ibc_admin_password_hash', '' );
		}
	}
}
