<?php
/**
 * Minimal autoloader placeholder for Dompdf.
 *
 * The plugin vérifie la présence de \Dompdf\Dompdf. Si vous souhaitez une génération PDF complète,
 * remplacez ce dossier par la distribution officielle de Dompdf (https://github.com/dompdf/dompdf).
 *
 * @package IBC\EnrollmentManager
 */

spl_autoload_register(
	static function ( $class ) {
		if ( strpos( $class, 'Dompdf\\' ) !== 0 ) {
			return;
		}

		// No bundled polyfill: la classe sera absente et le plugin activera le fallback HTML.
	}
);
