# wp-hosting

Backend WordPress headless **mutualisé** pour tous les projets utilisant WordPress comme CMS (domoloc, oncompare, …). Un seul service Cloud Run, une base MySQL dédiée par site, routées dynamiquement par nom de domaine.

Chaque projet garde son propre front (Next.js, export statique) dans son propre repo — ce repo ne sert que l'admin (`wp-admin`) et la WP REST API consommée au build par ces fronts.

## Architecture

```
wp.domoloc.fr ──┐
                 ├──→ Cloud Run "wp" (ce repo) ──→ Cloud SQL (1 instance, 1 base par site)
wp.oncompare.fr ─┘
```

- `wp-content/mu-plugins/sites-config.php` — table de routage domaine → base/user/secret/front. **C'est ici qu'on ajoute un nouveau tenant.**
- `wp-content/db.php` — drop-in de connexion : résout la base à partir du domaine de la requête, va chercher le mot de passe dans Secret Manager.
- `wp-content/mu-plugins/headless-preview.php` — bouton "Aperçu" wp-admin → front Next.js du tenant, token HMAC signé.
- Filesystem Cloud Run en lecture seule à l'exécution : core + plugins/thèmes gérés via `composer.json`, installés au build de l'image Docker (pas d'installation depuis wp-admin).

## Ajouter un nouveau site

1. Créer la base + l'utilisateur MySQL dédiés sur l'instance Cloud SQL partagée (cf. Terraform `my-terraform/projects/wp-hosting`).
2. Créer le secret du mot de passe dans Secret Manager (`db-password-wp-<site>`).
3. Ajouter une entrée dans `wp-content/mu-plugins/sites-config.php` (domaine, `db_name`, `db_user`, `db_secret_id`, `front_url`).
4. Mapper le domaine `wp.<site>.<tld>` vers le service Cloud Run (`gcloud run domain-mappings create`).
5. Terminer l'installation WordPress du site via l'assistant wp-admin (seule étape non composer-able), activer/configurer wp-stateless (médias) + Yoast SEO.

## Déploiement

Automatisé par Cloud Build (`cloudbuild.yaml`) sur push `main` : build de l'image (composer install inclus), push vers Artifact Registry, déploiement Cloud Run avec les secrets montés directement en variables d'environnement.

## Variables d'environnement (Cloud Run)

```
CLOUDSQL_CONNECTION_NAME   # instance Cloud SQL partagée (project:region:instance)
GOOGLE_CLOUD_PROJECT       # projet GCP (pour les appels Secret Manager)
WP_DEBUG
WP_AUTH_KEY / WP_SECURE_AUTH_KEY / WP_LOGGED_IN_KEY / WP_NONCE_KEY
WP_AUTH_SALT / WP_SECURE_AUTH_SALT / WP_LOGGED_IN_SALT / WP_NONCE_SALT
WP_PREVIEW_SECRET
```

Ces clés/salts sont partagées entre tous les tenants (un seul service) — l'isolation entre sites se fait au niveau base de données, pas au niveau session.
