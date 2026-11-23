<?php
/**
 * Import des données Yoast SEO : titres, descriptions, noindex, canoniques et images de partage
 * des contenus et des termes, titre et description de l'accueil.
 *
 * Fonctionne que Yoast soit actif ou non (ses données restent en base après désactivation).
 * N'écrase jamais une valeur déjà saisie dans Flib'SEO. Les variables Yoast (%%title%%…) sont
 * converties ; celles sans équivalent sont retirées.
 *
 * @package FlibSEO
 */

defined( 'ABSPATH' ) || exit;

final class Flib_SEO_Import_Yoast {

	/** Méta Yoast => méta Flib'SEO. */
	const POSTS = array(
		'_yoast_wpseo_title'                 => 'titre',
		'_yoast_wpseo_metadesc'              => 'desc',
		'_yoast_wpseo_meta-robots-noindex'   => 'noindex',
		'_yoast_wpseo_canonical'             => 'canonique',
		'_yoast_wpseo_opengraph-image-id'    => 'image',
	);

	public static function init() {
		add_action( 'admin_post_flib_seo_import_yoast', array( __CLASS__, 'importer' ) );
	}

	/** Conversion des variables Yoast. */
	public static function convertir( $texte ) {
		$texte = strtr(
			(string) $texte,
			array(
				'%%title%%'            => '%titre%',
				'%%sitename%%'         => '%site%',
				'%%sep%%'              => '%sep%',
				'%%sitedesc%%'         => '%slogan%',
				'%%primary_category%%' => '%categorie%',
				'%%category%%'         => '%categorie%',
				'%%term_title%%'       => '%titre%',
				'%%searchphrase%%'     => '%recherche%',
				'%%currentyear%%'      => '%annee%',
			)
		);
		$texte = preg_replace( '/%%[a-z0-9_]+%%/i', '', $texte );
		return trim( preg_replace( '/\s{2,}/u', ' ', $texte ) );
	}

	/** Nombre de contenus portant des données Yoast. */
	public static function compter() {
		global $wpdb;
		$cles = "'" . implode( "','", array_map( 'esc_sql', array_keys( self::POSTS ) ) ) . "'";
		return (int) $wpdb->get_var( "SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key IN ($cles) AND meta_value <> ''" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- clés constantes échappées.
	}

	public static function ecran() {
		$n     = self::compter();
		$taxos = get_option( 'wpseo_taxonomy_meta' );
		$nt    = 0;
		if ( is_array( $taxos ) ) {
			foreach ( $taxos as $termes ) {
				$nt += is_array( $termes ) ? count( $termes ) : 0;
			}
		}
		echo '<h2>' . esc_html__( 'Importer depuis Yoast SEO', 'flib-seo' ) . '</h2>';
		if ( ! $n && ! $nt && ! get_option( 'wpseo_titles' ) ) {
			echo '<p><em>' . esc_html__( 'Aucune donnée Yoast trouvée sur ce site.', 'flib-seo' ) . '</em></p>';
			return;
		}
		/* translators: 1: nombre de contenus, 2: nombre de termes. */
		echo '<p>' . esc_html( sprintf( __( 'Données trouvées : %1$d contenu(s) et %2$d terme(s), plus le titre et la description de l’accueil.', 'flib-seo' ), $n, $nt ) ) . '</p>';
		echo '<p>' . esc_html__( 'Les valeurs déjà saisies dans Flib’SEO ne sont pas écrasées : l’import peut être relancé sans risque. Les données de Yoast ne sont ni modifiées ni supprimées.', 'flib-seo' ) . '</p>';
		echo '<ol><li>' . esc_html__( 'Importer ici.', 'flib-seo' ) . '</li><li>' . esc_html__( 'Désactiver puis supprimer Yoast.', 'flib-seo' ) . '</li><li>' . esc_html__( 'Vérifier quelques pages (code source : balises title, description, canonical) et déclarer /wp-sitemap.xml dans la Search Console.', 'flib-seo' ) . '</li></ol>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'flib_seo_import_yoast' );
		echo '<input type="hidden" name="action" value="flib_seo_import_yoast">';
		submit_button( __( 'Importer les données Yoast', 'flib-seo' ) );
		echo '</form>';
	}

	public static function importer() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Action non autorisée.', 'flib-seo' ), 403 );
		}
		check_admin_referer( 'flib_seo_import_yoast' );
		global $wpdb;

		// Contenus.
		$cles   = "'" . implode( "','", array_map( 'esc_sql', array_keys( self::POSTS ) ) ) . "'";
		$lignes = $wpdb->get_results( "SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE meta_key IN ($cles) AND meta_value <> ''" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- clés constantes échappées.
		$posts  = array();
		$champs = 0;
		foreach ( $lignes as $l ) {
			$cible = self::POSTS[ $l->meta_key ];
			$val   = $l->meta_value;
			switch ( $cible ) {
				case 'noindex':
					if ( '1' !== (string) $val ) {
						continue 2; // 2 = « index » forcé, 0 = défaut : rien à importer.
					}
					break;
				case 'image':
					$val = absint( $val );
					break;
				case 'canonique':
					$val = esc_url_raw( $val );
					break;
				default:
					$val = self::convertir( $val );
			}
			if ( ! $val || '' !== (string) get_post_meta( (int) $l->post_id, Flib_SEO_Options::META . $cible, true ) ) {
				continue;
			}
			update_post_meta( (int) $l->post_id, Flib_SEO_Options::META . $cible, $val );
			$posts[ $l->post_id ] = true;
			++$champs;
		}

		// Termes.
		$termes = 0;
		$taxos  = get_option( 'wpseo_taxonomy_meta' );
		if ( is_array( $taxos ) ) {
			foreach ( $taxos as $termes_tax ) {
				foreach ( (array) $termes_tax as $term_id => $d ) {
					$map = array(
						'titre'   => isset( $d['wpseo_title'] ) ? self::convertir( $d['wpseo_title'] ) : '',
						'desc'    => isset( $d['wpseo_desc'] ) ? self::convertir( $d['wpseo_desc'] ) : '',
						'noindex' => ( isset( $d['wpseo_noindex'] ) && 'noindex' === $d['wpseo_noindex'] ) ? '1' : '',
					);
					foreach ( array_filter( $map ) as $cible => $val ) {
						if ( '' === (string) get_term_meta( (int) $term_id, Flib_SEO_Options::META . $cible, true ) ) {
							update_term_meta( (int) $term_id, Flib_SEO_Options::META . $cible, $val );
							++$termes;
						}
					}
				}
			}
		}

		// Accueil.
		$titres = get_option( 'wpseo_titles' );
		$reg    = (array) get_option( Flib_SEO_Options::OPTION, array() );
		$maj    = false;
		if ( is_array( $titres ) ) {
			$defauts = Flib_SEO_Options::schema()['general'];
			if ( ! empty( $titres['title-home-wpseo'] ) && ( empty( $reg['general_titre_accueil'] ) || $reg['general_titre_accueil'] === $defauts['titre_accueil'][1] ) ) {
				$reg['general_titre_accueil'] = self::convertir( $titres['title-home-wpseo'] );
				$maj                          = true;
			}
			if ( ! empty( $titres['metadesc-home-wpseo'] ) && empty( $reg['general_desc_accueil'] ) ) {
				$reg['general_desc_accueil'] = self::convertir( $titres['metadesc-home-wpseo'] );
				$maj                         = true;
			}
			if ( $maj ) {
				update_option( Flib_SEO_Options::OPTION, $reg );
			}
		}

		Flib_SEO_IA::vider_cache();
		Flib_SEO_Admin::noter(
			sprintf(
				/* translators: 1: champs, 2: contenus, 3: champs de termes. */
				__( 'Import terminé : %1$d champ(s) sur %2$d contenu(s), %3$d champ(s) de termes.', 'flib-seo' ),
				$champs,
				count( $posts ),
				$termes
			) . ( $maj ? ' ' . __( 'Titre et description de l’accueil repris.', 'flib-seo' ) : '' )
		);
		wp_safe_redirect( admin_url( 'admin.php?page=' . Flib_SEO_Admin::PAGE . '&onglet=outils' ) );
		exit;
	}
}
