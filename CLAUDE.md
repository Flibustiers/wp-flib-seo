# Flib'SEO — mémoire projet

Extension WordPress de référencement des Flibustiers (remplace Yoast). Réponds et commente en français.
Standards WordPress, tabulations, PHP 8.1, WordPress 6.4 minimum, domaine de traduction `flib-seo`.

## Architecture

- `flib-seo.php` — amorçage, constantes, `flib_seo_conflit()` (Yoast / Rank Math / SEOPress actif : rien n'est écrit dans le `<head>`).
- `includes/class-options.php` — **source unique des réglages** (`Flib_SEO_Options::schema()`, onglet => clé => type, défaut) et sanitisation générique par type. Valeurs à plat dans l'option `flib_seo` : `<onglet>_<clé>`.
- `includes/class-contexte.php` — titre, description, noindex, canonique, image de la page visitée.
- `includes/class-head.php` — balises du `<head>` ; `class-indexation.php` — archives, médias, sitemaps natifs ; `class-schema.php` — JSON-LD ; `class-fil-ariane.php` — chemin, bloc `flib-seo/fil-ariane`, shortcode `[flib_fil_ariane]` ; `class-ia.php` — robots.txt et /llms.txt ; `class-redirections.php` — redirections et journal 404.
- `admin/` — page de réglages à onglets, boîte « Référencement » et champs des termes, import Yoast.
- `flib-update/` — brique commune de mise à jour (source `Flibustiers/flib-extensions`, `lib/wp/flib-update`, recopier avec `tools/sync-lib.sh`, ne pas modifier ici).

## Ajouter un réglage

1. `Flib_SEO_Options::schema()` : clé, type, défaut (la sanitisation suit le type).
2. Afficher le champ dans l'onglet correspondant de `admin/class-admin.php` (`self::champ( $onglet, $cle, … )`).
3. Lire avec `Flib_SEO_Options::get( $onglet, $cle )`.

Méta : `_flib_seo_titre`, `_desc`, `_noindex` (`'1'` = masqué ; toute autre valeur ou l'absence = indexé, voir `clause_indexables()`), `_canonique`, `_image`.

## Publier une version

Monter la version à quatre endroits : en-tête `Version:` et constante `FLIB_SEO_VERSION` dans `flib-seo.php`, `Stable tag:` et section `== Changelog ==` de `readme.txt` (+ `CHANGELOG.md`). Pousser sur `main` : `.github/workflows/build.yml` vérifie la concordance, construit `flib-seo.zip` et crée le tag et la release.

## Vérifications avant commit

```bash
find . -name "*.php" -not -path "./.git/*" -exec php -l {} \;
for f in assets/js/*.js; do node --check "$f"; done
```

## À ne pas faire

- Ne jamais committer de jeton.
- Pas de dépendance JavaScript côté site ; pas d'étape de compilation (le bloc est écrit en JS natif).
