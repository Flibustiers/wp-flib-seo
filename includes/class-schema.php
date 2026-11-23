<?php
/**
 * Données structurées schema.org (JSON-LD, un seul graphe par page) :
 * site, entreprise (LocalBusiness), page, article, fil d'Ariane, FAQ (blocs Détails).
 *
 * C'est ce qui permet aux moteurs et aux IA de comprendre sans ambiguïté qui est
 * l'entreprise, où elle intervient et de quoi parle la page.
 *
 * Filtre : flib_seo_schema (tableau @graph).
 *
 * @package FlibSEO
 */

defined( 'ABSPATH' ) || exit;

final class Flib_SEO_Schema {

	/** Types proposés dans les réglages (sous-types courants de LocalBusiness pour nos clients). */
	public static function types() {
		return array(
			'LocalBusiness'         => __( 'Entreprise locale (générique)', 'flib-seo' ),
			'Organization'          => __( 'Organisation sans adresse d’accueil', 'flib-seo' ),
			'HomeAndConstructionBusiness' => __( 'Bâtiment et habitat', 'flib-seo' ),
			'RoofingContractor'     => __( 'Couvreur', 'flib-seo' ),
			'Plumber'               => __( 'Plombier', 'flib-seo' ),
			'Electrician'           => __( 'Électricien', 'flib-seo' ),
			'HVACBusiness'          => __( 'Chauffage, climatisation', 'flib-seo' ),
			'GeneralContractor'     => __( 'Entreprise générale du bâtiment', 'flib-seo' ),
			'HousePainter'          => __( 'Peintre', 'flib-seo' ),
			'Locksmith'             => __( 'Serrurier', 'flib-seo' ),
			'MovingCompany'         => __( 'Déménageur', 'flib-seo' ),
			'ProfessionalService'   => __( 'Service aux professionnels', 'flib-seo' ),
			'LegalService'          => __( 'Juridique', 'flib-seo' ),
			'AccountingService'     => __( 'Expert-comptable', 'flib-seo' ),
			'RealEstateAgent'       => __( 'Agence immobilière', 'flib-seo' ),
			'Store'                 => __( 'Magasin', 'flib-seo' ),
			'FoodEstablishment'     => __( 'Alimentation', 'flib-seo' ),
			'Restaurant'            => __( 'Restaurant', 'flib-seo' ),
			'Bakery'                => __( 'Boulangerie', 'flib-seo' ),
			'LodgingBusiness'       => __( 'Hébergement', 'flib-seo' ),
			'Campground'            => __( 'Camping', 'flib-seo' ),
			'HealthAndBeautyBusiness' => __( 'Beauté, bien-être', 'flib-seo' ),
			'DaySpa'                => __( 'Spa', 'flib-seo' ),
			'MedicalBusiness'       => __( 'Santé', 'flib-seo' ),
			'AutoRepair'            => __( 'Garage automobile', 'flib-seo' ),
			'AutoDealer'            => __( 'Concession automobile', 'flib-seo' ),
			'SportsActivityLocation' => __( 'Activité sportive, loisirs', 'flib-seo' ),
			'EducationalOrganization' => __( 'Établissement scolaire, formation', 'flib-seo' ),
			'GovernmentOrganization' => __( 'Collectivité', 'flib-seo' ),
			'NGO'                   => __( 'Association', 'flib-seo' ),
		);
	}

	public static function init() {
		if ( flib_seo_conflit() ) {
			return;
		}
		add_action( 'wp_head', array( __CLASS__, 'afficher' ), 30 );
	}

	public static function afficher() {
		$graphe = apply_filters( 'flib_seo_schema', self::graphe() );
		if ( ! $graphe ) {
			return;
		}
		$json = wp_json_encode(
			array(
				'@context' => 'https://schema.org',
				'@graph'   => array_values( $graphe ),
			),
			JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG
		);
		echo '<script type="application/ld+json">' . $json . "</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON encodé, balises neutralisées par JSON_HEX_TAG.
	}

	private static function id( $suffixe ) {
		return home_url( '/' ) . '#' . $suffixe;
	}

	public static function graphe() {
		$g            = array();
		$g['site']    = self::site();
		$g['orga']    = self::entreprise();
		$url          = Flib_SEO_Contexte::canonique();
		$ariane       = ! is_front_page() ? Flib_SEO_Fil_Ariane::elements() : array();

		if ( '' !== $url ) {
			$page = array(
				'@type'      => is_search() ? 'SearchResultsPage' : ( ( is_archive() || is_home() ) ? 'CollectionPage' : 'WebPage' ),
				'@id'        => $url . '#page',
				'url'        => $url,
				'name'       => Flib_SEO_Contexte::titre(),
				'isPartOf'   => array( '@id' => self::id( 'site' ) ),
				'inLanguage' => get_bloginfo( 'language' ),
			);
			$desc = Flib_SEO_Contexte::description();
			if ( '' !== $desc ) {
				$page['description'] = $desc;
			}
			if ( is_front_page() ) {
				$page['about'] = array( '@id' => self::id( 'organisation' ) );
			}
			if ( is_singular() ) {
				$post                  = get_queried_object();
				$page['datePublished'] = get_post_time( 'c', true, $post );
				$page['dateModified']  = get_post_modified_time( 'c', true, $post );
				$img                   = Flib_SEO_Contexte::image_id();
				if ( $img && wp_get_attachment_url( $img ) ) {
					$page['primaryImageOfPage'] = array(
						'@type' => 'ImageObject',
						'url'   => wp_get_attachment_url( $img ),
					);
				}
			}
			if ( count( $ariane ) > 1 ) {
				$page['breadcrumb'] = array( '@id' => $url . '#ariane' );
				$g['ariane']        = self::ariane( $ariane, $url );
			}
			$g['page'] = $page;

			if ( is_singular( 'post' ) && Flib_SEO_Options::get( 'entreprise', 'articles' ) ) {
				$g['article'] = self::article( get_queried_object(), $url );
			}
			if ( is_singular() && Flib_SEO_Options::get( 'entreprise', 'faq' ) ) {
				$faq = self::faq( get_queried_object(), $url );
				if ( $faq ) {
					$g['faq'] = $faq;
				}
			}
		}
		return array_filter( $g );
	}

	private static function site() {
		return array_filter( array(
			'@type'      => 'WebSite',
			'@id'        => self::id( 'site' ),
			'url'        => home_url( '/' ),
			'name'       => get_bloginfo( 'name' ),
			'description' => get_bloginfo( 'description' ),
			'inLanguage' => get_bloginfo( 'language' ),
			'publisher'  => array( '@id' => self::id( 'organisation' ) ),
		) );
	}

	/** Entreprise : réglages de l'onglet Entreprise, complétés par les « Réglages du site » de flib-base. */
	public static function entreprise() {
		$e    = array( 'Flib_SEO_Options', 'entreprise' );
		$type = (string) Flib_SEO_Options::get( 'entreprise', 'type' );
		$o    = array(
			'@type' => $type ? $type : 'LocalBusiness',
			'@id'   => self::id( 'organisation' ),
			'name'  => call_user_func( $e, 'nom' ),
			'url'   => home_url( '/' ),
		);
		$logo = Flib_SEO_Contexte::logo_id();
		if ( $logo && wp_get_attachment_url( $logo ) ) {
			$o['logo']  = array(
				'@type' => 'ImageObject',
				'url'   => wp_get_attachment_url( $logo ),
			);
			$o['image'] = wp_get_attachment_url( $logo );
		}
		foreach ( array( 'telephone' => 'telephone', 'email' => 'email' ) as $cle => $prop ) {
			$v = call_user_func( $e, $cle );
			if ( '' !== $v ) {
				$o[ $prop ] = $v;
			}
		}
		$rue   = call_user_func( $e, 'adresse' );
		$cp    = call_user_func( $e, 'code_postal' );
		$ville = call_user_func( $e, 'ville' );
		if ( $rue || $cp || $ville ) {
			$o['address'] = array_filter(
				array(
					'@type'           => 'PostalAddress',
					'streetAddress'   => $rue,
					'postalCode'      => $cp,
					'addressLocality' => $ville,
					'addressCountry'  => (string) Flib_SEO_Options::get( 'entreprise', 'pays' ),
				)
			);
		}
		$lat = str_replace( ',', '.', (string) Flib_SEO_Options::get( 'entreprise', 'latitude' ) );
		$lng = str_replace( ',', '.', (string) Flib_SEO_Options::get( 'entreprise', 'longitude' ) );
		if ( is_numeric( $lat ) && is_numeric( $lng ) ) {
			$o['geo'] = array(
				'@type'     => 'GeoCoordinates',
				'latitude'  => (float) $lat,
				'longitude' => (float) $lng,
			);
		}
		$zone = array_filter( array_map( 'trim', explode( ',', (string) Flib_SEO_Options::get( 'entreprise', 'zone' ) ) ) );
		if ( $zone ) {
			$o['areaServed'] = array_map(
				static function ( $z ) {
					return array(
						'@type' => 'City',
						'name'  => $z,
					);
				},
				array_values( $zone )
			);
		}
		$horaires = array_filter( array_map( 'trim', preg_split( '/\R/', (string) Flib_SEO_Options::get( 'entreprise', 'horaires' ) ) ) );
		if ( $horaires && 'Organization' !== $o['@type'] ) {
			$o['openingHours'] = array_values( $horaires );
		}
		$prix = (string) Flib_SEO_Options::get( 'entreprise', 'prix' );
		if ( '' !== $prix && 'Organization' !== $o['@type'] ) {
			$o['priceRange'] = $prix;
		}
		$reseaux = Flib_SEO_Options::reseaux();
		if ( $reseaux ) {
			$o['sameAs'] = $reseaux;
		}
		return $o;
	}

	private static function ariane( $elements, $url ) {
		$liste = array();
		foreach ( array_values( $elements ) as $i => $el ) {
			$item = array(
				'@type'    => 'ListItem',
				'position' => $i + 1,
				'name'     => $el['titre'],
			);
			if ( ! empty( $el['url'] ) ) {
				$item['item'] = $el['url'];
			}
			$liste[] = $item;
		}
		return array(
			'@type'           => 'BreadcrumbList',
			'@id'             => $url . '#ariane',
			'itemListElement' => $liste,
		);
	}

	private static function article( $post, $url ) {
		$a   = array(
			'@type'            => 'BlogPosting',
			'@id'              => $url . '#article',
			'headline'         => wp_strip_all_tags( get_the_title( $post ) ),
			'datePublished'    => get_post_time( 'c', true, $post ),
			'dateModified'     => get_post_modified_time( 'c', true, $post ),
			'mainEntityOfPage' => array( '@id' => $url . '#page' ),
			'publisher'        => array( '@id' => self::id( 'organisation' ) ),
			'author'           => array( '@id' => self::id( 'organisation' ) ),
			'inLanguage'       => get_bloginfo( 'language' ),
		);
		$auteur = get_the_author_meta( 'display_name', (int) $post->post_author );
		if ( '' !== trim( (string) $auteur ) ) {
			$a['author'] = array(
				'@type' => 'Person',
				'name'  => $auteur,
			);
		}
		$img = get_post_thumbnail_id( $post );
		if ( $img ) {
			$a['image'] = wp_get_attachment_url( $img );
		}
		$desc = Flib_SEO_Contexte::description();
		if ( '' !== $desc ) {
			$a['description'] = $desc;
		}
		return $a;
	}

	/** FAQ : chaque bloc « Détails » du contenu devient une question (résumé) et sa réponse. */
	private static function faq( $post, $url ) {
		if ( ! has_block( 'core/details', $post ) ) {
			return null;
		}
		$questions = array();
		$parcourir = static function ( $blocs ) use ( &$parcourir, &$questions ) {
			foreach ( $blocs as $b ) {
				if ( 'core/details' === $b['blockName'] && preg_match( '#<summary[^>]*>(.*?)</summary>#is', $b['innerHTML'], $m ) ) {
					$reponse = '';
					foreach ( $b['innerBlocks'] as $enfant ) {
						$reponse .= render_block( $enfant );
					}
					$q = trim( wp_strip_all_tags( $m[1] ) );
					$r = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $reponse ) ) );
					if ( '' !== $q && '' !== $r ) {
						$questions[] = array(
							'@type'          => 'Question',
							'name'           => $q,
							'acceptedAnswer' => array(
								'@type' => 'Answer',
								'text'  => $r,
							),
						);
					}
				}
				if ( ! empty( $b['innerBlocks'] ) ) {
					$parcourir( $b['innerBlocks'] );
				}
			}
		};
		$parcourir( parse_blocks( $post->post_content ) );
		if ( ! $questions ) {
			return null;
		}
		return array(
			'@type'      => 'FAQPage',
			'@id'        => $url . '#faq',
			'isPartOf'   => array( '@id' => $url . '#page' ),
			'mainEntity' => $questions,
		);
	}
}
