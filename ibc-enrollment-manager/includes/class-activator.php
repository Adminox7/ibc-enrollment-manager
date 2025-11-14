<?php
/**
 * Activation logic: database table + defaults.
 *
 * @package IBC\Enrollment
 */

declare( strict_types=1 );

namespace IBC\Enrollment\Core;

use IBC\Enrollment\Database\DB;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fired on plugin activation.
 */
class Activator {

	/**
	 * Performs DB/table creation and seeds defaults.
	 *
	 * @return void
	 */
	public static function activate(): void {
		DB::instance()->migrate();
		self::seed_options();
		flush_rewrite_rules();
	}

	/**
	 * Inserts the default plugin options if absent.
	 *
	 * @return void
	 */
	private static function seed_options(): void {
		$defaults = [
			'ibc_capacity_limit'      => 1466,
			'ibc_price_prep'          => 1000,
			'ibc_brand_name'          => get_bloginfo( 'name', 'display' ) ?: 'IBC Morocco',
			'ibc_contact_address'     => '98 Avenue Fal Ould Oumeir, 4ᵉ étage, Agdal – Rabat',
			'ibc_contact_email'       => get_option( 'admin_email' ),
			'ibc_contact_phone'       => '06 77 14 52 81 • 06 68 66 89 45',
			'ibc_contact_landline'    => '05 37 68 26 16',
			'ibc_bank_name'           => 'Saham Bank',
			'ibc_account_holder'      => 'IBC-MOROCCO-MA',
			'ibc_rib'                 => '022810000050003032644323',
			'ibc_bic'                 => 'SGMBMAMCXXX',
			'ibc_agency'              => 'Agence Rabat',
			'ibc_payment_note'        => 'Indiquez impérativement la référence ci-dessous comme motif du paiement.',
			'ibc_brand_primary'       => '#4CB4B4',
			'ibc_brand_primary_dark'  => '#3A9191',
			'ibc_brand_primary_light' => '#E0F5F5',
			'ibc_brand_text_dark'     => '#1f2937',
			'ibc_brand_text_muted'    => '#6b7280',
			'ibc_brand_success'       => '#10b981',
			'ibc_brand_success_bg'    => '#d1fae5',
			'ibc_brand_danger'        => '#ef4444',
			'ibc_brand_danger_bg'     => '#fee2e2',
		];

		foreach ( $defaults as $key => $value ) {
			if ( false === get_option( $key, false ) ) {
				update_option( $key, $value );
			}
		}

		if ( false === get_option( 'ibc_admin_token_hash', false ) ) {
			update_option( 'ibc_admin_token_hash', '' );
		}

		if ( false === get_option( 'ibc_admin_token_last', false ) ) {
			update_option( 'ibc_admin_token_last', '' );
		}
	}
}
