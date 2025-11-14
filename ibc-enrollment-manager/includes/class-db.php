<?php
/**
 * Database gateway responsible for the custom registrations table.
 *
 * @package IBC\Enrollment
 */

declare( strict_types=1 );

namespace IBC\Enrollment\Database;

use wpdb;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides CRUD helpers for `wp_ibc_registrations`.
 */
class DB {

	private const TABLE_SUFFIX = 'ibc_registrations';

	/**
	 * Shared singleton.
	 *
	 * @var DB|null
	 */
	private static ?DB $instance = null;

	private wpdb $wpdb;

	private string $table;

	private function __construct() {
		global $wpdb;

		$this->wpdb  = $wpdb;
		$this->table = $wpdb->prefix . self::TABLE_SUFFIX;
	}

	/**
	 * Returns singleton instance.
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Exposes table name.
	 */
	public function table(): string {
		return $this->table;
	}

	/**
	 * Creates or updates the table schema.
	 *
	 * @return void
	 */
	public function migrate(): void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $this->wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$this->table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			`timestamp` DATETIME NOT NULL,
			prenom VARCHAR(120) NOT NULL,
			nom VARCHAR(120) NOT NULL,
			date_naissance DATE NULL,
			lieu_naissance VARCHAR(160) NULL,
			email VARCHAR(190) NOT NULL,
			telephone VARCHAR(60) NOT NULL,
			niveau VARCHAR(10) NOT NULL,
			message TEXT NULL,
			cin_recto VARCHAR(255) NULL,
			cin_verso VARCHAR(255) NULL,
			pdf_url VARCHAR(255) NULL,
			statut VARCHAR(20) NOT NULL DEFAULT 'Confirme',
			reference VARCHAR(40) NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY reference_unique (reference),
			KEY email_idx (email),
			KEY telephone_idx (telephone),
			KEY statut_idx (statut),
			KEY niveau_idx (niveau)
		) {$charset};";

		dbDelta( $sql );
	}

	/**
	 * Inserts a registration row.
	 *
	 * @param array<string,mixed> $data Column => value.
	 * @return int Inserted ID.
	 */
	public function insert( array $data ): int {
		if ( empty( $data ) ) {
			return 0;
		}

		if ( empty( $data['timestamp'] ) ) {
			$data['timestamp'] = current_time( 'mysql' );
		}

		$this->wpdb->insert( $this->table, $data ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return (int) $this->wpdb->insert_id;
	}

	/**
	 * Updates a row by ID.
	 *
	 * @param int                  $id    Row ID.
	 * @param array<string,mixed>  $data  Data map.
	 * @return bool
	 */
	public function update( int $id, array $data ): bool {
		if ( $id <= 0 || empty( $data ) ) {
			return false;
		}

		$result = $this->wpdb->update(
			$this->table,
			$data,
			[ 'id' => $id ],
			null,
			[ '%d' ]
		);

		return false !== $result;
	}

	/**
	 * Updates a row by reference.
	 *
	 * @param string               $reference Unique reference.
	 * @param array<string,mixed>  $data      Data map.
	 * @return bool
	 */
	public function update_by_reference( string $reference, array $data ): bool {
		if ( '' === $reference || empty( $data ) ) {
			return false;
		}

		$result = $this->wpdb->update(
			$this->table,
			$data,
			[ 'reference' => $reference ],
			null,
			[ '%s' ]
		);

		return false !== $result;
	}

	/**
	 * Retrieves a row by ID.
	 */
	public function get( int $id ): ?array {
		if ( $id <= 0 ) {
			return null;
		}

		$sql = $this->wpdb->prepare(
			"SELECT * FROM {$this->table} WHERE id = %d",
			$id
		);

		$row = $this->wpdb->get_row( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return $row ?: null;
	}

	/**
	 * Retrieves a row by reference.
	 */
	public function get_by_reference( string $reference ): ?array {
		if ( '' === $reference ) {
			return null;
		}

		$sql = $this->wpdb->prepare(
			"SELECT * FROM {$this->table} WHERE reference = %s",
			$reference
		);

		$row = $this->wpdb->get_row( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return $row ?: null;
	}

	/**
	 * Returns the first active registration matching email or phone.
	 *
	 * @param string $email Normalized email.
	 * @param string $phone Normalized phone.
	 * @return array|null
	 */
	public function find_duplicate( string $email, string $phone ): ?array {
		$clauses = [];
		$params  = [];

		if ( $email ) {
			$clauses[] = 'email = %s';
			$params[]  = $email;
		}

		if ( $phone ) {
			$clauses[] = 'telephone = %s';
			$params[]  = $phone;
		}

		if ( empty( $clauses ) ) {
			return null;
		}

		$sql = "SELECT * FROM {$this->table} WHERE statut <> 'Annule' AND (" . implode( ' OR ', $clauses ) . ') LIMIT 1';

		$sql = $this->wpdb->prepare( $sql, $params );

		$row = $this->wpdb->get_row( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return $row ?: null;
	}

	/**
	 * Counts active (non annulées) registrations.
	 */
	public function count_active(): int {
		$sql = "SELECT COUNT(*) FROM {$this->table} WHERE statut <> 'Annule'"; // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return (int) $this->wpdb->get_var( $sql );
	}

	/**
	 * Runs a filtered query for the dashboard/API.
	 *
	 * @param array<string,mixed> $args Filters.
	 * @return array<int,array<string,mixed>>
	 */
	public function query( array $args = [] ): array {
		$defaults = [
			'search'   => '',
			'niveau'   => '',
			'statut'   => '',
			'per_page' => 25,
			'page'     => 1,
		];

		$args   = wp_parse_args( $args, $defaults );
		$offset = max( 0, ( (int) $args['page'] - 1 ) * (int) $args['per_page'] );
		$limit  = max( 1, (int) $args['per_page'] );

		$where  = [];
		$params = [];

		if ( $args['search'] ) {
			$like      = '%' . $this->wpdb->esc_like( $args['search'] ) . '%';
			$where[]   = '(prenom LIKE %s OR nom LIKE %s OR email LIKE %s OR telephone LIKE %s OR reference LIKE %s OR message LIKE %s)';
			$params    = array_merge( $params, array_fill( 0, 6, $like ) );
		}

		if ( $args['niveau'] ) {
			$where[]  = 'niveau = %s';
			$params[] = $args['niveau'];
		}

		if ( $args['statut'] ) {
			$where[]  = 'statut = %s';
			$params[] = $args['statut'];
		}

		$sql = "SELECT * FROM {$this->table}";

		if ( $where ) {
			$sql .= ' WHERE ' . implode( ' AND ', $where );
		}

		$sql .= ' ORDER BY `timestamp` DESC';
		$sql .= $this->wpdb->prepare( ' LIMIT %d OFFSET %d', $limit, $offset );

		if ( $params ) {
			$sql = $this->wpdb->prepare( $sql, $params );
		}

		return $this->wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * Counts rows with same filters as query().
	 *
	 * @param array<string,mixed> $args Filters.
	 * @return int
	 */
	public function count_filtered( array $args = [] ): int {
		$args = wp_parse_args(
			$args,
			[
				'search' => '',
				'niveau' => '',
				'statut' => '',
			]
		);

		$where  = [];
		$params = [];

		if ( $args['search'] ) {
			$like    = '%' . $this->wpdb->esc_like( $args['search'] ) . '%';
			$where[] = '(prenom LIKE %s OR nom LIKE %s OR email LIKE %s OR telephone LIKE %s OR reference LIKE %s OR message LIKE %s)';
			$params  = array_merge( $params, array_fill( 0, 6, $like ) );
		}

		if ( $args['niveau'] ) {
			$where[]  = 'niveau = %s';
			$params[] = $args['niveau'];
		}

		if ( $args['statut'] ) {
			$where[]  = 'statut = %s';
			$params[] = $args['statut'];
		}

		$sql = "SELECT COUNT(*) FROM {$this->table}";

		if ( $where ) {
			$sql .= ' WHERE ' . implode( ' AND ', $where );
		}

		if ( $params ) {
			$sql = $this->wpdb->prepare( $sql, $params );
		}

		return (int) $this->wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * Marks a registration as cancelled.
	 */
	public function soft_cancel( string $reference ): bool {
		if ( '' === $reference ) {
			return false;
		}

		return false !== $this->wpdb->update(
			$this->table,
			[ 'statut' => 'Annule' ],
			[ 'reference' => $reference ],
			[ '%s' ],
			[ '%s' ]
		);
	}
}
