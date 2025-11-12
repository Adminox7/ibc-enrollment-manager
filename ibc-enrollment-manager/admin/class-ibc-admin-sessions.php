<?php
/**
 * Sessions management.
 *
 * @package IBC\EnrollmentManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class IBC_Admin_Sessions
 */
class IBC_Admin_Sessions {

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

		add_action( 'admin_post_ibc_save_session', array( $this, 'handle_save_session' ) );
		add_action( 'admin_post_ibc_delete_session', array( $this, 'handle_delete_session' ) );
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

		$action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : 'list'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( in_array( $action, array( 'new', 'edit' ), true ) ) {
			$this->render_form( $action );
			return;
		}

		$this->render_list();
	}

	/**
	 * Render list.
	 *
	 * @return void
	 */
	private function render_list(): void {
		$status = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$sessions = $this->db->get_sessions(
			array(
				'status' => $status,
				'search' => $search,
				'order'  => 'DESC',
			)
		);
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Sessions', 'ibc-enrollment' ); ?></h1>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=ibc-manager-sessions&action=new' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Ajouter', 'ibc-enrollment' ); ?></a>

			<form method="get" class="ibc-filter-form">
				<input type="hidden" name="page" value="ibc-manager-sessions"/>
				<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Recherche…', 'ibc-enrollment' ); ?>"/>
				<select name="status">
					<option value=""><?php esc_html_e( 'Tous les statuts', 'ibc-enrollment' ); ?></option>
					<option value="draft" <?php selected( $status, 'draft' ); ?>><?php esc_html_e( 'Brouillon', 'ibc-enrollment' ); ?></option>
					<option value="published" <?php selected( $status, 'published' ); ?>><?php esc_html_e( 'Publié', 'ibc-enrollment' ); ?></option>
					<option value="closed" <?php selected( $status, 'closed' ); ?>><?php esc_html_e( 'Clôturé', 'ibc-enrollment' ); ?></option>
				</select>
				<button class="button"><?php esc_html_e( 'Filtrer', 'ibc-enrollment' ); ?></button>
			</form>

			<table class="widefat striped">
				<thead>
				<tr>
					<th><?php esc_html_e( 'Titre', 'ibc-enrollment' ); ?></th>
					<th><?php esc_html_e( 'Type', 'ibc-enrollment' ); ?></th>
					<th><?php esc_html_e( 'Niveau', 'ibc-enrollment' ); ?></th>
					<th><?php esc_html_e( 'Campus', 'ibc-enrollment' ); ?></th>
					<th><?php esc_html_e( 'Inscription', 'ibc-enrollment' ); ?></th>
					<th><?php esc_html_e( 'Dates', 'ibc-enrollment' ); ?></th>
					<th><?php esc_html_e( 'Places', 'ibc-enrollment' ); ?></th>
					<th><?php esc_html_e( 'Statut', 'ibc-enrollment' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'ibc-enrollment' ); ?></th>
				</tr>
				</thead>
				<tbody>
				<?php if ( empty( $sessions ) ) : ?>
					<tr>
						<td colspan="9"><?php esc_html_e( 'Aucune session trouvée.', 'ibc-enrollment' ); ?></td>
					</tr>
				<?php else : ?>
					<?php foreach ( $sessions as $session ) : ?>
						<tr>
							<td><?php echo esc_html( $session['title'] ); ?></td>
							<td><?php echo esc_html( $session['type'] ); ?></td>
							<td><?php echo esc_html( $session['level'] ); ?></td>
							<td><?php echo esc_html( $session['campus'] ); ?></td>
							<td>
								<?php echo esc_html( ibc_format_datetime( $session['reg_start'] ) ); ?><br/>
								<?php echo esc_html( ibc_format_datetime( $session['reg_end'] ) ); ?>
							</td>
							<td>
								<?php echo esc_html( ibc_format_datetime( $session['start_at'] ) ); ?><br/>
								<?php echo esc_html( ibc_format_datetime( $session['end_at'] ) ); ?>
							</td>
							<td>
								<?php
								$total = (int) $session['total_seats'];
								$taken = (int) $session['seats_taken'];
								printf( '%1$s / %2$s', esc_html( $taken ), esc_html( $total ) );
								?>
							</td>
							<td><?php echo esc_html( $session['status'] ); ?></td>
							<td>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=ibc-manager-sessions&action=edit&id=' . (int) $session['id'] ) ); ?>">
									<?php esc_html_e( 'Modifier', 'ibc-enrollment' ); ?>
								</a>
								|
								<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ibc_delete_session&id=' . (int) $session['id'] ), 'ibc_delete_session_' . (int) $session['id'] ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Supprimer cette session ?', 'ibc-enrollment' ) ); ?>')">
									<?php esc_html_e( 'Supprimer', 'ibc-enrollment' ); ?>
								</a>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Render form.
	 *
	 * @param string $action Action.
	 *
	 * @return void
	 */
	private function render_form( string $action ): void {
		$session_id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$session    = array(
			'title'       => '',
			'type'        => 'prep',
			'level'       => '',
			'campus'      => '',
			'reg_start'   => '',
			'reg_end'     => '',
			'start_at'    => '',
			'end_at'      => '',
			'total_seats' => 0,
			'price'       => 0,
			'currency'    => 'MAD',
			'status'      => 'draft',
			'notes'       => '',
		);

		if ( 'edit' === $action ) {
			$session = $this->db->get_session( $session_id );

			if ( ! $session ) {
				wp_die( esc_html__( 'Session introuvable.', 'ibc-enrollment' ) );
			}
		}

		?>
		<div class="wrap">
			<h1><?php echo 'new' === $action ? esc_html__( 'Créer une session', 'ibc-enrollment' ) : esc_html__( 'Modifier la session', 'ibc-enrollment' ); ?></h1>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ibc-form">
				<?php wp_nonce_field( 'ibc_save_session' ); ?>
				<input type="hidden" name="action" value="ibc_save_session"/>
				<input type="hidden" name="session_id" value="<?php echo esc_attr( $session_id ); ?>"/>

				<table class="form-table">
					<tbody>
					<tr>
						<th scope="row"><label for="ibc_title"><?php esc_html_e( 'Titre', 'ibc-enrollment' ); ?></label></th>
						<td><input name="title" type="text" id="ibc_title" class="regular-text" value="<?php echo esc_attr( $session['title'] ); ?>" required/></td>
					</tr>
					<tr>
						<th scope="row"><label for="ibc_type"><?php esc_html_e( 'Type', 'ibc-enrollment' ); ?></label></th>
						<td>
							<select name="type" id="ibc_type">
								<option value="prep" <?php selected( $session['type'], 'prep' ); ?>><?php esc_html_e( 'Préparation', 'ibc-enrollment' ); ?></option>
								<option value="exam" <?php selected( $session['type'], 'exam' ); ?>><?php esc_html_e( 'Examen', 'ibc-enrollment' ); ?></option>
								<option value="bundle" <?php selected( $session['type'], 'bundle' ); ?>><?php esc_html_e( 'Pack', 'ibc-enrollment' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="ibc_level"><?php esc_html_e( 'Niveau', 'ibc-enrollment' ); ?></label></th>
						<td><input name="level" type="text" id="ibc_level" class="regular-text" value="<?php echo esc_attr( $session['level'] ); ?>"/></td>
					</tr>
					<tr>
						<th scope="row"><label for="ibc_campus"><?php esc_html_e( 'Campus', 'ibc-enrollment' ); ?></label></th>
						<td><input name="campus" type="text" id="ibc_campus" class="regular-text" value="<?php echo esc_attr( $session['campus'] ); ?>"/></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Fenêtre d’inscription', 'ibc-enrollment' ); ?></th>
						<td>
							<input name="reg_start" type="datetime-local" value="<?php echo esc_attr( $this->format_for_input( $session['reg_start'] ) ); ?>"/>
							<input name="reg_end" type="datetime-local" value="<?php echo esc_attr( $this->format_for_input( $session['reg_end'] ) ); ?>"/>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Dates de session', 'ibc-enrollment' ); ?></th>
						<td>
							<input name="start_at" type="datetime-local" value="<?php echo esc_attr( $this->format_for_input( $session['start_at'] ) ); ?>"/>
							<input name="end_at" type="datetime-local" value="<?php echo esc_attr( $this->format_for_input( $session['end_at'] ) ); ?>"/>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="ibc_seats"><?php esc_html_e( 'Nombre de places', 'ibc-enrollment' ); ?></label></th>
						<td><input name="total_seats" type="number" id="ibc_seats" value="<?php echo esc_attr( $session['total_seats'] ); ?>" min="0"/></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Tarif', 'ibc-enrollment' ); ?></th>
						<td>
							<input name="price" type="number" step="0.01" value="<?php echo esc_attr( $session['price'] ); ?>"/>
							<input name="currency" type="text" value="<?php echo esc_attr( $session['currency'] ); ?>" class="small-text"/>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="ibc_status"><?php esc_html_e( 'Statut', 'ibc-enrollment' ); ?></label></th>
						<td>
							<select name="status" id="ibc_status">
								<option value="draft" <?php selected( $session['status'], 'draft' ); ?>><?php esc_html_e( 'Brouillon', 'ibc-enrollment' ); ?></option>
								<option value="published" <?php selected( $session['status'], 'published' ); ?>><?php esc_html_e( 'Publié', 'ibc-enrollment' ); ?></option>
								<option value="closed" <?php selected( $session['status'], 'closed' ); ?>><?php esc_html_e( 'Clôturé', 'ibc-enrollment' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="ibc_notes"><?php esc_html_e( 'Notes internes', 'ibc-enrollment' ); ?></label></th>
						<td><textarea name="notes" id="ibc_notes" rows="4" class="large-text"><?php echo esc_textarea( $session['notes'] ); ?></textarea></td>
					</tr>
					</tbody>
				</table>

				<?php submit_button( __( 'Enregistrer', 'ibc-enrollment' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Handle save.
	 *
	 * @return void
	 */
	public function handle_save_session(): void {
		if ( ! ibc_current_user_can( IBC_Capabilities::CAPABILITY ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'ibc-enrollment' ) );
		}

		check_admin_referer( 'ibc_save_session' );

		$session_id = isset( $_POST['session_id'] ) ? (int) $_POST['session_id'] : 0;

		$data = array(
			'title'       => sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) ),
			'type'        => sanitize_text_field( wp_unslash( $_POST['type'] ?? 'prep' ) ),
			'level'       => sanitize_text_field( wp_unslash( $_POST['level'] ?? '' ) ),
			'campus'      => sanitize_text_field( wp_unslash( $_POST['campus'] ?? '' ) ),
			'reg_start'   => $this->parse_datetime( $_POST['reg_start'] ?? '' ),
			'reg_end'     => $this->parse_datetime( $_POST['reg_end'] ?? '' ),
			'start_at'    => $this->parse_datetime( $_POST['start_at'] ?? '' ),
			'end_at'      => $this->parse_datetime( $_POST['end_at'] ?? '' ),
			'total_seats' => isset( $_POST['total_seats'] ) ? (int) $_POST['total_seats'] : 0,
			'price'       => isset( $_POST['price'] ) ? (float) $_POST['price'] : 0,
			'currency'    => sanitize_text_field( wp_unslash( $_POST['currency'] ?? 'MAD' ) ),
			'status'      => sanitize_text_field( wp_unslash( $_POST['status'] ?? 'draft' ) ),
			'notes'       => ibc_sanitize_textarea( wp_unslash( $_POST['notes'] ?? '' ) ),
		);

		if ( $session_id > 0 ) {
			$this->db->update_session( $session_id, $data );
			$redirect = admin_url( 'admin.php?page=ibc-manager-sessions&updated=1' );
		} else {
			$this->db->insert_session( $data );
			$redirect = admin_url( 'admin.php?page=ibc-manager-sessions&created=1' );
		}

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Handle delete action.
	 *
	 * @return void
	 */
	public function handle_delete_session(): void {
		if ( ! ibc_current_user_can( IBC_Capabilities::CAPABILITY ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'ibc-enrollment' ) );
		}

		$session_id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		check_admin_referer( 'ibc_delete_session_' . $session_id );

		if ( $session_id ) {
			$this->db->delete_session( $session_id );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=ibc-manager-sessions&deleted=1' ) );
		exit;
	}

	/**
	 * Format datetime for input field.
	 *
	 * @param string|null $datetime Datetime.
	 *
	 * @return string
	 */
	private function format_for_input( ?string $datetime ): string {
		if ( empty( $datetime ) || '0000-00-00 00:00:00' === $datetime ) {
			return '';
		}

		return gmdate( 'Y-m-d\\TH:i', strtotime( $datetime ) );
	}

	/**
	 * Parse datetime from input.
	 *
	 * @param string $value Value.
	 *
	 * @return string|null
	 */
	private function parse_datetime( string $value ): ?string {
		if ( empty( $value ) ) {
			return null;
		}

		$timestamp = strtotime( $value );
		if ( false === $timestamp ) {
			return null;
		}

		return gmdate( 'Y-m-d H:i:s', $timestamp );
	}
}
