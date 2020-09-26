/*
 * There's no need for webpack etc, since we can assume wp-admin users will have modern browsers.
 *
 * JSX is the only thing that'd be nice to have, but it's not worth the tooling costs for just a few fields.
 */

const ReblogFeed = () => {
	const { useSelect, useDispatch } = wp.data;
	const { createElement, useState, useEffect } = wp.element;
	const { TextControl } = wp.components;
	const { PluginDocumentSettingPanel } = wp.editPost;
	const { editPost } = useDispatch( 'core/editor' );

	const postMetaData = useSelect( select => select( 'core/editor' ).getEditedPostAttribute( 'meta' ) || {} );
	const [ sourceUrl, setSourceUrl ] = useState( postMetaData.rbf_source_url );

	useEffect(
		() => {
			editPost( {
				meta: {
					...postMetaData,
					rbf_source_url: sourceUrl,
				},
			} );
		},
		[ sourceUrl ]
	);

	const sourceUrlInput = createElement(
		TextControl,
		{
			label: 'URL',
			value: sourceUrl,
			onChange: setSourceUrl
		}
	);

	return createElement(
		PluginDocumentSettingPanel,
		{
			name: 'source-post',
			title: 'Source Post'
		},
		sourceUrlInput
	);
};


wp.plugins.registerPlugin( 'reblog-feed', {
	render: ReblogFeed,
	icon: null,
} );
