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

		update_option( 'ibc_price_prep', get_option( 'ibc_price_prep', 1000 ) );

		$brand_defaults = array(
			'primary'      => '#e94162',
			'secondary'    => '#0f172a',
			'text'         => '#1f2937',
			'muted'        => '#f8fafc',
			'border'       => '#e2e8f0',
			'button'       => '#e94162',
			'button_text'  => '#ffffff',
			'success_bg'   => '#dcfce7',
			'success_text' => '#166534',
			'error_bg'     => '#fee2e2',
			'error_text'   => '#991b1b',
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

		if ( false === get_option( 'ibc_form_schema', false ) && class_exists( '\IBC\FormBuilder' ) ) {
			$builder = new \IBC\FormBuilder();
			update_option( 'ibc_form_schema', $builder->get_default_schema() );
		}
	}
}
