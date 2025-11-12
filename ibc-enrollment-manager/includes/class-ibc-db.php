<?php
/**
 * Database handler for IBC Enrollment Manager.
 *
 * @package IBC\EnrollmentManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class IBC_DB
 */
class IBC_DB {

	/**
	 * Singleton instance.
	 *
	 * @var IBC_DB|null
	 */
	private static $instance = null;

	/**
	 * WordPress database accessor.
	 *
	 * @var wpdb
	 */
	private $wpdb;

	/**
	 * Table names.
	 *
	 * @var array<string, string>
	 */
	private $tables = array();

	/**
	 * Constructor.
	 */
	private function __construct() {
		global $wpdb;

		$this->wpdb = $wpdb;
		$prefix     = $wpdb->prefix;

		$this->tables = array(
			'sessions'      => "{$prefix}ibc_sessions",
			'students'      => "{$prefix}ibc_students",
			'registrations' => "{$prefix}ibc_registrations",
		);
	}

	/**
	 * Retrieve singleton.
	 *
	 * @return IBC_DB
	 */
	public static function get_instance(): IBC_DB {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Get table name.
	 *
	 * @param string $key Table key.
	 *
	 * @return string
	 */
	public function table( string $key ): string {
		return $this->tables[ $key ] ?? '';
	}

	/**
	 * Create database tables.
	 *
	 * @return void
	 */
	public function create_tables(): void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $this->wpdb->get_charset_collate();
		if ( false === stripos( $charset_collate, 'ENGINE' ) ) {
			$charset_collate .= ' ENGINE=InnoDB';
		}

		$queries = array(
			"CREATE TABLE {$this->tables['sessions']} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				title VARCHAR(255) NOT NULL,
				type VARCHAR(20) NOT NULL DEFAULT 'prep',
				level VARCHAR(100) DEFAULT '',
				campus VARCHAR(120) DEFAULT '',
				reg_start DATETIME NULL,
				reg_end DATETIME NULL,
				start_at DATETIME NULL,
				end_at DATETIME NULL,
				total_seats INT UNSIGNED NOT NULL DEFAULT 0,
				seats_taken INT UNSIGNED NOT NULL DEFAULT 0,
				price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
				currency VARCHAR(10) NOT NULL DEFAULT 'MAD',
				status VARCHAR(20) NOT NULL DEFAULT 'draft',
				notes TEXT NULL,
				created_at DATETIME NOT NULL,
				updated_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				KEY status (status),
				KEY type (type),
				KEY start_at (start_at),
				KEY reg_start (reg_start)
			) {$charset_collate}",
			"CREATE TABLE {$this->tables['students']} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				full_name VARCHAR(200) NOT NULL,
				email VARCHAR(180) DEFAULT NULL,
				phone VARCHAR(25) DEFAULT NULL,
				cin VARCHAR(60) DEFAULT '',
				birthdate DATE NULL,
				city VARCHAR(120) DEFAULT '',
				notes TEXT NULL,
				created_at DATETIME NOT NULL,
				updated_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY unique_email (email),
				UNIQUE KEY unique_phone (phone),
				KEY full_name (full_name)
			) {$charset_collate}",
			"CREATE TABLE {$this->tables['registrations']} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				session_id BIGINT UNSIGNED NOT NULL,
				student_id BIGINT UNSIGNED NOT NULL,
				status VARCHAR(20) NOT NULL DEFAULT 'pending',
				amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
				currency VARCHAR(10) NOT NULL DEFAULT 'MAD',
				payment_method VARCHAR(50) DEFAULT '',
				payment_ref VARCHAR(100) DEFAULT '',
				seat_lock_until DATETIME NULL,
				notes TEXT NULL,
				created_at DATETIME NOT NULL,
				updated_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY session_student (session_id, student_id),
				KEY status (status),
				KEY seat_lock_until (seat_lock_until),
				CONSTRAINT fk_ibc_reg_session FOREIGN KEY (session_id) REFERENCES {$this->tables['sessions']}(id) ON DELETE CASCADE,
				CONSTRAINT fk_ibc_reg_student FOREIGN KEY (student_id) REFERENCES {$this->tables['students']}(id) ON DELETE CASCADE
			) {$charset_collate}",
		);

		foreach ( $queries as $sql ) {
			dbDelta( $sql );
		}
	}

	/**
	 * Drop database tables.
	 *
	 * @return void
	 */
	public function drop_tables(): void {
		foreach ( $this->tables as $table ) {
			$this->wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
	}

	/**
	 * Retrieve sessions.
	 *
	 * @param array $args Query args.
	 *
	 * @return array
	 */
	public function get_sessions( array $args = array() ): array {
		$defaults = array(
			'status'   => '',
			'search'   => '',
			'orderby'  => 'start_at',
			'order'    => 'ASC',
			'limit'    => 0,
			'offset'   => 0,
		);

		$args   = wp_parse_args( $args, $defaults );
		$order  = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';
		$limit  = (int) $args['limit'];
		$offset = (int) $args['offset'];

		$where   = array();
		$params  = array();

		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'status = %s';
			$params[] = $args['status'];
		}

		if ( ! empty( $args['search'] ) ) {
			$like     = '%' . $this->wpdb->esc_like( $args['search'] ) . '%';
			$where[]  = '(title LIKE %s OR campus LIKE %s OR level LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		$where_sql = '';
		if ( ! empty( $where ) ) {
			$where_sql = 'WHERE ' . implode( ' AND ', $where );
		}

		$orderby = in_array( $args['orderby'], array( 'start_at', 'reg_start', 'title', 'status' ), true ) ? $args['orderby'] : 'start_at';

		$sql = "SELECT * FROM {$this->tables['sessions']} {$where_sql} ORDER BY {$orderby} {$order}";

		if ( $limit > 0 ) {
			$sql .= $this->wpdb->prepare( ' LIMIT %d OFFSET %d', $limit, $offset );
		}

		if ( ! empty( $params ) ) {
			$sql = $this->wpdb->prepare( $sql, $params );
		}

		return $this->wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Retrieve open sessions for front-end display.
	 *
	 * @return array
	 */
	public function get_open_sessions(): array {
		$now = ibc_current_time();

		$sql = $this->wpdb->prepare(
			"SELECT s.*, (s.total_seats - s.seats_taken) AS seats_left
			FROM {$this->tables['sessions']} s
			WHERE s.status = %s
			AND (s.reg_start IS NULL OR s.reg_start <= %s)
			AND (s.reg_end IS NULL OR s.reg_end >= %s)
			AND (s.total_seats = 0 OR s.total_seats > s.seats_taken)
			ORDER BY s.start_at ASC",
			'published',
			$now,
			$now
		);

		return $this->wpdb->get_results( $sql, ARRAY_A );
	}

	/**
	 * Retrieve session by ID.
	 *
	 * @param int $id Session ID.
	 *
	 * @return array|null
	 */
	public function get_session( int $id ): ?array {
		$sql = $this->wpdb->prepare(
			"SELECT * FROM {$this->tables['sessions']} WHERE id = %d",
			$id
		);

		$data = $this->wpdb->get_row( $sql, ARRAY_A );

		return $data ? $data : null;
	}

	/**
	 * Insert new session.
	 *
	 * @param array $data Session data.
	 *
	 * @return int Session ID.
	 */
	public function insert_session( array $data ): int {
		$now = ibc_current_time();

		$prepared = array(
			'title'       => sanitize_text_field( $data['title'] ?? '' ),
			'type'        => sanitize_text_field( $data['type'] ?? 'prep' ),
			'level'       => sanitize_text_field( $data['level'] ?? '' ),
			'campus'      => sanitize_text_field( $data['campus'] ?? '' ),
			'reg_start'   => ! empty( $data['reg_start'] ) ? sanitize_text_field( $data['reg_start'] ) : null,
			'reg_end'     => ! empty( $data['reg_end'] ) ? sanitize_text_field( $data['reg_end'] ) : null,
			'start_at'    => ! empty( $data['start_at'] ) ? sanitize_text_field( $data['start_at'] ) : null,
			'end_at'      => ! empty( $data['end_at'] ) ? sanitize_text_field( $data['end_at'] ) : null,
			'total_seats' => isset( $data['total_seats'] ) ? max( 0, (int) $data['total_seats'] ) : 0,
			'seats_taken' => isset( $data['seats_taken'] ) ? max( 0, (int) $data['seats_taken'] ) : 0,
			'price'       => isset( $data['price'] ) ? (float) $data['price'] : 0,
			'currency'    => ! empty( $data['currency'] ) ? sanitize_text_field( $data['currency'] ) : 'MAD',
			'status'      => sanitize_text_field( $data['status'] ?? 'draft' ),
			'notes'       => ! empty( $data['notes'] ) ? ibc_sanitize_textarea( $data['notes'] ) : null,
			'created_at'  => $now,
			'updated_at'  => $now,
		);

		$this->wpdb->insert(
			$this->tables['sessions'],
			$prepared,
			array(
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%d',
				'%d',
				'%f',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
			)
		);

		return (int) $this->wpdb->insert_id;
	}

	/**
	 * Update session.
	 *
	 * @param int   $id   Session ID.
	 * @param array $data Data.
	 *
	 * @return bool
	 */
	public function update_session( int $id, array $data ): bool {
		$prepared = array(
			'title'       => sanitize_text_field( $data['title'] ?? '' ),
			'type'        => sanitize_text_field( $data['type'] ?? 'prep' ),
			'level'       => sanitize_text_field( $data['level'] ?? '' ),
			'campus'      => sanitize_text_field( $data['campus'] ?? '' ),
			'reg_start'   => ! empty( $data['reg_start'] ) ? sanitize_text_field( $data['reg_start'] ) : null,
			'reg_end'     => ! empty( $data['reg_end'] ) ? sanitize_text_field( $data['reg_end'] ) : null,
			'start_at'    => ! empty( $data['start_at'] ) ? sanitize_text_field( $data['start_at'] ) : null,
			'end_at'      => ! empty( $data['end_at'] ) ? sanitize_text_field( $data['end_at'] ) : null,
			'total_seats' => isset( $data['total_seats'] ) ? max( 0, (int) $data['total_seats'] ) : 0,
			'price'       => isset( $data['price'] ) ? (float) $data['price'] : 0,
			'currency'    => ! empty( $data['currency'] ) ? sanitize_text_field( $data['currency'] ) : 'MAD',
			'status'      => sanitize_text_field( $data['status'] ?? 'draft' ),
			'notes'       => ! empty( $data['notes'] ) ? ibc_sanitize_textarea( $data['notes'] ) : null,
			'updated_at'  => ibc_current_time(),
		);

		$this->wpdb->update(
			$this->tables['sessions'],
			$prepared,
			array( 'id' => $id ),
			array(
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%d',
				'%f',
				'%s',
				'%s',
				'%s',
			),
			array( '%d' )
		);

		return ! empty( $this->wpdb->rows_affected );
	}

	/**
	 * Delete session.
	 *
	 * @param int $id Session ID.
	 *
	 * @return bool
	 */
	public function delete_session( int $id ): bool {
		$this->wpdb->delete(
			$this->tables['sessions'],
			array( 'id' => $id ),
			array( '%d' )
		);

		return ! empty( $this->wpdb->rows_affected );
	}

	/**
	 * Synchronize seats taken for a session.
	 *
	 * @param int $session_id Session ID.
	 *
	 * @return void
	 */
	public function sync_session_seats( int $session_id ): void {
		$count = (int) $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->tables['registrations']}
				WHERE session_id = %d AND status IN ('confirmed', 'paid')",
				$session_id
			)
		);

		$this->wpdb->update(
			$this->tables['sessions'],
			array(
				'seats_taken' => $count,
				'updated_at'  => ibc_current_time(),
			),
			array( 'id' => $session_id ),
			array( '%d', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Retrieve student by ID.
	 *
	 * @param int $id Student ID.
	 *
	 * @return array|null
	 */
	public function get_student( int $id ): ?array {
		$sql = $this->wpdb->prepare(
			"SELECT * FROM {$this->tables['students']} WHERE id = %d",
			$id
		);

		$data = $this->wpdb->get_row( $sql, ARRAY_A );

		return $data ? $data : null;
	}

	/**
	 * Locate student by email or phone.
	 *
	 * @param string $email Email.
	 * @param string $phone Phone.
	 *
	 * @return array|null
	 */
	public function get_student_by_contact( string $email, string $phone ): ?array {
		$email = sanitize_email( $email );
		$phone = ibc_sanitize_phone( $phone );

		$conditions = array();
		$params     = array();

		if ( ! empty( $email ) ) {
			$conditions[] = 'email = %s';
			$params[]     = $email;
		}

		if ( ! empty( $phone ) ) {
			$conditions[] = 'phone = %s';
			$params[]     = $phone;
		}

		if ( empty( $conditions ) ) {
			return null;
		}

		$sql = "SELECT * FROM {$this->tables['students']} WHERE " . implode( ' OR ', $conditions ) . ' LIMIT 1';
		$sql = $this->wpdb->prepare( $sql, $params );

		$data = $this->wpdb->get_row( $sql, ARRAY_A );

		return $data ? $data : null;
	}

	/**
	 * Insert or update student record.
	 *
	 * @param array $data Data.
	 *
	 * @return int Student ID.
	 */
	public function upsert_student( array $data ): int {
		$email = sanitize_email( $data['email'] ?? '' );
		$phone = ibc_sanitize_phone( $data['phone'] ?? '' );

		$existing = $this->get_student_by_contact( $email, $phone );

		$prepared = array(
			'full_name'  => sanitize_text_field( $data['full_name'] ?? '' ),
			'email'      => ! empty( $email ) ? $email : null,
			'phone'      => ! empty( $phone ) ? $phone : null,
			'cin'        => ! empty( $data['cin'] ) ? sanitize_text_field( $data['cin'] ) : '',
			'birthdate'  => ! empty( $data['birthdate'] ) ? sanitize_text_field( $data['birthdate'] ) : null,
			'city'       => ! empty( $data['city'] ) ? sanitize_text_field( $data['city'] ) : '',
			'notes'      => ! empty( $data['notes'] ) ? ibc_sanitize_textarea( $data['notes'] ) : null,
			'updated_at' => ibc_current_time(),
		);

		if ( $existing ) {
			$this->wpdb->update(
				$this->tables['students'],
				$prepared,
				array( 'id' => $existing['id'] ),
				array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' ),
				array( '%d' )
			);

			return (int) $existing['id'];
		}

		$prepared['created_at'] = ibc_current_time();

		$this->wpdb->insert(
			$this->tables['students'],
			$prepared,
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return (int) $this->wpdb->insert_id;
	}

	/**
	 * Merge duplicate students.
	 *
	 * @param int   $primary_id   Destination student ID.
	 * @param array $duplicate_ids Duplicate student IDs.
	 *
	 * @return int Number of merged records.
	 */
	public function merge_students( int $primary_id, array $duplicate_ids ): int {
		$duplicate_ids = array_filter(
			array_map( 'intval', $duplicate_ids ),
			static fn ( int $value ): bool => $value > 0 && $value !== $primary_id
		);

		if ( empty( $duplicate_ids ) ) {
			return 0;
		}

		$id_placeholders = implode( ',', array_fill( 0, count( $duplicate_ids ), '%d' ) );

		$this->wpdb->query(
			$this->wpdb->prepare(
				"UPDATE {$this->tables['registrations']} SET student_id = %d WHERE student_id IN ({$id_placeholders})",
				array_merge( array( $primary_id ), $duplicate_ids )
			)
		);

		$this->wpdb->query(
			$this->wpdb->prepare(
				"DELETE FROM {$this->tables['students']} WHERE id IN ({$id_placeholders})",
				$duplicate_ids
			)
		);

		return (int) $this->wpdb->rows_affected;
	}

	/**
	 * Retrieve students with filters.
	 *
	 * @param array $args Query args.
	 *
	 * @return array
	 */
	public function get_students( array $args = array() ): array {
		$defaults = array(
			'search' => '',
			'limit'  => 50,
			'offset' => 0,
		);

		$args = wp_parse_args( $args, $defaults );

		$where  = array();
		$params = array();

		if ( ! empty( $args['search'] ) ) {
			$like     = '%' . $this->wpdb->esc_like( $args['search'] ) . '%';
			$where[]  = '(full_name LIKE %s OR email LIKE %s OR phone LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		$sql = "SELECT * FROM {$this->tables['students']}";

		if ( ! empty( $where ) ) {
			$sql .= ' WHERE ' . implode( ' AND ', $where );
		}

		$sql .= ' ORDER BY updated_at DESC';

		if ( $args['limit'] > 0 ) {
			$sql .= $this->wpdb->prepare( ' LIMIT %d OFFSET %d', (int) $args['limit'], (int) $args['offset'] );
		}

		if ( ! empty( $params ) ) {
			$sql = $this->wpdb->prepare( $sql, $params );
		}

		return $this->wpdb->get_results( $sql, ARRAY_A );
	}

	/**
	 * Count active locks for a session.
	 *
	 * @param int $session_id Session ID.
	 *
	 * @return int
	 */
	public function count_active_locks( int $session_id ): int {
		$sql = $this->wpdb->prepare(
			"SELECT COUNT(*) FROM {$this->tables['registrations']}
			WHERE session_id = %d
			AND status = 'pending'
			AND seat_lock_until IS NOT NULL
			AND seat_lock_until >= %s",
			$session_id,
			ibc_current_time()
		);

		return (int) $this->wpdb->get_var( $sql );
	}

	/**
	 * Create registration.
	 *
	 * @param array $data Registration data.
	 *
	 * @return int Registration ID.
	 */
	public function create_registration( array $data ): int {
		$prepared = array(
			'session_id'     => (int) $data['session_id'],
			'student_id'     => (int) $data['student_id'],
			'status'         => sanitize_text_field( $data['status'] ?? 'pending' ),
			'amount'         => isset( $data['amount'] ) ? (float) $data['amount'] : 0.0,
			'currency'       => ! empty( $data['currency'] ) ? sanitize_text_field( $data['currency'] ) : 'MAD',
			'payment_method' => ! empty( $data['payment_method'] ) ? sanitize_text_field( $data['payment_method'] ) : '',
			'payment_ref'    => ! empty( $data['payment_ref'] ) ? sanitize_text_field( $data['payment_ref'] ) : '',
			'seat_lock_until'=> ! empty( $data['seat_lock_until'] ) ? sanitize_text_field( $data['seat_lock_until'] ) : null,
			'notes'          => ! empty( $data['notes'] ) ? ibc_sanitize_textarea( $data['notes'] ) : null,
			'created_at'     => ibc_current_time(),
			'updated_at'     => ibc_current_time(),
		);

		$this->wpdb->insert(
			$this->tables['registrations'],
			$prepared,
			array(
				'%d',
				'%d',
				'%s',
				'%f',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
			)
		);

		$this->sync_session_seats( (int) $data['session_id'] );

		return (int) $this->wpdb->insert_id;
	}

	/**
	 * Update registration status and related fields.
	 *
	 * @param int   $registration_id Registration ID.
	 * @param array $data            Data.
	 *
	 * @return bool
	 */
	public function update_registration( int $registration_id, array $data ): bool {
		$fields = array();

		if ( isset( $data['status'] ) ) {
			$fields['status'] = array(
				'value'  => sanitize_text_field( $data['status'] ),
				'format' => '%s',
			);
		}

		if ( isset( $data['amount'] ) ) {
			$fields['amount'] = array(
				'value'  => (float) $data['amount'],
				'format' => '%f',
			);
		}

		if ( isset( $data['currency'] ) ) {
			$fields['currency'] = array(
				'value'  => sanitize_text_field( $data['currency'] ),
				'format' => '%s',
			);
		}

		if ( isset( $data['payment_method'] ) ) {
			$fields['payment_method'] = array(
				'value'  => sanitize_text_field( $data['payment_method'] ),
				'format' => '%s',
			);
		}

		if ( isset( $data['payment_ref'] ) ) {
			$fields['payment_ref'] = array(
				'value'  => sanitize_text_field( $data['payment_ref'] ),
				'format' => '%s',
			);
		}

		if ( array_key_exists( 'seat_lock_until', $data ) ) {
			$fields['seat_lock_until'] = array(
				'value'  => ! empty( $data['seat_lock_until'] ) ? sanitize_text_field( $data['seat_lock_until'] ) : null,
				'format' => '%s',
			);
		}

		if ( array_key_exists( 'notes', $data ) ) {
			$fields['notes'] = array(
				'value'  => ! empty( $data['notes'] ) ? ibc_sanitize_textarea( $data['notes'] ) : null,
				'format' => '%s',
			);
		}

		$fields['updated_at'] = array(
			'value'  => ibc_current_time(),
			'format' => '%s',
		);

		if ( empty( $fields ) ) {
			return false;
		}

		$data_values = array();
		$formats     = array();

		foreach ( $fields as $key => $field ) {
			if ( null === $field['value'] ) {
				$data_values[ $key ] = null;
			} else {
				$data_values[ $key ] = $field['value'];
			}
			$formats[] = $field['format'];
		}

		$this->wpdb->update(
			$this->tables['registrations'],
			$data_values,
			array( 'id' => $registration_id ),
			$formats,
			array( '%d' )
		);

		$session_id = (int) $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT session_id FROM {$this->tables['registrations']} WHERE id = %d",
				$registration_id
			)
		);

		if ( $session_id ) {
			$this->sync_session_seats( $session_id );
		}

		return ! empty( $this->wpdb->rows_affected );
	}

	/**
	 * Retrieve registrations.
	 *
	 * @param array $args Args.
	 *
	 * @return array
	 */
	public function get_registrations( array $args = array() ): array {
		$defaults = array(
			'session_id' => 0,
			'status'     => '',
			'search'     => '',
			'limit'      => 50,
			'offset'     => 0,
		);

		$args = wp_parse_args( $args, $defaults );

		$where  = array();
		$params = array();

		if ( ! empty( $args['session_id'] ) ) {
			$where[]  = 'r.session_id = %d';
			$params[] = (int) $args['session_id'];
		}

		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'r.status = %s';
			$params[] = sanitize_text_field( $args['status'] );
		}

		if ( ! empty( $args['search'] ) ) {
			$like     = '%' . $this->wpdb->esc_like( $args['search'] ) . '%';
			$where[]  = '(s.full_name LIKE %s OR s.email LIKE %s OR s.phone LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		$sql = "SELECT r.*, sess.title AS session_title, sess.start_at AS session_start, stu.full_name, stu.email, stu.phone
				FROM {$this->tables['registrations']} r
				INNER JOIN {$this->tables['sessions']} sess ON sess.id = r.session_id
				INNER JOIN {$this->tables['students']} stu ON stu.id = r.student_id";

		if ( ! empty( $where ) ) {
			$sql .= ' WHERE ' . implode( ' AND ', $where );
		}

		$sql .= ' ORDER BY r.created_at DESC';

		if ( $args['limit'] > 0 ) {
			$sql .= $this->wpdb->prepare( ' LIMIT %d OFFSET %d', (int) $args['limit'], (int) $args['offset'] );
		}

		if ( ! empty( $params ) ) {
			$sql = $this->wpdb->prepare( $sql, $params );
		}

		return $this->wpdb->get_results( $sql, ARRAY_A );
	}

	/**
	 * Retrieve registration by ID.
	 *
	 * @param int $id Registration ID.
	 *
	 * @return array|null
	 */
	public function get_registration( int $id ): ?array {
		$sql = $this->wpdb->prepare(
			"SELECT * FROM {$this->tables['registrations']} WHERE id = %d",
			$id
		);

		$data = $this->wpdb->get_row( $sql, ARRAY_A );

		return $data ? $data : null;
	}

	/**
	 * Retrieve registration by session and student.
	 *
	 * @param int $session_id Session ID.
	 * @param int $student_id Student ID.
	 *
	 * @return array|null
	 */
	public function get_registration_by_session_student( int $session_id, int $student_id ): ?array {
		$sql = $this->wpdb->prepare(
			"SELECT * FROM {$this->tables['registrations']} WHERE session_id = %d AND student_id = %d",
			$session_id,
			$student_id
		);

		$data = $this->wpdb->get_row( $sql, ARRAY_A );

		return $data ? $data : null;
	}

	/**
	 * Cancel expired locks.
	 *
	 * @return int
	 */
	public function cancel_expired_locks(): int {
		$now = ibc_current_time();

		$registrations = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT id, session_id FROM {$this->tables['registrations']}
				WHERE status = 'pending'
				AND seat_lock_until IS NOT NULL
				AND seat_lock_until < %s",
				$now
			),
			ARRAY_A
		);

		if ( empty( $registrations ) ) {
			return 0;
		}

		$ids        = wp_list_pluck( $registrations, 'id' );
		$id_place   = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$session_ids = array_map( 'intval', wp_list_pluck( $registrations, 'session_id' ) );

		$this->wpdb->query(
			$this->wpdb->prepare(
				"UPDATE {$this->tables['registrations']}
				SET status = 'canceled', seat_lock_until = NULL, updated_at = %s
				WHERE id IN ({$id_place})",
				array_merge( array( $now ), $ids )
			)
		);

		foreach ( array_unique( $session_ids ) as $session_id ) {
			$this->sync_session_seats( (int) $session_id );
		}

		return count( $ids );
	}

	/**
	 * Count registrations by status for dashboard.
	 *
	 * @return array
	 */
	public function get_registration_counts(): array {
		$sql = "SELECT status, COUNT(*) as total
				FROM {$this->tables['registrations']}
				GROUP BY status";

		$results = $this->wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$output  = array();

		foreach ( $results as $row ) {
			$output[ $row['status'] ] = (int) $row['total'];
		}

		return $output;
	}

	/**
	 * Get KPI metrics.
	 *
	 * @return array
	 */
	public function get_dashboard_metrics(): array {
		$today_start = gmdate( 'Y-m-d 00:00:00', current_time( 'timestamp' ) );
		$today_end   = gmdate( 'Y-m-d 23:59:59', current_time( 'timestamp' ) );

		$today_regs = (int) $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->tables['registrations']}
				WHERE created_at BETWEEN %s AND %s",
				$today_start,
				$today_end
			)
		);

		$total_students = (int) $this->wpdb->get_var(
			"SELECT COUNT(*) FROM {$this->tables['students']}" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);

		$upcoming_sessions = (int) $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->tables['sessions']}
				WHERE status = %s AND (start_at IS NULL OR start_at >= %s)",
				'published',
				ibc_current_time()
			)
		);

		$seats_left = (int) $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT SUM(GREATEST(total_seats - seats_taken, 0)) FROM {$this->tables['sessions']}
				WHERE status = %s",
				'published'
			)
		);

		return array(
			'today_registrations' => $today_regs,
			'total_students'      => $total_students,
			'upcoming_sessions'   => $upcoming_sessions,
			'seats_left'          => $seats_left,
		);
	}

	/**
	 * Export table rows.
	 *
	 * @param string $table_key Table key.
	 *
	 * @return array
	 */
	public function export_table( string $table_key ): array {
		$table = $this->table( $table_key );
		if ( empty( $table ) ) {
			return array();
		}

		$sql = "SELECT * FROM {$table} ORDER BY id ASC";

		return $this->wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Import students from CSV.
	 *
	 * @param array $rows Rows.
	 *
	 * @return int
	 */
	public function import_students( array $rows ): int {
		$imported = 0;

		foreach ( $rows as $row ) {
			if ( empty( $row['full_name'] ) ) {
				continue;
			}

			$student_id = $this->upsert_student( $row );
			if ( $student_id ) {
				++$imported;
			}
		}

		return $imported;
	}

	/**
	 * Import registrations from CSV.
	 *
	 * @param array $rows Rows.
	 *
	 * @return int
	 */
	public function import_registrations( array $rows ): int {
		$imported = 0;

		foreach ( $rows as $row ) {
			if ( empty( $row['session_id'] ) || empty( $row['full_name'] ) ) {
				continue;
			}

			$student_id = $this->upsert_student( $row );
			if ( ! $student_id ) {
				continue;
			}

			$existing = $this->wpdb->get_var(
				$this->wpdb->prepare(
					"SELECT id FROM {$this->tables['registrations']}
					WHERE session_id = %d AND student_id = %d",
					(int) $row['session_id'],
					$student_id
				)
			);

			if ( $existing ) {
				$this->update_registration(
					(int) $existing,
					array(
						'status'   => $row['status'] ?? 'pending',
						'amount'   => isset( $row['amount'] ) ? (float) $row['amount'] : 0,
						'currency' => $row['currency'] ?? 'MAD',
					)
				);
			} else {
				$this->create_registration(
					array(
						'session_id' => (int) $row['session_id'],
						'student_id' => $student_id,
						'status'     => $row['status'] ?? 'pending',
						'amount'     => isset( $row['amount'] ) ? (float) $row['amount'] : 0,
						'currency'   => $row['currency'] ?? 'MAD',
					)
				);
			}

			++$imported;
		}

		return $imported;
	}
}
