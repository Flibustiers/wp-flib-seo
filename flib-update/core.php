<?php
/**
 * Mises à jour Flibustiers — cœur 1.0.0 (chargé une seule fois par loader.php).
 *
 * - Détecte les nouvelles versions via les releases GitHub (dépôts privés).
 * - Jeton : define( 'FLIB_GITHUB_TOKEN', 'github_pat_…' ); dans wp-config.php
 *   (jeton fine-grained, Contents: Read-only, limité aux dépôts d'extensions).
 * - Les mises à jour s'installent depuis la page Extensions, comme les officielles.
 *
 * Source : https://github.com/Flibustiers/flib-extensions (lib/wp/flib-update).
 */

defined( 'ABSPATH' ) || exit;

if ( class_exists( 'Flib_Update', false ) ) {
	return;
}

final class Flib_Update {

	const VERSION   = '1.0.0';
	const OWNER     = 'Flibustiers';
	const CACHE_TTL = 21600; // 6 h.

	public static function init() {
		add_filter( 'update_plugins_github.com', array( __CLASS__, 'check_update' ), 10, 3 );
		add_filter( 'upgrader_pre_download', array( __CLASS__, 'download' ), 10, 3 );
		add_filter( 'plugins_api', array( __CLASS__, 'plugin_info' ), 20, 3 );
		add_action( 'upgrader_process_complete', array( __CLASS__, 'flush_cache' ) );
		add_action( 'load-update-core.php', array( __CLASS__, 'maybe_flush_on_force_check' ) );
		add_action( 'load-plugins.php', array( __CLASS__, 'plugins_screen' ) );
	}

	/* ------------------------------------------------------------------ */
	/* GitHub                                                              */
	/* ------------------------------------------------------------------ */

	private static function token() {
		return defined( 'FLIB_GITHUB_TOKEN' ) && FLIB_GITHUB_TOKEN ? (string) FLIB_GITHUB_TOKEN : '';
	}

	private static function headers( $accept = 'application/vnd.github+json' ) {
		$headers = array(
			'Accept'               => $accept,
			'User-Agent'           => 'flib-update',
			'X-GitHub-Api-Version' => '2022-11-28',
		);
		if ( '' !== self::token() ) {
			$headers['Authorization'] = 'Bearer ' . self::token();
		}
		return $headers;
	}

	/** Nom du dépôt si l'Update URI pointe vers github.com/Flibustiers/<dépôt>. */
	private static function repo_from_uri( $uri ) {
		if ( is_string( $uri ) && preg_match( '#^https://github\.com/' . self::OWNER . '/([A-Za-z0-9._-]+?)/?$#i', trim( $uri ), $m ) ) {
			return $m[1];
		}
		return null;
	}

	private static function cache_key( $repo ) {
		return 'flib_upd_' . md5( strtolower( $repo ) );
	}

	/**
	 * Dernière release : version, URL API de l'asset .zip, notes.
	 * Échecs mis en cache 1 h.
	 *
	 * @return array|null
	 */
	public static function release( $repo ) {
		$key    = self::cache_key( $repo );
		$cached = get_site_transient( $key );
		if ( is_array( $cached ) ) {
			return isset( $cached['version'] ) ? $cached : null;
		}

		$error = null;
		$data  = null;
		if ( '' === self::token() ) {
			$error = 'Jeton manquant : ajouter FLIB_GITHUB_TOKEN dans wp-config.php.';
		} else {
			$response = wp_remote_get(
				'https://api.github.com/repos/' . self::OWNER . '/' . rawurlencode( $repo ) . '/releases/latest',
				array(
					'timeout' => 15,
					'headers' => self::headers(),
				)
			);
			if ( is_wp_error( $response ) ) {
				$error = $response->get_error_message();
			} else {
				$code = (int) wp_remote_retrieve_response_code( $response );
				if ( 200 === $code ) {
					$data = json_decode( wp_remote_retrieve_body( $response ), true );
				} elseif ( 401 === $code ) {
					$error = 'Jeton GitHub refusé (expiré ou révoqué).';
				} elseif ( 404 === $code ) {
					$error = 'Dépôt ' . $repo . ' introuvable, jeton sans accès, ou aucune release.';
				} else {
					$error = 'Réponse GitHub HTTP ' . $code . '.';
				}
			}
		}

		$asset = null;
		if ( is_array( $data ) ) {
			foreach ( isset( $data['assets'] ) ? (array) $data['assets'] : array() as $a ) {
				if ( isset( $a['name'], $a['id'] ) && '.zip' === strtolower( substr( $a['name'], -4 ) ) ) {
					$asset = $a;
					break;
				}
			}
			if ( ! $asset ) {
				$error = 'La dernière release ne contient pas de .zip.';
			}
		}

		if ( $error || ! $asset ) {
			set_site_transient( $key, array( 'error' => $error ? $error : 'Release illisible.' ), HOUR_IN_SECONDS );
			return null;
		}

		$release = array(
			'version'  => ltrim( (string) $data['tag_name'], 'vV' ),
			'package'  => 'https://api.github.com/repos/' . self::OWNER . '/' . $repo . '/releases/assets/' . (int) $asset['id'],
			'notes'    => isset( $data['body'] ) ? (string) $data['body'] : '',
			'date'     => isset( $data['published_at'] ) ? (string) $data['published_at'] : '',
			'html_url' => isset( $data['html_url'] ) ? (string) $data['html_url'] : '',
		);
		set_site_transient( $key, $release, self::CACHE_TTL );
		return $release;
	}

	private static function last_error( $repo ) {
		$cached = get_site_transient( self::cache_key( $repo ) );
		return is_array( $cached ) && isset( $cached['error'] ) ? $cached['error'] : '';
	}

	/** [ plugin_file => [ 'repo' => …, 'data' => … ] ] */
	private static function managed_plugins() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$out = array();
		foreach ( get_plugins() as $file => $data ) {
			$repo = self::repo_from_uri( isset( $data['UpdateURI'] ) ? $data['UpdateURI'] : '' );
			if ( $repo ) {
				$out[ $file ] = array(
					'repo' => $repo,
					'data' => $data,
				);
			}
		}
		return $out;
	}

	/* ------------------------------------------------------------------ */
	/* Branchements WordPress                                              */
	/* ------------------------------------------------------------------ */

	/** Filtre update_plugins_github.com (WP 5.8+). */
	public static function check_update( $update, $plugin_data, $plugin_file ) {
		$repo = self::repo_from_uri( isset( $plugin_data['UpdateURI'] ) ? $plugin_data['UpdateURI'] : '' );
		if ( ! $repo ) {
			return $update;
		}
		$release = self::release( $repo );
		if ( ! $release ) {
			return $update;
		}
		return array(
			'slug'         => dirname( $plugin_file ),
			'version'      => $release['version'],
			'url'          => $release['html_url'],
			'package'      => $release['package'],
			'requires_php' => isset( $plugin_data['RequiresPHP'] ) ? $plugin_data['RequiresPHP'] : '',
		);
	}

	/**
	 * Asset de release privée : l'API redirige vers une URL signée, à suivre
	 * SANS l'en-tête Authorization.
	 */
	public static function download( $reply, $package, $upgrader ) {
		if ( false !== $reply || ! is_string( $package ) ) {
			return $reply;
		}
		if ( ! preg_match( '#^https://api\.github\.com/repos/' . self::OWNER . '/[A-Za-z0-9._-]+/releases/assets/\d+$#i', $package ) ) {
			return $reply;
		}

		$response = wp_remote_get(
			$package,
			array(
				'timeout'     => 30,
				'redirection' => 0,
				'headers'     => self::headers( 'application/octet-stream' ),
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code >= 300 && $code < 400 ) {
			$location = wp_remote_retrieve_header( $response, 'location' );
			if ( ! $location ) {
				return new WP_Error( 'flib_download', 'GitHub : redirection sans adresse.' );
			}
			return download_url( $location, 300 );
		}
		if ( 200 === $code ) {
			$tmp = wp_tempnam( 'flib-update.zip' );
			if ( ! $tmp || false === file_put_contents( $tmp, wp_remote_retrieve_body( $response ) ) ) {
				return new WP_Error( 'flib_download', 'Impossible d\'écrire le fichier temporaire.' );
			}
			return $tmp;
		}
		return new WP_Error( 'flib_download', sprintf( 'Téléchargement GitHub impossible (HTTP %d). Vérifier FLIB_GITHUB_TOKEN.', $code ) );
	}

	/** Fenêtre « Voir les détails ». */
	public static function plugin_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) ) {
			return $result;
		}
		foreach ( self::managed_plugins() as $file => $p ) {
			if ( dirname( $file ) !== $args->slug ) {
				continue;
			}
			$release = self::release( $p['repo'] );
			$notes   = $release && '' !== trim( $release['notes'] ) ? $release['notes'] : 'Aucune note de version.';
			return (object) array(
				'name'          => $p['data']['Name'],
				'slug'          => $args->slug,
				'version'       => $release ? $release['version'] : $p['data']['Version'],
				'author'        => $p['data']['Author'],
				'homepage'      => 'https://www.les-flibustiers.fr',
				'requires_php'  => $p['data']['RequiresPHP'],
				'last_updated'  => $release ? $release['date'] : '',
				'download_link' => $release ? $release['package'] : '',
				'sections'      => array(
					'description' => wpautop( esc_html( $p['data']['Description'] ) ),
					'changelog'   => wpautop( esc_html( $notes ) ),
				),
			);
		}
		return $result;
	}

	public static function flush_cache() {
		foreach ( self::managed_plugins() as $p ) {
			delete_site_transient( self::cache_key( $p['repo'] ) );
		}
	}

	/** « Vérifier à nouveau » (Tableau de bord > Mises à jour) vide aussi notre cache. */
	public static function maybe_flush_on_force_check() {
		if ( isset( $_GET['force-check'] ) && current_user_can( 'update_plugins' ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			self::flush_cache();
		}
	}

	/* ------------------------------------------------------------------ */
	/* Page Extensions : état sous chaque extension gérée                  */
	/* ------------------------------------------------------------------ */

	public static function plugins_screen() {
		if ( ! current_user_can( 'update_plugins' ) ) {
			return;
		}
		add_filter( 'plugin_row_meta', array( __CLASS__, 'row_meta' ), 10, 2 );
		if ( '' === self::token() ) {
			add_action( 'admin_notices', array( __CLASS__, 'notice_no_token' ) );
		}
	}

	public static function row_meta( $meta, $file ) {
		static $managed = null;
		if ( null === $managed ) {
			$managed = self::managed_plugins();
		}
		if ( ! isset( $managed[ $file ] ) ) {
			return $meta;
		}
		$repo = $managed[ $file ]['repo'];
		if ( self::release( $repo ) ) {
			$meta[] = '<span style="color:#008a20">Mises à jour Flibustiers actives</span>';
		} else {
			$meta[] = '<span style="color:#b32d2e">Mises à jour Flibustiers : ' . esc_html( self::last_error( $repo ) ) . '</span>';
		}
		return $meta;
	}

	public static function notice_no_token() {
		echo '<div class="notice notice-warning"><p><strong>Mises à jour Flibustiers :</strong> ajouter <code>define( \'FLIB_GITHUB_TOKEN\', \'…\' );</code> dans wp-config.php pour recevoir les mises à jour des extensions sur mesure.</p></div>';
	}
}
