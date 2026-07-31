<?php
/**
 * Plugin Name: Headless Preview (multi-tenant)
 * Description: Redirige le bouton "Aperçu" de wp-admin vers le front Next.js
 *              du tenant courant (headless) et expose un endpoint REST
 *              protégé par token signé pour lire un brouillon sans
 *              authentification WP côté navigateur.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function wph_preview_secret(): string {
    return (string) getenv( 'WP_PREVIEW_SECRET' );
}

function wph_preview_token( int $post_id, int $expires ): string {
    return hash_hmac( 'sha256', "{$post_id}:{$expires}", wph_preview_secret() );
}

// Le bouton "Aperçu" de wp-admin pointe vers le front Next.js du tenant
// courant (résolu par domaine, cf. mu-plugins/sites-config.php) plutôt que
// vers le thème WordPress (inutilisé en headless).
add_filter( 'preview_post_link', function ( string $link, WP_Post $post ) {
    $site = wph_current_site();
    if ( ! $site ) {
        return $link;
    }

    $expires = time() + HOUR_IN_SECONDS;
    $token   = wph_preview_token( $post->ID, $expires );

    return add_query_arg(
        [ 'id' => $post->ID, 'expires' => $expires, 'token' => $token ],
        $site['front_url']
    );
}, 10, 2 );

// Endpoint public mais protégé par token — expose UNIQUEMENT le post demandé
// (jamais de listing), et seulement si le token HMAC + l'expiration sont
// valides. Comme la connexion DB est déjà scopée au tenant courant par
// wp-content/db.php, ce endpoint ne peut de toute façon lire que les
// articles du domaine sur lequel la requête est arrivée.
add_action( 'rest_api_init', function () {
    register_rest_route( 'domoloc/v1', '/preview/(?P<id>\d+)', [
        'methods'             => 'GET',
        'permission_callback' => function ( WP_REST_Request $req ) {
            $post_id = (int) $req['id'];
            $expires = (int) $req->get_param( 'expires' );
            $token   = (string) $req->get_param( 'token' );

            if ( ! $post_id || ! $expires || ! $token || $expires < time() ) {
                return false;
            }

            return hash_equals( wph_preview_token( $post_id, $expires ), $token );
        },
        'callback' => function ( WP_REST_Request $req ) {
            $post = get_post( (int) $req['id'] );
            if ( ! $post ) {
                return new WP_Error( 'not_found', 'Article introuvable', [ 'status' => 404 ] );
            }

            $media_id = get_post_thumbnail_id( $post );

            return [
                'id'            => $post->ID,
                'slug'          => $post->post_name,
                'title'         => get_the_title( $post ),
                'html'          => apply_filters( 'the_content', $post->post_content ),
                'excerpt'       => get_the_excerpt( $post ),
                'featuredImage' => $media_id ? wp_get_attachment_image_url( $media_id, 'large' ) : null,
                'publishedAt'   => get_the_date( 'c', $post ),
                'updatedAt'     => get_the_modified_date( 'c', $post ),
                'status'        => $post->post_status,
            ];
        },
    ] );
} );
