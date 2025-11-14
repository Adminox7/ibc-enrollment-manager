<?php
/**
 * PDF receipt template (mirrors the Apps Script design).
 *
 * @var array $template_context
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$ctx      = $template_context;
$colors   = $ctx['colors'] ?? [];
$payment  = $ctx['payment'] ?? [];
$contact  = $ctx['contact'] ?? [];
$extras   = $ctx['extra'] ?? [];
$notes    = trim( (string) ( $ctx['message'] ?? '' ) );
$fullName = esc_html( $ctx['full_name'] ?? '' );
$reference = esc_html( $ctx['reference'] ?? '' );
$created  = esc_html( $ctx['created_at'] ?? '' );
$deadline = esc_html( $ctx['deadline'] ?? '' );
$price    = esc_html( $ctx['price'] ?? '' );
$level    = esc_html( $ctx['level'] ?? '' );
$email    = esc_html( $ctx['email'] ?? '' );
$phone    = esc_html( $ctx['telephone'] ?? '' );
$dateN    = esc_html( $ctx['date_naissance'] ?? '' );
$lieuN    = esc_html( $ctx['lieu_naissance'] ?? '' );

$primary   = esc_html( $colors['primary'] ?? '#4CB4B4' );
$primary_d = esc_html( $colors['primary_dark'] ?? '#3A9191' );
$primary_l = esc_html( $colors['primary_light'] ?? '#E0F5F5' );
$text_dark = esc_html( $colors['text_dark'] ?? '#1f2937' );
$text_muted = esc_html( $colors['text_muted'] ?? '#6b7280' );

?>
<!DOCTYPE html>
<html lang="fr">
<head>
	<meta charset="utf-8">
	<title><?php echo esc_html( $ctx['brand_name'] ); ?></title>
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
	<style>
		:root{
			--ibc-primary: <?php echo $primary; ?>;
			--ibc-primary-dark: <?php echo $primary_d; ?>;
			--ibc-primary-light: <?php echo $primary_l; ?>;
			--ibc-text: <?php echo $text_dark; ?>;
			--ibc-muted: <?php echo $text_muted; ?>;
		}
		*{box-sizing:border-box;}
		body{
			margin:0;
			font-family:"Inter",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;
			background:#f5fbfb;
			color:var(--ibc-text);
			font-size:13px;
			line-height:1.55;
			-apple-system-font:"Inter";
		}
		.wrapper{padding:28px;}
		.card{
			background:#fff;
			border-radius:26px;
			border:1px solid rgba(76,180,180,0.18);
			box-shadow:0 30px 75px -45px rgba(12,74,72,0.35);
			overflow:hidden;
		}
		.hero{
			position:relative;
			padding:32px 36px;
			background:linear-gradient(135deg, rgba(76,180,180,0.95), rgba(58,145,145,0.92));
			color:#fff;
		}
		.hero h1{
			margin:0 0 6px;
			font-size:24px;
			font-weight:800;
			letter-spacing:-0.3px;
		}
		.hero p{
			margin:0;
			color:rgba(255,255,255,0.92);
			max-width:540px;
		}
		.badge{
			position:absolute;
			top:28px;
			right:32px;
			padding:10px 18px;
			background:#fff;
			color:var(--ibc-primary-dark);
			border-radius:999px;
			font-size:12px;
			font-weight:700;
			letter-spacing:0.1em;
			text-transform:uppercase;
			box-shadow:0 20px 40px -32px rgba(15,118,110,0.65);
		}
		.meta{
			margin-top:22px;
			display:flex;
			flex-wrap:wrap;
			gap:18px 32px;
		}
		.meta div span{
			display:block;
			font-size:11px;
			text-transform:uppercase;
			letter-spacing:0.12em;
			color:rgba(255,255,255,0.7);
		}
		.meta div strong{
			display:block;
			margin-top:4px;
			font-size:14px;
			font-weight:700;
		}
		.section{
			padding:26px 32px;
			border-top:1px solid rgba(15,118,110,0.08);
		}
		.section h2{
			margin:0 0 8px;
			font-size:15px;
			font-weight:700;
			text-transform:uppercase;
			letter-spacing:0.08em;
			color:var(--ibc-primary-dark);
		}
		.section p.lead{
			margin:0 0 18px;
			color:var(--ibc-muted);
		}
		.grid{
			display:grid;
			grid-template-columns:repeat(auto-fit,minmax(180px,1fr));
			gap:14px;
		}
		.tile{
			padding:14px 16px;
			border-radius:16px;
			background:var(--ibc-primary-light);
			border:1px solid rgba(76,180,180,0.22);
		}
		.tile span{
			display:block;
			font-size:11px;
			text-transform:uppercase;
			letter-spacing:0.12em;
			color:rgba(15,118,110,0.8);
		}
		.tile strong{
			display:block;
			margin-top:6px;
			font-size:13.5px;
			color:var(--ibc-primary-dark);
		}
		.notice{
			margin-top:18px;
			padding:14px 18px;
			border-radius:16px;
			border:1px solid rgba(239,68,68,0.25);
			background:rgba(239,68,68,0.1);
			color:#b91c1c;
			font-weight:600;
			font-size:12.5px;
		}
		.warning{
			margin-top:12px;
			padding:14px 18px;
			border-radius:16px;
			border:1px solid rgba(250,204,21,0.35);
			background:rgba(250,204,21,0.18);
			color:#92400e;
			font-weight:600;
			font-size:12px;
		}
		.footer{
			padding:22px 32px 32px;
			border-top:1px solid rgba(15,118,110,0.08);
			color:var(--ibc-muted);
			font-size:12px;
		}
		.footer p{margin:4px 0;}
	</style>
</head>
<body>
	<div class="wrapper">
		<div class="card">
			<section class="hero">
				<div class="badge"><?php echo esc_html__( 'Réf.', 'ibc-enrollment' ); ?> <?php echo $reference; ?></div>
				<h1><?php esc_html_e( 'Reçu de préinscription – Préparation d’examen', 'ibc-enrollment' ); ?></h1>
				<p><?php esc_html_e( 'Ce reçu confirme que votre dossier a été enregistré. Merci d’effectuer le paiement sous 24 h en mentionnant la référence ci-dessus.', 'ibc-enrollment' ); ?></p>
				<div class="meta">
					<div>
						<span><?php esc_html_e( 'Date de préinscription', 'ibc-enrollment' ); ?></span>
						<strong><?php echo $created; ?></strong>
					</div>
					<div>
						<span><?php esc_html_e( 'Échéance paiement (24 h)', 'ibc-enrollment' ); ?></span>
						<strong><?php echo $deadline; ?></strong>
					</div>
					<div>
						<span><?php esc_html_e( 'Montant dû', 'ibc-enrollment' ); ?></span>
						<strong><?php echo $price; ?></strong>
					</div>
				</div>
			</section>

			<section class="section">
				<h2><?php esc_html_e( 'Informations personnelles', 'ibc-enrollment' ); ?></h2>
				<div class="grid">
					<div class="tile"><span><?php esc_html_e( 'Nom complet', 'ibc-enrollment' ); ?></span><strong><?php echo $fullName; ?></strong></div>
					<div class="tile"><span><?php esc_html_e( 'Téléphone', 'ibc-enrollment' ); ?></span><strong><?php echo $phone; ?></strong></div>
					<div class="tile"><span><?php esc_html_e( 'Email', 'ibc-enrollment' ); ?></span><strong><?php echo $email; ?></strong></div>
					<?php if ( $dateN ) : ?>
						<div class="tile"><span><?php esc_html_e( 'Date de naissance', 'ibc-enrollment' ); ?></span><strong><?php echo $dateN; ?></strong></div>
					<?php endif; ?>
					<?php if ( $lieuN ) : ?>
						<div class="tile"><span><?php esc_html_e( 'Lieu de naissance', 'ibc-enrollment' ); ?></span><strong><?php echo $lieuN; ?></strong></div>
					<?php endif; ?>
				</div>
			</section>

			<section class="section">
				<h2><?php esc_html_e( 'Détails de la préparation', 'ibc-enrollment' ); ?></h2>
				<div class="grid">
					<div class="tile"><span><?php esc_html_e( 'Niveau ciblé', 'ibc-enrollment' ); ?></span><strong><?php echo $level; ?></strong></div>
					<div class="tile"><span><?php esc_html_e( 'Formule', 'ibc-enrollment' ); ?></span><strong><?php esc_html_e( 'Préparation à l’examen', 'ibc-enrollment' ); ?></strong></div>
					<div class="tile"><span><?php esc_html_e( 'Montant', 'ibc-enrollment' ); ?></span><strong><?php echo $price; ?></strong></div>
				</div>
			</section>

			<?php if ( $notes || ! empty( $extras ) ) : ?>
				<section class="section">
					<h2><?php esc_html_e( 'Informations complémentaires', 'ibc-enrollment' ); ?></h2>
					<?php if ( $notes ) : ?>
						<p class="lead"><?php echo nl2br( esc_html( $notes ) ); ?></p>
					<?php endif; ?>
					<?php if ( ! empty( $extras ) ) : ?>
						<div class="grid">
							<?php foreach ( $extras as $extra ) :
								if ( empty( $extra['value'] ) ) {
									continue;
								}
								$label = esc_html( $extra['label'] ?? $extra['id'] ?? '' );
								$value = (string) ( $extra['display'] ?? $extra['value'] );
								?>
								<div class="tile">
									<span><?php echo $label; ?></span>
									<strong><?php echo esc_html( $value ); ?></strong>
								</div>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</section>
			<?php endif; ?>

			<section class="section">
				<h2><?php esc_html_e( 'Coordonnées bancaires (paiement sous 24 h)', 'ibc-enrollment' ); ?></h2>
				<div class="grid">
					<?php if ( ! empty( $payment['bank_name'] ) ) : ?>
						<div class="tile"><span><?php esc_html_e( 'Banque', 'ibc-enrollment' ); ?></span><strong><?php echo esc_html( $payment['bank_name'] ); ?></strong></div>
					<?php endif; ?>
					<?php if ( ! empty( $payment['account_holder'] ) ) : ?>
						<div class="tile"><span><?php esc_html_e( 'Titulaire', 'ibc-enrollment' ); ?></span><strong><?php echo esc_html( $payment['account_holder'] ); ?></strong></div>
					<?php endif; ?>
					<?php if ( ! empty( $payment['rib'] ) ) : ?>
						<div class="tile"><span><?php esc_html_e( 'RIB', 'ibc-enrollment' ); ?></span><strong><?php echo esc_html( $payment['rib'] ); ?></strong></div>
					<?php endif; ?>
					<?php if ( ! empty( $payment['iban'] ) ) : ?>
						<div class="tile"><span><?php esc_html_e( 'IBAN', 'ibc-enrollment' ); ?></span><strong><?php echo esc_html( $payment['iban'] ); ?></strong></div>
					<?php endif; ?>
					<?php if ( ! empty( $payment['bic'] ) ) : ?>
						<div class="tile"><span><?php esc_html_e( 'BIC / SWIFT', 'ibc-enrollment' ); ?></span><strong><?php echo esc_html( $payment['bic'] ); ?></strong></div>
					<?php endif; ?>
					<?php if ( ! empty( $payment['agency'] ) ) : ?>
						<div class="tile"><span><?php esc_html_e( 'Agence', 'ibc-enrollment' ); ?></span><strong><?php echo esc_html( $payment['agency'] ); ?></strong></div>
					<?php endif; ?>
				</div>
				<?php if ( ! empty( $payment['payment_note'] ) ) : ?>
					<p class="lead" style="margin-top:12px;"><?php echo esc_html( $payment['payment_note'] ); ?></p>
				<?php endif; ?>
				<p class="notice"><?php printf( esc_html__( 'Mentionnez impérativement la référence %s dans l’objet de votre virement.', 'ibc-enrollment' ), $reference ); ?></p>
				<p class="warning"><?php esc_html_e( 'Le paiement doit être effectué sous 24 heures. Passé ce délai, l’inscription sera automatiquement annulée.', 'ibc-enrollment' ); ?></p>
			</section>

			<section class="section">
				<h2><?php esc_html_e( 'Important', 'ibc-enrollment' ); ?></h2>
				<p class="notice" style="background:rgba(239,68,68,0.08);border-color:rgba(239,68,68,0.3);">
					<?php esc_html_e( 'Une fois votre inscription validée, elle est considérée comme définitive et non remboursable.', 'ibc-enrollment' ); ?>
				</p>
			</section>

			<footer class="footer">
				<p><strong><?php echo esc_html( $ctx['brand_name'] ); ?></strong></p>
				<?php if ( ! empty( $contact['address'] ) ) : ?>
					<p><?php echo esc_html( $contact['address'] ); ?></p>
				<?php endif; ?>
				<?php if ( ! empty( $contact['email'] ) ) : ?>
					<p><?php esc_html_e( 'Email :', 'ibc-enrollment' ); ?> <?php echo esc_html( $contact['email'] ); ?></p>
				<?php endif; ?>
				<?php if ( ! empty( $contact['phone'] ) ) : ?>
					<p><?php esc_html_e( 'Mobile :', 'ibc-enrollment' ); ?> <?php echo esc_html( $contact['phone'] ); ?></p>
				<?php endif; ?>
				<?php if ( ! empty( $contact['landline'] ) ) : ?>
					<p><?php esc_html_e( 'Fixe :', 'ibc-enrollment' ); ?> <?php echo esc_html( $contact['landline'] ); ?></p>
				<?php endif; ?>
			</footer>
		</div>
	</div>
</body>
</html>
