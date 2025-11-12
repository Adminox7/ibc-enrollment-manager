<?php
/**
 * Students management.
 *
 * @package IBC\EnrollmentManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class IBC_Admin_Students
 */
class IBC_Admin_Students {

	/**
	 * Database layer.
	 *
	 * @var IBC_DB
	 */
	private $db;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->db = IBC_DB::get_instance();

		add_action( 'admin_post_ibc_export_students', array( $this, 'handle_export' ) );
		add_action( 'admin_post_ibc_import_students', array( $this, 'handle_import' ) );
		add_action( 'admin_post_ibc_merge_students', array( $this, 'handle_merge' ) );
	}

	/**
	 * Render page.
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! ibc_current_user_can( IBC_Capabilities::CAPABILITY ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'ibc-enrollment' ) );
		}

		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$students = $this->db->get_students(
			array(
				'search' => $search,
				'limit'  => 200,
			)
		);
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Étudiants', 'ibc-enrollment' ); ?></h1>

			<div class="ibc-toolbar">
				<form method="get" class="ibc-filter-form">
					<input type="hidden" name="page" value="ibc-manager-students"/>
					<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Recherche email, téléphone...', 'ibc-enrollment' ); ?>"/>
					<button class="button"><?php esc_html_e( 'Rechercher', 'ibc-enrollment' ); ?></button>
				</form>

				<div class="ibc-toolbar-actions">
					<a class="button button-secondary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ibc_export_students' ), 'ibc_export_students' ) ); ?>">
						<?php esc_html_e( 'Exporter CSV', 'ibc-enrollment' ); ?>
					</a>
				</div>
			</div>

			<h2><?php esc_html_e( 'Importer un fichier CSV', 'ibc-enrollment' ); ?></h2>
			<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ibc-import-form">
				<?php wp_nonce_field( 'ibc_import_students' ); ?>
				<input type="hidden" name="action" value="ibc_import_students"/>
				<input type="file" name="import_file" accept=".csv" required/>
				<button class="button button-primary"><?php esc_html_e( 'Importer', 'ibc-enrollment' ); ?></button>
				<p class="description"><?php esc_html_e( 'Colonnes supportées : full_name, email, phone, cin, birthdate (YYYY-MM-DD), city, notes.', 'ibc-enrollment' ); ?></p>
			</form>

			<h2><?php esc_html_e( 'Liste des étudiants', 'ibc-enrollment' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'ibc_merge_students' ); ?>
				<input type="hidden" name="action" value="ibc_merge_students"/>
				<table class="widefat striped">
					<thead>
					<tr>
						<th><?php esc_html_e( 'Fusionner', 'ibc-enrollment' ); ?></th>
						<th><?php esc_html_e( 'Principal', 'ibc-enrollment' ); ?></th>
						<th><?php esc_html_e( 'Nom complet', 'ibc-enrollment' ); ?></th>
						<th><?php esc_html_e( 'Email', 'ibc-enrollment' ); ?></th>
						<th><?php esc_html_e( 'Téléphone', 'ibc-enrollment' ); ?></th>
						<th><?php esc_html_e( 'CIN', 'ibc-enrollment' ); ?></th>
						<th><?php esc_html_e( 'Ville', 'ibc-enrollment' ); ?></th>
						<th><?php esc_html_e( 'Dernière mise à jour', 'ibc-enrollment' ); ?></th>
					</tr>
					</thead>
					<tbody>
					<?php if ( empty( $students ) ) : ?>
						<tr>
							<td colspan="8"><?php esc_html_e( 'Aucun étudiant trouvé.', 'ibc-enrollment' ); ?></td>
						</tr>
					<?php else : ?>
						<?php foreach ( $students as $student ) : ?>
							<tr>
								<td><input type="checkbox" name="student_ids[]" value="<?php echo esc_attr( $student['id'] ); ?>"/></td>
								<td><input type="radio" name="primary_id" value="<?php echo esc_attr( $student['id'] ); ?>"/></td>
								<td><?php echo esc_html( $student['full_name'] ); ?></td>
								<td><?php echo esc_html( $student['email'] ); ?></td>
								<td><?php echo esc_html( $student['phone'] ); ?></td>
								<td><?php echo esc_html( $student['cin'] ); ?></td>
								<td><?php echo esc_html( $student['city'] ); ?></td>
								<td><?php echo esc_html( ibc_format_datetime( $student['updated_at'] ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
					</tbody>
				</table>

				<p>
					<button class="button button-primary"><?php esc_html_e( 'Fusionner la sélection', 'ibc-enrollment' ); ?></button>
					<span class="description"><?php esc_html_e( 'Sélectionnez les doublons avec la case, puis choisissez l\'enregistrement principal.', 'ibc-enrollment' ); ?></span>
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * Handle export.
	 *
	 * @return void
	 */
	public function handle_export(): void {
		if ( ! ibc_current_user_can( IBC_Capabilities::CAPABILITY ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'ibc-enrollment' ) );
		}

		check_admin_referer( 'ibc_export_students' );

		$rows = $this->db->export_table( 'students' );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=ibc-students-' . gmdate( 'Ymd-His' ) . '.csv' );

		$output = fopen( 'php://output', 'w' );

		fputcsv(
			$output,
			array( 'id', 'full_name', 'email', 'phone', 'cin', 'birthdate', 'city', 'notes', 'created_at', 'updated_at' )
		);

		foreach ( $rows as $row ) {
			fputcsv(
				$output,
				array(
					$row['id'],
					$row['full_name'],
					$row['email'],
					$row['phone'],
					$row['cin'],
					$row['birthdate'],
					$row['city'],
					$row['notes'],
					$row['created_at'],
					$row['updated_at'],
				)
			);
		}

		fclose( $output );
		exit;
	}

	/**
	 * Handle import.
	 *
	 * @return void
	 */
	public function handle_import(): void {
		if ( ! ibc_current_user_can( IBC_Capabilities::CAPABILITY ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'ibc-enrollment' ) );
		}

		check_admin_referer( 'ibc_import_students' );

		if ( empty( $_FILES['import_file']['tmp_name'] ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=ibc-manager-students&import=0' ) );
			exit;
		}

		$file = $_FILES['import_file'];
		if ( ! empty( $file['error'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=ibc-manager-students&import=0' ) );
			exit;
		}

		$handle = fopen( $file['tmp_name'], 'r' );
		if ( ! $handle ) {
			wp_safe_redirect( admin_url( 'admin.php?page=ibc-manager-students&import=0' ) );
			exit;
		}

		$header  = fgetcsv( $handle, 0, ',' );
		$rows    = array();
		$columns = array();

		if ( $header ) {
			foreach ( $header as $index => $column ) {
				$columns[ strtolower( trim( $column ) ) ] = $index;
			}
		}

		while ( ( $data = fgetcsv( $handle, 0, ',' ) ) !== false ) {
			$row = array(
				'full_name' => $data[ $columns['full_name'] ] ?? '',
				'email'     => $data[ $columns['email'] ] ?? '',
				'phone'     => $data[ $columns['phone'] ] ?? '',
				'cin'       => $data[ $columns['cin'] ] ?? '',
				'birthdate' => $data[ $columns['birthdate'] ] ?? '',
				'city'      => $data[ $columns['city'] ] ?? '',
				'notes'     => $data[ $columns['notes'] ] ?? '',
			);

			$rows[] = $row;
		}

		fclose( $handle );

		$count = $this->db->import_students( $rows );

		wp_safe_redirect( admin_url( 'admin.php?page=ibc-manager-students&imported=' . $count ) );
		exit;
	}

	/**
	 * Handle merge.
	 *
	 * @return void
	 */
	public function handle_merge(): void {
		if ( ! ibc_current_user_can( IBC_Capabilities::CAPABILITY ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'ibc-enrollment' ) );
		}

		check_admin_referer( 'ibc_merge_students' );

		$primary_id   = isset( $_POST['primary_id'] ) ? (int) $_POST['primary_id'] : 0;
		$student_ids  = isset( $_POST['student_ids'] ) ? array_map( 'intval', (array) $_POST['student_ids'] ) : array();

		if ( $primary_id && ! empty( $student_ids ) ) {
			$this->db->merge_students( $primary_id, $student_ids );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=ibc-manager-students&merged=1' ) );
		exit;
	}
}
