=== IBC Enrollment Manager ===
Contributors: ibc-morocco
Requires at least: 6.2
Tested up to: 6.6
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Gestion complète des inscriptions IBC (préparation d’examen) avec formulaires front, dashboard sécurisé, PDF, e-mailing et API REST.

== Description ==

IBC Enrollment Manager remplace le flux Google Apps Script historique par un plugin WordPress industrialisable. Il stocke les préinscriptions dans une table dédiée, envoie les récépissés par e-mail (avec PDF), expose des endpoints REST sécurisés par jeton et fournit un tableau de bord opérationnel pour l’équipe IBC.

Fonctionnalités clés :

* Table personnalisée `wp_ibc_registrations` (InnoDB, indexes email/téléphone/statut).
* Formulaire front moderne (`[ibc_register]`) avec contrôle capacité + anti-doublon temps réel.
* Dashboard front sécurisé (`[ibc_admin_dashboard]`) : recherche, filtres, éditions inline, annulation, export CSV.
* API REST (`ibc/v1`) : login, check capacité, register multipart, list/update/delete admin.
* Génération de récépissés PDF (fallback si Dompdf indisponible) et e-mail de confirmation HTML.
* Page de réglages (Branding, Paiement, Contact, Sécurité, Capacité & Prix).
* Upload sécurisé des justificatifs (CIN recto/verso – JPG/PNG/PDF).
* Internationalisation prête (`ibc-enrollment-manager`).

== Installation ==

1. Dézippez le dossier `ibc-enrollment-manager` dans `wp-content/plugins/`.
2. Activez l’extension via le menu *Extensions*.
3. Rendez-vous dans *Réglages → IBC Enrollment* pour définir capacité, branding, coordonnées bancaires, contact et mot de passe admin.
4. Ajoutez les shortcodes sur vos pages publiques :
	* `[ibc_register]` : formulaire de préinscription.
	* `[ibc_admin_dashboard]` : interface opérateur (protégée par mot de passe jeton).

== Shortcodes ==

`[ibc_register]`  
Affiche le formulaire multi-champs. Vérifie automatiquement la capacité restante (option `ibc_capacity_limit`) et les doublons (email/téléphone). À la soumission : insertion BDD, PDF, email.  
Attributs :  
* `title` (optionnel) – titre affiché ( défaut : “Préinscription IBC” ).

`[ibc_admin_dashboard]`  
Interface back-office (front). Modal de connexion → POST `/ibc/v1/login` → obtention d’un jeton (stocké en sessionStorage). Permet la consultation, la mise à jour et l’annulation des inscriptions + export CSV.

== Endpoints REST ==

Namespace : `ibc/v1`

* `POST /login` – Entrée : `password`. Retourne `token`, `ttl`. Stocke un transient `ibc_tok_{hash}` (2h). Fallback sur ancienne option `ibc_admin_password_plain` si le hash n’est pas encore défini.
* `GET /check` – Params : `email`, `phone`. Réponse : `capacity`, `total`, `existsEmail`, `existsPhone`.
* `POST /register` – Multipart (champs form, fichiers `cin_recto`, `cin_verso`). Crée la ligne BDD, génère le PDF, envoie l’e-mail. Retourne `ref`, `downloadUrl`, `receiptId`.
* `GET /regs` – (Token requis) Liste paginable filtrable (`search`, `niveau`, `statut`, `page`, `per_page`).
* `POST /reg/update` – (Token) Params : `id` + `fields{}`. Mets à jour les colonnes autorisées.
* `POST /reg/delete` – (Token) Param : `ref`. Effectue un soft delete (`statut = Annule`).

Toutes les réponses ont la forme `{ success: bool, data|message }` avec codes HTTP adaptés.

== Options stockées ==

* `ibc_capacity_limit` (int) – capacité maximale (défaut 1066).
* `ibc_price_prep` (int) – prix en MAD (défaut 1000).
* `ibc_brand_colors` (array) – primary, secondary, text, muted, border.
* `ibc_brand_bankName`, `ibc_brand_accountHolder`, `ibc_brand_rib`, `ibc_brand_iban`, `ibc_brand_bic`, `ibc_brand_agency`, `ibc_brand_paymentNote`.
* `ibc_contact_address`, `ibc_contact_email`, `ibc_contact_phone`, `ibc_contact_landline`.
* `ibc_admin_password_hash` – hash BCrypt du mot de passe admin.
* `ibc_last_token_issued` – horodatage du dernier jeton délivré.

== PDF ==

Le plugin tente de charger `vendor/dompdf/autoload.inc.php`. Si Dompdf est présent, un reçu A4 est produit (couleurs branding). Sans Dompdf, la génération retourne une erreur gérée : l’e-mail part tout de même sans pièce jointe.

== Désinstallation ==

`uninstall.php` supprime les options listées ci-dessus et la table `wp_ibc_registrations`.

== Changelog ==

= 1.0.0 =
* Implémentation complète du flux d’inscription IBC.
* API REST dédiée avec sécurisation par token.
* Formulaire front moderne + contrôles live.
* Dashboard opérateur (edits inline, export CSV).
* Génération PDF + e-mail HTML.