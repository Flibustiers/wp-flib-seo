<?php
/**
 * Mises à jour Flibustiers — chargeur (copie embarquée dans chaque extension).
 *
 * Chaque extension inclut ce fichier. Toutes les copies présentes sur le site
 * s'inscrivent ; au chargement des extensions, seule la plus récente est
 * chargée et gère toutes les extensions dont l'en-tête contient
 * « Update URI: https://github.com/Flibustiers/<dépôt> ».
 *
 * Source : https://github.com/Flibustiers/flib-extensions (lib/wp/flib-update).
 * Ne pas modifier ici : modifier la source puis la recopier partout.
 */

defined( 'ABSPATH' ) || exit;

$GLOBALS['flib_update_copies'][ __DIR__ . '/core.php' ] = '1.0.0';

if ( ! function_exists( 'flib_update_boot' ) ) {
	function flib_update_boot() {
		if ( class_exists( 'Flib_Update', false ) || empty( $GLOBALS['flib_update_copies'] ) ) {
			return;
		}
		$copies = $GLOBALS['flib_update_copies'];
		uasort( $copies, 'version_compare' );
		end( $copies );
		require_once key( $copies );
		Flib_Update::init();
	}
	add_action( 'plugins_loaded', 'flib_update_boot', 1 );
}
