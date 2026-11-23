/* Bloc « Plan du site » : réglages dans la barre latérale, aperçu rendu par le serveur. */
( function ( wp ) {
	const el = wp.element.createElement;
	const { __ } = wp.i18n;
	const { InspectorControls, useBlockProps } = wp.blockEditor;
	const { PanelBody, TextControl, SelectControl, Disabled } = wp.components;
	const ServerSideRender = wp.serverSideRender;

	wp.blocks.registerBlockType( 'flib-seo/plan-site', {
		edit: function ( props ) {
			const a = props.attributes;
			const set = function ( cle ) {
				return function ( v ) {
					const o = {};
					o[ cle ] = v;
					props.setAttributes( o );
				};
			};
			return el(
				'div',
				useBlockProps(),
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'Plan du site', 'flib-seo' ) },
						el( 'p', { style: { fontSize: '12px', opacity: 0.8 } }, __( 'Champs vides : réglages de l’onglet SEO › Plan du site.', 'flib-seo' ) ),
						el( SelectControl, {
							label: __( 'Colonnes', 'flib-seo' ),
							value: a.colonnes,
							onChange: set( 'colonnes' ),
							options: [
								{ label: __( 'Comme dans les réglages', 'flib-seo' ), value: '' },
								{ label: '1', value: '1' },
								{ label: '2', value: '2' },
								{ label: '3', value: '3' },
								{ label: '4', value: '4' },
							],
						} ),
						el( SelectControl, {
							label: __( 'Titres des rubriques', 'flib-seo' ),
							value: a.titres,
							onChange: set( 'titres' ),
							options: [
								{ label: __( 'Comme dans les réglages', 'flib-seo' ), value: 'defaut' },
								{ label: __( 'Afficher', 'flib-seo' ), value: 'oui' },
								{ label: __( 'Masquer', 'flib-seo' ), value: 'non' },
							],
						} ),
						el( SelectControl, {
							label: __( 'Classer par catégorie', 'flib-seo' ),
							help: __( 'Articles et contenus non hiérarchiques rangés sous leur catégorie.', 'flib-seo' ),
							value: a.regrouper,
							onChange: set( 'regrouper' ),
							options: [
								{ label: __( 'Comme dans les réglages', 'flib-seo' ), value: 'defaut' },
								{ label: __( 'Oui', 'flib-seo' ), value: 'oui' },
								{ label: __( 'Non', 'flib-seo' ), value: 'non' },
							],
						} ),
						el( SelectControl, {
							label: __( 'Tri', 'flib-seo' ),
							value: a.tri,
							onChange: set( 'tri' ),
							options: [
								{ label: __( 'Comme dans les réglages', 'flib-seo' ), value: '' },
								{ label: __( 'Ordre des pages, puis récents d’abord', 'flib-seo' ), value: 'defaut' },
								{ label: __( 'Alphabétique', 'flib-seo' ), value: 'titre' },
								{ label: __( 'Date de publication', 'flib-seo' ), value: 'date' },
								{ label: __( 'Dernière mise à jour', 'flib-seo' ), value: 'modif' },
							],
						} )
					),
					el(
						PanelBody,
						{ title: __( 'Contenus et exclusions', 'flib-seo' ), initialOpen: false },
						el( TextControl, { label: __( 'Types de contenus', 'flib-seo' ), help: __( 'Séparés par des virgules, ex. : page,post', 'flib-seo' ), value: a.types, onChange: set( 'types' ) } ),
						el( TextControl, { label: __( 'Taxonomies', 'flib-seo' ), help: __( 'Ex. : category', 'flib-seo' ), value: a.taxonomies, onChange: set( 'taxonomies' ) } ),
						el( TextControl, { label: __( 'Ordre des rubriques', 'flib-seo' ), help: __( 'Ex. : service,page,post', 'flib-seo' ), value: a.ordre, onChange: set( 'ordre' ) } ),
						el( TextControl, { label: __( 'Noms des rubriques', 'flib-seo' ), help: __( 'Ex. : post:Nos actualités,page:Le cabinet', 'flib-seo' ), value: a.libelles, onChange: set( 'libelles' ) } ),
						el( TextControl, { label: __( 'Contenus exclus', 'flib-seo' ), help: __( 'Identifiants, ex. : 12,34', 'flib-seo' ), value: a.exclure, onChange: set( 'exclure' ) } ),
						el( TextControl, { label: __( 'Catégories exclues', 'flib-seo' ), help: __( 'Slugs ou identifiants, avec leurs contenus.', 'flib-seo' ), value: a.exclureTermes, onChange: set( 'exclureTermes' ) } )
					)
				),
				el( Disabled, null, el( ServerSideRender, { block: 'flib-seo/plan-site', attributes: a } ) )
			);
		},
		save: function () {
			return null;
		},
	} );
} )( window.wp );
