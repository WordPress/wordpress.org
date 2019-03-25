/**
 * External dependencies
 */
import classnames from 'classnames';

/**
 * WordPress dependencies
 */
const { Button, Placeholder } = wp.components;
const { __ } = wp.i18n;

/**
 * Internal dependencies
 */
import { BlockControls, PlaceholderNoContent, PlaceholderSpecificMode } from '../shared/block-controls';
import SessionsBlockContent from './block-content';
import SessionsSelect from './sessions-select';

const LABEL = __( 'Sessions', 'wordcamporg' );

class SessionsBlockControls extends BlockControls {
	render() {
		const { icon, attributes, setAttributes, sessionPosts } = this.props;
		const { mode } = attributes;

		const hasPosts = Array.isArray( sessionPosts ) && sessionPosts.length;

		if ( mode && ! hasPosts ) {
			return (
				<PlaceholderNoContent
					icon={ icon }
					label={ LABEL }
					loading={ ! Array.isArray( sessionPosts ) }
				/>
			);
		}

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
