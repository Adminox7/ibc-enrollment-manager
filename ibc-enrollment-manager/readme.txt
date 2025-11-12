=== IBC Enrollment Manager ===
Contributors: ibc-morocco
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Gestion complète des sessions, étudiants et inscriptions pour IBC Morocco.

== Description ==

IBC Enrollment Manager est un plugin WordPress conçu pour les écoles de langues. Il permet de gérer les sessions de préparation et d'examen, les étudiants et les inscriptions en ligne avec verrouillage de place et notifications par email/WhatsApp.

Fonctionnalités principales :

* Tables personnalisées pour les sessions, étudiants et inscriptions (InnoDB, clés étrangères).
* Tableau de bord administrateur "IBC Manager" avec KPIs.
* Gestion CRUD des sessions, étudiants et inscriptions.
* Formulaire d'inscription front-end sécurisé via shortcode `[ibc_register]`.
* Catalogue public des sessions ouvertes via shortcode `[ibc_sessions]`.
* Verrouillage automatique des places pendant 10 minutes (WP-Cron).
* Notifications par email (SMTP) et WhatsApp Cloud API.
* Paramétrage reCAPTCHA v3, Stripe/CMI Maroc, WhatsApp et SMTP.
* Import/export CSV pour étudiants et inscriptions.
* Internationalisation prête (text domain `ibc-enrollment`).

== Installation ==

1. Télécharger le dossier `ibc-enrollment-manager` et le placer dans `wp-content/plugins/`.
2. Activer le plugin dans le menu *Extensions* de WordPress.
3. Accéder au menu *IBC Manager* pour configurer les paramètres (SMTP, reCAPTCHA, WhatsApp...).
4. Ajouter les shortcodes sur vos pages :
   * `[ibc_sessions]` pour afficher les sessions ouvertes.
   * `[ibc_register session="123"]` pour afficher le formulaire d'inscription lié à la session 123.

== FAQ ==

= Comment activer reCAPTCHA v3 ? =
Renseignez les clés reCAPTCHA v3 dans *IBC Manager → Paramètres → reCAPTCHA*. Le script est intégré automatiquement.

= Comment supprimer les données lors de la désinstallation ? =
Activez l'option *Supprimer les données lors de la désinstallation* dans les paramètres avant de désactiver et supprimer le plugin.

== Changelog ==

= 1.0.0 =
* Première version stable.
* Création des tables personnalisées avec dbDelta.
* Tableau de bord administrateur complet.
* Formulaires front-end sécurisés et verrouillage de places.
* Notifications email et WhatsApp.
* Import/export CSV.

== Mise à jour ==

Pour mettre à jour, remplacez simplement le dossier du plugin par la nouvelle version et réactivez-le si nécessaire. Les données restent intactes sauf si l'option de suppression est activée.