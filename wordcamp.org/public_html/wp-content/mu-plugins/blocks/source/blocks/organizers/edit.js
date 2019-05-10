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

const blockData = window.WordCampBlocks.organizers || {};

/**
 * Top-level component for the editing UI for the block.
 */
class OrganizersEdit extends Component {
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

				{ '' !== mode &&
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

const organizerSelect = ( select ) => {
	const { getEntities } = select( WC_BLOCKS_STORE );

	const entities = {
		wcb_organizer      : getEntities( 'postType', 'wcb_organizer', { _embed: true } ),
		wcb_organizer_team : getEntities( 'taxonomy', 'wcb_organizer_team' ),
	};

	return {
		blockData,
		entities,
	};
};

export const Edit = withSelect( organizerSelect )( OrganizersEdit );
