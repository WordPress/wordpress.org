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

const blockData = window.WordCampBlocks.sessions || {};

/**
 * Top-level component for the editing UI for the block.
 */
class SessionsEdit extends Component {
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

const sessionsSelect = ( select ) => {
	const { getEntities } = select( WC_BLOCKS_STORE );

	const sessionArgs = {
		_embed        : true,
		wc_meta_key   : '_wcpt_session_type',
		wc_meta_value : 'session', // Regular sessions only, no breaks/lunch/etc sessions.
	};

	const entities = {
		wcb_session          : getEntities( 'postType', 'wcb_session', sessionArgs ),
		wcb_track            : getEntities( 'taxonomy', 'wcb_track' ),
		wcb_session_category : getEntities( 'taxonomy', 'wcb_session_category' ),
	};

	return {
		blockData,
		entities,
	};
};

export const Edit = withSelect( sessionsSelect )( SessionsEdit );
