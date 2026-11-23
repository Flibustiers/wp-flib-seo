<?php
/**
 * Fil d'Ariane : construction du chemin, bloc « Fil d'Ariane » et shortcode [flib_fil_ariane].
 *
 * - Bloc (thèmes à blocs comme flib-base) : à poser une fois dans les gabarits (page, article…).
 *   Couleurs, typographie et marges se règlent dans la barre latérale de l'éditeur.
 * - Shortcode, pour les autres cas : [flib_fil_ariane sep="/" accueil="Accueil" courant="non" class="ma-classe"].
 * - Réglages par défaut : onglet « Fil d'Ariane ». Le balisage BreadcrumbList est ajouté
 *   aux données structurées sur toutes les pages, que le fil soit affiché ou non.
 *
 * Filtre : flib_seo_fil_ariane (tableau d'éléments array( 'titre' => …, 'url' => … )).
 *
 * @package FlibSEO
 */

defined( 'ABSPATH' ) || exit;

final class Flib_SEO_Fil_Ariane {

	const BLOC      = 'flib-seo/fil-ariane';
	const SHORTCODE = 'flib_fil_ariane';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'enregistrer' ) );
	}

	public static function enregistrer() {
		wp_register_style( 'flib-seo-fil-ariane', FLIB_SEO_URL . 'assets/css/fil-ariane.css', array(), FLIB_SEO_VERSION );
		wp_register_script(
			'flib-seo-fil-ariane-editeur',
			FLIB_SEO_URL . 'assets/js/fil-ariane-bloc.js',
			array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-server-side-render', 'wp-i18n' ),
			FLIB_SEO_VERSION,
			true
		);
		register_block_type(
			FLIB_SEO_DIR . 'blocks/fil-ariane',
			array( 'render_callback' => array( __CLASS__, 'rendu_bloc' ) )
		);
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'shortcode' ) );
	}

	/* ------------------------------------------------------------------ */
	/* Chemin                                                              */
	/* ------------------------------------------------------------------ */

	private static function el( $titre, $url = '' ) {
		return array(
			'titre' => wp_strip_all_tags( (string) $titre ),
			'url'   => $url ? (string) $url : '',
		);
	}

	/** Page des articles (si définie et activée dans les réglages). */
	private static function page_articles() {
		$id = (int) get_option( 'page_for_posts' );
		if ( $id && Flib_SEO_Options::get( 'ariane', 'page_articles' ) ) {
			return self::el( get_the_title( $id ), get_permalink( $id ) );
		}
		return null;
	}

	private static function ancetres_termes( $term ) {
		$els = array();
		foreach ( array_reverse( get_ancestors( $term->term_id, $term->taxonomy, 'taxonomy' ) ) as $id ) {
			$t = get_term( $id, $term->taxonomy );
			if ( $t && ! is_wp_error( $t ) ) {
				$els[] = self::el( $t->name, get_term_link( $t ) );
			}
		}
		return $els;
	}

	/**
	 * Éléments du fil pour la page visitée (le dernier est la page courante, avec son URL).
	 *
	 * @param string $accueil Libellé de l'accueil (vide = réglage).
	 */
	public static function elements( $accueil = null ) {
		$accueil = null === $accueil ? (string) Flib_SEO_Options::get( 'ariane', 'accueil' ) : $accueil;
		$els     = array( self::el( '' !== $accueil ? $accueil : get_bloginfo( 'name' ), home_url( '/' ) ) );

		if ( is_front_page() ) {
			return $els;
		}

		if ( is_home() ) {
			$p = self::page_articles();
			if ( $p ) {
				$els[] = $p;
			}
		} elseif ( is_singular() ) {
			$post = get_queried_object();
			if ( 'post' === $post->post_type ) {
				$p = self::page_articles();
				if ( $p ) {
					$els[] = $p;
				}
				$cats = get_the_category( $post->ID );
				if ( $cats ) {
					$els   = array_merge( $els, self::ancetres_termes( $cats[0] ) );
					$els[] = self::el( $cats[0]->name, get_term_link( $cats[0] ) );
				}
			} elseif ( 'page' !== $post->post_type ) {
				$pto = get_post_type_object( $post->post_type );
				if ( $pto && $pto->has_archive ) {
					$els[] = self::el( $pto->labels->name, get_post_type_archive_link( $post->post_type ) );
				}
			}
			foreach ( array_reverse( get_post_ancestors( $post ) ) as $id ) {
				$els[] = self::el( get_the_title( $id ), get_permalink( $id ) );
			}
			$els[] = self::el( get_the_title( $post ), get_permalink( $post ) );
		} elseif ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();
			$tax  = get_taxonomy( $term->taxonomy );
			if ( $tax && in_array( 'post', (array) $tax->object_type, true ) ) {
				$p = self::page_articles();
				if ( $p ) {
					$els[] = $p;
				}
			}
			$els   = array_merge( $els, self::ancetres_termes( $term ) );
			$els[] = self::el( $term->name, get_term_link( $term ) );
		} elseif ( is_post_type_archive() ) {
			$pt    = get_query_var( 'post_type' );
			$els[] = self::el( post_type_archive_title( '', false ), get_post_type_archive_link( is_array( $pt ) ? (string) reset( $pt ) : (string) $pt ) );
		} elseif ( is_search() ) {
			/* translators: %s: terme recherché. */
			$els[] = self::el( sprintf( __( 'Recherche : %s', 'flib-seo' ), get_search_query( false ) ) );
		} elseif ( is_404() ) {
			$els[] = self::el( __( 'Page introuvable', 'flib-seo' ) );
		} elseif ( is_author() || is_date() ) {
			$els[] = self::el( get_the_archive_title() );
		}

		return apply_filters( 'flib_seo_fil_ariane', $els );
	}

	/** Exemple affiché dans l'éditeur et dans les réglages. */
	private static function exemple( $accueil ) {
		return array(
			self::el( '' !== $accueil ? $accueil : get_bloginfo( 'name' ), home_url( '/' ) ),
			self::el( __( 'Rubrique', 'flib-seo' ), home_url( '/' ) ),
			self::el( __( 'Page visitée', 'flib-seo' ) ),
		);
	}

	/* ------------------------------------------------------------------ */
	/* Rendu                                                               */
	/* ------------------------------------------------------------------ */

	/**
	 * HTML du fil.
	 *
	 * @param array  $args sep, accueil, courant (bool), prefixe, exemple (bool).
	 * @param string $attributs Attributs HTML supplémentaires du <nav> (déjà échappés).
	 */
	public static function html( $args = array(), $attributs = '' ) {
		$o    = 'ariane';
		$args = wp_parse_args(
			array_filter( $args, static fn( $v ) => null !== $v && '' !== $v ),
			array(
				'sep'     => (string) Flib_SEO_Options::get( $o, 'sep' ),
				'accueil' => (string) Flib_SEO_Options::get( $o, 'accueil' ),
				'courant' => (bool) Flib_SEO_Options::get( $o, 'courant' ),
				'prefixe' => (string) Flib_SEO_Options::get( $o, 'prefixe' ),
				'exemple' => false,
			)
		);
		$els = $args['exemple'] ? self::exemple( $args['accueil'] ) : self::elements( $args['accueil'] );
		if ( count( $els ) < 2 ) {
			return '';
		}
		if ( ! $args['courant'] ) {
			array_pop( $els );
		}
		wp_enqueue_style( 'flib-seo-fil-ariane' );

		$dernier = count( $els ) - 1;
		$items   = '';
		foreach ( $els as $i => $el ) {
			$sep    = $i > 0 ? '<span class="flib-fil-ariane__sep" aria-hidden="true">' . esc_html( $args['sep'] ) . '</span>' : '';
			$actuel = $i === $dernier && $args['courant'];
			if ( $actuel || '' === $el['url'] ) {
				$contenu = '<span' . ( $actuel ? ' aria-current="page"' : '' ) . '>' . esc_html( $el['titre'] ) . '</span>';
			} else {
				$contenu = '<a href="' . esc_url( $el['url'] ) . '">' . esc_html( $el['titre'] ) . '</a>';
			}
			$items .= '<li class="flib-fil-ariane__item">' . $sep . $contenu . '</li>';
		}
		$prefixe = '' !== $args['prefixe'] ? '<span class="flib-fil-ariane__prefixe">' . esc_html( $args['prefixe'] ) . '</span> ' : '';

		return '<nav ' . $attributs . ' aria-label="' . esc_attr__( 'Fil d’Ariane', 'flib-seo' ) . '">' . $prefixe . '<ol class="flib-fil-ariane__liste">' . $items . '</ol></nav>';
	}

	public static function rendu_bloc( $attrs ) {
		$courant = $attrs['courant'] ?? 'defaut';
		// Aperçu dans l'éditeur (rendu par l'API REST, hors de toute page visitée) : chemin d'exemple.
		$exemple = ! did_action( 'template_redirect' );
		return self::html(
			array(
				'sep'     => $attrs['separateur'] ?? '',
				'accueil' => $attrs['accueil'] ?? '',
				'prefixe' => $attrs['prefixe'] ?? '',
				'courant' => 'defaut' === $courant ? null : 'oui' === $courant,
				'exemple' => $exemple,
			),
			get_block_wrapper_attributes( array( 'class' => 'flib-fil-ariane' ) )
		);
	}

	public static function shortcode( $atts ) {
		$atts    = shortcode_atts(
			array(
				'sep'     => '',
				'accueil' => '',
				'courant' => '',
				'prefixe' => '',
				'class'   => '',
			),
			$atts,
			self::SHORTCODE
		);
		$classes = trim( 'flib-fil-ariane ' . implode( ' ', array_map( 'sanitize_html_class', preg_split( '/\s+/', $atts['class'] ) ) ) );
		return self::html(
			array(
				'sep'     => $atts['sep'],
				'accueil' => $atts['accueil'],
				'prefixe' => $atts['prefixe'],
				'courant' => '' === $atts['courant'] ? null : in_array( strtolower( $atts['courant'] ), array( 'oui', '1', 'true', 'yes' ), true ),
			),
			'class="' . esc_attr( $classes ) . '"'
		);
	}

	/** Aperçu pour la page de réglages. */
	public static function apercu() {
		return self::html( array( 'exemple' => true ), 'class="flib-fil-ariane"' );
	}
}
