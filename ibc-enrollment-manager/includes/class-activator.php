<?php
/**
 * Activation callbacks.
 *
 * @package IBC\Enrollment
 */

namespace IBC\Enrollment;

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

		update_option( 'ibc_price_prep', get_option( 'ibc_price_prep', 1000 ) );

		if ( false === get_option( 'ibc_brand_name', false ) ) {
			$site_name = get_bloginfo( 'name', 'display' );
			update_option( 'ibc_brand_name', $site_name ?: 'IBC Morocco' );
		}

		$brand_defaults = array(
			'primary'      => '#4CB4B4',
			'secondary'    => '#2A8E8E',
			'text'         => '#1F2937',
			'muted'        => '#E0F5F5',
			'border'       => '#E5E7EB',
			'button'       => '#4CB4B4',
			'button_text'  => '#FFFFFF',
			'success_bg'   => '#DCFCE7',
			'success_text' => '#166534',
			'error_bg'     => '#FEE2E2',
			'error_text'   => '#B91C1C',
		);

		foreach ( $brand_defaults as $key => $value ) {
			$option = 'ibc_brand_' . $key;
			if ( false === get_option( $option, false ) ) {
				update_option( $option, $value );
			}
		}

		$payment_defaults = array(
			'ibc_bank_name'      => 'Attijariwafa Bank',
			'ibc_account_holder' => 'IBC Morocco',
			'ibc_rib'            => '',
			'ibc_iban'           => '',
			'ibc_bic'            => '',
			'ibc_agency'         => '',
			'ibc_payment_note'   => \__( 'Paiement non remboursable, à effectuer sous 24h.', 'ibc-enrollment-manager' ),
		);

		foreach ( $payment_defaults as $key => $value ) {
			if ( false === get_option( $key, false ) ) {
				update_option( $key, $value );
			}
		}

		$contact_defaults = array(
			'ibc_contact_address'  => '',
			'ibc_contact_email'    => get_option( 'admin_email' ),
			'ibc_contact_phone'    => '',
			'ibc_contact_landline' => '',
		);

		foreach ( $contact_defaults as $key => $value ) {
			if ( false === get_option( $key, false ) ) {
				update_option( $key, $value );
			}
		}

		if ( false === get_option( 'ibc_admin_password_hash', false ) ) {
			update_option( 'ibc_admin_password_hash', '' );
		}

		if ( false === get_option( 'ibc_active_tokens', false ) ) {
			update_option( 'ibc_active_tokens', array() );
		}

		if ( false === get_option( 'ibc_last_token_issued', false ) ) {
			update_option( 'ibc_last_token_issued', '' );
		}

		if ( false === get_option( 'ibc_form_schema', false ) && class_exists( '\IBC\Enrollment\FormBuilder' ) ) {
			$builder = new \IBC\Enrollment\FormBuilder();
			update_option( 'ibc_form_schema', $builder->get_default_schema() );
		}
	}
}
