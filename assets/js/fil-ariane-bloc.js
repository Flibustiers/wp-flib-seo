/* Bloc « Fil d'Ariane » : réglages dans la barre latérale, aperçu rendu par le serveur. */
( function ( wp ) {
	const el = wp.element.createElement;
	const { __ } = wp.i18n;
	const { InspectorControls, useBlockProps } = wp.blockEditor;
	const { PanelBody, TextControl, SelectControl, Disabled } = wp.components;
	const ServerSideRender = wp.serverSideRender;

	wp.blocks.registerBlockType( 'flib-seo/fil-ariane', {
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
						{ title: __( 'Fil d’Ariane', 'flib-seo' ) },
						el( 'p', { style: { fontSize: '12px', opacity: 0.8 } }, __( 'Champs vides : réglages de l’onglet SEO › Fil d’Ariane.', 'flib-seo' ) ),
						el( TextControl, { label: __( 'Séparateur', 'flib-seo' ), value: a.separateur, onChange: set( 'separateur' ), placeholder: '›' } ),
						el( TextControl, { label: __( 'Libellé de l’accueil', 'flib-seo' ), value: a.accueil, onChange: set( 'accueil' ) } ),
						el( TextControl, { label: __( 'Texte avant le fil', 'flib-seo' ), value: a.prefixe, onChange: set( 'prefixe' ), placeholder: __( 'Vous êtes ici :', 'flib-seo' ) } ),
						el( SelectControl, {
							label: __( 'Page courante', 'flib-seo' ),
							value: a.courant,
							onChange: set( 'courant' ),
							options: [
								{ label: __( 'Comme dans les réglages', 'flib-seo' ), value: 'defaut' },
								{ label: __( 'Afficher', 'flib-seo' ), value: 'oui' },
								{ label: __( 'Masquer', 'flib-seo' ), value: 'non' },
							],
						} )
					)
				),
				el( Disabled, null, el( ServerSideRender, { block: 'flib-seo/fil-ariane', attributes: a } ) )
			);
		},
		save: function () {
			return null;
		},
	} );
} )( window.wp );
