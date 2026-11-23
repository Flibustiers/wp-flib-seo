# Flib'SEO

Extension de référencement légère des Flibustiers, en remplacement de Yoast. Aucune dépendance, aucune requête externe, rien de chargé côté visiteur à part une petite feuille de style quand le fil d'Ariane est affiché.

## Ce qu'elle fait

| Onglet | Contenu |
|---|---|
| Titres et descriptions | Modèles par défaut (`%titre% %sep% %site%`…), accueil, image de partage par défaut. Chaque contenu et chaque terme a ses propres champs, avec aperçu Google. |
| Indexation | Types et taxonomies à ne pas indexer, archives d'auteur / de date, pages de médias, redirection des anciens plans du site Yoast. |
| Entreprise | Données structurées LocalBusiness : type, coordonnées (reprises des Réglages du site de flib-base si vides), zone d'intervention, horaires, réseaux ; FAQ et articles. |
| Plan du site | Page « Plan du site » pour les visiteurs : bloc ou shortcode `[flib_plan_site]`, contenus et taxonomies au choix, exclusions, colonnes. |
| Fil d'Ariane | Bloc « Fil d'Ariane » et shortcode `[flib_fil_ariane]`, réglages par défaut, aperçu. |
| IA (GEO) | Robots des moteurs de réponse IA et de l'entraînement dans le robots.txt, fichier `/llms.txt`. |
| Redirections | 301 / 302 / 410, jokers `*`, import en masse depuis un tableur, journal des 404. |
| Outils | Import des données Yoast. |

## Passer un site de Yoast à Flib'SEO

1. Installer et activer Flib'SEO (Yoast encore actif : Flib'SEO n'écrit rien dans les pages).
2. SEO › Outils › Importer les données Yoast.
3. Désactiver puis supprimer Yoast.
4. Vérifier le code source de quelques pages et déclarer `/wp-sitemap.xml` dans la Search Console (l'ancienne adresse `sitemap_index.xml` est redirigée).

## Fil d'Ariane

- Thème à blocs : Apparence › Éditeur › Modèles, insérer le bloc « Fil d'Ariane » dans les gabarits voulus.
- Ailleurs : `[flib_fil_ariane sep="/" accueil="Accueil" courant="non" prefixe="Vous êtes ici :" class="ma-classe"]`.
- Le balisage `BreadcrumbList` est présent sur toutes les pages, fil affiché ou non.

## Plan du site visiteurs

`[flib_plan_site types="service,page,post" taxonomies="category" ordre="service,page,post" regrouper="oui" tri="titre" libelles="post:Nos actualités" exclure="12,34" exclure_termes="interne" articles="20" colonnes="3" titres="non" niveau="3" class="ma-classe"]` — attributs absents : réglages de l'onglet. Un contenu se masque aussi au cas par cas (« Masquer du plan du site » dans le panneau Référencement), sans toucher à son indexation.

## Mises à jour

Comme les autres extensions Flibustiers : en-tête `Update URI: https://github.com/Flibustiers/wp-flib-seo`, jeton `FLIB_GITHUB_TOKEN` dans wp-config.php, mise à jour depuis la page Extensions.

## Filtres

`flib_seo_titre`, `flib_seo_description`, `flib_seo_canonique`, `flib_seo_image_id`, `flib_seo_open_graph`, `flib_seo_schema`, `flib_seo_fil_ariane`, `flib_seo_llms_txt`.
