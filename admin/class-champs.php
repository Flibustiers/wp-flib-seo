<?php
/**
 * Champs SEO des contenus (boîte « Référencement » sous l'éditeur) et des termes
 * (catégories, étiquettes…), avec aperçu du résultat Google.
 *
 * Accessibles à toute personne qui peut modifier le contenu (les clients en compte Éditeur compris).
 *
 * @package FlibSEO
 */

defined( 'ABSPATH' ) || exit;

final class Flib_SEO_Champs {

	const NONCE = 'flib_seo_champs';

	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'boite' ) );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'panneau' ) );
		add_action( 'save_post', array( __CLASS__, 'enregistrer_post' ), 10, 2 );
		add_action( 'admin_init', array( __CLASS__, 'termes' ) );
	}

	/* ------------------------------------------------------------------ */
	/* Contenus                                                            */
	/* ------------------------------------------------------------------ */

	public static function boite() {
		foreach ( array_keys( Flib_SEO_Options::types() ) as $type ) {
			// Éditeur de blocs : panneau latéral (assets/js/editeur.js) ; la boîte ne sert qu'à l'éditeur classique.
			add_meta_box( 'flib-seo', __( 'Référencement', 'flib-seo' ), array( __CLASS__, 'rendu_post' ), $type, 'normal', 'high', array( '__back_compat_meta_box' => true ) );
		}
	}

	/** Panneau « Référencement » de l'éditeur de blocs. */
	public static function panneau() {
		$post = get_post();
		if ( ! $post || ! isset( Flib_SEO_Options::types()[ $post->post_type ] ) ) {
			return;
		}
		wp_enqueue_script(
			'flib-seo-editeur',
			FLIB_SEO_URL . 'assets/js/editeur.js',
			array( 'wp-plugins', 'wp-editor', 'wp-element', 'wp-components', 'wp-data', 'wp-core-data', 'wp-block-editor', 'wp-i18n' ),
			FLIB_SEO_VERSION,
			true
		);
		wp_enqueue_style( 'flib-seo-admin', FLIB_SEO_URL . 'assets/css/admin.css', array(), FLIB_SEO_VERSION );
		wp_localize_script(
			'flib-seo-editeur',
			'flibSeoEditeur',
			array(
				'modele'    => self::modele_js( $post ),
				'site'      => get_bloginfo( 'name' ),
				'sep'       => (string) Flib_SEO_Options::get( 'general', 'sep' ),
				'auto'      => (bool) Flib_SEO_Options::get( 'general', 'desc_auto' ),
				'exclu'     => in_array( $post->post_type, (array) Flib_SEO_Options::get( 'indexation', 'types_noindex' ), true ),
				'accueil'   => home_url( '/' ),
				'meta'      => Flib_SEO_Options::META,
			)
		);
	}

	/** Modèle de titre avec un marqueur {{titre}} remplacé en direct par le JavaScript. */
	private static function modele_js( $post ) {
		$vars = array_merge( Flib_SEO_Contexte::vars_post( $post ), array( '%titre%' => '{{titre}}' ) );
		$est_accueil = 'page' === get_option( 'show_on_front' ) && (int) get_option( 'page_on_front' ) === (int) $post->ID;
		return Flib_SEO_Contexte::remplacer( Flib_SEO_Options::get( 'general', $est_accueil ? 'titre_accueil' : 'titre_contenu' ), $vars );
	}

	public static function rendu_post( $post ) {
		wp_nonce_field( self::NONCE, self::NONCE );
		$m      = static fn( $cle ) => (string) Flib_SEO_Options::post_meta( $post->ID, $cle );
		$defaut = Flib_SEO_Contexte::titre_defaut_post( $post );
		$auto   = Flib_SEO_Contexte::desc_auto_post( $post );
		$url    = 'publish' === $post->post_status ? get_permalink( $post ) : home_url( '/' . ( $post->post_name ? $post->post_name . '/' : '' ) );
		$type   = get_post_type_object( $post->post_type );
		$exclu  = in_array( $post->post_type, (array) Flib_SEO_Options::get( 'indexation', 'types_noindex' ), true );
		?>
		<div class="flib-seo-boite" data-modele="<?php echo esc_attr( self::modele_js( $post ) ); ?>" data-site="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" data-sep="<?php echo esc_attr( (string) Flib_SEO_Options::get( 'general', 'sep' ) ); ?>">
			<div class="flib-seo-apercu" aria-hidden="true">
				<div class="flib-seo-apercu__url"><?php echo esc_html( preg_replace( '#^https?://#', '', untrailingslashit( $url ) ) ); ?></div>
				<div class="flib-seo-apercu__titre"></div>
				<div class="flib-seo-apercu__desc"></div>
			</div>
			<p>
				<label for="flib-seo-titre"><strong><?php esc_html_e( 'Titre pour Google', 'flib-seo' ); ?></strong></label>
				<input type="text" class="widefat" id="flib-seo-titre" name="flib_seo[titre]" value="<?php echo esc_attr( $m( 'titre' ) ); ?>" placeholder="<?php echo esc_attr( $defaut ); ?>">
				<span class="description"><?php esc_html_e( 'Vide : titre automatique (texte grisé). Variables : %titre% %site% %sep%.', 'flib-seo' ); ?></span>
			</p>
			<p>
				<label for="flib-seo-desc"><strong><?php esc_html_e( 'Description pour Google', 'flib-seo' ); ?></strong></label>
				<textarea class="widefat" rows="3" id="flib-seo-desc" name="flib_seo[desc]" placeholder="<?php echo esc_attr( $auto ); ?>"><?php echo esc_textarea( $m( 'desc' ) ); ?></textarea>
				<span class="description"><?php esc_html_e( 'Une ou deux phrases qui donnent envie de cliquer. Le texte trop long est coupé dans l’aperçu comme dans Google.', 'flib-seo' ); ?></span>
			</p>
			<details class="flib-seo-avance"<?php echo ( $m( 'noindex' ) || $m( 'canonique' ) || $m( 'image' ) ) ? ' open' : ''; ?>>
				<summary><?php esc_html_e( 'Options avancées', 'flib-seo' ); ?></summary>
				<p>
					<?php if ( $exclu ) : ?>
						<em>
						<?php
						/* translators: %s: nom du type de contenu. */
						echo esc_html( sprintf( __( 'Les « %s » ne sont pas indexés (réglage général SEO › Indexation).', 'flib-seo' ), $type->labels->name ) );
						?>
						</em>
					<?php else : ?>
						<label><input type="checkbox" name="flib_seo[noindex]" value="1" <?php checked( (bool) $m( 'noindex' ) ); ?>> <?php esc_html_e( 'Masquer ce contenu de Google (noindex, retiré du plan du site et du llms.txt)', 'flib-seo' ); ?></label>
					<?php endif; ?>
				</p>
				<p>
					<label for="flib-seo-canonique"><?php esc_html_e( 'URL canonique', 'flib-seo' ); ?></label>
					<input type="url" class="widefat" id="flib-seo-canonique" name="flib_seo[canonique]" value="<?php echo esc_attr( $m( 'canonique' ) ); ?>" placeholder="<?php echo esc_attr( $url ); ?>">
					<span class="description"><?php esc_html_e( 'Seulement si ce contenu est la copie d’une autre page.', 'flib-seo' ); ?></span>
				</p>
				<div>
					<p><?php esc_html_e( 'Image de partage (réseaux sociaux)', 'flib-seo' ); ?> <span class="description"><?php esc_html_e( '— vide : image mise en avant.', 'flib-seo' ); ?></span></p>
					<?php Flib_SEO_Admin::image( 'flib-seo-image', 'flib_seo[image]', (int) $m( 'image' ) ); ?>
				</div>
			</details>
		</div>
		<?php
	}

	/** Enregistre ou supprime une méta (valeur vide = suppression, pour garder la base propre). */
	private static function meta( $fn_maj, $fn_suppr, $id, $cle, $valeur ) {
		$cle = Flib_SEO_Options::META . $cle;
		if ( '' === $valeur || 0 === $valeur || null === $valeur ) {
			$fn_suppr( $id, $cle );
		} else {
			$fn_maj( $id, $cle, $valeur );
		}
	}

	public static function enregistrer_post( $post_id, $post ) {
		if ( ! isset( $_POST[ self::NONCE ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ), self::NONCE ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$d = isset( $_POST['flib_seo'] ) && is_array( $_POST['flib_seo'] ) ? wp_unslash( $_POST['flib_seo'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- chaque valeur est nettoyée ci-dessous.
		$f = array( 'update_post_meta', 'delete_post_meta' );
		self::meta( $f[0], $f[1], $post_id, 'titre', sanitize_text_field( $d['titre'] ?? '' ) );
		self::meta( $f[0], $f[1], $post_id, 'desc', sanitize_textarea_field( $d['desc'] ?? '' ) );
		self::meta( $f[0], $f[1], $post_id, 'noindex', empty( $d['noindex'] ) ? '' : '1' );
		self::meta( $f[0], $f[1], $post_id, 'canonique', esc_url_raw( $d['canonique'] ?? '' ) );
		self::meta( $f[0], $f[1], $post_id, 'image', absint( $d['image'] ?? 0 ) );
	}

	/* ------------------------------------------------------------------ */
	/* Termes                                                              */
	/* ------------------------------------------------------------------ */

	public static function termes() {
		foreach ( array_keys( Flib_SEO_Options::taxonomies() ) as $tax ) {
			add_action( $tax . '_edit_form_fields', array( __CLASS__, 'rendu_terme' ), 20 );
			add_action( 'edited_' . $tax, array( __CLASS__, 'enregistrer_terme' ) );
		}
	}

	public static function rendu_terme( $term ) {
		$m       = static fn( $cle ) => (string) Flib_SEO_Options::term_meta( $term->term_id, $cle );
		$defaut  = Flib_SEO_Contexte::remplacer( Flib_SEO_Options::get( 'general', 'titre_archive' ), array( '%titre%' => $term->name ) );
		$exclu   = in_array( $term->taxonomy, (array) Flib_SEO_Options::get( 'indexation', 'taxos_noindex' ), true );
		wp_nonce_field( self::NONCE, self::NONCE );
		?>
		<tr class="form-field"><th colspan="2"><h2><?php esc_html_e( 'Référencement', 'flib-seo' ); ?></h2></th></tr>
		<tr class="form-field">
			<th scope="row"><label for="flib-seo-titre"><?php esc_html_e( 'Titre pour Google', 'flib-seo' ); ?></label></th>
			<td><input type="text" id="flib-seo-titre" name="flib_seo[titre]" value="<?php echo esc_attr( $m( 'titre' ) ); ?>" placeholder="<?php echo esc_attr( $defaut ); ?>"></td>
		</tr>
		<tr class="form-field">
			<th scope="row"><label for="flib-seo-desc"><?php esc_html_e( 'Description pour Google', 'flib-seo' ); ?></label></th>
			<td><textarea id="flib-seo-desc" rows="3" name="flib_seo[desc]"><?php echo esc_textarea( $m( 'desc' ) ); ?></textarea>
			<p class="description"><?php esc_html_e( 'Vide : description de la catégorie.', 'flib-seo' ); ?></p></td>
		</tr>
		<tr class="form-field">
			<th scope="row"><?php esc_html_e( 'Indexation', 'flib-seo' ); ?></th>
			<td>
				<?php if ( $exclu ) : ?>
					<em><?php esc_html_e( 'Cette taxonomie n’est pas indexée (réglage général SEO › Indexation).', 'flib-seo' ); ?></em>
				<?php else : ?>
					<label><input type="checkbox" name="flib_seo[noindex]" value="1" <?php checked( (bool) $m( 'noindex' ) ); ?>> <?php esc_html_e( 'Masquer de Google', 'flib-seo' ); ?></label>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}

	public static function enregistrer_terme( $term_id ) {
		if ( ! isset( $_POST[ self::NONCE ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ), self::NONCE ) || ! current_user_can( 'edit_term', $term_id ) ) {
			return;
		}
		$d = isset( $_POST['flib_seo'] ) && is_array( $_POST['flib_seo'] ) ? wp_unslash( $_POST['flib_seo'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nettoyé ci-dessous.
		$f = array( 'update_term_meta', 'delete_term_meta' );
		self::meta( $f[0], $f[1], $term_id, 'titre', sanitize_text_field( $d['titre'] ?? '' ) );
		self::meta( $f[0], $f[1], $term_id, 'desc', sanitize_textarea_field( $d['desc'] ?? '' ) );
		self::meta( $f[0], $f[1], $term_id, 'noindex', empty( $d['noindex'] ) ? '' : '1' );
	}
}
