<?php
/**
 * Database layer.
 *
 * @package IBC\EnrollmentManager
 */

namespace IBC;

use wpdb;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DB
 */
class DB {

	/**
	 * Singleton instance.
	 *
	 * @var DB|null
	 */
	private static ?DB $instance = null;

	/**
	 * WPDB instance.
	 *
	 * @var wpdb
	 */
	private wpdb $wpdb;

	/**
	 * Table name.
	 *
	 * @var string
	 */
	private string $table;

	/**
	 * Constructor.
	 */
	private function __construct() {
		global $wpdb;

		$this->wpdb  = $wpdb;
		$this->table = $wpdb->prefix . 'ibc_registrations';
	}

	/**
	 * Retrieve singleton.
	 *
	 * @return DB
	 */
	public static function instance(): DB {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Get table name.
	 *
	 * @return string
	 */
	public function table(): string {
		return $this->table;
	}

	/**
	 * Create database table.
	 *
	 * @return void
	 */
	public function create_table(): void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $this->wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$this->table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			created_at DATETIME NOT NULL,
			ref VARCHAR(40) NOT NULL UNIQUE,
			prenom VARCHAR(120) DEFAULT '',
			nom VARCHAR(120) DEFAULT '',
			full_name VARCHAR(240) DEFAULT '',
			birth_date VARCHAR(20) DEFAULT '',
			birth_place VARCHAR(160) DEFAULT '',
			email VARCHAR(190) DEFAULT '',
			phone VARCHAR(60) DEFAULT '',
			niveau VARCHAR(10) DEFAULT '',
			message TEXT NULL,
			cin_recto_url TEXT NULL,
			cin_verso_url TEXT NULL,
			statut VARCHAR(30) NOT NULL DEFAULT 'Confirme',
			PRIMARY KEY  (id),
			INDEX idx_email (email),
			INDEX idx_phone (phone),
			INDEX idx_statut (statut)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	/**
	 * Drop table.
	 *
	 * @return void
	 */
	public function drop_table(): void {
		$this->wpdb->query( "DROP TABLE IF EXISTS {$this->table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Insert registration.
	 *
	 * @param array $data Data.
	 *
	 * @return int Insert ID.
	 */
	public function insert( array $data ): int {
		$this->wpdb->insert(
			$this->table,
			$data,
			array(
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
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
	 * Update registration.
	 *
	 * @param int   $id   Row ID.
	 * @param array $data Data.
	 *
	 * @return bool
	 */
	public function update( int $id, array $data ): bool {
		if ( empty( $data ) ) {
			return false;
		}

		$formats = array();
		foreach ( $data as $value ) {
			$formats[] = is_numeric( $value ) ? '%s' : '%s';
		}

		$updated = $this->wpdb->update(
			$this->table,
			$data,
			array( 'id' => $id ),
			$formats,
			array( '%d' )
		);

		return false !== $updated;
	}

	/**
	 * Retrieve registration by ID.
	 *
	 * @param int $id Row ID.
	 *
	 * @return array|null
	 */
	public function get( int $id ): ?array {
		$sql = $this->wpdb->prepare(
			"SELECT * FROM {$this->table} WHERE id = %d",
			$id
		);

		$result = $this->wpdb->get_row( $sql, ARRAY_A );

		return $result ?: null;
	}

	/**
	 * Retrieve registration by reference.
	 *
	 * @param string $reference Reference.
	 *
	 * @return array|null
	 */
	public function get_by_ref( string $reference ): ?array {
		$sql = $this->wpdb->prepare(
			"SELECT * FROM {$this->table} WHERE ref = %s",
			$reference
		);

		$result = $this->wpdb->get_row( $sql, ARRAY_A );

		return $result ?: null;
	}

	/**
	 * Retrieve first registration by email.
	 *
	 * @param string $email Email.
	 *
	 * @return array|null
	 */
	public function get_by_email( string $email ): ?array {
		$sql = $this->wpdb->prepare(
			"SELECT * FROM {$this->table} WHERE email = %s LIMIT 1",
			$email
		);

		$result = $this->wpdb->get_row( $sql, ARRAY_A );

		return $result ?: null;
	}

	/**
	 * Retrieve first registration by phone.
	 *
	 * @param string $phone Phone.
	 *
	 * @return array|null
	 */
	public function get_by_phone( string $phone ): ?array {
		$sql = $this->wpdb->prepare(
			"SELECT * FROM {$this->table} WHERE phone = %s LIMIT 1",
			$phone
		);

		$result = $this->wpdb->get_row( $sql, ARRAY_A );

		return $result ?: null;
	}

	/**
	 * Count total registrations with statut different de Annule.
	 *
	 * @return int
	 */
	public function count_active(): int {
		$sql = "SELECT COUNT(*) FROM {$this->table} WHERE statut <> 'Annule'"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return (int) $this->wpdb->get_var( $sql );
	}

	/**
	 * Query registrations.
	 *
	 * @param array $args Arguments.
	 *
	 * @return array
	 */
	public function query( array $args = array() ): array {
		$defaults = array(
			'search' => '',
			'niveau' => '',
			'statut' => '',
			'offset' => 0,
			'limit'  => 100,
		);

		$args = wp_parse_args( $args, $defaults );

		$where  = array();
		$params = array();

		if ( ! empty( $args['search'] ) ) {
			$like     = '%' . $this->wpdb->esc_like( $args['search'] ) . '%';
			$where[]  = '(prenom LIKE %s OR nom LIKE %s OR full_name LIKE %s OR email LIKE %s OR phone LIKE %s OR ref LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		if ( ! empty( $args['niveau'] ) ) {
			$where[]  = 'niveau = %s';
			$params[] = $args['niveau'];
		}

		if ( ! empty( $args['statut'] ) ) {
			$where[]  = 'statut = %s';
			$params[] = $args['statut'];
		}

		$sql = "SELECT * FROM {$this->table}";

		if ( ! empty( $where ) ) {
			$sql .= ' WHERE ' . implode( ' AND ', $where );
		}

		$sql .= ' ORDER BY created_at DESC';

		if ( $args['limit'] > 0 ) {
			$sql .= $this->wpdb->prepare( ' LIMIT %d OFFSET %d', (int) $args['limit'], (int) $args['offset'] );
		}

		if ( ! empty( $params ) ) {
			$sql = $this->wpdb->prepare( $sql, $params );
		}

		return $this->wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Soft delete registration.
	 *
	 * @param string $reference Reference.
	 *
	 * @return bool
	 */
	public function soft_delete_by_ref( string $reference ): bool {
		$updated = $this->wpdb->update(
			$this->table,
			array( 'statut' => 'Annule' ),
			array( 'ref' => $reference ),
			array( '%s' ),
			array( '%s' )
		);

		return false !== $updated;
	}
}
