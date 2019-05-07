/**
 * WordPress dependencies
 */
const { withSelect }          = wp.data;
const { Component, Fragment } = wp.element;

/**
 * Internal dependencies
 */
import SessionsBlockControls     from './block-controls';
import SessionsInspectorControls from './inspector-controls';
import { LayoutToolbar }         from '../shared/post-list';
import { ICON }                  from './index';
import { WC_BLOCKS_STORE }       from '../blocks-store';

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
				<SessionsBlockControls
					icon={ ICON }
					{ ...this.props }
				/>
				{ mode &&
					<Fragment>
						<SessionsInspectorControls { ...this.props } />
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

	const entities = {
		wcb_session          : getEntities( 'postType', 'wcb_session', { _embed: true } ),
		wcb_track            : getEntities( 'taxonomy', 'wcb_track' ),
		wcb_session_category : getEntities( 'taxonomy', 'wcb_session_category' ),
	};

	return {
		blockData,
		entities,
	};
};

export const edit = withSelect( sessionsSelect )( SessionsEdit );
