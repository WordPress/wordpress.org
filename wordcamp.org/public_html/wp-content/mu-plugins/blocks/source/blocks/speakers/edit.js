/**
 * WordPress dependencies
 */
const { withSelect }          = wp.data;
const { Component, Fragment } = wp.element;

/**
 * Internal dependencies
 */
import { LayoutToolbar }     from '../../components/post-list';
import { WC_BLOCKS_STORE }   from '../../data';
import { BlockControls }     from './block-controls';
import { InspectorControls } from './inspector-controls';
import { ICON }              from './index';

const blockData = window.WordCampBlocks.speakers || {};

/**
 * Top-level component for the editing UI for the block.
 */
class SpeakersEdit extends Component {
	/**
	 * Render the block's editing UI.
	 *
	 * @return {Element}
	 */
	render() {
		const { attributes, setAttributes }  = this.props;
		const { mode, layout }               = attributes;
		const { layout: layoutOptions = {} } = blockData.options;

		return (
			<Fragment>
				<BlockControls
					icon={ ICON }
					{ ...this.props }
				/>
				{ mode &&
					<Fragment>
						<InspectorControls { ...this.props } />
						<LayoutToolbar
							layout={ layout }
							options={ layoutOptions }
							setAttributes={ setAttributes }
						/>
					</Fragment>
				}
			</Fragment>
		);
	}
}

const speakersSelect = ( select ) => {
	const { getEntities } = select( WC_BLOCKS_STORE );

	const entities = {
		wcb_speaker       : getEntities( 'postType', 'wcb_speaker', { _embed: true } ),
		wcb_speaker_group : getEntities( 'taxonomy', 'wcb_speaker_group' ),
		wcb_track         : getEntities( 'taxonomy', 'wcb_track' ),
	};

	return {
		blockData,
		entities,
	};
};

export const Edit = withSelect( speakersSelect )( SpeakersEdit );
