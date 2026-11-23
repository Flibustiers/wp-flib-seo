/* Flib'SEO, administration : sélecteur d'image, aperçu Google, copie des shortcodes. */
( function ( $ ) {
	'use strict';
	const i18n = window.flibSeo || {};

	// Sélecteur d'image (médiathèque).
	$( document ).on( 'click', '.flib-seo-image__choisir', function ( e ) {
		e.preventDefault();
		const $bloc = $( this ).closest( '.flib-seo-image' );
		const cadre = wp.media( { title: i18n.choisir, library: { type: 'image' }, multiple: false } );
		cadre.on( 'select', function () {
			const img = cadre.state().get( 'selection' ).first().toJSON();
			const src = img.sizes && img.sizes.medium ? img.sizes.medium.url : img.url;
			$bloc.find( 'input' ).val( img.id );
			$bloc.find( 'img' ).attr( 'src', src ).prop( 'hidden', false );
			$bloc.find( '.flib-seo-image__retirer' ).prop( 'hidden', false );
		} );
		cadre.open();
	} );
	$( document ).on( 'click', '.flib-seo-image__retirer', function ( e ) {
		e.preventDefault();
		const $bloc = $( this ).closest( '.flib-seo-image' );
		$bloc.find( 'input' ).val( '' );
		$bloc.find( 'img' ).attr( 'src', '' ).prop( 'hidden', true );
		$( this ).prop( 'hidden', true );
	} );

	// Copie d'un shortcode au clic.
	$( document ).on( 'click', '.flib-seo-copier', function () {
		const $c = $( this );
		const texte = $c.text();
		if ( navigator.clipboard ) {
			navigator.clipboard.writeText( texte ).then( function () {
				$c.attr( 'data-copie', i18n.copie );
				setTimeout( function () {
					$c.removeAttr( 'data-copie' );
				}, 1500 );
			} );
		}
	} );

	// Aperçu Google dans la boîte « Référencement ».
	const $boite = $( '.flib-seo-boite' );
	if ( ! $boite.length ) {
		return;
	}
	const $titre = $( '#flib-seo-titre' );
	const $desc = $( '#flib-seo-desc' );

	function titreContenu() {
		if ( window.wp && wp.data && wp.data.select( 'core/editor' ) ) {
			return wp.data.select( 'core/editor' ).getEditedPostAttribute( 'title' ) || '';
		}
		return $( '#title' ).val() || '';
	}

	function variables( texte, titre ) {
		return texte
			.split( '%titre%' ).join( titre )
			.split( '%site%' ).join( $boite.data( 'site' ) )
			.split( '%sep%' ).join( $boite.data( 'sep' ) );
	}

	function maj() {
		const titre = titreContenu();
		const modele = String( $boite.data( 'modele' ) ).split( '{{titre}}' ).join( titre );
		const auto = modele.trim();
		$titre.attr( 'placeholder', auto );
		$boite.find( '.flib-seo-apercu__titre' ).text( $titre.val().trim() ? variables( $titre.val(), titre ) : auto );
		$boite.find( '.flib-seo-apercu__desc' ).text( $desc.val().trim() || $desc.attr( 'placeholder' ) || '' );
	}

	$titre.add( $desc ).on( 'input', maj );
	$( '#title' ).on( 'input', maj );
	if ( window.wp && wp.data && wp.data.subscribe ) {
		let precedent = null;
		wp.data.subscribe( function () {
			const editeur = wp.data.select( 'core/editor' );
			if ( ! editeur ) {
				return;
			}
			const t = editeur.getEditedPostAttribute( 'title' );
			if ( t !== precedent ) {
				precedent = t;
				maj();
			}
		} );
	}
	maj();
} )( jQuery );
