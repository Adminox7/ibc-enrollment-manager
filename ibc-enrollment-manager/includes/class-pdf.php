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
		$file   = trailingslashit( $upload['path'] ) . 'recu-' . $ref . '.pdf';

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
		$colors = get_option(
			'ibc_brand_colors',
			array(
				'primary'   => '#e94162',
				'secondary' => '#0f172a',
				'text'      => '#1f2937',
				'muted'     => '#f8fafc',
				'border'    => '#e2e8f0',
			)
		);

		$bank = array(
			'bankName'      => (string) get_option( 'ibc_brand_bankName', '' ),
			'accountHolder' => (string) get_option( 'ibc_brand_accountHolder', '' ),
			'rib'           => (string) get_option( 'ibc_brand_rib', '' ),
			'iban'          => (string) get_option( 'ibc_brand_iban', '' ),
			'bic'           => (string) get_option( 'ibc_brand_bic', '' ),
			'agency'        => (string) get_option( 'ibc_brand_agency', '' ),
			'paymentNote'   => (string) get_option( 'ibc_brand_paymentNote', '' ),
		);

		$rows = array(
			\__( 'Nom complet', 'ibc-enrollment-manager' ) => $data['fullName'] ?? '',
			\__( 'Email', 'ibc-enrollment-manager' )      => $data['email'] ?? '',
			\__( 'Téléphone', 'ibc-enrollment-manager' )  => $data['phone'] ?? '',
			\__( 'Niveau', 'ibc-enrollment-manager' )     => $data['level'] ?? '',
			\__( 'Référence', 'ibc-enrollment-manager' )  => $data['ref'] ?? '',
			\__( 'Date', 'ibc-enrollment-manager' )       => $data['createdAt'] ?? '',
		);

		$deadline = ! empty( $data['payDeadline'] ) ? $data['payDeadline'] : \__( 'Sous 24 heures', 'ibc-enrollment-manager' );
		$price    = $data['price'] ?? ( ibc_get_price_prep() . ' MAD' );

		ob_start();
		?>
		<!DOCTYPE html>
		<html lang="fr">
		<head>
			<meta charset="utf-8">
			<title>IBC Morocco</title>
			<style>
				body{font-family:"Roboto","Helvetica Neue",Helvetica,Arial,sans-serif;color:<?php echo esc_html( $colors['text'] ); ?>;margin:0;font-size:13px;}
				.header{background:<?php echo esc_html( $colors['primary'] ); ?>;color:#fff;padding:20px;text-align:center;}
				.wrapper{padding:24px 32px;}
				h1{margin:0;font-size:22px;}
				.meta-table{width:100%;border-collapse:collapse;margin:20px 0;}
				.meta-table th,.meta-table td{padding:10px;border:1px solid <?php echo esc_html( $colors['border'] ); ?>;text-align:left;}
				.section{margin-top:24px;padding:16px;border:1px solid <?php echo esc_html( $colors['border'] ); ?>;border-radius:8px;background:<?php echo esc_html( $colors['muted'] ); ?>;}
				.section h2{margin:0 0 10px 0;font-size:16px;color:<?php echo esc_html( $colors['secondary'] ); ?>;}
				.bank-list{margin:0;padding-left:18px;}
				.bank-list li{margin:6px 0;}
				.footer{margin-top:28px;font-size:12px;color:<?php echo esc_html( $colors['secondary'] ); ?>;}
			</style>
		</head>
		<body>
			<div class="header">
				<h1>IBC Morocco – Préinscription</h1>
				<p><?php echo esc_html( $data['ref'] ?? '' ); ?></p>
			</div>
			<div class="wrapper">
				<p><?php echo \esc_html__( 'Merci pour votre préinscription à la préparation IBC.', 'ibc-enrollment-manager' ); ?></p>
				<table class="meta-table">
					<tbody>
					<?php foreach ( $rows as $label => $value ) : ?>
						<tr>
							<th><?php echo esc_html( $label ); ?></th>
							<td><?php echo esc_html( $value ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>

				<div class="section">
					<h2><?php echo \esc_html__( 'Récapitulatif paiement', 'ibc-enrollment-manager' ); ?></h2>
					<p><strong><?php echo \esc_html__( 'Montant', 'ibc-enrollment-manager' ); ?> :</strong> <?php echo esc_html( $price ); ?></p>
					<p><strong><?php echo \esc_html__( 'Échéance', 'ibc-enrollment-manager' ); ?> :</strong> <?php echo esc_html( $deadline ); ?></p>
				</div>

				<div class="section">
					<h2><?php echo \esc_html__( 'Coordonnées bancaires', 'ibc-enrollment-manager' ); ?></h2>
					<ul class="bank-list">
						<?php if ( ! empty( $bank['bankName'] ) ) : ?>
							<li><?php echo \esc_html__( 'Banque', 'ibc-enrollment-manager' ) . ' : ' . esc_html( $bank['bankName'] ); ?></li>
						<?php endif; ?>
						<?php if ( ! empty( $bank['accountHolder'] ) ) : ?>
							<li><?php echo \esc_html__( 'Titulaire', 'ibc-enrollment-manager' ) . ' : ' . esc_html( $bank['accountHolder'] ); ?></li>
						<?php endif; ?>
						<?php if ( ! empty( $bank['rib'] ) ) : ?>
							<li><?php echo \esc_html__( 'RIB', 'ibc-enrollment-manager' ) . ' : ' . esc_html( $bank['rib'] ); ?></li>
						<?php endif; ?>
						<?php if ( ! empty( $bank['iban'] ) ) : ?>
							<li><?php echo \esc_html__( 'IBAN', 'ibc-enrollment-manager' ) . ' : ' . esc_html( $bank['iban'] ); ?></li>
						<?php endif; ?>
						<?php if ( ! empty( $bank['bic'] ) ) : ?>
							<li><?php echo \esc_html__( 'BIC/SWIFT', 'ibc-enrollment-manager' ) . ' : ' . esc_html( $bank['bic'] ); ?></li>
						<?php endif; ?>
						<?php if ( ! empty( $bank['agency'] ) ) : ?>
							<li><?php echo \esc_html__( 'Agence', 'ibc-enrollment-manager' ) . ' : ' . esc_html( $bank['agency'] ); ?></li>
						<?php endif; ?>
					</ul>
					<?php if ( ! empty( $bank['paymentNote'] ) ) : ?>
						<p><?php echo esc_html( $bank['paymentNote'] ); ?></p>
					<?php endif; ?>
				</div>

				<div class="footer">
					<p><?php echo \esc_html__( 'Veuillez indiquer la référence sur votre virement. Cette réservation de place est valable jusqu’à l’échéance indiquée.', 'ibc-enrollment-manager' ); ?></p>
				</div>
			</div>
		</body>
		</html>
		<?php
		return (string) ob_get_clean();
	}
}
