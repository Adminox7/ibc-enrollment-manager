<?php
/**
 * PDF generation utilities.
 *
 * @package IBC\EnrollmentManager
 */

namespace IBC;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PDF
 */
class PDF {

	/**
	 * Generate preparation receipt PDF.
	 *
	 * @param array $data Context data (fullName, phone, email, level, ref, createdAt, payDeadline, price).
	 *
	 * @return string|WP_Error Absolute file path or error.
	 */
	public function generate_prep_receipt( array $data ) {
		if ( ! class_exists( '\Dompdf\Dompdf' ) ) {
			return new WP_Error(
				'ibc_pdf_disabled',
				\__( 'La génération de PDF est indisponible. Merci de contacter l’administrateur.', 'ibc-enrollment-manager' )
			);
		}

		$html = $this->build_html( $data );

		$options = new \Dompdf\Options();
		$options->set( 'isRemoteEnabled', true );
		$options->set( 'isHtml5ParserEnabled', true );

		$dompdf = new \Dompdf\Dompdf( $options );
		$dompdf->loadHtml( $html );
		$dompdf->setPaper( 'A4', 'portrait' );
		$dompdf->render();

		$output = $dompdf->output();

		$upload = ibc_get_upload_dir();
		$ref    = ! empty( $data['ref'] ) ? sanitize_title( $data['ref'] ) : uniqid( 'ibc', true );
		$file   = trailingslashit( $upload['path'] ) . 'recu-prepa-' . $ref . '.pdf';

		if ( ! wp_mkdir_p( dirname( $file ) ) ) {
			return new WP_Error( 'ibc_pdf_path', \__( 'Impossible de créer le dossier de stockage PDF.', 'ibc-enrollment-manager' ) );
		}

		$result = file_put_contents( $file, $output ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents
		if ( false === $result ) {
			return new WP_Error( 'ibc_pdf_write', \__( 'Impossible d’enregistrer le PDF généré.', 'ibc-enrollment-manager' ) );
		}

		return $file;
	}

	/**
	 * Build HTML receipt.
	 *
	 * @param array $data Context.
	 *
	 * @return string
	 */
	private function build_html( array $data ): string {
		$colors      = ibc_get_brand_colors_with_legacy();
		$primary     = $colors['primary'] ?? '#16a085';
		$secondary   = $colors['secondary'] ?? '#0f172a';
		$border      = $colors['border'] ?? '#e2e8f0';
		$muted       = $colors['muted'] ?? '#f8fafc';
		$text        = $colors['text'] ?? '#1f2937';
		$brand_name  = get_bloginfo( 'name' );
		$details     = ibc_get_payment_details();
		$contact     = array(
			'address'  => get_option( 'ibc_contact_address', '' ),
			'email'    => get_option( 'ibc_contact_email', '' ),
			'phone'    => get_option( 'ibc_contact_phone', '' ),
			'landline' => get_option( 'ibc_contact_landline', '' ),
		);

		$full_name = esc_html( $data['fullName'] ?? '' );
		$email     = esc_html( $data['email'] ?? '' );
		$phone     = esc_html( $data['phone'] ?? '' );
		$level     = esc_html( $data['level'] ?? '' );
		$reference = esc_html( $data['ref'] ?? '' );
		$created   = esc_html( $data['createdAt'] ?? '' );
		$deadline  = esc_html( $data['payDeadline'] ?? __( 'Sous 24 heures', 'ibc-enrollment-manager' ) );
		$price     = esc_html( $data['price'] ?? ( ibc_get_price_prep() . ' MAD' ) );
		$formule   = esc_html__( 'Préparation examen IBC', 'ibc-enrollment-manager' );

		$bank_name      = esc_html( $details['bank_name'] ?? '' );
		$account_holder = esc_html( $details['account_holder'] ?? '' );
		$rib            = esc_html( $details['rib'] ?? '' );
		$iban           = esc_html( $details['iban'] ?? '' );
		$bic            = esc_html( $details['bic'] ?? '' );
		$agency         = esc_html( $details['agency'] ?? '' );
		$payment_note   = esc_html( $details['payment_note'] ?? '' );

		ob_start();
		?>
		<!DOCTYPE html>
		<html lang="fr">
		<head>
			<meta charset="utf-8">
			<title><?php echo esc_html( $brand_name ); ?></title>
			<style>
				:root{
					--primary: <?php echo esc_html( $primary ); ?>;
					--secondary: <?php echo esc_html( $secondary ); ?>;
					--border: <?php echo esc_html( $border ); ?>;
					--muted: <?php echo esc_html( $muted ); ?>;
					--text: <?php echo esc_html( $text ); ?>;
				}
				*{box-sizing:border-box;}
				body{margin:0;font-family:"Inter","Segoe UI","Helvetica Neue",Arial,sans-serif;background:#f5fbfb;color:var(--text);font-size:13px;line-height:1.6;}
				.pdf-wrapper{padding:36px 40px;}
				.pdf-hero{background:linear-gradient(135deg, rgba(22,160,133,0.94), rgba(15,118,110,0.9));color:#fff;padding:32px 36px;border-radius:24px;position:relative;overflow:hidden;}
				.pdf-hero::after{content:"";position:absolute;inset:0;background:linear-gradient(125deg, rgba(255,255,255,0.18), rgba(255,255,255,0));mix-blend-mode:soft-light;pointer-events:none;}
				.hero__brand{font-size:14px;font-weight:600;text-transform:uppercase;letter-spacing:0.08em;margin-bottom:10px;opacity:0.92;}
				.pdf-hero h1{margin:0;font-size:24px;font-weight:700;line-height:1.35;}
				.hero__subtitle{margin:12px 0 0;font-size:14px;max-width:520px;}
				.hero__meta{margin-top:28px;display:flex;flex-wrap:wrap;gap:18px 36px;font-size:13px;}
				.hero__meta-item span{display:block;font-size:12px;text-transform:uppercase;letter-spacing:0.06em;opacity:0.75;}
				.hero__meta-item strong{display:block;font-size:14px;font-weight:600;margin-top:4px;}
				.hero__badge{position:absolute;top:24px;right:24px;background:#fff;color:var(--primary);padding:10px 18px;border-radius:999px;font-weight:700;letter-spacing:0.05em;font-size:12px;box-shadow:0 18px 36px -28px rgba(10, 140, 120, 0.55);}
				.section{margin-top:28px;padding:24px 28px;background:#fff;border-radius:20px;border:1px solid rgba(15,118,110,0.08);box-shadow:0 26px 60px -48px rgba(15,118,110,0.15);}
				.section h2{margin:0;font-size:16px;font-weight:700;color:var(--secondary);text-transform:uppercase;letter-spacing:0.05em;}
				.section__intro{margin:10px 0 18px;font-size:13px;color:rgba(31,41,55,0.7);}
				.details-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:18px;margin-top:18px;}
				.detail-tile{padding:16px 18px;border-radius:14px;background:var(--muted);border:1px solid rgba(15,118,110,0.08);}
				.detail-tile span{display:block;font-size:11px;font-weight:600;letter-spacing:0.08em;text-transform:uppercase;color:rgba(31,41,55,0.55);}
				.detail-tile strong{display:block;margin-top:6px;font-size:13px;color:var(--secondary);}
				.payment-card{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:18px;margin-top:20px;}
				.payment-card .detail-tile{background:rgba(22,160,133,0.08);border:1px solid rgba(22,160,133,0.18);}
				.notice{margin-top:22px;padding:16px 18px;border-radius:14px;background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.2);color:#991b1b;font-weight:600;}
				.important{margin-top:18px;padding:18px;border-radius:16px;background:rgba(251,191,36,0.12);border:1px solid rgba(202,138,4,0.22);color:#92400e;font-size:12px;font-weight:600;}
				.footer{margin-top:32px;font-size:12px;color:rgba(31,41,55,0.7);}
				.footer p{margin:4px 0;}
				.footer strong{color:var(--secondary);}
			</style>
		</head>
		<body>
			<div class="pdf-wrapper">
				<header class="pdf-hero">
					<div class="hero__brand"><?php echo esc_html( $brand_name ); ?></div>
					<h1><?php esc_html_e( 'Reçu de préinscription – Préparation d\'examen', 'ibc-enrollment-manager' ); ?></h1>
					<p class="hero__subtitle"><?php esc_html_e( 'Merci pour votre confiance. Ce reçu confirme la bonne réception de votre demande de préinscription à la préparation IBC.', 'ibc-enrollment-manager' ); ?></p>
					<div class="hero__badge"><?php echo esc_html__( 'Réf.', 'ibc-enrollment-manager' ) . ' ' . $reference; ?></div>
					<div class="hero__meta">
						<div class="hero__meta-item">
							<span><?php esc_html_e( 'Date de préinscription', 'ibc-enrollment-manager' ); ?></span>
							<strong><?php echo $created; ?></strong>
						</div>
						<div class="hero__meta-item">
							<span><?php esc_html_e( 'Échéance de paiement', 'ibc-enrollment-manager' ); ?></span>
							<strong><?php echo $deadline; ?></strong>
						</div>
						<div class="hero__meta-item">
							<span><?php esc_html_e( 'Référence à rappeler', 'ibc-enrollment-manager' ); ?></span>
							<strong><?php echo $reference; ?></strong>
						</div>
					</div>
				</header>

				<section class="section">
					<h2><?php esc_html_e( 'Informations personnelles', 'ibc-enrollment-manager' ); ?></h2>
					<p class="section__intro"><?php esc_html_e( 'Ces informations seront utilisées pour votre suivi administratif et votre convocation.', 'ibc-enrollment-manager' ); ?></p>
					<div class="details-grid">
						<div class="detail-tile"><span><?php esc_html_e( 'Nom complet', 'ibc-enrollment-manager' ); ?></span><strong><?php echo $full_name; ?></strong></div>
						<div class="detail-tile"><span><?php esc_html_e( 'Téléphone', 'ibc-enrollment-manager' ); ?></span><strong><?php echo $phone; ?></strong></div>
						<div class="detail-tile"><span><?php esc_html_e( 'Email', 'ibc-enrollment-manager' ); ?></span><strong><?php echo $email; ?></strong></div>
					</div>
				</section>

				<section class="section">
					<h2><?php esc_html_e( 'Détails de la préparation', 'ibc-enrollment-manager' ); ?></h2>
					<div class="details-grid">
						<div class="detail-tile"><span><?php esc_html_e( 'Niveau souhaité', 'ibc-enrollment-manager' ); ?></span><strong><?php echo $level; ?></strong></div>
						<div class="detail-tile"><span><?php esc_html_e( 'Formule', 'ibc-enrollment-manager' ); ?></span><strong><?php echo $formule; ?></strong></div>
						<div class="detail-tile"><span><?php esc_html_e( 'Frais de préparation', 'ibc-enrollment-manager' ); ?></span><strong><?php echo $price; ?></strong></div>
					</div>
				</section>

				<section class="section">
					<h2><?php esc_html_e( 'Coordonnées bancaires (paiement sous 24 h)', 'ibc-enrollment-manager' ); ?></h2>
					<div class="payment-card">
						<?php if ( $bank_name ) : ?>
							<div class="detail-tile"><span><?php esc_html_e( 'Banque', 'ibc-enrollment-manager' ); ?></span><strong><?php echo $bank_name; ?></strong></div>
						<?php endif; ?>
						<?php if ( $account_holder ) : ?>
							<div class="detail-tile"><span><?php esc_html_e( 'Titulaire du compte', 'ibc-enrollment-manager' ); ?></span><strong><?php echo $account_holder; ?></strong></div>
						<?php endif; ?>
						<?php if ( $rib ) : ?>
							<div class="detail-tile"><span><?php esc_html_e( 'RIB', 'ibc-enrollment-manager' ); ?></span><strong><?php echo $rib; ?></strong></div>
						<?php endif; ?>
						<?php if ( $iban ) : ?>
							<div class="detail-tile"><span><?php esc_html_e( 'IBAN', 'ibc-enrollment-manager' ); ?></span><strong><?php echo $iban; ?></strong></div>
						<?php endif; ?>
						<?php if ( $bic ) : ?>
							<div class="detail-tile"><span><?php esc_html_e( 'BIC / SWIFT', 'ibc-enrollment-manager' ); ?></span><strong><?php echo $bic; ?></strong></div>
						<?php endif; ?>
						<?php if ( $agency ) : ?>
							<div class="detail-tile"><span><?php esc_html_e( 'Agence', 'ibc-enrollment-manager' ); ?></span><strong><?php echo $agency; ?></strong></div>
						<?php endif; ?>
					</div>
					<?php if ( $payment_note ) : ?>
						<p class="section__intro" style="margin-top:18px;"><?php echo $payment_note; ?></p>
					<?php endif; ?>
					<p class="notice"><?php echo sprintf( /* translators: %s reference number */ esc_html__( 'Indiquez impérativement la référence %s dans l’objet de votre virement.', 'ibc-enrollment-manager' ), $reference ); ?></p>
				</section>

				<div class="important"><?php esc_html_e( 'Le règlement doit être effectué dans les 24 heures suivant la réception de ce reçu. Sans paiement, la préinscription sera automatiquement annulée.', 'ibc-enrollment-manager' ); ?></div>
				<div class="notice" style="margin-top:16px;"><?php esc_html_e( 'Une fois votre inscription validée, elle est définitive et non remboursable.', 'ibc-enrollment-manager' ); ?></div>

				<footer class="footer">
					<p><strong><?php echo esc_html( $brand_name ); ?></strong></p>
					<?php if ( ! empty( $contact['address'] ) ) : ?>
						<p><?php echo esc_html( $contact['address'] ); ?></p>
					<?php endif; ?>
					<?php if ( ! empty( $contact['email'] ) ) : ?>
						<p><?php esc_html_e( 'Email :', 'ibc-enrollment-manager' ); ?> <?php echo esc_html( $contact['email'] ); ?></p>
					<?php endif; ?>
					<?php if ( ! empty( $contact['phone'] ) ) : ?>
						<p><?php esc_html_e( 'Mobile :', 'ibc-enrollment-manager' ); ?> <?php echo esc_html( $contact['phone'] ); ?></p>
					<?php endif; ?>
					<?php if ( ! empty( $contact['landline'] ) ) : ?>
						<p><?php esc_html_e( 'Fixe :', 'ibc-enrollment-manager' ); ?> <?php echo esc_html( $contact['landline'] ); ?></p>
					<?php endif; ?>
				</footer>
			</div>
		</body>
		</html>
		<?php
		return (string) ob_get_clean();
	}
}
