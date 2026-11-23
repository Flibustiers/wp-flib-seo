<?php
/**
 * Page de réglages « SEO » (administrateurs) : onglets Titres, Indexation, Entreprise,
 * Fil d'Ariane, IA, Redirections, Outils.
 *
 * @package FlibSEO
 */

defined( 'ABSPATH' ) || exit;

final class Flib_SEO_Admin {

	const PAGE = 'flib-seo';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'enregistrer' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_notices', array( __CLASS__, 'avertissements' ) );
		add_action( 'admin_post_flib_seo_redirection', array( __CLASS__, 'action_redirection' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( FLIB_SEO_FILE ), array( __CLASS__, 'lien_reglages' ) );
	}

	public static function onglets() {
		return array(
			'general'      => __( 'Titres et descriptions', 'flib-seo' ),
			'indexation'   => __( 'Indexation', 'flib-seo' ),
			'entreprise'   => __( 'Entreprise', 'flib-seo' ),
			'plan'         => __( 'Plan du site', 'flib-seo' ),
			'ariane'       => __( 'Fil d’Ariane', 'flib-seo' ),
			'ia'           => __( 'IA (GEO)', 'flib-seo' ),
			'redirections' => __( 'Redirections', 'flib-seo' ),
			'outils'       => __( 'Outils', 'flib-seo' ),
		);
	}

	public static function menu() {
		add_menu_page( 'Flib’SEO', 'SEO', 'manage_options', self::PAGE, array( __CLASS__, 'page' ), 'dashicons-search', 81 );
	}

	public static function lien_reglages( $liens ) {
		array_unshift( $liens, '<a href="' . esc_url( admin_url( 'admin.php?page=' . self::PAGE ) ) . '">' . esc_html__( 'Réglages', 'flib-seo' ) . '</a>' );
		return $liens;
	}

	public static function enregistrer() {
		register_setting(
			'flib_seo',
			Flib_SEO_Options::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( 'Flib_SEO_Options', 'sanitize' ),
			)
		);
	}

	public static function assets( $hook ) {
		$ecran = get_current_screen();
		$ici   = 'toplevel_page_' . self::PAGE === $hook;
		$edit  = $ecran && in_array( $ecran->base, array( 'post', 'term' ), true );
		if ( ! $ici && ! $edit ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_style( 'flib-seo-admin', FLIB_SEO_URL . 'assets/css/admin.css', array(), FLIB_SEO_VERSION );
		wp_enqueue_script( 'flib-seo-admin', FLIB_SEO_URL . 'assets/js/admin.js', array( 'jquery' ), FLIB_SEO_VERSION, true );
		wp_localize_script(
			'flib-seo-admin',
			'flibSeo',
			array(
				'choisir' => __( 'Choisir une image', 'flib-seo' ),
				'copie'   => __( 'Copié !', 'flib-seo' ),
				'sep'     => (string) Flib_SEO_Options::get( 'general', 'sep' ),
				'site'    => get_bloginfo( 'name' ),
			)
		);
		if ( $ici ) {
			wp_enqueue_style( 'flib-seo-fil-ariane' );
			wp_enqueue_style( 'flib-seo-plan-site' );
		}
	}

	public static function avertissements() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( flib_seo_conflit() ) {
			echo '<div class="notice notice-warning"><p><strong>Flib’SEO</strong> : ' . esc_html__( 'une autre extension de référencement (Yoast, Rank Math ou SEOPress) est active. Flib’SEO n’ajoute rien aux pages tant qu’elle n’est pas désactivée. Importez d’abord ses données dans SEO › Outils.', 'flib-seo' ) . '</p></div>';
		}
	}

	/* ------------------------------------------------------------------ */
	/* Champs                                                              */
	/* ------------------------------------------------------------------ */

	private static function nom( $onglet, $cle ) {
		return Flib_SEO_Options::OPTION . '[' . $onglet . '_' . $cle . ']';
	}

	/**
	 * Une ligne de formulaire.
	 *
	 * @param array $opt aide, choix (select), placeholder, classe.
	 */
	private static function champ( $onglet, $cle, $libelle, $opt = array() ) {
		$schema = Flib_SEO_Options::schema()[ $onglet ][ $cle ];
		$type   = $schema[0];
		$val    = Flib_SEO_Options::get( $onglet, $cle );
		$id     = 'flib-seo-' . $onglet . '-' . $cle;
		$nom    = self::nom( $onglet, $cle );
		$ph     = isset( $opt['placeholder'] ) ? ' placeholder="' . esc_attr( $opt['placeholder'] ) . '"' : '';

		echo '<tr><th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( $libelle ) . '</label></th><td>';
		switch ( $type ) {
			case 'case':
				echo '<label><input type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( $nom ) . '" value="1"' . checked( (bool) $val, true, false ) . '> ' . esc_html( $opt['case'] ?? __( 'Activer', 'flib-seo' ) ) . '</label>';
				break;
			case 'zone':
				echo '<textarea class="large-text" rows="' . (int) ( $opt['lignes'] ?? 3 ) . '" id="' . esc_attr( $id ) . '" name="' . esc_attr( $nom ) . '"' . $ph . '>' . esc_textarea( (string) $val ) . '</textarea>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $ph échappé.
				break;
			case 'choix':
				echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $nom ) . '">';
				foreach ( $opt['choix'] as $v => $l ) {
					echo '<option value="' . esc_attr( $v ) . '"' . selected( (string) $val, (string) $v, false ) . '>' . esc_html( $l ) . '</option>';
				}
				echo '</select>';
				break;
			case 'image':
				self::image( $id, $nom, (int) $val );
				break;
			case 'liste':
				foreach ( $opt['choix'] as $v => $l ) {
					echo '<label style="display:block;margin-bottom:4px"><input type="checkbox" name="' . esc_attr( $nom ) . '[]" value="' . esc_attr( $v ) . '"' . checked( in_array( $v, (array) $val, true ), true, false ) . '> ' . esc_html( $l ) . ' <code>' . esc_html( $v ) . '</code></label>';
				}
				echo '<input type="hidden" name="' . esc_attr( $nom ) . '[]" value="">';
				break;
			default:
				echo '<input type="text" class="' . esc_attr( $opt['classe'] ?? 'regular-text' ) . '" id="' . esc_attr( $id ) . '" name="' . esc_attr( $nom ) . '" value="' . esc_attr( (string) $val ) . '"' . $ph . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $ph échappé.
		}
		if ( ! empty( $opt['aide'] ) ) {
			echo '<p class="description">' . wp_kses( $opt['aide'], array( 'code' => array(), 'strong' => array(), 'a' => array( 'href' => array(), 'target' => array() ), 'br' => array() ) ) . '</p>';
		}
		echo '</td></tr>';
	}

	/** Sélecteur d'image de la médiathèque. */
	public static function image( $id, $nom, $valeur ) {
		$src = $valeur ? wp_get_attachment_image_url( $valeur, 'medium' ) : '';
		echo '<div class="flib-seo-image">';
		echo '<input type="hidden" id="' . esc_attr( $id ) . '" name="' . esc_attr( $nom ) . '" value="' . esc_attr( (string) $valeur ) . '">';
		echo '<img src="' . esc_url( (string) $src ) . '" alt=""' . ( $src ? '' : ' hidden' ) . '>';
		echo '<button type="button" class="button flib-seo-image__choisir">' . esc_html__( 'Choisir', 'flib-seo' ) . '</button> ';
		echo '<button type="button" class="button-link flib-seo-image__retirer"' . ( $src ? '' : ' hidden' ) . '>' . esc_html__( 'Retirer', 'flib-seo' ) . '</button>';
		echo '</div>';
	}

	private static function debut_formulaire( $onglet ) {
		echo '<form method="post" action="options.php">';
		settings_fields( 'flib_seo' );
		echo '<input type="hidden" name="' . esc_attr( Flib_SEO_Options::OPTION ) . '[_onglet]" value="' . esc_attr( $onglet ) . '">';
		echo '<table class="form-table" role="presentation">';
	}

	private static function fin_formulaire() {
		echo '</table>';
		submit_button();
		echo '</form>';
	}

	/** Valeur indicative tirée des « Réglages du site » de flib-base. */
	private static function du_theme( $cle ) {
		if ( ! function_exists( 'flib_reglage' ) ) {
			return '';
		}
		$v = (string) flib_reglage( $cle );
		return '' !== $v ? $v : '';
	}

	/* ------------------------------------------------------------------ */
	/* Page                                                                */
	/* ------------------------------------------------------------------ */

	public static function page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$onglets = self::onglets();
		$actif   = isset( $_GET['onglet'] ) ? sanitize_key( wp_unslash( $_GET['onglet'] ) ) : 'general'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- navigation.
		$actif   = isset( $onglets[ $actif ] ) ? $actif : 'general';

		echo '<div class="wrap flib-seo"><h1>Flib’SEO</h1>';
		self::message();
		echo '<nav class="nav-tab-wrapper">';
		foreach ( $onglets as $cle => $libelle ) {
			$url = add_query_arg(
				array(
					'page'   => self::PAGE,
					'onglet' => $cle,
				),
				admin_url( 'admin.php' )
			);
			echo '<a href="' . esc_url( $url ) . '" class="nav-tab' . ( $cle === $actif ? ' nav-tab-active' : '' ) . '">' . esc_html( $libelle ) . '</a>';
		}
		echo '</nav>';
		call_user_func( array( __CLASS__, 'onglet_' . $actif ) );
		echo '</div>';
	}

	/** Message après une action (paramètre flib_msg, texte stocké en transient). */
	private static function message() {
		$cle = 'flib_seo_msg_' . get_current_user_id();
		$msg = get_transient( $cle );
		if ( $msg ) {
			delete_transient( $cle );
			echo '<div class="notice notice-' . esc_attr( $msg['type'] ) . ' is-dismissible"><p>' . wp_kses_post( $msg['texte'] ) . '</p></div>';
		}
	}

	public static function noter( $texte, $type = 'success' ) {
		set_transient(
			'flib_seo_msg_' . get_current_user_id(),
			array(
				'texte' => $texte,
				'type'  => $type,
			),
			60
		);
	}

	private static function onglet_general() {
		$o = 'general';
		echo '<p>' . wp_kses_post( __( 'Modèles utilisés quand un contenu n’a pas de titre ou de description personnalisés. Variables : <code>%titre%</code> <code>%site%</code> <code>%sep%</code> <code>%slogan%</code> <code>%categorie%</code> <code>%recherche%</code> <code>%annee%</code>.', 'flib-seo' ) ) . '</p>';
		self::debut_formulaire( $o );
		self::champ(
			$o,
			'sep',
			__( 'Séparateur', 'flib-seo' ),
			array(
				'choix' => array(
					'–' => '–',
					'-' => '-',
					'|' => '|',
					'·' => '·',
					'•' => '•',
					'»' => '»',
				),
			)
		);
		self::champ( $o, 'titre_accueil', __( 'Titre de l’accueil', 'flib-seo' ), array( 'classe' => 'large-text' ) );
		self::champ( $o, 'desc_accueil', __( 'Description de l’accueil', 'flib-seo' ), array( 'aide' => __( 'Vide : description saisie sur la page d’accueil, sinon slogan du site. Reprise aussi en tête du llms.txt.', 'flib-seo' ) ) );
		self::champ( $o, 'titre_contenu', __( 'Titre des pages et articles', 'flib-seo' ), array( 'classe' => 'large-text' ) );
		self::champ( $o, 'titre_archive', __( 'Titre des archives', 'flib-seo' ), array( 'classe' => 'large-text', 'aide' => __( 'Catégories, étiquettes, archives de types de contenus.', 'flib-seo' ) ) );
		self::champ( $o, 'titre_recherche', __( 'Titre des résultats de recherche', 'flib-seo' ), array( 'classe' => 'large-text' ) );
		self::champ( $o, 'titre_404', __( 'Titre de la page introuvable', 'flib-seo' ), array( 'classe' => 'large-text' ) );
		self::champ(
			$o,
			'desc_auto',
			__( 'Description automatique', 'flib-seo' ),
			array(
				'case' => __( 'Sans description saisie, utiliser l’extrait ou le début du texte', 'flib-seo' ),
				'aide' => __( 'Google réécrit souvent les descriptions : une description automatique vaut mieux qu’aucune.', 'flib-seo' ),
			)
		);
		self::champ( $o, 'image_defaut', __( 'Image de partage par défaut', 'flib-seo' ), array( 'aide' => __( 'Affichée quand une page partagée (Facebook, LinkedIn, WhatsApp…) n’a ni image de partage ni image mise en avant. Idéalement 1200 × 630 px.', 'flib-seo' ) ) );
		self::fin_formulaire();
	}

	private static function onglet_indexation() {
		$o = 'indexation';
		if ( '1' !== (string) get_option( 'blog_public' ) ) {
			echo '<div class="notice notice-warning inline"><p>' . wp_kses_post( sprintf( /* translators: %s: lien vers Réglages › Lecture. */ __( 'Le site demande actuellement aux moteurs de ne pas l’indexer (<a href="%s">Réglages › Lecture</a>). C’est normal pendant le développement ; le plan du site et le llms.txt sont alors désactivés.', 'flib-seo' ), esc_url( admin_url( 'options-reading.php' ) ) ) ) . '</p></div>';
		}
		$types = array_map( static fn( $t ) => $t->labels->name, Flib_SEO_Options::types() );
		$taxos = array_map( static fn( $t ) => $t->labels->name, Flib_SEO_Options::taxonomies() );

		self::debut_formulaire( $o );
		self::champ( $o, 'types_noindex', __( 'Types de contenus à ne pas indexer', 'flib-seo' ), array( 'choix' => $types, 'aide' => __( 'Exclus de Google, du plan du site et du llms.txt. Chaque contenu peut aussi être exclu individuellement.', 'flib-seo' ) ) );
		self::champ( $o, 'taxos_noindex', __( 'Taxonomies à ne pas indexer', 'flib-seo' ), array( 'choix' => $taxos, 'aide' => __( 'Les pages d’étiquettes sont rarement utiles et font doublon : exclues par défaut.', 'flib-seo' ) ) );
		self::champ( $o, 'archives_auteur', __( 'Archives d’auteur', 'flib-seo' ), array( 'case' => __( 'Conserver', 'flib-seo' ), 'aide' => __( 'Décoché : redirigées vers l’accueil (recommandé pour un site d’entreprise, évite aussi d’exposer les identifiants).', 'flib-seo' ) ) );
		self::champ( $o, 'archives_date', __( 'Archives par date', 'flib-seo' ), array( 'case' => __( 'Conserver', 'flib-seo' ), 'aide' => __( 'Décoché : redirigées vers l’accueil.', 'flib-seo' ) ) );
		self::champ( $o, 'medias_fichier', __( 'Pages des médias', 'flib-seo' ), array( 'case' => __( 'Rediriger vers le fichier', 'flib-seo' ), 'aide' => __( 'Évite des centaines de pages vides « image-123 » dans Google.', 'flib-seo' ) ) );
		self::champ( $o, 'redirection_sitemap', __( 'Anciens plans du site', 'flib-seo' ), array( 'case' => __( 'Rediriger sitemap_index.xml et *-sitemap.xml vers wp-sitemap.xml', 'flib-seo' ), 'aide' => __( 'Utile après Yoast : l’adresse déclarée dans la Search Console continue de fonctionner.', 'flib-seo' ) ) );
		self::fin_formulaire();

		if ( '1' === (string) get_option( 'blog_public' ) ) {
			echo '<p>' . esc_html__( 'Plan du site :', 'flib-seo' ) . ' <a href="' . esc_url( home_url( '/wp-sitemap.xml' ) ) . '" target="_blank">' . esc_html( home_url( '/wp-sitemap.xml' ) ) . '</a> — ' . esc_html__( 'à déclarer dans la Google Search Console.', 'flib-seo' ) . '</p>';
		}
	}

	private static function onglet_entreprise() {
		$o     = 'entreprise';
		$theme = function_exists( 'flib_reglage' );
		echo '<p>' . esc_html__( 'Ces informations alimentent les données structurées (schema.org) lues par Google et les IA, et le llms.txt.', 'flib-seo' ) . ' ';
		if ( $theme ) {
			echo wp_kses_post( sprintf( /* translators: %s: lien vers les Réglages du site. */ __( 'Laissez un champ vide pour reprendre la valeur des <a href="%s">Réglages du site</a> (affichée en grisé).', 'flib-seo' ), esc_url( admin_url( 'admin.php?page=flib-reglages' ) ) ) );
		}
		echo '</p>';
		self::debut_formulaire( $o );
		self::champ( $o, 'type', __( 'Type d’activité', 'flib-seo' ), array( 'choix' => Flib_SEO_Schema::types(), 'aide' => __( 'Le plus précis possible.', 'flib-seo' ) ) );
		foreach (
			array(
				'nom'         => __( 'Nom', 'flib-seo' ),
				'telephone'   => __( 'Téléphone', 'flib-seo' ),
				'email'       => __( 'E-mail', 'flib-seo' ),
				'adresse'     => __( 'Adresse', 'flib-seo' ),
				'code_postal' => __( 'Code postal', 'flib-seo' ),
				'ville'       => __( 'Ville', 'flib-seo' ),
			) as $cle => $libelle
		) {
			$ph = $theme ? self::du_theme( $cle ) : ( 'nom' === $cle ? get_bloginfo( 'name' ) : '' );
			self::champ( $o, $cle, $libelle, array( 'placeholder' => $ph ) );
		}
		self::champ( $o, 'pays', __( 'Pays (code)', 'flib-seo' ), array( 'classe' => 'small-text' ) );
		self::champ( $o, 'latitude', __( 'Latitude', 'flib-seo' ), array( 'classe' => 'regular-text', 'placeholder' => '48.5146' ) );
		self::champ( $o, 'longitude', __( 'Longitude', 'flib-seo' ), array( 'placeholder' => '-4.0739', 'aide' => __( 'Clic droit sur le lieu dans Google Maps : les coordonnées s’affichent en premier.', 'flib-seo' ) ) );
		self::champ( $o, 'zone', __( 'Zone d’intervention', 'flib-seo' ), array( 'classe' => 'large-text', 'placeholder' => 'Brest, Landerneau, Morlaix, Finistère', 'aide' => __( 'Villes ou secteurs séparés par des virgules. Très utile pour les recherches « près de chez moi » et les IA.', 'flib-seo' ) ) );
		self::champ( $o, 'horaires', __( 'Horaires (format schema.org)', 'flib-seo' ), array( 'placeholder' => "Mo-Fr 08:00-18:00\nSa 09:00-12:00", 'aide' => __( 'Une ligne par période. Jours : Mo Tu We Th Fr Sa Su.', 'flib-seo' ) ) );
		self::champ( $o, 'prix', __( 'Gamme de prix', 'flib-seo' ), array( 'classe' => 'small-text', 'placeholder' => '€€' ) );
		self::champ( $o, 'logo', __( 'Logo', 'flib-seo' ), array( 'aide' => __( 'Vide : logo du site, sinon icône du site.', 'flib-seo' ) ) );
		self::champ( $o, 'reseaux', __( 'Profils en ligne', 'flib-seo' ), array( 'placeholder' => 'https://www.facebook.com/…', 'aide' => $theme ? __( 'Une URL par ligne. Vide : réseaux des Réglages du site.', 'flib-seo' ) : __( 'Une URL par ligne (réseaux sociaux, fiche Google…).', 'flib-seo' ) ) );
		self::champ( $o, 'faq', __( 'FAQ', 'flib-seo' ), array( 'case' => __( 'Transformer les blocs « Détails » en questions-réponses (FAQPage)', 'flib-seo' ) ) );
		self::champ( $o, 'articles', __( 'Articles', 'flib-seo' ), array( 'case' => __( 'Baliser les articles (auteur, dates de publication et de mise à jour)', 'flib-seo' ) ) );
		self::fin_formulaire();
	}

	private static function onglet_plan() {
		$o = 'plan';
		echo '<div class="flib-seo-encart"><h2>' . esc_html__( 'Plan du site pour les visiteurs', 'flib-seo' ) . '</h2>';
		echo '<p>' . esc_html__( 'Le plan du site XML (wp-sitemap.xml) sert aux moteurs. Celui-ci est une page lisible par les visiteurs, souvent liée depuis le pied de page. Les contenus masqués de Google n’y figurent pas.', 'flib-seo' ) . '</p>';
		echo '<p>' . wp_kses_post( __( 'Créer une page « Plan du site » et y insérer le bloc <strong>« Plan du site »</strong> (colonnes, titres, couleurs et taille réglables dans la barre latérale), ou le shortcode :', 'flib-seo' ) ) . '</p>';
		echo '<p><code class="flib-seo-copier">[flib_plan_site]</code> <code class="flib-seo-copier">[flib_plan_site ordre="service,page,post" regrouper="oui" tri="titre" libelles="post:Actualités" exclure="12,34" exclure_termes="interne" colonnes="3"]</code></p>';
		echo '<p class="description">' . esc_html__( 'Attributs disponibles : types, taxonomies, ordre, regrouper (oui/non), tri (defaut, titre, date, modif), libelles, exclure (identifiants de contenus, visibles dans l’adresse de modification : post=123), exclure_termes (slugs ou identifiants), articles, colonnes, titres (oui/non), niveau (2 = h2), class. Attributs absents : réglages ci-dessous.', 'flib-seo' ) . '</p></div>';
		$types = array_map( static fn( $t ) => $t->labels->name, Flib_SEO_Options::types() );
		$taxos = array_map( static fn( $t ) => $t->labels->name, Flib_SEO_Options::taxonomies() );
		self::debut_formulaire( $o );
		self::champ( $o, 'types', __( 'Contenus affichés', 'flib-seo' ), array( 'choix' => $types, 'aide' => __( 'Les types non indexés (onglet Indexation) sont ignorés. Les pages gardent leur arborescence.', 'flib-seo' ) ) );
		self::champ( $o, 'taxos', __( 'Taxonomies affichées', 'flib-seo' ), array( 'choix' => $taxos ) );
		self::champ( $o, 'ordre', __( 'Ordre des rubriques', 'flib-seo' ), array( 'classe' => 'large-text', 'placeholder' => 'service, page, category, post', 'aide' => __( 'Identifiants des types et taxonomies (entre parenthèses ci-dessus), dans l’ordre voulu. Les rubriques non citées suivent.', 'flib-seo' ) ) );
		self::champ( $o, 'libelles', __( 'Noms des rubriques', 'flib-seo' ), array( 'classe' => 'large-text', 'placeholder' => 'post:Nos actualités, page:Le cabinet', 'aide' => __( 'Vide : nom du type ou de la taxonomie.', 'flib-seo' ) ) );
		self::champ( $o, 'regrouper', __( 'Classer par catégorie', 'flib-seo' ), array( 'case' => __( 'Ranger les articles et les contenus non hiérarchiques sous leur catégorie', 'flib-seo' ), 'aide' => __( 'Catégories pour les articles ; pour un type personnalisé, sa première taxonomie hiérarchique.', 'flib-seo' ) ) );
		self::champ( $o, 'tri', __( 'Tri', 'flib-seo' ), array( 'choix' => Flib_SEO_Plan_Site::tris() ) );
		self::champ( $o, 'articles', __( 'Nombre maximal par type', 'flib-seo' ), array( 'classe' => 'small-text', 'aide' => __( 'Pour les articles et les autres contenus non hiérarchiques (les plus récents d’abord).', 'flib-seo' ) ) );
		self::champ( $o, 'exclure', __( 'Contenus exclus', 'flib-seo' ), array( 'placeholder' => '12, 34', 'aide' => __( 'Identifiants séparés par des virgules. Les sous-pages d’une page exclue remontent d’un niveau. Pour masquer un contenu au cas par cas : case « Masquer du plan du site » dans l’éditeur (options avancées du panneau Référencement).', 'flib-seo' ) ) );
		self::champ( $o, 'exclure_termes', __( 'Catégories exclues', 'flib-seo' ), array( 'placeholder' => 'actus-internes, 8', 'aide' => __( 'Slugs ou identifiants de catégories ou de termes : ils disparaissent du plan avec leurs contenus.', 'flib-seo' ) ) );
		self::champ(
			$o,
			'colonnes',
			__( 'Colonnes', 'flib-seo' ),
			array(
				'choix' => array(
					'1' => '1',
					'2' => '2',
					'3' => '3',
					'4' => '4',
				),
				'aide'  => __( 'Une seule colonne sur mobile.', 'flib-seo' ),
			)
		);
		self::champ( $o, 'titres', __( 'Titres des rubriques', 'flib-seo' ), array( 'case' => __( 'Afficher « Pages », « Articles »… au-dessus de chaque liste', 'flib-seo' ) ) );
		self::fin_formulaire();
		$apercu = Flib_SEO_Plan_Site::html( array(), 'class="flib-plan-site"' );
		if ( '' !== $apercu ) {
			echo '<h2>' . esc_html__( 'Aperçu', 'flib-seo' ) . '</h2><div class="flib-seo-encart">' . $apercu . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML échappé dans html().
		}
	}

	private static function onglet_ariane() {
		$o = 'ariane';
		echo '<div class="flib-seo-encart"><h2>' . esc_html__( 'Utilisation', 'flib-seo' ) . '</h2>';
		echo '<p>' . wp_kses_post( __( '<strong>Dans un thème à blocs (flib-base)</strong> : Apparence › Éditeur › Modèles, ouvrir le gabarit (Page, Article…) et insérer le bloc <strong>« Fil d’Ariane »</strong>, par exemple sous l’en-tête. Couleurs, taille et marges se règlent dans la barre latérale du bloc.', 'flib-seo' ) ) . '</p>';
		echo '<p>' . wp_kses_post( __( '<strong>Ailleurs</strong> (bloc Code court, widget, page) : le shortcode, personnalisable :', 'flib-seo' ) ) . '</p>';
		echo '<p><code class="flib-seo-copier" title="' . esc_attr__( 'Cliquer pour copier', 'flib-seo' ) . '">[flib_fil_ariane]</code> <code class="flib-seo-copier">[flib_fil_ariane sep="/" accueil="Accueil" courant="non" prefixe="Vous êtes ici :" class="ma-classe"]</code></p>';
		echo '<p class="description">' . esc_html__( 'Le balisage BreadcrumbList est ajouté aux données structurées de toutes les pages, que le fil soit affiché ou non.', 'flib-seo' ) . '</p>';
		echo '<h3>' . esc_html__( 'Aperçu', 'flib-seo' ) . '</h3><div class="flib-seo-apercu-ariane">' . Flib_SEO_Fil_Ariane::apercu() . '</div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML échappé dans html().
		self::debut_formulaire( $o );
		self::champ( $o, 'accueil', __( 'Libellé de l’accueil', 'flib-seo' ), array( 'aide' => __( 'Vide : nom du site.', 'flib-seo' ) ) );
		self::champ( $o, 'sep', __( 'Séparateur', 'flib-seo' ), array( 'classe' => 'small-text', 'aide' => __( 'Ex. : › / » > · —', 'flib-seo' ) ) );
		self::champ( $o, 'prefixe', __( 'Texte avant le fil', 'flib-seo' ), array( 'placeholder' => __( 'Vous êtes ici :', 'flib-seo' ) ) );
		self::champ( $o, 'courant', __( 'Page courante', 'flib-seo' ), array( 'case' => __( 'Afficher le titre de la page visitée en fin de fil', 'flib-seo' ) ) );
		self::champ( $o, 'page_articles', __( 'Page des articles', 'flib-seo' ), array( 'case' => __( 'Insérer la page des articles (Actualités…) avant la catégorie', 'flib-seo' ) ) );
		self::fin_formulaire();
	}

	private static function onglet_ia() {
		$o = 'ia';
		foreach ( array( 'robots.txt', 'llms.txt' ) as $fichier ) {
			if ( file_exists( ABSPATH . $fichier ) ) {
				/* translators: %s: nom de fichier. */
				echo '<div class="notice notice-warning inline"><p>' . esc_html( sprintf( __( 'Un fichier %s existe à la racine du site : il est servi à la place de celui généré par WordPress. Supprimez-le pour que ces réglages s’appliquent.', 'flib-seo' ), $fichier ) ) . '</p></div>';
			}
		}
		echo '<p>' . esc_html__( 'Être repris dans les réponses de ChatGPT, Perplexity, Claude ou des résumés IA de Google tient surtout à trois choses : laisser les robots des IA lire le site, des données structurées complètes (onglet Entreprise) et des pages qui répondent clairement aux questions des clients.', 'flib-seo' ) . '</p>';
		self::debut_formulaire( $o );
		self::champ(
			$o,
			'reponses',
			__( 'Moteurs de réponse IA', 'flib-seo' ),
			array(
				'case' => __( 'Autoriser', 'flib-seo' ),
				/* translators: %s: liste de robots. */
				'aide' => sprintf( __( 'Robots qui consultent le site pour répondre à un internaute, avec un lien vers la source : %s. À laisser autorisé.', 'flib-seo' ), '<code>' . implode( '</code> <code>', Flib_SEO_IA::robots_reponses() ) . '</code>' ),
			)
		);
		self::champ(
			$o,
			'entrainement',
			__( 'Entraînement des IA', 'flib-seo' ),
			array(
				'case' => __( 'Autoriser', 'flib-seo' ),
				/* translators: %s: liste de robots. */
				'aide' => sprintf( __( 'Robots qui collectent des pages pour entraîner les modèles : %s. Les bloquer n’empêche pas d’apparaître dans les résumés IA de Google (qui passent par Googlebot), mais rend l’entreprise moins connue des modèles.', 'flib-seo' ), '<code>' . implode( '</code> <code>', Flib_SEO_IA::robots_entrainement() ) . '</code>' ),
			)
		);
		self::champ( $o, 'robots_extra', __( 'Lignes supplémentaires du robots.txt', 'flib-seo' ), array( 'lignes' => 4, 'placeholder' => "User-agent: *\nDisallow: /espace-client/" ) );
		self::champ(
			$o,
			'llms',
			__( 'Fichier llms.txt', 'flib-seo' ),
			array(
				'case' => __( 'Générer /llms.txt', 'flib-seo' ),
				'aide' => __( 'Résumé du site pour les IA : présentation, informations pratiques, pages et derniers articles avec leur description. Très peu lu à ce jour (Google ne s’en sert pas), mais sans inconvénient.', 'flib-seo' ),
			)
		);
		self::champ( $o, 'llms_intro', __( 'Présentation pour les IA', 'flib-seo' ), array( 'lignes' => 5, 'aide' => __( 'Quelques phrases factuelles : activité, savoir-faire, clientèle, ce qui distingue l’entreprise. Placées en tête du llms.txt.', 'flib-seo' ) ) );
		self::fin_formulaire();
		echo '<p><a href="' . esc_url( home_url( '/robots.txt' ) ) . '" target="_blank">robots.txt</a>';
		if ( Flib_SEO_IA::llms_actif() ) {
			echo ' · <a href="' . esc_url( home_url( '/llms.txt' ) ) . '" target="_blank">llms.txt</a>';
		}
		echo '</p>';
		echo '<p class="description">' . esc_html__( 'Pour mesurer les visites venant des IA : dans Matomo, segment « Référent » contenant chatgpt.com, perplexity.ai, claude.ai, gemini.google.com, copilot.microsoft.com.', 'flib-seo' ) . '</p>';
	}

	private static function onglet_redirections() {
		$url_action = admin_url( 'admin-post.php' );
		$prefill    = isset( $_GET['de'] ) ? sanitize_text_field( wp_unslash( $_GET['de'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- pré-remplissage.
		$champ_cache = static function ( $faire ) {
			wp_nonce_field( 'flib_seo_redirection' );
			echo '<input type="hidden" name="action" value="flib_seo_redirection"><input type="hidden" name="faire" value="' . esc_attr( $faire ) . '">';
		};

		echo '<h2>' . esc_html__( 'Ajouter une redirection', 'flib-seo' ) . '</h2>';
		echo '<form method="post" action="' . esc_url( $url_action ) . '" class="flib-seo-ajout">';
		$champ_cache( 'ajouter' );
		echo '<input type="text" name="de" required class="regular-text" placeholder="/ancienne-page/" value="' . esc_attr( $prefill ) . '"> → ';
		echo '<input type="text" name="vers" class="regular-text" placeholder="/nouvelle-page/"> ';
		echo '<select name="code"><option value="301">301 ' . esc_html__( 'définitive', 'flib-seo' ) . '</option><option value="302">302 ' . esc_html__( 'temporaire', 'flib-seo' ) . '</option><option value="410">410 ' . esc_html__( 'supprimée', 'flib-seo' ) . '</option></select> ';
		submit_button( __( 'Ajouter', 'flib-seo' ), 'primary', 'submit', false );
		echo '<p class="description">' . wp_kses_post( __( 'Chemins relatifs ou adresses complètes. <code>/blog/*</code> → <code>/actualites/*</code> redirige toutes les adresses commençant par /blog/. 410 : contenu supprimé définitivement, sans remplaçant.', 'flib-seo' ) ) . '</p>';
		echo '</form>';

		$liste = Flib_SEO_Redirections::liste();
		/* translators: %d: nombre de redirections. */
		echo '<h2>' . esc_html( sprintf( _n( '%d redirection', '%d redirections', count( $liste ), 'flib-seo' ), count( $liste ) ) ) . '</h2>';
		if ( $liste ) {
			echo '<table class="widefat striped flib-seo-table"><thead><tr><th>' . esc_html__( 'Source', 'flib-seo' ) . '</th><th>' . esc_html__( 'Destination', 'flib-seo' ) . '</th><th>' . esc_html__( 'Type', 'flib-seo' ) . '</th><th>' . esc_html__( 'Utilisations', 'flib-seo' ) . '</th><th></th></tr></thead><tbody>';
			foreach ( $liste as $r ) {
				echo '<tr><td><a href="' . esc_url( home_url( str_replace( '*', '', $r['de'] ) ) ) . '" target="_blank"><code>' . esc_html( $r['de'] ) . '</code></a></td><td>' . ( '' !== $r['vers'] ? '<code>' . esc_html( $r['vers'] ) . '</code>' : '—' ) . '</td><td>' . (int) $r['code'] . '</td><td>' . (int) ( $r['vues'] ?? 0 ) . '</td><td>';
				echo '<form method="post" action="' . esc_url( $url_action ) . '">';
				$champ_cache( 'supprimer' );
				echo '<input type="hidden" name="de" value="' . esc_attr( $r['de'] ) . '"><button class="button-link button-link-delete">' . esc_html__( 'Supprimer', 'flib-seo' ) . '</button></form></td></tr>';
			}
			echo '</tbody></table>';
		}

		echo '<details class="flib-seo-encart"><summary><strong>' . esc_html__( 'Importer une liste', 'flib-seo' ) . '</strong></summary>';
		echo '<form method="post" action="' . esc_url( $url_action ) . '">';
		$champ_cache( 'importer' );
		echo '<p>' . esc_html__( 'Une redirection par ligne : source, destination, code facultatif (séparés par une espace, une tabulation, une virgule ou un point-virgule). Copier-coller depuis un tableur fonctionne.', 'flib-seo' ) . '</p>';
		echo '<textarea name="liste" rows="8" class="large-text code" placeholder="/ancienne-page/ /nouvelle-page/&#10;/vieux-service/ /services/ 301&#10;/produit-arrete/ - 410"></textarea>';
		submit_button( __( 'Importer', 'flib-seo' ), 'secondary' );
		echo '</form></details>';

		$journal = Flib_SEO_Redirections::journal();
		uasort( $journal, static fn( $a, $b ) => $b['n'] <=> $a['n'] );
		echo '<h2>' . esc_html__( 'Pages introuvables (404)', 'flib-seo' ) . '</h2>';
		echo '<p>' . esc_html__( 'Adresses demandées qui n’existent pas, à rediriger après une refonte. Les tentatives de piratage (wp-login, .php…) sont ignorées.', 'flib-seo' ) . '</p>';
		if ( $journal ) {
			echo '<table class="widefat striped flib-seo-table"><thead><tr><th>' . esc_html__( 'Adresse', 'flib-seo' ) . '</th><th>' . esc_html__( 'Demandes', 'flib-seo' ) . '</th><th>' . esc_html__( 'Dernière', 'flib-seo' ) . '</th><th></th></tr></thead><tbody>';
			foreach ( array_slice( $journal, 0, 100, true ) as $chemin => $l ) {
				$lien = add_query_arg(
					array(
						'page'   => self::PAGE,
						'onglet' => 'redirections',
						'de'     => rawurlencode( $chemin ),
					),
					admin_url( 'admin.php' )
				);
				echo '<tr><td><code>' . esc_html( $chemin ) . '</code></td><td>' . (int) $l['n'] . '</td><td>' . esc_html( wp_date( 'd/m/Y H:i', (int) $l['t'] ) ) . '</td><td><a href="' . esc_url( $lien ) . '">' . esc_html__( 'Rediriger', 'flib-seo' ) . '</a></td></tr>';
			}
			echo '</tbody></table>';
			echo '<form method="post" action="' . esc_url( $url_action ) . '">';
			$champ_cache( 'vider' );
			submit_button( __( 'Vider le journal', 'flib-seo' ), 'secondary small' );
			echo '</form>';
		} else {
			echo '<p><em>' . esc_html__( 'Aucune page introuvable enregistrée.', 'flib-seo' ) . '</em></p>';
		}

		self::debut_formulaire( 'redirections' );
		self::champ( 'redirections', 'journal_404', __( 'Journal des 404', 'flib-seo' ), array( 'case' => __( 'Enregistrer les pages introuvables', 'flib-seo' ) ) );
		self::fin_formulaire();
	}

	public static function action_redirection() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Action non autorisée.', 'flib-seo' ), 403 );
		}
		check_admin_referer( 'flib_seo_redirection' );
		$faire = isset( $_POST['faire'] ) ? sanitize_key( wp_unslash( $_POST['faire'] ) ) : '';
		switch ( $faire ) {
			case 'ajouter':
				$res = Flib_SEO_Redirections::ajouter(
					isset( $_POST['de'] ) ? sanitize_text_field( wp_unslash( $_POST['de'] ) ) : '',
					isset( $_POST['vers'] ) ? sanitize_text_field( wp_unslash( $_POST['vers'] ) ) : '',
					isset( $_POST['code'] ) ? absint( $_POST['code'] ) : 301
				);
				true === $res ? self::noter( __( 'Redirection ajoutée.', 'flib-seo' ) ) : self::noter( $res, 'error' );
				break;
			case 'supprimer':
				Flib_SEO_Redirections::supprimer( isset( $_POST['de'] ) ? sanitize_text_field( wp_unslash( $_POST['de'] ) ) : '' );
				self::noter( __( 'Redirection supprimée.', 'flib-seo' ) );
				break;
			case 'importer':
				[ $ok, $erreurs ] = Flib_SEO_Redirections::importer( isset( $_POST['liste'] ) ? sanitize_textarea_field( wp_unslash( $_POST['liste'] ) ) : '' );
				/* translators: %d: nombre de redirections. */
				$texte = sprintf( _n( '%d redirection importée.', '%d redirections importées.', $ok, 'flib-seo' ), $ok );
				if ( $erreurs ) {
					$texte .= '<br>' . implode( '<br>', array_map( 'esc_html', $erreurs ) );
				}
				self::noter( $texte, $erreurs ? 'warning' : 'success' );
				break;
			case 'vider':
				Flib_SEO_Redirections::vider_journal();
				self::noter( __( 'Journal vidé.', 'flib-seo' ) );
				break;
		}
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE . '&onglet=redirections' ) );
		exit;
	}

	private static function onglet_outils() {
		Flib_SEO_Import_Yoast::ecran();
	}
}
