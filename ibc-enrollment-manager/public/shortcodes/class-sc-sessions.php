<?php
/**
 * Sessions shortcode.
 *
 * @package IBC\EnrollmentManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class IBC_SC_Sessions
 */
class IBC_SC_Sessions {

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
	 * Register shortcode.
	 *
	 * @return void
	 */
	public function register(): void {
		add_shortcode( 'ibc_sessions', array( $this, 'render' ) );
	}

	/**
	 * Render shortcode.
	 *
	 * @param array $atts Attributes.
	 *
	 * @return string
	 */
	public function render( array $atts ): string {
		wp_enqueue_style( 'ibc-public' );

		$atts = shortcode_atts(
			array(
				'register_page' => '',
			),
			$atts,
			'ibc_sessions'
		);

		$sessions = $this->db->get_open_sessions();

		if ( empty( $sessions ) ) {
			return '<div class="ibc-sessions-list"><p>' . esc_html__( 'Aucune session disponible pour le moment.', 'ibc-enrollment' ) . '</p></div>';
		}

		$current_id  = get_queried_object_id();
		$current_url = ! empty( $atts['register_page'] ) ? esc_url( $atts['register_page'] ) : ( $current_id ? get_permalink( $current_id ) : home_url() );

		ob_start();
		?>
		<div class="ibc-sessions-list">
			<?php foreach ( $sessions as $session ) : ?>
				<?php
				$remaining = (int) $session['total_seats'] > 0 ? max( 0, (int) $session['total_seats'] - (int) $session['seats_taken'] ) : esc_html__( 'Illimité', 'ibc-enrollment' );
				$url       = add_query_arg(
					array(
						'session_id' => (int) $session['id'],
					),
					$current_url
				) . '#ibc-register';
				?>
				<div class="ibc-session-card">
					<h3 class="ibc-session-title"><?php echo esc_html( $session['title'] ); ?></h3>
					<ul class="ibc-session-meta">
						<li><strong><?php esc_html_e( 'Type', 'ibc-enrollment' ); ?> :</strong> <?php echo esc_html( ucfirst( $session['type'] ) ); ?></li>
						<li><strong><?php esc_html_e( 'Niveau', 'ibc-enrollment' ); ?> :</strong> <?php echo esc_html( $session['level'] ?: '-' ); ?></li>
						<li><strong><?php esc_html_e( 'Campus', 'ibc-enrollment' ); ?> :</strong> <?php echo esc_html( $session['campus'] ?: '-' ); ?></li>
						<li><strong><?php esc_html_e( 'Début', 'ibc-enrollment' ); ?> :</strong> <?php echo esc_html( ibc_format_datetime( $session['start_at'] ) ); ?></li>
						<li><strong><?php esc_html_e( 'Prix', 'ibc-enrollment' ); ?> :</strong> <?php echo esc_html( ibc_format_currency( (float) $session['price'], $session['currency'] ) ); ?></li>
						<li><strong><?php esc_html_e( 'Places restantes', 'ibc-enrollment' ); ?> :</strong> <?php echo esc_html( $remaining ); ?></li>
					</ul>
					<a class="ibc-button" href="<?php echo esc_url( $url ); ?>">
						<?php esc_html_e( 'S’inscrire', 'ibc-enrollment' ); ?>
					</a>
				</div>
			<?php endforeach; ?>
		</div>
		<?php

		return (string) ob_get_clean();
	}
}
