<?php
/**
 * Drop-in de connexion DB multi-tenant.
 *
 * Chargé par require_wp_db() (wp-includes/load.php) AVANT tout le reste de
 * WordPress — aucun hook, aucun plugin disponible ici. La classe `wpdb` est
 * déjà chargée par le core à ce stade ; ce fichier se contente de résoudre
 * QUELLE base utiliser (à partir du nom de domaine) et d'instancier $wpdb
 * lui-même, en cache-court-circuitant la logique par défaut basée sur les
 * constantes DB_* (qui ne sont que des valeurs de compat dans wp-config.php).
 */

require_once __DIR__ . '/mu-plugins/sites-config.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Google\Cloud\SecretManager\V1\Client\SecretManagerServiceClient;
use Google\Cloud\SecretManager\V1\AccessSecretVersionRequest;

function wph_fetch_db_password( string $secret_id ): string {
    $project = getenv( 'GOOGLE_CLOUD_PROJECT' );
    $client  = new SecretManagerServiceClient();
    $name    = "projects/{$project}/secrets/{$secret_id}/versions/latest";
    $request = ( new AccessSecretVersionRequest() )->setName( $name );
    return $client->accessSecretVersion( $request )->getPayload()->getData();
}

$wph_site = wph_current_site();

if ( ! $wph_site ) {
    http_response_code( 404 );
    exit( 'Domaine non reconnu par ce backend WordPress.' );
}

$wph_cloudsql_connection = getenv( 'CLOUDSQL_CONNECTION_NAME' );
$wph_db_host = $wph_cloudsql_connection
    ? ":/cloudsql/{$wph_cloudsql_connection}"
    : ( getenv( 'DB_HOST' ) ?: 'localhost' );

global $wpdb;
$wpdb = new wpdb(
    $wph_site['db_user'],
    wph_fetch_db_password( $wph_site['db_secret_id'] ),
    $wph_site['db_name'],
    $wph_db_host
);
