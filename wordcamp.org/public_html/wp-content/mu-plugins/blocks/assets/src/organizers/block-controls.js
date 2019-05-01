/**
 * External dependencies
 */
import classnames from 'classnames';

/**
 * WordPress dependencies
 */
const { Button, Placeholder } = wp.components;
const { __ }                  = wp.i18n;

/**
 * Internal dependencies
 */
import { BlockControls, PlaceholderSpecificMode } from '../shared/block-controls';
import OrganizersBlockContent                     from './block-content';
import OrganizersSelect                           from './organizers-select';
import { LABEL }                                  from './index';

/**
 * Component for displaying a UI within the block.
 */
class OrganizersBlockControls extends BlockControls {
	/**
	 * Render the internal block UI.
	 *
	 * @return {Element}
	 */
	render() {
		const { icon, attributes, setAttributes } = this.props;
		const { mode } = attributes;

		let output;

		switch ( mode ) {
			case 'all' :
				output = (
					<OrganizersBlockContent { ...this.props } />
				);
				break;

			case 'wcb_organizer' :
			case 'wcb_organizer_team' :
				output = (
					<PlaceholderSpecificMode
						label={ this.getModeLabel( mode ) }
						icon={ icon }
						content={
							<OrganizersBlockContent { ...this.props } />
						}
						placeholderChildren={
							<OrganizersSelect { ...this.props } />
						}
					/>
				);
				break;

			default :
				output = (
					<Placeholder
						className={ classnames( 'wordcamp-block-edit-placeholder', 'wordcamp-block-edit-placeholder-no-mode' ) }
						icon={ icon }
						label={ LABEL }
					>
						<div className="wordcamp-block-edit-mode-option">
							<Button
								isDefault
								isLarge
								onClick={ () => {
									setAttributes( { mode: 'all' } );
								} }
							>
								{ this.getModeLabel( 'all' ) }
							</Button>
						</div>

						<div className="wordcamp-block-edit-mode-option">
							<OrganizersSelect
								label={ __( 'Choose specific organizers or teams', 'wordcamporg' ) }
								{ ...this.props }
							/>
						</div>
					</Placeholder>
				);
				break;
		}

		return output;
	}
}

export default OrganizersBlockControls;
