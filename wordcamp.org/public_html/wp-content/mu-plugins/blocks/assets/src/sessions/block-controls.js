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
import SessionsBlockContent                       from './block-content';
import SessionsSelect                             from './sessions-select';
import { LABEL }                                  from './index';

/**
 * Component for displaying a UI within the block.
 */
class SessionsBlockControls extends BlockControls {
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
					<SessionsBlockContent { ...this.props } />
				);
				break;

			case 'wcb_session' :
			case 'wcb_track' :
			case 'wcb_session_category' :
				output = (
					<PlaceholderSpecificMode
						label={ this.getModeLabel( mode ) }
						icon={ icon }
						content={
							<SessionsBlockContent { ...this.props } />
						}
						placeholderChildren={
							<SessionsSelect { ...this.props } />
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
							<SessionsSelect
								icon={ icon }
								label={ __( 'Choose specific sessions, tracks, or categories', 'wordcamporg' ) }
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

export default SessionsBlockControls;
