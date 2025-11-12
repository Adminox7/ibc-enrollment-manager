<?php
/**
 * Seat lock handler.
 *
 * @package IBC\EnrollmentManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class IBC_SeatLock
 */
class IBC_SeatLock {

	/**
	 * Cron hook.
	 */
	public const CRON_HOOK = 'ibc_purge_expired_locks';

	/**
	 * Seat lock duration (in minutes).
	 */
	private const LOCK_DURATION = 10;

	/**
	 * Singleton instance.
	 *
	 * @var IBC_SeatLock|null
	 */
	private static $instance = null;

	/**
	 * Database layer.
	 *
	 * @var IBC_DB
	 */
	private $db;

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->db = IBC_DB::get_instance();

		add_action( self::CRON_HOOK, array( $this, 'purge_expired_locks' ) );
	}

	/**
	 * Retrieve singleton.
	 *
	 * @return IBC_SeatLock
	 */
	public static function get_instance(): IBC_SeatLock {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Schedule cron event.
	 *
	 * @return void
	 */
	public function schedule(): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, 'five_minutes', self::CRON_HOOK );
		}
	}

	/**
	 * Schedule if needed.
	 *
	 * @return void
	 */
	public function maybe_schedule(): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			$this->schedule();
		}
	}

	/**
	 * Clear cron.
	 *
	 * @return void
	 */
	public function clear_schedule(): void {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
		}
	}

	/**
	 * Purge expired seat locks.
	 *
	 * @return void
	 */
	public function purge_expired_locks(): void {
		$this->db->cancel_expired_locks();
	}

	/**
	 * Calculate seat lock expiration time.
	 *
	 * @return string
	 */
	public function get_lock_expiration(): string {
		return gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) + ( self::LOCK_DURATION * MINUTE_IN_SECONDS ) );
	}

	/**
	 * Validate seat availability for a session.
	 *
	 * @param array $session Session row.
	 *
	 * @return bool
	 */
	public function has_available_seat( array $session ): bool {
		if ( empty( $session ) ) {
			return false;
		}

		if ( (int) $session['total_seats'] === 0 ) {
			return true;
		}

		$locks = $this->db->count_active_locks( (int) $session['id'] );
		$left  = (int) $session['total_seats'] - (int) $session['seats_taken'] - $locks;

		return $left > 0;
	}
}

/**
 * Register custom schedule.
 *
 * @param array $schedules Schedules.
 *
 * @return array
 */
function ibc_register_five_minutes_schedule( array $schedules ): array {
	if ( ! isset( $schedules['five_minutes'] ) ) {
		$schedules['five_minutes'] = array(
			'interval' => 5 * MINUTE_IN_SECONDS,
			'display'  => esc_html__( 'Toutes les 5 minutes', 'ibc-enrollment' ),
		);
	}

	return $schedules;
}

add_filter( 'cron_schedules', 'ibc_register_five_minutes_schedule' );
