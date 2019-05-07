/**
 * WordPress dependencies
 */
const { Component, Fragment } = wp.element;
const { withSelect }          = wp.data;

/**
 * Internal dependencies
 */
import SponsorsInspectorControls from './inspector-controls';
import SponsorsBlockControls     from './block-controls';
import { LayoutToolbar }         from '../shared/post-list';
import { ICON }                  from './index';
import { WC_BLOCKS_STORE } from '../blocks-store';

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
				<SponsorsBlockControls
					icon={ ICON }
					{ ...this.props }
				/>
				{ mode &&
					<Fragment>
						<SponsorsInspectorControls { ...this.props } />
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

export const edit = withSelect( sponsorSelect )( SponsorsEdit );
