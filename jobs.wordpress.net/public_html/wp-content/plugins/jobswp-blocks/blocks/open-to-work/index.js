( function ( wp ) {
	const { registerBlockType } = wp.blocks;
	const { createElement: el, Fragment } = wp.element;
	const { useBlockProps, InspectorControls } = wp.blockEditor;
	const { PanelBody, SelectControl, RangeControl, ToggleControl, Disabled } = wp.components;
	const ServerSideRender = wp.serverSideRender;
	const { __ } = wp.i18n;

	registerBlockType( 'jobswp/open-to-work', {
		edit( { attributes, setAttributes } ) {
			const blockProps = useBlockProps();
			return el(
				Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'Settings', 'jobswp-blocks' ) },
						el( SelectControl, {
							label: __( 'Layout', 'jobswp-blocks' ),
							value: attributes.variant,
							options: [
								{ label: __( 'Grid of cards', 'jobswp-blocks' ), value: 'grid' },
								{ label: __( 'Compact cards', 'jobswp-blocks' ), value: 'compact' },
								{ label: __( 'List of names', 'jobswp-blocks' ), value: 'list' },
							],
							onChange: ( variant ) => setAttributes( { variant } ),
							__nextHasNoMarginBottom: true,
						} ),
						el( RangeControl, {
							label: __( 'People shown', 'jobswp-blocks' ),
							value: attributes.perPage,
							min: 1,
							max: 30,
							onChange: ( perPage ) => setAttributes( { perPage } ),
							__nextHasNoMarginBottom: true,
						} ),
						'grid' === attributes.variant &&
							el( ToggleControl, {
								label: __( 'Show pagination', 'jobswp-blocks' ),
								checked: attributes.showPagination,
								onChange: ( showPagination ) => setAttributes( { showPagination } ),
								__nextHasNoMarginBottom: true,
							} )
					)
				),
				el(
					'div',
					blockProps,
					el( Disabled, null, el( ServerSideRender, { block: 'jobswp/open-to-work', attributes, skipBlockSupportAttributes: true } ) )
				)
			);
		},
		save() {
			return null;
		},
	} );
} )( window.wp );
