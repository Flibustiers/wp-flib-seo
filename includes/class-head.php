<?php
/**
 * Balises du <head> : title, description, robots, canonical, Open Graph et carte Twitter/X.
 *
 * @package FlibSEO
 */

defined( 'ABSPATH' ) || exit;

final class Flib_SEO_Head {

	public static function init() {
		if ( flib_seo_conflit() ) {
			return;
		}
		add_filter( 'pre_get_document_title', array( __CLASS__, 'titre' ), 20 );
		add_filter( 'wp_robots', array( __CLASS__, 'robots' ), 20 );
		remove_action( 'wp_head', 'rel_canonical' );
		add_action( 'wp_head', array( __CLASS__, 'balises' ), 2 );
	}

	public static function titre( $titre ) {
		$calcule = Flib_SEO_Contexte::titre();
		return '' !== $calcule ? $calcule : $titre;
	}

	public static function robots( $robots ) {
		if ( Flib_SEO_Contexte::noindex() ) {
			unset( $robots['max-image-preview'] );
			$robots['noindex'] = true;
			$robots['follow']  = true;
		}
		return $robots;
	}

	public static function balises() {
		$sortie = array();
		$desc   = Flib_SEO_Contexte::description();
		$canon  = Flib_SEO_Contexte::canonique();
		$titre  = Flib_SEO_Contexte::titre();
		if ( '' === $titre ) {
			$titre = wp_get_document_title();
		}

		if ( '' !== $desc ) {
			$sortie[] = '<meta name="description" content="' . esc_attr( $desc ) . '">';
		}
		if ( '' !== $canon ) {
			$sortie[] = '<link rel="canonical" href="' . esc_url( $canon ) . '">';
		}

		// Open Graph (Facebook, LinkedIn, WhatsApp…) et X.
		$og = array(
			'og:locale'    => get_locale(),
			'og:type'      => is_singular( 'post' ) ? 'article' : 'website',
			'og:title'     => $titre,
			'og:description' => $desc,
			'og:url'       => $canon ? $canon : ( is_singular() ? get_permalink() : '' ),
			'og:site_name' => get_bloginfo( 'name' ),
		);
		$image_id = Flib_SEO_Contexte::image_id();
		$image    = $image_id ? wp_get_attachment_image_src( $image_id, 'large' ) : false;
		if ( $image ) {
			$og['og:image']        = $image[0];
			$og['og:image:width']  = (string) $image[1];
			$og['og:image:height'] = (string) $image[2];
			$alt                   = trim( (string) get_post_meta( $image_id, '_wp_attachment_image_alt', true ) );
			if ( '' !== $alt ) {
				$og['og:image:alt'] = $alt;
			}
		}
		if ( is_singular( 'post' ) ) {
			$og['article:published_time'] = get_post_time( 'c', true );
			$og['article:modified_time']  = get_post_modified_time( 'c', true );
		}
		foreach ( array_filter( apply_filters( 'flib_seo_open_graph', $og ) ) as $prop => $val ) {
			$sortie[] = '<meta property="' . esc_attr( $prop ) . '" content="' . esc_attr( $val ) . '">';
		}
		$sortie[] = '<meta name="twitter:card" content="' . ( $image ? 'summary_large_image' : 'summary' ) . '">';

		echo "\n<!-- Flib'SEO -->\n" . implode( "\n", $sortie ) . "\n<!-- /Flib'SEO -->\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- chaque balise est échappée ci-dessus.
	}
}
