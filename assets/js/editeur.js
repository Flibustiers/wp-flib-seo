/* Flib'SEO : panneau « Référencement » de l'éditeur de blocs (barre latérale), avec aperçu Google. */
( function ( wp ) {
	const el = wp.element.createElement;
	const { __ } = wp.i18n;
	const { useSelect, useDispatch } = wp.data;
	const { TextControl, TextareaControl, CheckboxControl, Button, ExternalLink } = wp.components;
	const { MediaUpload, MediaUploadCheck } = wp.blockEditor;
	const Panneau = ( wp.editor && wp.editor.PluginDocumentSettingPanel ) || ( wp.editPost && wp.editPost.PluginDocumentSettingPanel );
	const d = window.flibSeoEditeur || {};
	const M = d.meta || '_flib_seo_';

	function texteBrut( html ) {
		const div = document.createElement( 'div' );
		div.innerHTML = html.replace( /<!--[\s\S]*?-->/g, ' ' );
		return ( div.textContent || '' ).replace( /\s+/g, ' ' ).trim();
	}

	function couper( texte, max ) {
		if ( texte.length <= max ) {
			return texte;
		}
		return texte.slice( 0, max ).replace( /\s+\S*$/, '' ).replace( /[\s,;:\-–]+$/, '' ) + '…';
	}

	function variables( texte, titre ) {
		return texte.split( '%titre%' ).join( titre ).split( '%site%' ).join( d.site ).split( '%sep%' ).join( d.sep );
	}

	function Reglages() {
		const infos = useSelect( function ( select ) {
			const e = select( 'core/editor' );
			return {
				meta: e.getEditedPostAttribute( 'meta' ) || {},
				titre: e.getEditedPostAttribute( 'title' ) || '',
				extrait: e.getEditedPostAttribute( 'excerpt' ) || '',
				contenu: e.getEditedPostContent ? e.getEditedPostContent() : '',
				lien: e.getPermalink ? e.getPermalink() : '',
			};
		}, [] );
		const { editPost } = useDispatch( 'core/editor' );
		const meta = infos.meta;
		const image = useSelect( function ( select ) {
			const id = meta[ M + 'image' ];
			return id ? select( 'core' ).getMedia( id ) : null;
		}, [ meta[ M + 'image' ] ] );

		function maj( cle, valeur ) {
			const m = {};
			m[ M + cle ] = valeur;
			editPost( { meta: m } );
		}

		const titreAuto = String( d.modele || '' ).split( '{{titre}}' ).join( infos.titre ).trim();
		const descAuto = d.auto ? couper( texteBrut( infos.extrait || infos.contenu ), 155 ) : '';
		const titre = ( meta[ M + 'titre' ] || '' ).trim();
		const desc = ( meta[ M + 'desc' ] || '' ).trim();
		const url = ( infos.lien || d.accueil || '' ).replace( /^https?:\/\//, '' ).replace( /\/$/, '' );

		return el(
			Panneau,
			{ name: 'flib-seo', title: __( 'Référencement', 'flib-seo' ), className: 'flib-seo-panneau' },
			el(
				'div',
				{ className: 'flib-seo-apercu', 'aria-hidden': 'true' },
				el( 'div', { className: 'flib-seo-apercu__url' }, url ),
				el( 'div', { className: 'flib-seo-apercu__titre' }, titre ? variables( titre, infos.titre ) : titreAuto ),
				el( 'div', { className: 'flib-seo-apercu__desc' }, desc || descAuto )
			),
			el( TextControl, {
				label: __( 'Titre pour Google', 'flib-seo' ),
				value: meta[ M + 'titre' ] || '',
				placeholder: titreAuto,
				help: __( 'Vide : titre automatique (texte grisé).', 'flib-seo' ),
				onChange: function ( v ) {
					maj( 'titre', v );
				},
				__nextHasNoMarginBottom: true,
			} ),
			el( TextareaControl, {
				label: __( 'Description pour Google', 'flib-seo' ),
				value: meta[ M + 'desc' ] || '',
				placeholder: descAuto,
				rows: 4,
				help: __( 'Une ou deux phrases qui donnent envie de cliquer.', 'flib-seo' ),
				onChange: function ( v ) {
					maj( 'desc', v );
				},
				__nextHasNoMarginBottom: true,
			} ),
			d.exclu
				? el( 'p', { className: 'description' }, __( 'Ce type de contenu n’est pas indexé (réglage SEO › Indexation).', 'flib-seo' ) )
				: el( CheckboxControl, {
						label: __( 'Masquer de Google', 'flib-seo' ),
						help: __( 'noindex, retiré du plan du site et du llms.txt.', 'flib-seo' ),
						checked: '1' === meta[ M + 'noindex' ],
						onChange: function ( v ) {
							// null = suppression de la méta.
							maj( 'noindex', v ? '1' : null );
						},
						__nextHasNoMarginBottom: true,
				  } ),
			el(
				'details',
				{ className: 'flib-seo-avance', open: !! ( meta[ M + 'canonique' ] || meta[ M + 'image' ] || meta[ M + 'plan_masque' ] ) },
				el( 'summary', null, __( 'Options avancées', 'flib-seo' ) ),
				el( CheckboxControl, {
					label: __( 'Masquer du plan du site', 'flib-seo' ),
					help: __( 'Retiré de la page « Plan du site » seulement, toujours visible dans Google.', 'flib-seo' ),
					checked: '1' === meta[ M + 'plan_masque' ],
					onChange: function ( v ) {
						maj( 'plan_masque', v ? '1' : null );
					},
					__nextHasNoMarginBottom: true,
				} ),
				el( TextControl, {
					label: __( 'URL canonique', 'flib-seo' ),
					type: 'url',
					value: meta[ M + 'canonique' ] || '',
					help: __( 'Seulement si ce contenu copie une autre page.', 'flib-seo' ),
					onChange: function ( v ) {
						maj( 'canonique', v );
					},
					__nextHasNoMarginBottom: true,
				} ),
				el( 'p', { className: 'flib-seo-panneau__label' }, __( 'Image de partage (réseaux sociaux)', 'flib-seo' ) ),
				el(
					MediaUploadCheck,
					null,
					el( MediaUpload, {
						allowedTypes: [ 'image' ],
						value: meta[ M + 'image' ] || 0,
						onSelect: function ( media ) {
							maj( 'image', media.id );
						},
						render: function ( o ) {
							return el(
								'div',
								null,
								image && image.source_url ? el( 'img', { src: image.source_url, alt: '', className: 'flib-seo-panneau__image' } ) : null,
								el( Button, { variant: 'secondary', onClick: o.open }, image ? __( 'Remplacer', 'flib-seo' ) : __( 'Choisir', 'flib-seo' ) ),
								image ? el( Button, { variant: 'link', isDestructive: true, onClick: function () { maj( 'image', 0 ); } }, __( 'Retirer', 'flib-seo' ) ) : null,
								el( 'p', { className: 'description' }, __( 'Vide : image mise en avant.', 'flib-seo' ) )
							);
						},
					} )
				)
			)
		);
	}

	if ( Panneau ) {
		wp.plugins.registerPlugin( 'flib-seo', { render: Reglages, icon: 'search' } );
	}
} )( window.wp );
