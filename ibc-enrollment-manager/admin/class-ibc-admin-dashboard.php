<?php
/**
 * Dashboard page.
 *
 * @package IBC\EnrollmentManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class IBC_Admin_Dashboard
 */
class IBC_Admin_Dashboard {

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
	}

	/**
	 * Render admin page.
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! ibc_current_user_can( IBC_Capabilities::CAPABILITY ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'ibc-enrollment' ) );
		}

		$metrics         = $this->db->get_dashboard_metrics();
		$registration_by = $this->db->get_registration_counts();
		$upcoming        = $this->db->get_sessions(
			array(
				'status'  => 'published',
				'orderby' => 'start_at',
				'order'   => 'ASC',
				'limit'   => 5,
			)
		);
		?>
		<div class="wrap ibc-admin-page">
			<h1><?php esc_html_e( 'Tableau de bord IBC', 'ibc-enrollment' ); ?></h1>

			<div class="ibc-metrics-grid">
				<div class="ibc-card">
					<h2><?php esc_html_e( 'Inscriptions du jour', 'ibc-enrollment' ); ?></h2>
					<p class="ibc-metric-value"><?php echo esc_html( $metrics['today_registrations'] ); ?></p>
				</div>
				<div class="ibc-card">
					<h2><?php esc_html_e( 'Étudiants actifs', 'ibc-enrollment' ); ?></h2>
					<p class="ibc-metric-value"><?php echo esc_html( $metrics['total_students'] ); ?></p>
				</div>
				<div class="ibc-card">
					<h2><?php esc_html_e( 'Sessions à venir', 'ibc-enrollment' ); ?></h2>
					<p class="ibc-metric-value"><?php echo esc_html( $metrics['upcoming_sessions'] ); ?></p>
				</div>
				<div class="ibc-card">
					<h2><?php esc_html_e( 'Places disponibles', 'ibc-enrollment' ); ?></h2>
					<p class="ibc-metric-value"><?php echo esc_html( $metrics['seats_left'] ); ?></p>
				</div>
			</div>

			<div class="ibc-grid">
				<div class="ibc-card">
					<h2><?php esc_html_e( 'Statut des inscriptions', 'ibc-enrollment' ); ?></h2>
					<ul class="ibc-status-list">
						<li><strong><?php esc_html_e( 'En attente', 'ibc-enrollment' ); ?>:</strong> <?php echo esc_html( $registration_by['pending'] ?? 0 ); ?></li>
						<li><strong><?php esc_html_e( 'Confirmées', 'ibc-enrollment' ); ?>:</strong> <?php echo esc_html( $registration_by['confirmed'] ?? 0 ); ?></li>
						<li><strong><?php esc_html_e( 'Payées', 'ibc-enrollment' ); ?>:</strong> <?php echo esc_html( $registration_by['paid'] ?? 0 ); ?></li>
						<li><strong><?php esc_html_e( 'Annulées', 'ibc-enrollment' ); ?>:</strong> <?php echo esc_html( $registration_by['canceled'] ?? 0 ); ?></li>
					</ul>
				</div>
				<div class="ibc-card">
					<h2><?php esc_html_e( 'Sessions à venir', 'ibc-enrollment' ); ?></h2>
					<?php if ( empty( $upcoming ) ) : ?>
						<p><?php esc_html_e( 'Aucune session publiée.', 'ibc-enrollment' ); ?></p>
					<?php else : ?>
						<table class="widefat striped">
							<thead>
							<tr>
								<th><?php esc_html_e( 'Titre', 'ibc-enrollment' ); ?></th>
								<th><?php esc_html_e( 'Début', 'ibc-enrollment' ); ?></th>
								<th><?php esc_html_e( 'Campus', 'ibc-enrollment' ); ?></th>
								<th><?php esc_html_e( 'Places restantes', 'ibc-enrollment' ); ?></th>
							</tr>
							</thead>
							<tbody>
							<?php foreach ( $upcoming as $session ) : ?>
								<tr>
									<td><?php echo esc_html( $session['title'] ); ?></td>
									<td><?php echo esc_html( ibc_format_datetime( $session['start_at'] ) ); ?></td>
									<td><?php echo esc_html( $session['campus'] ); ?></td>
									<td><?php echo esc_html( max( 0, (int) $session['total_seats'] - (int) $session['seats_taken'] ) ); ?></td>
								</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
	}
}
