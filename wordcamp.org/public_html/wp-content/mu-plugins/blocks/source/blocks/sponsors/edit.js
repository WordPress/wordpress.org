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

const blockData = window.WordCampBlocks.sponsors || {};

/**
 * Top-level component for the editing UI for the block.
 */
class SponsorsEdit extends Component {
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

const sponsorSelect = ( select ) => {
	const { getEntities, getSiteSettings } = select( WC_BLOCKS_STORE );

	const entities = {
		wcb_sponsor       : getEntities( 'postType', 'wcb_sponsor', { _embed: true } ),
		wcb_sponsor_level : getEntities( 'taxonomy', 'wcb_sponsor_level' ),
	};

	return {
		blockData    : blockData,
		entities     : entities,
		siteSettings : getSiteSettings(),
	};
};

export const Edit = withSelect( sponsorSelect )( SponsorsEdit );
