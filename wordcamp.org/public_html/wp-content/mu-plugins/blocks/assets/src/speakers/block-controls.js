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
import SpeakersBlockContent                       from './block-content';
import SpeakersSelect                             from './speakers-select';
import { LABEL }                                  from './index';

/**
 * Component for displaying a UI within the block.
 */
class SpeakersBlockControls extends BlockControls {
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
					<SpeakersBlockContent { ...this.props } />
				);
				break;

			case 'wcb_speaker' :
			case 'wcb_speaker_group' :
				output = (
					<PlaceholderSpecificMode
						label={ this.getModeLabel( mode ) }
						icon={ icon }
						content={
							<SpeakersBlockContent { ...this.props } />
						}
						placeholderChildren={
							<SpeakersSelect { ...this.props } />
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
							<SpeakersSelect
								icon={ icon }
								label={ __( 'Choose specific speakers or groups', 'wordcamporg' ) }
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

export default SpeakersBlockControls;
