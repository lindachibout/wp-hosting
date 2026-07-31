<?php
/**
 * Config WordPress multi-tenant — la connexion DB réelle (laquelle base,
 * quel user, quel mot de passe) est résolue dynamiquement par domaine dans
 * wp-content/db.php, PAS ici. Les constantes DB_* ci-dessous ne sont que des
 * valeurs de compatibilité pour les plugins qui les lisent directement —
 * elles ne servent jamais à se connecter (cf. wp-content/db.php).
 */

function env( string $key, $default = null ) {
    $value = getenv( $key );
    return $value === false ? $default : $value;
}

// ── Compat — non utilisées pour la connexion réelle, cf. wp-content/db.php
define( 'DB_NAME', 'unused' );
define( 'DB_USER', 'unused' );
define( 'DB_PASSWORD', 'unused' );
define( 'DB_HOST', 'unused' );
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', '' );
$table_prefix = 'wp_';

// ── Clés et salts d'authentification (Secret Manager, partagées par tous
//    les tenants — un service unique, pas d'isolation de session requise
//    entre sites au-delà de l'isolation par base de données).
foreach ( [ 'AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY', 'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT' ] as $key ) {
    define( $key, env( "WP_{$key}", '' ) );
}

// ── URLs — résolues dynamiquement par domaine (chaque tenant garde son
//    propre wp-admin sur son propre sous-domaine).
define( 'WP_HOME', 'https://' . ( $_SERVER['HTTP_HOST'] ?? 'localhost' ) );
define( 'WP_SITEURL', WP_HOME );

// ── Cloud Run : filesystem en lecture seule à l'exécution — pas
//    d'installation/édition de plugins ou thèmes depuis wp-admin, tout passe
//    par Composer (image reconstruite + redéployée).
define( 'DISALLOW_FILE_MODS', true );
define( 'DISALLOW_FILE_EDIT', true );
define( 'AUTOMATIC_UPDATER_DISABLED', true );

define( 'FORCE_SSL_ADMIN', true );
define( 'WP_DEBUG', env( 'WP_DEBUG', 'false' ) === 'true' );

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ . '/' );
}

require_once ABSPATH . 'wp-settings.php';
