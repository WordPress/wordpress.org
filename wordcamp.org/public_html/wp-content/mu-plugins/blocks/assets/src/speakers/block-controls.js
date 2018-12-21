/**
 * External dependencies
 */
import { find } from 'lodash';

/**
 * WordPress dependencies
 */
const { Button, Placeholder, Spinner } = wp.components;
const { Component, Fragment } = wp.element;
const { __ } = wp.i18n;

/**
 * Internal dependencies
 */
import SpeakersBlockContent from './block-content';
import SpeakersSelect from './speakers-select';

const data = window.WordCampBlocks.speakers || {};

class SpeakersBlockControls extends Component {
	render() {
		const { attributes, setAttributes, speakerPosts } = this.props;
		const { mode } = attributes;
		const { options } = data;

		const hasPosts = Array.isArray( speakerPosts ) && speakerPosts.length;

		if ( mode && ! hasPosts ) {
			return (
				<Placeholder
					icon="megaphone"
					label={ __( 'Speakers', 'wordcamporg' ) }
				>
					{ ! Array.isArray( speakerPosts ) ?
						<Spinner /> :
						__( 'No posts found.', 'wordcamporg' )
					}
				</Placeholder>
			);
		}

		switch ( mode ) {
			case 'all' :
				return (
					<SpeakersBlockContent { ...this.props } />
				);

			case 'specific_posts' :
				const postsLabel = find( options.mode, ( modeOption ) => {
					return 'specific_posts' === modeOption.value;
				} ).label;

				return (
					<Fragment>
						<SpeakersBlockContent { ...this.props } />
						<Placeholder
							icon="megaphone"
							label={ postsLabel }
						>
							<SpeakersSelect { ...this.props } />
						</Placeholder>
					</Fragment>
				);

			case 'specific_terms' :
				const termsLabel = find( options.mode, ( modeOption ) => {
					return 'specific_terms' === modeOption.value;
				} ).label;

				return (
					<Fragment>
						<SpeakersBlockContent { ...this.props } />
						<Placeholder
							icon="megaphone"
							label={ termsLabel }
						>
							<SpeakersSelect { ...this.props } />
						</Placeholder>
					</Fragment>
				);
		}

		return (
			<Placeholder
				icon={ 'megaphone' }
				label={ __( 'Speakers', 'wordcamporg' ) }
			>
				<div className={ 'wordcamp-block-speakers-mode-option' }>
					<Button
						isDefault
						isLarge
						onClick={ () => {
							setAttributes( { mode: 'all' } );
						} }
					>
						{ find( options.mode, ( modeOption ) => {
							return 'all' === modeOption.value;
						} ).label }
					</Button>
				</div>

				<div className={ 'wordcamp-block-speakers-mode-option' }>
					<SpeakersSelect
						label={ __( 'Choose specific speakers or groups', 'wordcamporg' ) }
						{ ...this.props }
					/>
				</div>
			</Placeholder>
		);
	}
}

export default SpeakersBlockControls;
