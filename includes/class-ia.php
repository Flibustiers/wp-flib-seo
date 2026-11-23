<?php
/**
 * Visibilité dans les IA (GEO) : robots des IA dans le robots.txt et fichier /llms.txt.
 *
 * Le robots.txt et le llms.txt sont générés par WordPress : un fichier physique du même
 * nom à la racine du site prend le dessus (avertissement dans l'onglet « IA »).
 *
 * @package FlibSEO
 */

defined( 'ABSPATH' ) || exit;

final class Flib_SEO_IA {

	const CACHE = 'flib_seo_llms';

	/** Robots qui cherchent une réponse pour un utilisateur (ChatGPT search, Perplexity…). */
	public static function robots_reponses() {
		return array( 'OAI-SearchBot', 'ChatGPT-User', 'PerplexityBot', 'Perplexity-User', 'Claude-SearchBot', 'Claude-User', 'DuckAssistBot', 'MistralAI-User' );
	}

	/** Robots qui collectent des pages pour entraîner les modèles. */
	public static function robots_entrainement() {
		return array( 'GPTBot', 'ClaudeBot', 'Google-Extended', 'Applebot-Extended', 'CCBot', 'meta-externalagent', 'Bytespider', 'cohere-ai', 'Amazonbot' );
	}

	public static function init() {
		add_filter( 'robots_txt', array( __CLASS__, 'robots_txt' ), 20, 2 );
		add_action( 'template_redirect', array( __CLASS__, 'llms_txt' ), 0 );
		foreach ( array( 'save_post', 'deleted_post', 'edited_term', 'update_option_flib_seo', 'update_option_blogname', 'update_option_blogdescription' ) as $hook ) {
			add_action( $hook, array( __CLASS__, 'vider_cache' ) );
		}
	}

	public static function vider_cache() {
		delete_transient( self::CACHE );
	}

	public static function robots_txt( $sortie, $public ) {
		if ( ! $public ) {
			return $sortie;
		}
		$bloques = array();
		if ( ! Flib_SEO_Options::get( 'ia', 'reponses' ) ) {
			$bloques = array_merge( $bloques, self::robots_reponses() );
		}
		if ( ! Flib_SEO_Options::get( 'ia', 'entrainement' ) ) {
			$bloques = array_merge( $bloques, self::robots_entrainement() );
		}
		if ( $bloques ) {
			$sortie .= "\n# Flib'SEO : robots d'IA bloqués\n";
			foreach ( $bloques as $robot ) {
				$sortie .= 'User-agent: ' . $robot . "\n";
			}
			$sortie .= "Disallow: /\n";
		}
		$extra = trim( (string) Flib_SEO_Options::get( 'ia', 'robots_extra' ) );
		if ( '' !== $extra ) {
			$sortie .= "\n" . $extra . "\n";
		}
		return $sortie;
	}

	/** Chemin demandé, relatif à l'adresse du site. */
	public static function chemin_demande() {
		$chemin = (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '', PHP_URL_PATH ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- seulement comparé.
		$base   = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		if ( '' !== $base && str_starts_with( $chemin, $base ) ) {
			$chemin = substr( $chemin, strlen( $base ) );
		}
		return trim( rawurldecode( $chemin ), '/' );
	}

	public static function llms_actif() {
		return Flib_SEO_Options::get( 'ia', 'llms' ) && '1' === (string) get_option( 'blog_public' );
	}

	public static function llms_txt() {
		if ( 'llms.txt' !== self::chemin_demande() || ! self::llms_actif() ) {
			return;
		}
		$contenu = get_transient( self::CACHE );
		if ( false === $contenu ) {
			$contenu = self::generer();
			set_transient( self::CACHE, $contenu, 12 * HOUR_IN_SECONDS );
		}
		status_header( 200 );
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'X-Robots-Tag: noindex' );
		echo $contenu; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- texte brut, en-tête text/plain.
		exit;
	}

	/** Une ligne de lien Markdown : « - [Titre](url) : description ». */
	private static function ligne( $post ) {
		$titre = str_replace( array( '[', ']' ), array( '(', ')' ), wp_strip_all_tags( get_the_title( $post ) ) );
		$desc  = Flib_SEO_Contexte::desc_post( $post );
		return '- [' . $titre . '](' . get_permalink( $post ) . ')' . ( '' !== $desc ? ' : ' . $desc : '' );
	}

	private static function contenus( $type, $nombre, $ordre = array( 'menu_order' => 'ASC', 'title' => 'ASC' ) ) {
		$posts = get_posts(
			array(
				'post_type'      => $type,
				'post_status'    => 'publish',
				'posts_per_page' => $nombre,
				'orderby'        => $ordre,
				'has_password'   => false,
				'no_found_rows'  => true,
				'meta_query'     => array( Flib_SEO_Options::clause_indexables() ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			)
		);
		return array_map( array( __CLASS__, 'ligne' ), $posts );
	}

	/** Contenu du llms.txt (format https://llmstxt.org). */
	public static function generer() {
		$e      = array( 'Flib_SEO_Options', 'entreprise' );
		$resume = trim( (string) Flib_SEO_Options::get( 'general', 'desc_accueil' ) );
		if ( '' === $resume ) {
			$resume = get_bloginfo( 'description' );
		}
		$l = array( '# ' . call_user_func( $e, 'nom' ), '' );
		if ( '' !== $resume ) {
			$l[] = '> ' . $resume;
			$l[] = '';
		}
		$intro = trim( (string) Flib_SEO_Options::get( 'ia', 'llms_intro' ) );
		if ( '' !== $intro ) {
			$l[] = $intro;
			$l[] = '';
		}

		$infos = array();
		$adr   = trim( implode( ', ', array_filter( array( call_user_func( $e, 'adresse' ), trim( call_user_func( $e, 'code_postal' ) . ' ' . call_user_func( $e, 'ville' ) ) ) ) ) );
		if ( '' !== $adr ) {
			$infos[] = '- ' . __( 'Adresse', 'flib-seo' ) . ' : ' . $adr;
		}
		if ( '' !== call_user_func( $e, 'telephone' ) ) {
			$infos[] = '- ' . __( 'Téléphone', 'flib-seo' ) . ' : ' . call_user_func( $e, 'telephone' );
		}
		if ( '' !== call_user_func( $e, 'email' ) ) {
			$infos[] = '- ' . __( 'E-mail', 'flib-seo' ) . ' : ' . call_user_func( $e, 'email' );
		}
		$zone = trim( (string) Flib_SEO_Options::get( 'entreprise', 'zone' ) );
		if ( '' !== $zone ) {
			$infos[] = '- ' . __( 'Zone d’intervention', 'flib-seo' ) . ' : ' . $zone;
		}
		$horaires = array_filter( array_map( 'trim', preg_split( '/\R/', function_exists( 'flib_reglage' ) ? (string) flib_reglage( 'horaires' ) : '' ) ) );
		if ( $horaires ) {
			$infos[] = '- ' . __( 'Horaires', 'flib-seo' ) . ' : ' . implode( ' ; ', $horaires );
		}
		$infos[] = '- ' . __( 'Site', 'flib-seo' ) . ' : ' . home_url( '/' );
		$l[]     = '## ' . __( 'Informations pratiques', 'flib-seo' );
		$l[]     = '';
		$l       = array_merge( $l, $infos, array( '' ) );

		$exclus = (array) Flib_SEO_Options::get( 'indexation', 'types_noindex' );
		$types  = Flib_SEO_Options::types();
		// Pages d'abord, articles en dernier.
		uksort( $types, static fn( $a, $b ) => ( 'page' === $b ) - ( 'page' === $a ) ?: ( 'post' === $a ) - ( 'post' === $b ) );
		foreach ( $types as $type => $obj ) {
			if ( in_array( $type, $exclus, true ) ) {
				continue;
			}
			$lignes = 'post' === $type
				? self::contenus( 'post', 20, array( 'date' => 'DESC' ) )
				: self::contenus( $type, 'page' === $type ? 100 : 50 );
			if ( ! $lignes ) {
				continue;
			}
			$l[] = '## ' . ( 'post' === $type ? __( 'Articles récents', 'flib-seo' ) : $obj->labels->name );
			$l[] = '';
			$l   = array_merge( $l, $lignes, array( '' ) );
		}

		return apply_filters( 'flib_seo_llms_txt', implode( "\n", $l ) );
	}
}
