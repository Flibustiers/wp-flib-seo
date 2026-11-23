<?php
/**
 * Désinstallation de Flib'SEO : supprime les réglages, les redirections et le journal des 404.
 *
 * Les titres et descriptions saisis sur les contenus et les termes (méta _flib_seo_*) sont
 * conservés volontairement : supprimer l'extension par erreur ne doit pas effacer le travail
 * de référencement. Pour les retirer : DELETE FROM wp_postmeta WHERE meta_key LIKE '\_flib\_seo\_%'.
 *
 * @package FlibSEO
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'flib_seo' );
delete_option( 'flib_seo_redirections' );
delete_option( 'flib_seo_404' );
delete_transient( 'flib_seo_llms' );
