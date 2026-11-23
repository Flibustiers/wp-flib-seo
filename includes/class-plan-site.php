<?php
/**
 * Plan du site pour les visiteurs : bloc « Plan du site » et shortcode [flib_plan_site].
 *
 * Reprend les règles d'indexation : les contenus masqués de Google, les types et les
 * taxonomies non indexés n'y apparaissent pas. Un contenu peut aussi être masqué du seul
 * plan du site (case dans l'éditeur, méta _flib_seo_plan_masque).
 *
 * Shortcode (attributs absents = réglages de l'onglet « Plan du site ») :
 *   [flib_plan_site
 *     types="service,page,post"          contenus affichés, dans cet ordre
 *     taxonomies="category"              listes de termes affichées
 *     ordre="service,page,category,post" ordre des rubriques (types et taxonomies mélangés)
 *     regrouper="oui"                    articles et contenus non hiérarchiques classés par catégorie
 *     tri="titre"                        defaut | titre | date | modif
 *     exclure="12,34"                    contenus exclus (identifiants)
 *     exclure_termes="actus-internes,8"  catégories / termes exclus, avec leurs contenus (slug ou identifiant)
 *     libelles="post:Nos actualités,page:Le cabinet"  noms des rubriques
 *     articles="20" colonnes="2" titres="oui" niveau="2" class="ma-classe"]
 *
 * Filtres : flib_seo_plan_site_args (arguments), flib_seo_plan_site (HTML final).
 *
 * @package FlibSEO
 */

defined( 'ABSPATH' ) || exit;

final class Flib_SEO_Plan_Site {

	const BLOC      = 'flib-seo/plan-site';
	const SHORTCODE = 'flib_plan_site';
	const MASQUE    = 'plan_masque';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'enregistrer' ) );
	}

	public static function enregistrer() {
		wp_register_style( 'flib-seo-plan-site', FLIB_SEO_URL . 'assets/css/plan-site.css', array(), FLIB_SEO_VERSION );
		wp_register_script(
			'flib-seo-plan-site-editeur',
			FLIB_SEO_URL . 'assets/js/plan-site-bloc.js',
			array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-server-side-render', 'wp-i18n' ),
			FLIB_SEO_VERSION,
			true
		);
		register_block_type( FLIB_SEO_DIR . 'blocks/plan-site', array( 'render_callback' => array( __CLASS__, 'rendu_bloc' ) ) );
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'shortcode' ) );
	}

	/** Tris proposés. */
	public static function tris() {
		return array(
			'defaut' => __( 'Ordre des pages, puis plus récents d’abord', 'flib-seo' ),
			'titre'  => __( 'Alphabétique', 'flib-seo' ),
			'date'   => __( 'Date de publication (récents d’abord)', 'flib-seo' ),
			'modif'  => __( 'Dernière mise à jour (récents d’abord)', 'flib-seo' ),
		);
	}

	/* ------------------------------------------------------------------ */
	/* Arguments                                                           */
	/* ------------------------------------------------------------------ */

	private static function liste( $valeur ) {
		$valeur = is_array( $valeur ) ? $valeur : explode( ',', (string) $valeur );
		return array_values( array_filter( array_map( 'sanitize_key', array_map( 'trim', $valeur ) ) ) );
	}

	private static function oui( $valeur ) {
		return is_bool( $valeur ) ? $valeur : in_array( strtolower( trim( (string) $valeur ) ), array( '1', 'oui', 'true', 'yes' ), true );
	}

	/** Arguments complets : valeurs fournies, sinon réglages. */
	public static function args( $fournis = array() ) {
		$o    = 'plan';
		$args = array();
		foreach ( array( 'types', 'taxos', 'exclure', 'articles', 'colonnes', 'titres', 'ordre', 'tri', 'regrouper', 'exclure_termes', 'libelles' ) as $cle ) {
			$args[ 'taxos' === $cle ? 'taxonomies' : $cle ] = Flib_SEO_Options::get( $o, $cle );
		}
		$args['niveau'] = 2;
		foreach ( (array) $fournis as $cle => $val ) {
			if ( null !== $val && '' !== $val && array_key_exists( $cle, $args ) ) {
				$args[ $cle ] = $val;
			}
		}

		$exclus_types       = (array) Flib_SEO_Options::get( 'indexation', 'types_noindex' );
		$exclus_taxos       = (array) Flib_SEO_Options::get( 'indexation', 'taxos_noindex' );
		$args['types']      = array_values( array_diff( array_intersect( self::liste( $args['types'] ), array_keys( Flib_SEO_Options::types() ) ), $exclus_types ) );
		$args['taxonomies'] = array_values( array_diff( array_intersect( self::liste( $args['taxonomies'] ), array_keys( Flib_SEO_Options::taxonomies() ) ), $exclus_taxos ) );
		$args['exclure']    = array_values( array_filter( array_map( 'absint', preg_split( '/[\s,;]+/', (string) $args['exclure'] ) ) ) );
		$args['articles']   = max( 1, min( 500, (int) $args['articles'] ) );
		$args['colonnes']   = max( 1, min( 4, (int) $args['colonnes'] ) );
		$args['titres']     = self::oui( $args['titres'] );
		$args['regrouper']  = self::oui( $args['regrouper'] ) || 'categorie' === $args['regrouper'];
		$args['tri']        = isset( self::tris()[ $args['tri'] ] ) ? $args['tri'] : 'defaut';
		$args['niveau']     = max( 2, min( 6, (int) $args['niveau'] ) );

		// Termes exclus : slugs ou identifiants, dans n'importe quelle taxonomie publique.
		$termes = array();
		foreach ( array_filter( array_map( 'trim', explode( ',', (string) $args['exclure_termes'] ) ) ) as $ref ) {
			foreach ( array_keys( Flib_SEO_Options::taxonomies() ) as $tax ) {
				$t = ctype_digit( $ref ) ? get_term( (int) $ref, $tax ) : get_term_by( 'slug', sanitize_title( $ref ), $tax );
				if ( $t && ! is_wp_error( $t ) ) {
					$termes[ $t->term_id ] = $t;
				}
			}
		}
		$args['exclure_termes'] = $termes;

		// Libellés : « cle:Libellé » séparés par des virgules.
		$libelles = array();
		foreach ( array_filter( array_map( 'trim', explode( ',', (string) $args['libelles'] ) ) ) as $paire ) {
			if ( str_contains( $paire, ':' ) ) {
				[ $cle, $lib ]                     = array_map( 'trim', explode( ':', $paire, 2 ) );
				$libelles[ sanitize_key( $cle ) ] = sanitize_text_field( $lib );
			}
		}
		$args['libelles'] = $libelles;

		// Ordre des rubriques : celles citées d'abord, puis les autres dans l'ordre types → taxonomies.
		$rubriques = array_merge( $args['types'], $args['taxonomies'] );
		$ordre     = array_values( array_intersect( self::liste( $args['ordre'] ), $rubriques ) );
		$args['rubriques'] = array_values( array_unique( array_merge( $ordre, $rubriques ) ) );

		return apply_filters( 'flib_seo_plan_site_args', $args );
	}

	/* ------------------------------------------------------------------ */
	/* Construction                                                        */
	/* ------------------------------------------------------------------ */

	/** Liste imbriquée à partir d'éléments array( id, parent, titre, url ). */
	private static function arbre( $elements, $parent = 0 ) {
		$html = '';
		foreach ( $elements as $el ) {
			if ( (int) $el['parent'] !== (int) $parent ) {
				continue;
			}
			$enfants = self::arbre( $elements, $el['id'] );
			$html   .= '<li><a href="' . esc_url( $el['url'] ) . '">' . esc_html( $el['titre'] ) . '</a>' . ( '' !== $enfants ? '<ul>' . $enfants . '</ul>' : '' ) . '</li>';
		}
		return $html;
	}

	/** Remonte au niveau supérieur les éléments dont le parent est absent (exclu ou masqué). */
	private static function rattacher( $elements ) {
		$ids = wp_list_pluck( $elements, 'id' );
		foreach ( $elements as &$el ) {
			if ( $el['parent'] && ! in_array( $el['parent'], $ids, true ) ) {
				$el['parent'] = 0;
			}
		}
		return $elements;
	}

	/** Taxonomie de regroupement d'un type : catégories pour les articles, sinon la première taxonomie hiérarchique publique. */
	private static function taxonomie_principale( $type ) {
		if ( 'post' === $type ) {
			return 'category';
		}
		foreach ( get_object_taxonomies( $type, 'objects' ) as $tax ) {
			if ( $tax->public && $tax->hierarchical && isset( Flib_SEO_Options::taxonomies()[ $tax->name ] ) ) {
				return $tax->name;
			}
		}
		return '';
	}

	private static function requete( $type, $args, $hier ) {
		$tris = array(
			'titre' => array( 'title' => 'ASC' ),
			'date'  => array( 'date' => 'DESC' ),
			'modif' => array( 'modified' => 'DESC' ),
		);
		$q = array(
			'post_type'        => $type,
			'post_status'      => 'publish',
			'posts_per_page'   => $hier ? 500 : $args['articles'],
			'orderby'          => $tris[ $args['tri'] ] ?? ( $hier ? array( 'menu_order' => 'ASC', 'title' => 'ASC' ) : array( 'date' => 'DESC' ) ),
			'has_password'     => false,
			'no_found_rows'    => true,
			'post__not_in'     => $args['exclure'], // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in -- liste courte saisie dans les réglages.
			'meta_query'       => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'relation' => 'AND',
				Flib_SEO_Options::clause_indexables(),
				array(
					'key'     => Flib_SEO_Options::META . self::MASQUE,
					'compare' => 'NOT EXISTS',
				),
			),
			'suppress_filters' => false,
		);
		$taxos_type = get_object_taxonomies( $type );
		$exclus     = array();
		foreach ( $args['exclure_termes'] as $t ) {
			if ( in_array( $t->taxonomy, $taxos_type, true ) ) {
				$exclus[ $t->taxonomy ][] = $t->term_id;
			}
		}
		if ( $exclus ) {
			$q['tax_query'] = array( 'relation' => 'AND' ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			foreach ( $exclus as $tax => $ids ) {
				$q['tax_query'][] = array(
					'taxonomy'         => $tax,
					'terms'            => $ids,
					'operator'         => 'NOT IN',
					'include_children' => true,
				);
			}
		}
		return get_posts( $q );
	}

	private static function element( $p, $hier ) {
		return array(
			'id'     => (int) $p->ID,
			'parent' => $hier ? (int) $p->post_parent : 0,
			'titre'  => wp_strip_all_tags( get_the_title( $p ) ),
			'url'    => get_permalink( $p ),
		);
	}

	private static function contenus( $type, $args ) {
		$obj   = get_post_type_object( $type );
		$hier  = $obj && $obj->hierarchical;
		$posts = self::requete( $type, $args, $hier );
		if ( ! $posts ) {
			return '';
		}
		$tax = ( ! $hier && $args['regrouper'] ) ? self::taxonomie_principale( $type ) : '';
		if ( '' === $tax ) {
			return self::arbre( self::rattacher( array_map( static fn( $p ) => self::element( $p, $hier ), $posts ) ) );
		}

		// Regroupement par terme principal (premier terme du contenu).
		$groupes = array();
		$autres  = array();
		foreach ( $posts as $p ) {
			$termes = get_the_terms( $p, $tax );
			if ( $termes && ! is_wp_error( $termes ) ) {
				$groupes[ $termes[0]->term_id ][] = self::element( $p, false );
			} else {
				$autres[] = self::element( $p, false );
			}
		}
		$html = '';
		$liste_termes = get_terms(
			array(
				'taxonomy' => $tax,
				'include'  => array_keys( $groupes ) ? array_keys( $groupes ) : array( 0 ),
				'orderby'  => 'name',
			)
		);
		foreach ( is_wp_error( $liste_termes ) ? array() : $liste_termes as $t ) {
			$lien  = Flib_SEO_Contexte::term_noindex( $t ) ? '' : get_term_link( $t );
			$titre = esc_html( $t->name );
			$html .= '<li class="flib-plan-site__terme">' . ( $lien && ! is_wp_error( $lien ) ? '<a href="' . esc_url( $lien ) . '">' . $titre . '</a>' : '<span>' . $titre . '</span>' )
				. '<ul>' . self::arbre( $groupes[ $t->term_id ] ) . '</ul></li>';
		}
		if ( $autres ) {
			$html .= '<li class="flib-plan-site__terme"><span>' . esc_html__( 'Autres', 'flib-seo' ) . '</span><ul>' . self::arbre( $autres ) . '</ul></li>';
		}
		return $html;
	}

	private static function termes( $taxonomie, $args ) {
		$termes = get_terms(
			array(
				'taxonomy'   => $taxonomie,
				'hide_empty' => true,
				'exclude'    => array_keys( $args['exclure_termes'] ),
				'orderby'    => 'name',
				'meta_query' => array( Flib_SEO_Options::clause_indexables() ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			)
		);
		if ( is_wp_error( $termes ) ) {
			return '';
		}
		$elements = array();
		foreach ( $termes as $t ) {
			$elements[] = array(
				'id'     => (int) $t->term_id,
				'parent' => (int) $t->parent,
				'titre'  => $t->name,
				'url'    => get_term_link( $t ),
			);
		}
		return self::arbre( self::rattacher( $elements ) );
	}

	/** Nom d'une rubrique : libellé personnalisé, sinon nom du type ou de la taxonomie. */
	private static function libelle( $cle, $args ) {
		if ( ! empty( $args['libelles'][ $cle ] ) ) {
			return $args['libelles'][ $cle ];
		}
		if ( 'post' === $cle ) {
			return __( 'Articles', 'flib-seo' );
		}
		$obj = post_type_exists( $cle ) ? get_post_type_object( $cle ) : get_taxonomy( $cle );
		return $obj ? $obj->labels->name : $cle;
	}

	/**
	 * HTML du plan du site.
	 *
	 * @param array  $fournis   Arguments (voir l'en-tête du fichier).
	 * @param string $attributs Attributs HTML du conteneur (déjà échappés).
	 */
	public static function html( $fournis = array(), $attributs = 'class="flib-plan-site"' ) {
		$args    = self::args( $fournis );
		$h       = 'h' . $args['niveau'];
		$groupes = '';
		foreach ( $args['rubriques'] as $cle ) {
			$liste = in_array( $cle, $args['types'], true ) ? self::contenus( $cle, $args ) : self::termes( $cle, $args );
			if ( '' === $liste ) {
				continue;
			}
			$groupes .= '<div class="flib-plan-site__groupe flib-plan-site__groupe--' . esc_attr( $cle ) . '">'
				. ( $args['titres'] ? '<' . $h . ' class="flib-plan-site__titre">' . esc_html( self::libelle( $cle, $args ) ) . '</' . $h . '>' : '' )
				. '<ul class="flib-plan-site__liste">' . $liste . '</ul></div>';
		}
		if ( '' === $groupes ) {
			return '';
		}
		wp_enqueue_style( 'flib-seo-plan-site' );
		$html = '<nav ' . $attributs . ' style="--flib-plan-colonnes:' . (int) $args['colonnes'] . '" aria-label="' . esc_attr__( 'Plan du site', 'flib-seo' ) . '">' . $groupes . '</nav>';
		return apply_filters( 'flib_seo_plan_site', $html, $args );
	}

	/* ------------------------------------------------------------------ */
	/* Bloc et shortcode                                                   */
	/* ------------------------------------------------------------------ */

	public static function rendu_bloc( $attrs ) {
		$choix = static fn( $cle ) => isset( $attrs[ $cle ] ) && 'defaut' !== $attrs[ $cle ] && '' !== $attrs[ $cle ] ? 'oui' === $attrs[ $cle ] : null;
		return self::html(
			array(
				'types'          => $attrs['types'] ?? '',
				'taxonomies'     => $attrs['taxonomies'] ?? '',
				'ordre'          => $attrs['ordre'] ?? '',
				'tri'            => $attrs['tri'] ?? '',
				'exclure'        => $attrs['exclure'] ?? '',
				'exclure_termes' => $attrs['exclureTermes'] ?? '',
				'libelles'       => $attrs['libelles'] ?? '',
				'colonnes'       => $attrs['colonnes'] ?? '',
				'titres'         => $choix( 'titres' ),
				'regrouper'      => $choix( 'regrouper' ),
			),
			get_block_wrapper_attributes( array( 'class' => 'flib-plan-site' ) )
		);
	}

	public static function shortcode( $atts ) {
		$atts    = shortcode_atts(
			array(
				'types'          => '',
				'taxonomies'     => '',
				'ordre'          => '',
				'regrouper'      => '',
				'tri'            => '',
				'exclure'        => '',
				'exclure_termes' => '',
				'libelles'       => '',
				'articles'       => '',
				'colonnes'       => '',
				'titres'         => '',
				'niveau'         => '',
				'class'          => '',
			),
			$atts,
			self::SHORTCODE
		);
		$classes = trim( 'flib-plan-site ' . implode( ' ', array_map( 'sanitize_html_class', preg_split( '/\s+/', $atts['class'] ) ) ) );
		unset( $atts['class'] );
		// « non » doit l'emporter sur le réglage : on le transmet tel quel (args() le lit).
		return self::html( $atts, 'class="' . esc_attr( $classes ) . '"' );
	}
}
