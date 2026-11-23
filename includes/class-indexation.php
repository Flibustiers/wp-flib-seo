<?php
/**
 * Indexation : archives inutiles redirigées, pages de médias, plan du site natif de WordPress
 * (wp-sitemap.xml) épuré et daté.
 *
 * @package FlibSEO
 */

defined( 'ABSPATH' ) || exit;

final class Flib_SEO_Indexation {

	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'archives' ), 1 );
		add_filter( 'pre_option_wp_attachment_pages_enabled', array( __CLASS__, 'pages_medias' ) );

		add_filter( 'wp_sitemaps_add_provider', array( __CLASS__, 'fournisseurs' ), 10, 2 );
		add_filter( 'wp_sitemaps_post_types', array( __CLASS__, 'types' ) );
		add_filter( 'wp_sitemaps_taxonomies', array( __CLASS__, 'taxonomies' ) );
		add_filter( 'wp_sitemaps_posts_query_args', array( __CLASS__, 'requete_contenus' ) );
		add_filter( 'wp_sitemaps_taxonomies_query_args', array( __CLASS__, 'requete_termes' ) );
		add_filter( 'wp_sitemaps_posts_entry', array( __CLASS__, 'date_contenu' ), 10, 2 );
		add_action( 'template_redirect', array( __CLASS__, 'anciens_sitemaps' ), 0 );
	}

	/** Archives d'auteur et de date désactivées : redirection permanente vers l'accueil. */
	public static function archives() {
		$o = 'indexation';
		if ( ( is_author() && ! Flib_SEO_Options::get( $o, 'archives_auteur' ) ) || ( is_date() && ! Flib_SEO_Options::get( $o, 'archives_date' ) ) ) {
			wp_safe_redirect( home_url( '/' ), 301, 'Flib SEO' );
			exit;
		}
	}

	/** Pages de pièces jointes : WordPress (6.4+) redirige alors vers le fichier lui-même. */
	public static function pages_medias( $valeur ) {
		return Flib_SEO_Options::get( 'indexation', 'medias_fichier' ) ? '0' : $valeur;
	}

	public static function fournisseurs( $fournisseur, $nom ) {
		if ( 'users' === $nom && ! Flib_SEO_Options::get( 'indexation', 'archives_auteur' ) ) {
			return false;
		}
		return $fournisseur;
	}

	public static function types( $types ) {
		foreach ( (array) Flib_SEO_Options::get( 'indexation', 'types_noindex' ) as $t ) {
			unset( $types[ $t ] );
		}
		return $types;
	}

	public static function taxonomies( $taxos ) {
		foreach ( (array) Flib_SEO_Options::get( 'indexation', 'taxos_noindex' ) as $t ) {
			unset( $taxos[ $t ] );
		}
		return $taxos;
	}

	private static function exclure_noindex( $args ) {
		$args['meta_query']   = isset( $args['meta_query'] ) ? (array) $args['meta_query'] : array(); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		$args['meta_query'][] = Flib_SEO_Options::clause_indexables();
		return $args;
	}

	public static function requete_contenus( $args ) {
		return self::exclure_noindex( $args );
	}

	public static function requete_termes( $args ) {
		return self::exclure_noindex( $args );
	}

	public static function date_contenu( $entree, $post ) {
		$entree['lastmod'] = get_post_modified_time( 'c', true, $post );
		return $entree;
	}

	/**
	 * Anciennes adresses de plan du site (Yoast : sitemap_index.xml, page-sitemap.xml…) :
	 * redirection vers wp-sitemap.xml, pour ne rien perdre dans la Search Console.
	 * (WordPress redirige déjà /sitemap.xml.)
	 */
	public static function anciens_sitemaps() {
		if ( ! is_404() || ! Flib_SEO_Options::get( 'indexation', 'redirection_sitemap' ) || ! wp_sitemaps_get_server()->sitemaps_enabled() ) {
			return;
		}
		$chemin = trim( (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '', PHP_URL_PATH ), '/' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- comparé à un motif fixe.
		$base   = trim( (string) wp_parse_url( home_url(), PHP_URL_PATH ), '/' );
		if ( '' !== $base && str_starts_with( $chemin, $base . '/' ) ) {
			$chemin = substr( $chemin, strlen( $base ) + 1 );
		}
		if ( preg_match( '#^(sitemap_index\.xml|sitemap-index\.xml|[a-z0-9_-]+-sitemap[0-9]*\.xml)$#i', $chemin ) ) {
			wp_safe_redirect( home_url( '/wp-sitemap.xml' ), 301, 'Flib SEO' );
			exit;
		}
	}
}
