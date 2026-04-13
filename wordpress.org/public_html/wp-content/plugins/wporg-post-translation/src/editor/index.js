import { registerPlugin } from '@wordpress/plugins';
import { PluginDocumentSettingPanel } from '@wordpress/editor';
import { ToggleControl } from '@wordpress/components';
import { useEntityProp } from '@wordpress/core-data';
import { useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';

function PostTranslationPanel() {
	const postType = useSelect(
		( select ) => select( 'core/editor' ).getCurrentPostType(),
		[]
	);

	const [ meta, setMeta ] = useEntityProp( 'postType', postType, 'meta' );

	const isEnabled = meta?.[ '_post_translation_enabled' ] || false;

	return (
		<PluginDocumentSettingPanel
			name="post-translation"
			title={ __( 'Translation', 'wporg-post-translation' ) }
		>
			<ToggleControl
				__nextHasNoMarginBottom
				label={ __( 'Enable translations', 'wporg-post-translation' ) }
				help={ __(
					'When enabled, content from this post will be exported to GlotPress for translation.',
					'wporg-post-translation'
				) }
				checked={ isEnabled }
				onChange={ ( value ) =>
					setMeta( { ...meta, '_post_translation_enabled': value } )
				}
			/>
		</PluginDocumentSettingPanel>
	);
}

registerPlugin( 'wporg-post-translation', {
	render: PostTranslationPanel,
} );
