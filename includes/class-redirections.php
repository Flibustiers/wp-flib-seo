<?php
/**
 * Redirections 301 / 302 / 410 et journal des pages introuvables (404).
 *
 * - Source : chemin relatif (/ancienne-page/), insensible à la casse et à la barre finale.
 *   Un « * » final redirige tout ce qui commence par ce chemin ; un « * » dans la cible
 *   est remplacé par la suite du chemin (/blog/* → /actualites/*).
 * - Le journal des 404 aide à repérer les anciennes adresses après une refonte.
 *
 * Stockage : options `flib_seo_redirections` et `flib_seo_404` (non chargées automatiquement).
 *
 * @package FlibSEO
 */

defined( 'ABSPATH' ) || exit;

final class Flib_SEO_Redirections {

	const OPTION  = 'flib_seo_redirections';
	const JOURNAL = 'flib_seo_404';
	const MAX_404 = 200;

	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'rediriger' ), -10 );
		add_action( 'template_redirect', array( __CLASS__, 'journaliser' ), 99 );
	}

	/* ------------------------------------------------------------------ */
	/* Données                                                             */
	/* ------------------------------------------------------------------ */

	public static function liste() {
		$l = get_option( self::OPTION, array() );
		return is_array( $l ) ? $l : array();
	}

	private static function enregistrer( $liste ) {
		update_option( self::OPTION, array_values( $liste ), false );
	}

	/** Normalise un chemin : sans domaine, minuscules, sans barre finale, décodé. */
	public static function normaliser( $chemin ) {
		$chemin = trim( (string) $chemin );
		if ( preg_match( '#^https?://#i', $chemin ) ) {
			$p      = wp_parse_url( $chemin );
			$chemin = ( $p['path'] ?? '/' ) . ( isset( $p['query'] ) ? '?' . $p['query'] : '' );
		}
		$base = untrailingslashit( (string) wp_parse_url( home_url(), PHP_URL_PATH ) );
		if ( '' !== $base && str_starts_with( strtolower( $chemin ), strtolower( $base ) . '/' ) ) {
			$chemin = substr( $chemin, strlen( $base ) );
		}
		$chemin = '/' . ltrim( rawurldecode( $chemin ), '/' );
		[ $p, $q ] = array_pad( explode( '?', $chemin, 2 ), 2, '' );
		$p         = '/' === $p ? '/' : untrailingslashit( $p );
		return mb_strtolower( $p ) . ( '' !== $q ? '?' . $q : '' );
	}

	/**
	 * Ajoute ou remplace une redirection.
	 *
	 * @return true|string true, ou message d'erreur.
	 */
	public static function ajouter( $de, $vers, $code = 301 ) {
		$code = in_array( (int) $code, array( 301, 302, 307, 410 ), true ) ? (int) $code : 301;
		$de   = self::normaliser( sanitize_text_field( $de ) );
		$vers = trim( (string) $vers );
		if ( '/' === $de ) {
			return __( 'La page d’accueil ne peut pas être redirigée.', 'flib-seo' );
		}
		if ( 410 !== $code ) {
			if ( '' === $vers ) {
				return __( 'Indiquez une adresse de destination.', 'flib-seo' );
			}
			$vers = preg_match( '#^https?://#i', $vers ) ? esc_url_raw( $vers ) : '/' . ltrim( sanitize_text_field( $vers ), '/' );
			if ( ! str_contains( $de, '*' ) && self::normaliser( $vers ) === $de ) {
				return __( 'La source et la destination sont identiques.', 'flib-seo' );
			}
		} else {
			$vers = '';
		}
		$liste = array_filter( self::liste(), static fn( $r ) => $r['de'] !== $de );
		array_unshift(
			$liste,
			array(
				'de'   => $de,
				'vers' => $vers,
				'code' => $code,
				'vues' => 0,
			)
		);
		self::enregistrer( $liste );
		self::retirer_du_journal( $de );
		return true;
	}

	public static function supprimer( $de ) {
		self::enregistrer( array_filter( self::liste(), static fn( $r ) => $r['de'] !== $de ) );
	}

	/**
	 * Import en masse : une redirection par ligne, « source destination [code] »
	 * (séparateur : espace, tabulation, point-virgule ou virgule).
	 *
	 * @return array array( nombre importé, erreurs ).
	 */
	public static function importer( $texte ) {
		$ok      = 0;
		$erreurs = array();
		foreach ( preg_split( '/\R/', (string) $texte ) as $n => $ligne ) {
			$ligne = trim( $ligne );
			if ( '' === $ligne || str_starts_with( $ligne, '#' ) ) {
				continue;
			}
			$c        = preg_split( '/[\s;,]+/', $ligne );
			$resultat = self::ajouter( $c[0], $c[1] ?? '', $c[2] ?? 301 );
			if ( true === $resultat ) {
				++$ok;
			} else {
				/* translators: 1: numéro de ligne, 2: message. */
				$erreurs[] = sprintf( __( 'Ligne %1$d : %2$s', 'flib-seo' ), $n + 1, $resultat );
			}
		}
		return array( $ok, $erreurs );
	}

	/* ------------------------------------------------------------------ */
	/* Site                                                                */
	/* ------------------------------------------------------------------ */

	private static function demande() {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- normalisé puis comparé.
		return self::normaliser( $uri );
	}

	/** Cherche la redirection qui correspond à un chemin normalisé. */
	public static function trouver( $chemin ) {
		$sans_requete = explode( '?', $chemin, 2 )[0];
		foreach ( self::liste() as $i => $r ) {
			$de = $r['de'];
			if ( str_ends_with( $de, '*' ) ) {
				$prefixe = rtrim( substr( $de, 0, -1 ), '/' );
				if ( $sans_requete === $prefixe || str_starts_with( $sans_requete, $prefixe . '/' ) ) {
					$reste = ltrim( substr( $sans_requete, strlen( $prefixe ) ), '/' );
					return array( $i, $r, str_replace( '*', $reste, $r['vers'] ) );
				}
			} elseif ( $de === $chemin || ( ! str_contains( $de, '?' ) && $de === $sans_requete ) ) {
				return array( $i, $r, $r['vers'] );
			}
		}
		return null;
	}

	public static function rediriger() {
		if ( is_admin() || wp_doing_ajax() ) {
			return;
		}
		$trouve = self::trouver( self::demande() );
		if ( ! $trouve ) {
			return;
		}
		[ $i, $r, $cible ] = $trouve;

		$liste = self::liste();
		if ( isset( $liste[ $i ] ) ) {
			$liste[ $i ]['vues'] = (int) ( $liste[ $i ]['vues'] ?? 0 ) + 1;
			self::enregistrer( $liste );
		}

		if ( 410 === (int) $r['code'] ) {
			status_header( 410 );
			nocache_headers();
			header( 'X-Robots-Tag: noindex' );
			echo '<!doctype html><meta charset="utf-8"><title>' . esc_html__( 'Contenu supprimé', 'flib-seo' ) . '</title><p>' . esc_html__( 'Ce contenu n’existe plus.', 'flib-seo' ) . ' <a href="' . esc_url( home_url( '/' ) ) . '">' . esc_html__( 'Retour à l’accueil', 'flib-seo' ) . '</a></p>';
			exit;
		}
		$url = preg_match( '#^https?://#i', $cible ) ? $cible : home_url( $cible );
		// wp_redirect (et non wp_safe_redirect) : une destination externe est un choix explicite de l'administrateur.
		wp_redirect( esc_url_raw( $url ), (int) $r['code'], 'Flib SEO' ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
		exit;
	}

	/* ------------------------------------------------------------------ */
	/* Journal des 404                                                     */
	/* ------------------------------------------------------------------ */

	public static function journal() {
		$j = get_option( self::JOURNAL, array() );
		return is_array( $j ) ? $j : array();
	}

	private static function retirer_du_journal( $chemin ) {
		$j = self::journal();
		if ( isset( $j[ $chemin ] ) ) {
			unset( $j[ $chemin ] );
			update_option( self::JOURNAL, $j, false );
		}
	}

	public static function vider_journal() {
		delete_option( self::JOURNAL );
	}

	/** Requêtes de robots malveillants : inutile de les journaliser. */
	private static function sonde( $chemin ) {
		return (bool) preg_match( '#(\.(php\d?|env|ini|sql|bak|old|log|git|asp|aspx|cgi|zip|tar|gz)$)|^/(wp-admin|wp-login|wp-includes|xmlrpc|\.well-known|cgi-bin|vendor|\.git)#i', explode( '?', $chemin, 2 )[0] );
	}

	public static function journaliser() {
		if ( ! is_404() || ! Flib_SEO_Options::get( 'redirections', 'journal_404' ) ) {
			return;
		}
		$chemin = explode( '?', self::demande(), 2 )[0];
		if ( self::sonde( $chemin ) || mb_strlen( $chemin ) > 200 ) {
			return;
		}
		$j = self::journal();
		if ( isset( $j[ $chemin ] ) ) {
			++$j[ $chemin ]['n'];
		} else {
			$j[ $chemin ] = array( 'n' => 1 );
		}
		$j[ $chemin ]['t'] = time();
		if ( count( $j ) > self::MAX_404 ) {
			// On garde les adresses les plus demandées, puis les plus récentes.
			uasort( $j, static fn( $a, $b ) => array( $b['n'], $b['t'] ) <=> array( $a['n'], $a['t'] ) );
			$j = array_slice( $j, 0, self::MAX_404, true );
		}
		update_option( self::JOURNAL, $j, false );
	}
}
