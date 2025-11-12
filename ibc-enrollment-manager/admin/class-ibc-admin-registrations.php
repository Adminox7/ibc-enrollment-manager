<?php
/**
 * Registrations management.
 *
 * @package IBC\EnrollmentManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class IBC_Admin_Registrations
 */
class IBC_Admin_Registrations {

	/**
	 * Database layer.
	 *
	 * @var IBC_DB
	 */
	private $db;

	/**
	 * Email handler.
	 *
	 * @var IBC_Emails
	 */
	private $emails;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->db     = IBC_DB::get_instance();
		$this->emails = IBC_Emails::get_instance();

		add_action( 'admin_post_ibc_update_registration', array( $this, 'handle_update' ) );
		add_action( 'admin_post_ibc_resend_registration_email', array( $this, 'handle_resend_email' ) );
		add_action( 'admin_post_ibc_export_registrations', array( $this, 'handle_export' ) );
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

		$filters = array(
			'session_id' => isset( $_GET['session_id'] ) ? (int) $_GET['session_id'] : 0, // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'status'     => isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'search'     => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		);

		$registrations = $this->db->get_registrations(
			array(
				'session_id' => $filters['session_id'],
				'status'     => $filters['status'],
				'search'     => $filters['search'],
				'limit'      => 200,
			)
		);

		$sessions = $this->db->get_sessions(
			array(
				'limit' => 0,
				'order' => 'DESC',
			)
		);
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Inscriptions', 'ibc-enrollment' ); ?></h1>

			<div class="ibc-toolbar">
				<form method="get" class="ibc-filter-form">
					<input type="hidden" name="page" value="ibc-manager-registrations"/>
					<select name="session_id">
						<option value="0"><?php esc_html_e( 'Toutes les sessions', 'ibc-enrollment' ); ?></option>
						<?php foreach ( $sessions as $session ) : ?>
							<option value="<?php echo esc_attr( $session['id'] ); ?>" <?php selected( $filters['session_id'], (int) $session['id'] ); ?>>
								<?php echo esc_html( $session['title'] ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<select name="status">
						<option value=""><?php esc_html_e( 'Tous les statuts', 'ibc-enrollment' ); ?></option>
						<option value="pending" <?php selected( $filters['status'], 'pending' ); ?>><?php esc_html_e( 'En attente', 'ibc-enrollment' ); ?></option>
						<option value="confirmed" <?php selected( $filters['status'], 'confirmed' ); ?>><?php esc_html_e( 'Confirmée', 'ibc-enrollment' ); ?></option>
						<option value="paid" <?php selected( $filters['status'], 'paid' ); ?>><?php esc_html_e( 'Payée', 'ibc-enrollment' ); ?></option>
						<option value="canceled" <?php selected( $filters['status'], 'canceled' ); ?>><?php esc_html_e( 'Annulée', 'ibc-enrollment' ); ?></option>
					</select>
					<input type="search" name="s" value="<?php echo esc_attr( $filters['search'] ); ?>" placeholder="<?php esc_attr_e( 'Recherche étudiant…', 'ibc-enrollment' ); ?>"/>
					<button class="button"><?php esc_html_e( 'Filtrer', 'ibc-enrollment' ); ?></button>
				</form>

				<div class="ibc-toolbar-actions">
					<a class="button button-secondary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ibc_export_registrations' ), 'ibc_export_registrations' ) ); ?>">
						<?php esc_html_e( 'Exporter CSV', 'ibc-enrollment' ); ?>
					</a>
				</div>
			</div>

			<table class="widefat striped">
				<thead>
				<tr>
					<th><?php esc_html_e( 'Étudiant', 'ibc-enrollment' ); ?></th>
					<th><?php esc_html_e( 'Session', 'ibc-enrollment' ); ?></th>
					<th><?php esc_html_e( 'Montant', 'ibc-enrollment' ); ?></th>
					<th><?php esc_html_e( 'Statut', 'ibc-enrollment' ); ?></th>
					<th><?php esc_html_e( 'Créé le', 'ibc-enrollment' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'ibc-enrollment' ); ?></th>
				</tr>
				</thead>
				<tbody>
				<?php if ( empty( $registrations ) ) : ?>
					<tr>
						<td colspan="6"><?php esc_html_e( 'Aucune inscription trouvée.', 'ibc-enrollment' ); ?></td>
					</tr>
				<?php else : ?>
					<?php foreach ( $registrations as $registration ) : ?>
						<tr>
							<td>
								<strong><?php echo esc_html( $registration['full_name'] ); ?></strong><br/>
								<a href="mailto:<?php echo esc_attr( $registration['email'] ); ?>"><?php echo esc_html( $registration['email'] ); ?></a><br/>
								<a href="tel:<?php echo esc_attr( $registration['phone'] ); ?>"><?php echo esc_html( $registration['phone'] ); ?></a>
							</td>
							<td>
								<?php echo esc_html( $registration['session_title'] ); ?><br/>
								<small><?php echo esc_html( ibc_format_datetime( $registration['session_start'] ) ); ?></small>
							</td>
							<td><?php echo esc_html( ibc_format_currency( (float) $registration['amount'], $registration['currency'] ) ); ?></td>
							<td><?php echo esc_html( ucfirst( $registration['status'] ) ); ?></td>
							<td><?php echo esc_html( ibc_format_datetime( $registration['created_at'] ) ); ?></td>
							<td>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ibc-inline-form">
									<?php wp_nonce_field( 'ibc_update_registration_' . (int) $registration['id'] ); ?>
									<input type="hidden" name="action" value="ibc_update_registration"/>
									<input type="hidden" name="registration_id" value="<?php echo esc_attr( $registration['id'] ); ?>"/>
									<select name="status">
										<option value="pending" <?php selected( $registration['status'], 'pending' ); ?>><?php esc_html_e( 'En attente', 'ibc-enrollment' ); ?></option>
										<option value="confirmed" <?php selected( $registration['status'], 'confirmed' ); ?>><?php esc_html_e( 'Confirmée', 'ibc-enrollment' ); ?></option>
										<option value="paid" <?php selected( $registration['status'], 'paid' ); ?>><?php esc_html_e( 'Payée', 'ibc-enrollment' ); ?></option>
										<option value="canceled" <?php selected( $registration['status'], 'canceled' ); ?>><?php esc_html_e( 'Annulée', 'ibc-enrollment' ); ?></option>
									</select>
									<input type="text" name="payment_ref" value="<?php echo esc_attr( $registration['payment_ref'] ); ?>" placeholder="<?php esc_attr_e( 'Réf. paiement', 'ibc-enrollment' ); ?>" class="regular-text"/>
									<button class="button button-small"><?php esc_html_e( 'Mettre à jour', 'ibc-enrollment' ); ?></button>
								</form>
								<a class="button-link" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ibc_resend_registration_email&id=' . (int) $registration['id'] ), 'ibc_resend_registration_email_' . (int) $registration['id'] ) ); ?>">
									<?php esc_html_e( 'Renvoyer l’email', 'ibc-enrollment' ); ?>
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
	 * Handle registration update.
	 *
	 * @return void
	 */
	public function handle_update(): void {
		if ( ! ibc_current_user_can( IBC_Capabilities::CAPABILITY ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'ibc-enrollment' ) );
		}

		$registration_id = isset( $_POST['registration_id'] ) ? (int) $_POST['registration_id'] : 0;

		check_admin_referer( 'ibc_update_registration_' . $registration_id );

		$status      = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : 'pending';
		$payment_ref = isset( $_POST['payment_ref'] ) ? sanitize_text_field( wp_unslash( $_POST['payment_ref'] ) ) : '';

		$data = array(
			'status'       => $status,
			'payment_ref'  => $payment_ref,
		);

		if ( in_array( $status, array( 'confirmed', 'paid' ), true ) ) {
			$data['seat_lock_until'] = null;
		}

		$this->db->update_registration( $registration_id, $data );

		$this->maybe_send_status_email( $registration_id, $status );

		wp_safe_redirect( admin_url( 'admin.php?page=ibc-manager-registrations&updated=1' ) );
		exit;
	}

	/**
	 * Handle resend email.
	 *
	 * @return void
	 */
	public function handle_resend_email(): void {
		if ( ! ibc_current_user_can( IBC_Capabilities::CAPABILITY ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'ibc-enrollment' ) );
		}

		$registration_id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		check_admin_referer( 'ibc_resend_registration_email_' . $registration_id );

		$registration = $this->db->get_registration( $registration_id );

		if ( $registration ) {
			$this->send_email_for_registration( $registration );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=ibc-manager-registrations&resent=1' ) );
		exit;
	}

	/**
	 * Conditionally send email based on status.
	 *
	 * @param int    $registration_id Registration ID.
	 * @param string $status          Status.
	 *
	 * @return void
	 */
	private function maybe_send_status_email( int $registration_id, string $status ): void {
		$registration = $this->db->get_registration( $registration_id );
		if ( ! $registration ) {
			return;
		}

		$this->send_email_for_registration( $registration, $status );
	}

	/**
	 * Send email.
	 *
	 * @param array       $registration Registration.
	 * @param string|null $override_status Override status.
	 *
	 * @return void
	 */
	private function send_email_for_registration( array $registration, ?string $override_status = null ): void {
		$status = $override_status ?? $registration['status'];

		$session = $this->db->get_session( (int) $registration['session_id'] );
		$student = $this->db->get_student( (int) $registration['student_id'] );

		if ( ! $session || ! $student ) {
			return;
		}

		if ( 'pending' === $status ) {
			$this->emails->send_registration_received( $student, $session, $registration );
		} elseif ( 'confirmed' === $status ) {
			$this->emails->send_registration_confirmed( $student, $session );
		} elseif ( 'paid' === $status ) {
			$this->emails->send_payment_confirmed( $student, $session, $registration );
		}

		$session_date = ! empty( $session['start_at'] ) ? wp_date( 'd/m/Y H:i', strtotime( $session['start_at'] ) ) : '';
		ibc_send_whatsapp_template(
			$student['phone'],
			array(
				$student['full_name'],
				$session['title'],
				$session_date,
			)
		);
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

		check_admin_referer( 'ibc_export_registrations' );

		$rows = $this->db->get_registrations(
			array(
				'limit' => 0,
			)
		);

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=ibc-registrations-' . gmdate( 'Ymd-His' ) . '.csv' );

		$output = fopen( 'php://output', 'w' );
		fputcsv(
			$output,
			array(
				'id',
				'session_id',
				'session_title',
				'student_id',
				'full_name',
				'email',
				'phone',
				'status',
				'amount',
				'currency',
				'payment_method',
				'payment_ref',
				'created_at',
				'updated_at',
			)
		);

		foreach ( $rows as $row ) {
			fputcsv(
				$output,
				array(
					$row['id'],
					$row['session_id'],
					$row['session_title'],
					$row['student_id'],
					$row['full_name'],
					$row['email'],
					$row['phone'],
					$row['status'],
					$row['amount'],
					$row['currency'],
					$row['payment_method'],
					$row['payment_ref'],
					$row['created_at'],
					$row['updated_at'],
				)
			);
		}

		fclose( $output );
		exit;
	}
}
