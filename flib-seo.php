<?php
/**
 * Plugin Name:       Flib'SEO
 * Plugin URI:        https://github.com/Flibustiers/wp-flib-seo
 * Description:       Référencement léger des Flibustiers : balises title et description, indexation, plan du site, données structurées, partage social, fil d'Ariane, redirections 301 et visibilité dans les IA (llms.txt, robots IA). Remplace Yoast.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            Les Flibustiers
 * Author URI:        https://les-flibustiers.fr
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       flib-seo
 * Domain Path:       /languages
 * Update URI:        https://github.com/Flibustiers/wp-flib-seo
 *
 * @package FlibSEO
 */

defined( 'ABSPATH' ) || exit;

define( 'FLIB_SEO_VERSION', '1.0.0' );
define( 'FLIB_SEO_FILE', __FILE__ );
define( 'FLIB_SEO_DIR', plugin_dir_path( __FILE__ ) );
define( 'FLIB_SEO_URL', plugin_dir_url( __FILE__ ) );

require_once FLIB_SEO_DIR . 'flib-update/loader.php';

require_once FLIB_SEO_DIR . 'includes/class-options.php';
require_once FLIB_SEO_DIR . 'includes/class-contexte.php';
require_once FLIB_SEO_DIR . 'includes/class-head.php';
require_once FLIB_SEO_DIR . 'includes/class-indexation.php';
require_once FLIB_SEO_DIR . 'includes/class-schema.php';
require_once FLIB_SEO_DIR . 'includes/class-fil-ariane.php';
require_once FLIB_SEO_DIR . 'includes/class-ia.php';
require_once FLIB_SEO_DIR . 'includes/class-redirections.php';

if ( is_admin() ) {
	require_once FLIB_SEO_DIR . 'admin/class-admin.php';
	require_once FLIB_SEO_DIR . 'admin/class-champs.php';
	require_once FLIB_SEO_DIR . 'admin/class-import-yoast.php';
}

add_action( 'update_option_flib_seo', array( 'Flib_SEO_Options', 'flush' ) );
add_action( 'add_option_flib_seo', array( 'Flib_SEO_Options', 'flush' ) );

add_action(
	'plugins_loaded',
	static function () {
		load_plugin_textdomain( 'flib-seo', false, dirname( plugin_basename( FLIB_SEO_FILE ) ) . '/languages' );

		Flib_SEO_Head::init();
		Flib_SEO_Indexation::init();
		Flib_SEO_Schema::init();
		Flib_SEO_Fil_Ariane::init();
		Flib_SEO_IA::init();
		Flib_SEO_Redirections::init();
		add_action( 'init', array( 'Flib_SEO_Contexte', 'enregistrer_meta' ), 20 );

		if ( is_admin() ) {
			Flib_SEO_Admin::init();
			Flib_SEO_Champs::init();
			Flib_SEO_Import_Yoast::init();
		}
	}
);

/**
 * Yoast encore actif : on n'écrit rien dans le <head> pour éviter les balises en double.
 * Les réglages et l'import restent disponibles.
 */
function flib_seo_conflit() {
	return defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'SEOPRESS_VERSION' );
}
