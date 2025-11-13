<?php
/**
 * PDF generation utilities.
 *
 * @package IBC\Enrollment
 */

namespace IBC\Enrollment;

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
	 * @return array{path:string,url:string}|WP_Error Absolute path/url pair or error.
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
		$ref    = ! empty( $data['reference'] ?? '' ) ? $data['reference'] : ( $data['ref'] ?? '' );
		$slug   = $ref ? sanitize_title( $ref ) : uniqid( 'ibc', true );
		$file   = trailingslashit( $upload['path'] ) . 'recu-prepa-' . $slug . '.pdf';

		if ( ! wp_mkdir_p( dirname( $file ) ) ) {
			return new WP_Error( 'ibc_pdf_path', \__( 'Impossible de créer le dossier de stockage PDF.', 'ibc-enrollment-manager' ) );
		}

		$result = file_put_contents( $file, $output ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents
		if ( false === $result ) {
			return new WP_Error( 'ibc_pdf_write', \__( 'Impossible d’enregistrer le PDF généré.', 'ibc-enrollment-manager' ) );
		}

		$url = trailingslashit( $upload['url'] ) . basename( $file );

		return array(
			'path' => $file,
			'url'  => $url,
		);
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
		$primary     = $colors['primary'] ?? '#4CB4B4';
		$secondary   = $colors['secondary'] ?? '#2A8E8E';
		$border      = $colors['border'] ?? '#E5E7EB';
		$muted       = $colors['muted'] ?? '#E0F5F5';
		$text        = $colors['text'] ?? '#1F2937';
		$brand_name  = ibc_get_brand_name();
		$details     = ibc_get_payment_details();
		$contact     = array(
			'address'  => get_option( 'ibc_contact_address', '' ),
			'email'    => get_option( 'ibc_contact_email', '' ),
			'phone'    => get_option( 'ibc_contact_phone', '' ),
			'landline' => get_option( 'ibc_contact_landline', '' ),
		);

		$full_name      = esc_html( $data['fullName'] ?? '' );
		$email          = esc_html( $data['email'] ?? '' );
		$telephone      = esc_html( $data['telephone'] ?? '' );
		$level          = esc_html( $data['level'] ?? '' );
		$reference      = esc_html( $data['reference'] ?? $data['ref'] ?? '' );
		$created        = esc_html( $data['createdAtHuman'] ?? $data['createdAt'] ?? wp_date( 'd/m/Y H:i' ) );
		$deadline       = esc_html( $data['payDeadline'] ?? wp_date( 'd/m/Y H:i', strtotime( '+24 hours' ) ) );
		$price          = esc_html( $data['price'] ?? ( ibc_get_price_prep() . ' MAD' ) );
		$formule        = esc_html__( 'Préparation à l’examen', 'ibc-enrollment-manager' );
		$date_source    = $data['dateNaissance'] ?? $data['date_naissance'] ?? '';
		$date_naissance = esc_html( ibc_format_date_human( $date_source ) );
		$lieu_naissance = esc_html( $data['lieuNaissance'] ?? '' );
		$notes_text     = isset( $data['notes'] ) ? trim( wp_strip_all_tags( (string) $data['notes'] ) ) : '';
		$extra_fields   = is_array( $data['extra'] ?? null ) ? array_values( $data['extra'] ) : array();

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
				body{margin:0;font-family:"Inter","Segoe UI",Helvetica,Arial,sans-serif;color:var(--text);background:#f6fbfb;font-size:13px;line-height:1.6;}
				.wrapper{padding:32px 36px;}
				.header{background:linear-gradient(135deg, rgba(76,180,180,0.95), rgba(42,142,142,0.92));color:#ffffff;padding:32px;border-radius:20px;position:relative;overflow:hidden;}
				.header::after{content:"";position:absolute;inset:0;background:linear-gradient(120deg, rgba(255,255,255,0.18), rgba(255,255,255,0));mix-blend-mode:soft-light;}
				.brand{font-size:14px;font-weight:600;text-transform:uppercase;letter-spacing:0.1em;margin-bottom:8px;opacity:0.92;}
				.header h1{margin:0;font-size:24px;font-weight:700;line-height:1.35;}
				.subtitle{margin:10px 0 0;font-size:14px;max-width:520px;}
				.ref-badge{position:absolute;top:24px;right:24px;background:#ffffff;color:var(--primary);padding:10px 18px;border-radius:999px;font-weight:700;letter-spacing:0.06em;font-size:12px;box-shadow:0 14px 32px -24px rgba(42,142,142,0.55);}
				.meta{margin-top:24px;display:flex;flex-wrap:wrap;gap:16px 36px;font-size:13px;}
				.meta span{display:block;font-size:11px;text-transform:uppercase;letter-spacing:0.08em;opacity:0.78;}
				.meta strong{display:block;margin-top:4px;font-size:14px;font-weight:600;}
				.section{margin-top:26px;padding:22px 26px;background:#ffffff;border-radius:18px;border:1px solid rgba(76,180,180,0.12);box-shadow:0 22px 45px -38px rgba(76,180,180,0.28);}
				.section h2{margin:0;font-size:16px;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:var(--secondary);}
				.section p.lead{margin:10px 0 18px;font-size:13px;color:rgba(31,41,55,0.72);}
				.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:16px;}
				.tile{padding:16px 18px;border-radius:14px;background:var(--muted);border:1px solid rgba(76,180,180,0.16);}
				.tile span{display:block;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:0.1em;color:rgba(31,41,55,0.58);}
				.tile strong{display:block;margin-top:6px;font-size:13px;color:var(--secondary);}
				.bank-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:16px;margin-top:18px;}
				.notice{margin-top:18px;padding:14px 18px;border-radius:14px;border:1px solid rgba(239,68,68,0.24);background:rgba(239,68,68,0.1);color:#b91c1c;font-weight:600;}
				.warning{margin-top:18px;padding:16px 18px;border-radius:16px;border:1px solid rgba(217,119,6,0.24);background:rgba(250,204,21,0.14);color:#92400e;font-weight:600;font-size:12px;}
				.footer{margin-top:28px;font-size:12px;color:rgba(31,41,55,0.72);}
				.footer p{margin:3px 0;}
				.footer strong{color:var(--secondary);}
			</style>
		</head>
		<body>
			<div class="wrapper">
				<header class="header">
					<div class="brand"><?php echo esc_html( $brand_name ); ?></div>
					<h1><?php esc_html_e( 'Reçu de préinscription – Préparation d’examen', 'ibc-enrollment-manager' ); ?></h1>
					<p class="subtitle"><?php esc_html_e( 'Ce document atteste la bonne réception de votre dossier de préinscription à la préparation IBC.', 'ibc-enrollment-manager' ); ?></p>
					<div class="ref-badge"><?php echo esc_html__( 'Réf.', 'ibc-enrollment-manager' ) . ' ' . $reference; ?></div>
					<div class="meta">
						<div>
							<span><?php esc_html_e( 'Date de préinscription', 'ibc-enrollment-manager' ); ?></span>
							<strong><?php echo $created; ?></strong>
						</div>
						<div>
							<span><?php esc_html_e( 'Échéance de paiement', 'ibc-enrollment-manager' ); ?></span>
							<strong><?php echo $deadline; ?></strong>
						</div>
						<div>
							<span><?php esc_html_e( 'Référence à rappeler', 'ibc-enrollment-manager' ); ?></span>
							<strong><?php echo $reference; ?></strong>
						</div>
					</div>
				</header>

				<section class="section">
					<h2><?php esc_html_e( 'Informations personnelles', 'ibc-enrollment-manager' ); ?></h2>
					<p class="lead"><?php esc_html_e( 'Ces informations servent à la convocation et au suivi administratif de votre dossier.', 'ibc-enrollment-manager' ); ?></p>
					<div class="grid">
						<div class="tile"><span><?php esc_html_e( 'Nom complet', 'ibc-enrollment-manager' ); ?></span><strong><?php echo $full_name; ?></strong></div>
						<div class="tile"><span><?php esc_html_e( 'Téléphone', 'ibc-enrollment-manager' ); ?></span><strong><?php echo $telephone; ?></strong></div>
						<div class="tile"><span><?php esc_html_e( 'Email', 'ibc-enrollment-manager' ); ?></span><strong><?php echo $email; ?></strong></div>
						<?php if ( $date_naissance ) : ?>
							<div class="tile"><span><?php esc_html_e( 'Date de naissance', 'ibc-enrollment-manager' ); ?></span><strong><?php echo $date_naissance; ?></strong></div>
						<?php endif; ?>
						<?php if ( $lieu_naissance ) : ?>
							<div class="tile"><span><?php esc_html_e( 'Lieu de naissance', 'ibc-enrollment-manager' ); ?></span><strong><?php echo $lieu_naissance; ?></strong></div>
						<?php endif; ?>
					</div>
				</section>

				<section class="section">
					<h2><?php esc_html_e( 'Détails de la préparation', 'ibc-enrollment-manager' ); ?></h2>
					<div class="grid">
						<div class="tile"><span><?php esc_html_e( 'Niveau ciblé', 'ibc-enrollment-manager' ); ?></span><strong><?php echo $level; ?></strong></div>
						<div class="tile"><span><?php esc_html_e( 'Formule', 'ibc-enrollment-manager' ); ?></span><strong><?php echo $formule; ?></strong></div>
						<div class="tile"><span><?php esc_html_e( 'Frais de préparation', 'ibc-enrollment-manager' ); ?></span><strong><?php echo $price; ?></strong></div>
					</div>
				</section>

				<?php if ( $notes_text || ! empty( $extra_fields ) ) : ?>
					<section class="section">
						<h2><?php esc_html_e( 'Informations complémentaires', 'ibc-enrollment-manager' ); ?></h2>
						<?php if ( $notes_text ) : ?>
							<p class="lead"><?php echo nl2br( esc_html( $notes_text ) ); ?></p>
						<?php endif; ?>
						<?php if ( ! empty( $extra_fields ) ) : ?>
							<div class="grid">
								<?php foreach ( $extra_fields as $entry ) :
									if ( empty( $entry['value'] ) ) {
										continue;
									}
									$label = esc_html( $entry['label'] ?? $entry['id'] ?? '' );
									$value = $entry['value'];
									if ( ( $entry['type'] ?? '' ) === 'file' && filter_var( $value, FILTER_VALIDATE_URL ) ) {
										$display = esc_html__( 'Pièce jointe', 'ibc-enrollment-manager' );
									} else {
										$display = esc_html( $entry['display'] ?? $value );
									}
									?>
									<div class="tile">
										<span><?php echo $label; ?></span>
										<strong><?php echo $display; ?></strong>
									</div>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
					</section>
				<?php endif; ?>

				<section class="section">
					<h2><?php esc_html_e( 'Coordonnées bancaires (paiement sous 24 h)', 'ibc-enrollment-manager' ); ?></h2>
					<div class="bank-grid">
						<?php if ( $bank_name ) : ?>
							<div class="tile"><span><?php esc_html_e( 'Banque', 'ibc-enrollment-manager' ); ?></span><strong><?php echo $bank_name; ?></strong></div>
						<?php endif; ?>
						<?php if ( $account_holder ) : ?>
							<div class="tile"><span><?php esc_html_e( 'Titulaire du compte', 'ibc-enrollment-manager' ); ?></span><strong><?php echo $account_holder; ?></strong></div>
						<?php endif; ?>
						<?php if ( $rib ) : ?>
							<div class="tile"><span><?php esc_html_e( 'RIB', 'ibc-enrollment-manager' ); ?></span><strong><?php echo $rib; ?></strong></div>
						<?php endif; ?>
						<?php if ( $iban ) : ?>
							<div class="tile"><span><?php esc_html_e( 'IBAN', 'ibc-enrollment-manager' ); ?></span><strong><?php echo $iban; ?></strong></div>
						<?php endif; ?>
						<?php if ( $bic ) : ?>
							<div class="tile"><span><?php esc_html_e( 'BIC / SWIFT', 'ibc-enrollment-manager' ); ?></span><strong><?php echo $bic; ?></strong></div>
						<?php endif; ?>
						<?php if ( $agency ) : ?>
							<div class="tile"><span><?php esc_html_e( 'Agence', 'ibc-enrollment-manager' ); ?></span><strong><?php echo $agency; ?></strong></div>
						<?php endif; ?>
					</div>
					<?php if ( $payment_note ) : ?>
						<p class="lead" style="margin-top:16px;"><?php echo $payment_note; ?></p>
					<?php endif; ?>
					<p class="notice"><?php printf( esc_html__( 'Indiquez impérativement la référence %s dans l’objet de votre virement.', 'ibc-enrollment-manager' ), $reference ); ?></p>
				</section>

				<div class="warning"><?php esc_html_e( 'Le paiement doit être effectué dans les 24 heures suivant la réception de ce reçu. Passé ce délai, la préinscription sera automatiquement annulée.', 'ibc-enrollment-manager' ); ?></div>
				<div class="notice"><?php esc_html_e( 'Une fois votre inscription validée, celle-ci est considérée comme définitive et non remboursable.', 'ibc-enrollment-manager' ); ?></div>

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
