( function ( wp ) {
	const { registerBlockType } = wp.blocks;
	const { createElement: el, Fragment } = wp.element;
	const { useBlockProps, InspectorControls } = wp.blockEditor;
	const { PanelBody, SelectControl, RangeControl, ToggleControl, Disabled } = wp.components;
	const ServerSideRender = wp.serverSideRender;
	const { __ } = wp.i18n;

	registerBlockType( 'wporg-jobs/open-to-work', {
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
						{ title: __( 'Settings', 'wporg-jobs-blocks' ) },
						el( SelectControl, {
							label: __( 'Layout', 'wporg-jobs-blocks' ),
							value: attributes.variant,
							options: [
								{ label: __( 'Grid of cards', 'wporg-jobs-blocks' ), value: 'grid' },
								{ label: __( 'Compact cards', 'wporg-jobs-blocks' ), value: 'compact' },
								{ label: __( 'List of names', 'wporg-jobs-blocks' ), value: 'list' },
							],
							onChange: ( variant ) => setAttributes( { variant } ),
							__nextHasNoMarginBottom: true,
						} ),
						el( RangeControl, {
							label: __( 'People shown', 'wporg-jobs-blocks' ),
							value: attributes.perPage,
							min: 1,
							max: 30,
							onChange: ( perPage ) => setAttributes( { perPage } ),
							__nextHasNoMarginBottom: true,
						} ),
						'grid' === attributes.variant &&
							el( ToggleControl, {
								label: __( 'Show pagination', 'wporg-jobs-blocks' ),
								checked: attributes.showPagination,
								onChange: ( showPagination ) => setAttributes( { showPagination } ),
								__nextHasNoMarginBottom: true,
							} )
					)
				),
				el(
					'div',
					blockProps,
					el( Disabled, null, el( ServerSideRender, { block: 'wporg-jobs/open-to-work', attributes, skipBlockSupportAttributes: true } ) )
				)
			);
		},
		save() {
			return null;
		},
	} );
} )( window.wp );
