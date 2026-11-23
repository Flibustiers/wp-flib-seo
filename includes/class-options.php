<?php
/**
 * Réglages de l'extension : une seule option `flib_seo` (tableau), lue avec Flib_SEO_Options::get().
 *
 * Le schéma ci-dessous est la source unique des clés, des valeurs par défaut et des types
 * (le type sert à la sanitisation). Ajouter un réglage = l'ajouter ici, puis l'afficher
 * dans admin/class-admin.php.
 *
 * Données hors de cette option :
 * - méta des contenus et des termes : _flib_seo_titre, _flib_seo_desc, _flib_seo_noindex,
 *   _flib_seo_canonique, _flib_seo_image ;
 * - redirections : option `flib_seo_redirections` ; journal des 404 : `flib_seo_404`.
 *
 * @package FlibSEO
 */

defined( 'ABSPATH' ) || exit;

final class Flib_SEO_Options {

	const OPTION = 'flib_seo';

	/** Préfixe des méta (contenus et termes). */
	const META = '_flib_seo_';

	private static $cache = null;

	/**
	 * Onglet => clé => array( type, défaut ).
	 * Types : texte, zone, case, choix, image, url, liste (tableau de slugs), nombre.
	 */
	public static function schema() {
		return array(
			'general'    => array(
				'sep'             => array( 'choix', '–' ),
				'titre_contenu'   => array( 'texte', '%titre% %sep% %site%' ),
				'titre_accueil'   => array( 'texte', '%site% %sep% %slogan%' ),
				'desc_accueil'    => array( 'zone', '' ),
				'titre_archive'   => array( 'texte', '%titre% %sep% %site%' ),
				'titre_recherche' => array( 'texte', 'Recherche : %recherche% %sep% %site%' ),
				'titre_404'       => array( 'texte', 'Page introuvable %sep% %site%' ),
				'desc_auto'       => array( 'case', '1' ),
				'image_defaut'    => array( 'image', 0 ),
			),
			'indexation' => array(
				'types_noindex'   => array( 'liste', array() ),
				'taxos_noindex'   => array( 'liste', array( 'post_tag' ) ),
				'archives_auteur' => array( 'case', '' ),
				'archives_date'   => array( 'case', '' ),
				'medias_fichier'  => array( 'case', '1' ),
				'redirection_sitemap' => array( 'case', '1' ),
			),
			'entreprise' => array(
				'type'      => array( 'choix', 'LocalBusiness' ),
				'nom'       => array( 'texte', '' ),
				'telephone' => array( 'texte', '' ),
				'email'     => array( 'texte', '' ),
				'adresse'   => array( 'texte', '' ),
				'code_postal' => array( 'texte', '' ),
				'ville'     => array( 'texte', '' ),
				'pays'      => array( 'texte', 'FR' ),
				'latitude'  => array( 'texte', '' ),
				'longitude' => array( 'texte', '' ),
				'zone'      => array( 'texte', '' ),
				'horaires'  => array( 'zone', '' ),
				'prix'      => array( 'texte', '' ),
				'logo'      => array( 'image', 0 ),
				'reseaux'   => array( 'zone', '' ),
				'faq'       => array( 'case', '1' ),
				'articles'  => array( 'case', '1' ),
			),
			'ariane'     => array(
				'accueil'      => array( 'texte', 'Accueil' ),
				'sep'          => array( 'texte', '›' ),
				'courant'      => array( 'case', '1' ),
				'prefixe'      => array( 'texte', '' ),
				'page_articles' => array( 'case', '1' ),
			),
			'ia'         => array(
				'llms'          => array( 'case', '1' ),
				'llms_intro'    => array( 'zone', '' ),
				'reponses'   => array( 'case', '1' ),
				'entrainement' => array( 'case', '1' ),
				'robots_extra'  => array( 'zone', '' ),
			),
			'redirections' => array(
				'journal_404' => array( 'case', '1' ),
			),
		);
	}

	/** Toutes les valeurs, à plat (les clés sont préfixées par l'onglet : general.sep → general_sep). */
	public static function all() {
		if ( null === self::$cache ) {
			$enregistre  = (array) get_option( self::OPTION, array() );
			self::$cache = array();
			foreach ( self::schema() as $onglet => $champs ) {
				foreach ( $champs as $cle => $def ) {
					$k                 = $onglet . '_' . $cle;
					self::$cache[ $k ] = array_key_exists( $k, $enregistre ) ? $enregistre[ $k ] : $def[1];
				}
			}
		}
		return self::$cache;
	}

	/** Une valeur : get( 'general', 'sep' ). */
	public static function get( $onglet, $cle ) {
		$all = self::all();
		return $all[ $onglet . '_' . $cle ] ?? null;
	}

	public static function flush() {
		self::$cache = null;
	}

	/**
	 * Sanitisation d'un onglet posté, fusionnée avec les valeurs existantes des autres onglets.
	 *
	 * @param array $poste Valeurs brutes (déjà sans slashes).
	 */
	public static function sanitize( $poste ) {
		// Écriture interne (import, ou second passage de WordPress à la création de l'option) : déjà propre.
		if ( ! is_array( $poste ) || ! isset( $poste['_onglet'] ) ) {
			return is_array( $poste ) ? $poste : array();
		}
		$existant = (array) get_option( self::OPTION, array() );
		$onglet   = sanitize_key( $poste['_onglet'] );
		$schema   = self::schema();
		if ( ! isset( $schema[ $onglet ] ) ) {
			return $existant;
		}
		foreach ( $schema[ $onglet ] as $cle => $def ) {
			$k   = $onglet . '_' . $cle;
			$val = $poste[ $k ] ?? null;
			switch ( $def[0] ) {
				case 'case':
					$existant[ $k ] = empty( $val ) ? '' : '1';
					break;
				case 'zone':
					$existant[ $k ] = sanitize_textarea_field( (string) $val );
					break;
				case 'image':
				case 'nombre':
					$existant[ $k ] = absint( $val );
					break;
				case 'url':
					$existant[ $k ] = esc_url_raw( (string) $val );
					break;
				case 'liste':
					$existant[ $k ] = array_values( array_filter( array_map( 'sanitize_key', array_filter( (array) $val, 'is_string' ) ) ) );
					break;
				default:
					$existant[ $k ] = sanitize_text_field( (string) $val );
			}
		}
		self::flush();
		return $existant;
	}

	/* ------------------------------------------------------------------ */
	/* Méta des contenus et des termes                                     */
	/* ------------------------------------------------------------------ */

	public static function post_meta( $post_id, $cle ) {
		return get_post_meta( $post_id, self::META . $cle, true );
	}

	public static function term_meta( $term_id, $cle ) {
		return get_term_meta( $term_id, self::META . $cle, true );
	}

	/** Clause meta_query : contenus ou termes non masqués (méta noindex absente ou différente de 1). */
	public static function clause_indexables() {
		return array(
			'relation' => 'OR',
			array(
				'key'     => self::META . 'noindex',
				'compare' => 'NOT EXISTS',
			),
			array(
				'key'     => self::META . 'noindex',
				'value'   => '1',
				'compare' => '!=',
			),
		);
	}

	/** Types de contenus publics gérés (hors médias). */
	public static function types() {
		$types = get_post_types( array( 'public' => true ), 'objects' );
		unset( $types['attachment'] );
		return $types;
	}

	/** Taxonomies publiques gérées. */
	public static function taxonomies() {
		$taxos = get_taxonomies( array( 'public' => true ), 'objects' );
		unset( $taxos['post_format'] );
		return $taxos;
	}

	/**
	 * Information de l'entreprise : réglage de l'extension, sinon « Réglages du site » de flib-base.
	 * Correspondance des clés flib-base : nom, telephone, email, adresse, code_postal, ville.
	 */
	public static function entreprise( $cle ) {
		$val = self::get( 'entreprise', $cle );
		if ( is_string( $val ) && '' !== trim( $val ) ) {
			return trim( $val );
		}
		if ( function_exists( 'flib_reglage' ) && in_array( $cle, array( 'nom', 'telephone', 'email', 'adresse', 'code_postal', 'ville' ), true ) ) {
			$theme = (string) flib_reglage( $cle );
			if ( '' !== trim( $theme ) ) {
				return trim( $theme );
			}
		}
		return 'nom' === $cle ? get_bloginfo( 'name' ) : '';
	}

	/** Profils sociaux : liste de l'extension (une URL par ligne), sinon ceux de flib-base. */
	public static function reseaux() {
		$liste = array_filter( array_map( 'trim', preg_split( '/\R/', (string) self::get( 'entreprise', 'reseaux' ) ) ) );
		if ( ! $liste && function_exists( 'flib_reglage' ) ) {
			foreach ( array( 'facebook', 'instagram', 'linkedin', 'youtube', 'tiktok', 'google' ) as $r ) {
				$liste[] = (string) flib_reglage( $r );
			}
		}
		return array_values( array_filter( array_map( 'esc_url_raw', $liste ) ) );
	}
}
