( function ( wp ) {
	const { registerBlockType } = wp.blocks;
	const { useSelect }          = wp.data;
	const { SelectControl, PanelBody } = wp.components;
	const { InspectorControls, useBlockProps } = wp.blockEditor;
	const { createElement: el, Fragment }    = wp.element;
	const { __ }                              = wp.i18n;

	registerBlockType( 'formpipe/form', {
		edit: function ( props ) {
			const { attributes, setAttributes } = props;
			const formId = attributes.formId || 0;

			const forms = useSelect( function ( select ) {
				const records = select( 'core' ).getEntityRecords( 'postType', 'formpipe_form', { per_page: -1 } ) || [];
				return records.map( function ( r ) {
					return { value: r.id, label: r.title.rendered || '(untitled)' };
				} );
			}, [] );

			const options = [ { value: 0, label: '— Select —' } ].concat( forms || [] );

			return el( Fragment, null,
				el( InspectorControls, null,
					el( PanelBody, { title: __( 'Form', 'formpipe' ) },
						el( SelectControl, {
							label:  __( 'Form', 'formpipe' ),
							value:  formId,
							options: options,
							onChange: function ( v ) { setAttributes( { formId: parseInt( v, 10 ) || 0 } ); },
						} )
					)
				),
				el( 'div', useBlockProps(),
					formId
						? el( 'p', null, __( 'Form #', 'formpipe' ) + ' ' + formId )
						: el( 'p', null, __( 'Select a form in the sidebar.', 'formpipe' ) )
				)
			);
		},
		save: function () { return null; },
	} );
}( window.wp ) );
