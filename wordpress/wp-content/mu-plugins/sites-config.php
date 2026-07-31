<?php
/**
 * Table de routage multi-tenant — domaine d'admin → base de données dédiée
 * + front public associé. Chargée directement par wp-content/db.php (avant
 * tout bootstrap WP, donc en dehors du système de hooks) et par les
 * mu-plugins qui ont besoin de connaître le tenant courant.
 *
 * Chaque site a son propre user MySQL + son propre secret — aucun credential
 * partagé entre tenants, seul ce service PHP est mutualisé.
 */

if ( ! function_exists( 'wph_sites' ) ) {
    function wph_sites(): array {
        return [
            'wp.domoloc.fr' => [
                'db_name'      => 'wordpress_domoloc',
                'db_user'      => 'wp_domoloc',
                'db_secret_id' => 'db-password-wp-domoloc',
                'front_url'    => 'https://domoloc.fr/articles/preview',
            ],
            // À compléter avec le vrai domaine une fois le front oncompare en place.
            'wp.oncompare.fr' => [
                'db_name'      => 'wordpress_oncompare',
                'db_user'      => 'wp_oncompare',
                'db_secret_id' => 'db-password-wp-oncompare',
                'front_url'    => 'https://oncompare.fr/articles/preview',
            ],
        ];
    }
}

if ( ! function_exists( 'wph_current_site' ) ) {
    function wph_current_site(): ?array {
        $host  = $_SERVER['HTTP_HOST'] ?? '';
        $sites = wph_sites();
        return $sites[ $host ] ?? null;
    }
}
