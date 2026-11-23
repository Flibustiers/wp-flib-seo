<?php
/**
 * Calcule le titre, la description, l'image, l'URL canonique et l'indexation de la page visitée.
 * Utilisé par le <head>, les données structurées, le llms.txt et l'aperçu Google de l'administration.
 *
 * Variables des modèles de titre : %titre% %site% %sep% %slogan% %recherche% %categorie% %annee%.
 *
 * @package FlibSEO
 */

defined( 'ABSPATH' ) || exit;

final class Flib_SEO_Contexte {

	private static $memo = array();

	/** Méta exposées à l'API REST pour le panneau de l'éditeur de blocs. */
	public static function enregistrer_meta() {
		$types = array(
			'titre'     => 'string',
			'desc'      => 'string',
			'noindex'   => 'string',
			'canonique' => 'string',
			'image'     => 'integer',
		);
		foreach ( array_keys( Flib_SEO_Options::types() ) as $type ) {
			foreach ( $types as $cle => $t ) {
				register_post_meta(
					$type,
					Flib_SEO_Options::META . $cle,
					array(
						'type'              => $t,
						'single'            => true,
						'show_in_rest'      => true,
						'sanitize_callback' => 'image' === $cle ? 'absint' : ( 'desc' === $cle ? 'sanitize_textarea_field' : ( 'canonique' === $cle ? 'esc_url_raw' : 'sanitize_text_field' ) ),
						'auth_callback'     => static fn( $permis, $cle_meta, $post_id ) => current_user_can( 'edit_post', $post_id ),
					)
				);
			}
		}
	}

	/* ------------------------------------------------------------------ */
	/* Variables                                                           */
	/* ------------------------------------------------------------------ */

	public static function remplacer( $modele, $vars = array() ) {
		$vars = array_merge(
			array(
				'%site%'      => get_bloginfo( 'name' ),
				'%slogan%'    => get_bloginfo( 'description' ),
				'%sep%'       => (string) Flib_SEO_Options::get( 'general', 'sep' ),
				'%annee%'     => wp_date( 'Y' ),
				'%titre%'     => '',
				'%recherche%' => get_search_query( false ),
				'%categorie%' => '',
			),
			$vars
		);
		$texte = strtr( (string) $modele, $vars );
		// Séparateur orphelin (slogan vide, etc.) et espaces multiples.
		$sep   = preg_quote( $vars['%sep%'], '/' );
		$texte = preg_replace( '/^\s*' . $sep . '\s*|\s*' . $sep . '\s*$/u', '', trim( $texte ) );
		return trim( preg_replace( '/\s{2,}/u', ' ', $texte ) );
	}

	/** Variables propres à un contenu. */
	public static function vars_post( $post ) {
		$cat = '';
		if ( 'post' === $post->post_type ) {
			$cats = get_the_category( $post->ID );
			$cat  = $cats ? $cats[0]->name : '';
		}
		return array(
			'%titre%'     => wp_strip_all_tags( get_the_title( $post ) ),
			'%categorie%' => $cat,
		);
	}

	/* ------------------------------------------------------------------ */
	/* Titre                                                               */
	/* ------------------------------------------------------------------ */

	/** Titre par défaut d'un contenu (sans personnalisation). Sert aussi de texte indicatif dans l'éditeur. */
	public static function titre_defaut_post( $post ) {
		if ( (int) get_option( 'page_on_front' ) === (int) $post->ID && 'page' === get_option( 'show_on_front' ) ) {
			return self::remplacer( Flib_SEO_Options::get( 'general', 'titre_accueil' ), self::vars_post( $post ) );
		}
		return self::remplacer( Flib_SEO_Options::get( 'general', 'titre_contenu' ), self::vars_post( $post ) );
	}

	public static function titre_post( $post ) {
		$perso = (string) Flib_SEO_Options::post_meta( $post->ID, 'titre' );
		return '' !== trim( $perso ) ? self::remplacer( $perso, self::vars_post( $post ) ) : self::titre_defaut_post( $post );
	}

	public static function titre() {
		if ( isset( self::$memo['titre'] ) ) {
			return self::$memo['titre'];
		}
		$o     = 'general';
		$titre = '';
		if ( is_front_page() && ! is_singular() ) {
			$titre = self::remplacer( Flib_SEO_Options::get( $o, 'titre_accueil' ) );
		} elseif ( is_singular() || ( is_home() && get_option( 'page_for_posts' ) ) ) {
			$post  = is_home() ? get_post( (int) get_option( 'page_for_posts' ) ) : get_queried_object();
			$titre = $post instanceof WP_Post ? self::titre_post( $post ) : '';
		} elseif ( is_category() || is_tag() || is_tax() ) {
			$term  = get_queried_object();
			$perso = (string) Flib_SEO_Options::term_meta( $term->term_id, 'titre' );
			$vars  = array( '%titre%' => $term->name );
			$titre = self::remplacer( '' !== trim( $perso ) ? $perso : Flib_SEO_Options::get( $o, 'titre_archive' ), $vars );
		} elseif ( is_post_type_archive() ) {
			$titre = self::remplacer( Flib_SEO_Options::get( $o, 'titre_archive' ), array( '%titre%' => post_type_archive_title( '', false ) ) );
		} elseif ( is_author() ) {
			$titre = self::remplacer( Flib_SEO_Options::get( $o, 'titre_archive' ), array( '%titre%' => get_the_author_meta( 'display_name', (int) get_query_var( 'author' ) ) ) );
		} elseif ( is_date() ) {
			$titre = self::remplacer( Flib_SEO_Options::get( $o, 'titre_archive' ), array( '%titre%' => wp_strip_all_tags( get_the_archive_title() ) ) );
		} elseif ( is_search() ) {
			$titre = self::remplacer( Flib_SEO_Options::get( $o, 'titre_recherche' ) );
		} elseif ( is_404() ) {
			$titre = self::remplacer( Flib_SEO_Options::get( $o, 'titre_404' ) );
		}

		$page = max( (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) );
		if ( '' !== $titre && $page > 1 ) {
			/* translators: %d: numéro de page. */
			$titre .= ' ' . Flib_SEO_Options::get( $o, 'sep' ) . ' ' . sprintf( __( 'Page %d', 'flib-seo' ), $page );
		}
		return self::$memo['titre'] = apply_filters( 'flib_seo_titre', $titre );
	}

	/* ------------------------------------------------------------------ */
	/* Description                                                         */
	/* ------------------------------------------------------------------ */

	/** Extrait automatique : extrait saisi, sinon début du contenu. */
	public static function desc_auto_post( $post ) {
		if ( ! Flib_SEO_Options::get( 'general', 'desc_auto' ) ) {
			return '';
		}
		$texte = has_excerpt( $post ) ? $post->post_excerpt : $post->post_content;
		$texte = excerpt_remove_blocks( strip_shortcodes( $texte ) );
		$texte = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $texte ) ) );
		return self::couper( $texte, 155 );
	}

	public static function couper( $texte, $max ) {
		if ( mb_strlen( $texte ) <= $max ) {
			return $texte;
		}
		$coupe = mb_substr( $texte, 0, $max );
		$coupe = preg_replace( '/\s+\S*$/u', '', $coupe );
		return rtrim( $coupe, " ,;:-–" ) . '…';
	}

	public static function desc_post( $post ) {
		$perso = trim( (string) Flib_SEO_Options::post_meta( $post->ID, 'desc' ) );
		return '' !== $perso ? self::remplacer( $perso, self::vars_post( $post ) ) : self::desc_auto_post( $post );
	}

	public static function description() {
		if ( isset( self::$memo['desc'] ) ) {
			return self::$memo['desc'];
		}
		$desc = '';
		if ( is_front_page() ) {
			$accueil = trim( (string) Flib_SEO_Options::get( 'general', 'desc_accueil' ) );
			if ( is_singular() ) {
				$perso = trim( (string) Flib_SEO_Options::post_meta( get_queried_object_id(), 'desc' ) );
				$desc  = '' !== $perso ? $perso : ( '' !== $accueil ? $accueil : self::desc_post( get_queried_object() ) );
			} else {
				$desc = '' !== $accueil ? $accueil : get_bloginfo( 'description' );
			}
		} elseif ( is_singular() || ( is_home() && get_option( 'page_for_posts' ) ) ) {
			$post = is_home() ? get_post( (int) get_option( 'page_for_posts' ) ) : get_queried_object();
			$desc = $post instanceof WP_Post ? self::desc_post( $post ) : '';
		} elseif ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();
			$desc = trim( (string) Flib_SEO_Options::term_meta( $term->term_id, 'desc' ) );
			if ( '' === $desc ) {
				$desc = self::couper( trim( wp_strip_all_tags( term_description( $term ) ) ), 155 );
			}
		} elseif ( is_post_type_archive() ) {
			$pto  = get_queried_object();
			$desc = $pto && ! empty( $pto->description ) ? self::couper( wp_strip_all_tags( $pto->description ), 155 ) : '';
		}
		return self::$memo['desc'] = apply_filters( 'flib_seo_description', $desc );
	}

	/* ------------------------------------------------------------------ */
	/* Indexation et URL canonique                                         */
	/* ------------------------------------------------------------------ */

	public static function post_noindex( $post ) {
		if ( Flib_SEO_Options::post_meta( $post->ID, 'noindex' ) ) {
			return true;
		}
		return in_array( $post->post_type, (array) Flib_SEO_Options::get( 'indexation', 'types_noindex' ), true );
	}

	public static function term_noindex( $term ) {
		if ( Flib_SEO_Options::term_meta( $term->term_id, 'noindex' ) ) {
			return true;
		}
		return in_array( $term->taxonomy, (array) Flib_SEO_Options::get( 'indexation', 'taxos_noindex' ), true );
	}

	public static function noindex() {
		if ( is_search() || is_404() ) {
			return true;
		}
		if ( is_singular() ) {
			return self::post_noindex( get_queried_object() );
		}
		if ( is_home() && get_option( 'page_for_posts' ) ) {
			$p = get_post( (int) get_option( 'page_for_posts' ) );
			return $p ? self::post_noindex( $p ) : false;
		}
		if ( is_category() || is_tag() || is_tax() ) {
			return self::term_noindex( get_queried_object() );
		}
		if ( is_author() ) {
			return ! Flib_SEO_Options::get( 'indexation', 'archives_auteur' );
		}
		if ( is_date() ) {
			return ! Flib_SEO_Options::get( 'indexation', 'archives_date' );
		}
		return false;
	}

	public static function canonique() {
		if ( is_search() || is_404() || self::noindex() ) {
			return '';
		}
		$url = '';
		if ( is_singular() ) {
			$perso = trim( (string) Flib_SEO_Options::post_meta( get_queried_object_id(), 'canonique' ) );
			$url   = '' !== $perso ? $perso : wp_get_canonical_url( get_queried_object() );
		} elseif ( is_front_page() ) {
			$url = home_url( '/' );
		} elseif ( is_home() && get_option( 'page_for_posts' ) ) {
			$url = get_permalink( (int) get_option( 'page_for_posts' ) );
		} elseif ( is_category() || is_tag() || is_tax() ) {
			$url = get_term_link( get_queried_object() );
		} elseif ( is_post_type_archive() ) {
			$pt  = get_query_var( 'post_type' );
			$url = get_post_type_archive_link( is_array( $pt ) ? (string) reset( $pt ) : (string) $pt );
		}
		if ( is_wp_error( $url ) || ! $url ) {
			return '';
		}
		$page = (int) get_query_var( 'paged' );
		if ( $page > 1 && ! is_singular() ) {
			$url = trailingslashit( $url ) . user_trailingslashit( 'page/' . $page, 'paged' );
		}
		return apply_filters( 'flib_seo_canonique', $url );
	}

	/* ------------------------------------------------------------------ */
	/* Image de partage                                                    */
	/* ------------------------------------------------------------------ */

	/** Identifiant de pièce jointe de l'image de partage (0 si aucune). */
	public static function image_id() {
		$id = 0;
		if ( is_singular() ) {
			$pid = get_queried_object_id();
			$id  = (int) Flib_SEO_Options::post_meta( $pid, 'image' );
			if ( ! $id ) {
				$id = (int) get_post_thumbnail_id( $pid );
			}
		}
		if ( ! $id ) {
			$id = (int) Flib_SEO_Options::get( 'general', 'image_defaut' );
		}
		if ( ! $id ) {
			$id = (int) Flib_SEO_Options::get( 'entreprise', 'logo' );
		}
		if ( ! $id ) {
			$id = (int) get_theme_mod( 'custom_logo' );
		}
		return (int) apply_filters( 'flib_seo_image_id', $id );
	}

	/** Logo : réglage de l'extension, sinon logo du site, sinon icône du site. */
	public static function logo_id() {
		$id = (int) Flib_SEO_Options::get( 'entreprise', 'logo' );
		if ( ! $id ) {
			$id = (int) get_theme_mod( 'custom_logo' );
		}
		if ( ! $id ) {
			$id = (int) get_option( 'site_icon' );
		}
		return $id;
	}
}
