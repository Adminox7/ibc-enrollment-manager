<?php
/**
 * Shared admin page helpers.
 *
 * @package IBC\EnrollmentManager
 */

namespace IBC\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Admin_Page
 *
 * Provides small UI helpers used across the plugin admin screens.
 */
class Admin_Page {

	/**
	 * Output a section heading with optional description.
	 *
	 * @param string $title       Section title.
	 * @param string $description Optional description.
	 *
	 * @return void
	 */
	public static function heading( string $title, string $description = '' ): void {
		echo '<div class="ibc-admin-section">';
		echo '<h3 class="ibc-admin-section__title">' . esc_html( $title ) . '</h3>';

		if ( $description ) {
			echo '<p class="ibc-admin-section__description">' . esc_html( $description ) . '</p>';
		}

		echo '</div>';
	}

	/**
	 * Prints a key/value table based on associative array.
	 *
	 * @param array $rows Associative array of label => value.
	 *
	 * @return void
	 */
	public static function definition_list( array $rows ): void {
		if ( empty( $rows ) ) {
			return;
		}

		echo '<dl class="ibc-admin-definition">';
		foreach ( $rows as $label => $value ) {
			echo '<dt>' . esc_html( $label ) . '</dt>';
			echo '<dd>' . esc_html( $value ) . '</dd>';
		}
		echo '</dl>';
	}
}
