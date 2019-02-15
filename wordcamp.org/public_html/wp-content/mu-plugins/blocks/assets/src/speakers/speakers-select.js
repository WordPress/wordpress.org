/**
 * External dependencies
 */
import { includes } from 'lodash';

/**
 * WordPress dependencies
 */
const { Dashicon } = wp.components;
const { Component } = wp.element;
const { decodeEntities } = wp.htmlEntities;
const { __ } = wp.i18n;

/**
 * Internal dependencies
 */
import AvatarImage from '../shared/avatar';
import VersatileSelect from '../shared/versatile-select';

class SpeakersSelect extends Component {
	constructor( props ) {
		super( props );

		this.state = {
			posts   : [],
			terms   : [],
			loading : true,
		};

		this.buildSelectOptions = this.buildSelectOptions.bind( this );
		this.isOptionDisabled = this.isOptionDisabled.bind( this );
	}

	componentWillMount() {
		this.isStillMounted = true;

		const { allSpeakerPosts, allSpeakerTerms } = this.props;

		const parsedPosts = allSpeakerPosts.then(
			( fetchedPosts ) => {
				const posts = fetchedPosts.map( ( post ) => {
					return {
						label  : decodeEntities( post.title.rendered.trim() ) || __( '(Untitled)', 'wordcamporg' ),
						value  : post.id,
						type   : 'post',
						avatar : post.avatar_urls[ '24' ],
					};
				} );

				if ( this.isStillMounted ) {
					this.setState( { posts } );
				}
			}
		);

		const parsedTerms = allSpeakerTerms.then(
			( fetchedTerms ) => {
				const terms = fetchedTerms.map( ( term ) => {
					return {
						label : decodeEntities( term.name ) || __( '(Untitled)', 'wordcamporg' ),
						value : term.id,
						type  : 'term',
						count : term.count,
					};
				} );

				if ( this.isStillMounted ) {
					this.setState( { terms } );
				}
			}
		);

		Promise.all( [ parsedPosts, parsedTerms ] ).then( () => {
			this.setState( { loading: false } );
		} );
	}

	componentWillUnmount() {
		this.isStillMounted = false;
	}

	buildSelectOptions( mode ) {
		const { posts, terms } = this.state;
		const options = [];

		if ( ! mode || 'specific_terms' === mode ) {
			options.push( {
				label   : __( 'Groups', 'wordcamporg' ),
				options : terms,
			} );
		}

		if ( ! mode || 'specific_posts' === mode ) {
			options.push( {
				label   : __( 'Speakers', 'wordcamporg' ),
				options : posts,
			} );
		}

		return options;
	}

	isOptionDisabled( option, selected ) {
		const { mode } = this.props.attributes;
		let chosen;

		if ( 'loading' === option.type ) {
			return true;
		}

		if ( Array.isArray( selected ) && selected.length ) {
			chosen = selected[ 0 ].type;
		}

		if ( 'specific_terms' === mode && 'post' === option.type ) {
			return true;
		}

		if ( 'specific_posts' === mode && 'term' === option.type ) {
			return true;
		}

		return chosen && chosen !== option.type;
	}

	render() {
		const { label, attributes, setAttributes } = this.props;
		const { mode, post_ids, term_ids } = attributes;
		const options = this.buildSelectOptions( mode );

		let value = [];

		if ( 'specific_posts' === mode && options.length ) {
			value = options[ 0 ].options.filter( ( option ) => {
				return includes( post_ids, option.value );
			} );
		} else if ( 'specific_terms' === mode && options.length ) {
			value = options[ 0 ].options.filter( ( option ) => {
				return includes( term_ids, option.value );
			} );
		}

		return (
			<VersatileSelect
				className="wordcamp-speakers-select"
				label={ label }
				value={ value }
				onChange={ ( selectedOptions ) => {
					const newValue = selectedOptions.map( ( option ) => option.value );

					if ( newValue.length ) {
						const chosen = selectedOptions[ 0 ].type;

						switch ( chosen ) {
							case 'post' :
								setAttributes( {
									mode     : 'specific_posts',
									post_ids : newValue,
								} );
								break;

							case 'term' :
								setAttributes( {
									mode     : 'specific_terms',
									term_ids : newValue,
								} );
								break;
						}
					} else {
						setAttributes( {
							mode     : '',
							post_ids : [],
							term_ids : [],
						} );
					}
				} }
				selectProps={ {
					isLoading        : this.state.loading,
					options          : options,
					isMulti          : true,
					isOptionDisabled : this.isOptionDisabled,
					formatGroupLabel : ( groupData ) => {
						return (
							<span className="wordcamp-speakers-select-option-group-label">
								{ groupData.label }
							</span>
						);
					},
					formatOptionLabel: ( optionData ) => {
						return (
							<SpeakersOption { ...optionData } />
						);
					},
				} }
			/>
		);
	}
}

function SpeakersOption( { type, label = '', avatar = '', count = 0 } ) {
	let image, content;

	switch ( type ) {
		case 'post' :
			image = (
				<AvatarImage
					className="wordcamp-speakers-select-option-avatar"
					name={ label }
					size={ 24 }
					url={ avatar }
				/>
			);
			content = (
				<span className="wordcamp-speakers-select-option-label">
					{ label }
				</span>
			);
			break;

		case 'term' :
			image = (
				<div className="wordcamp-speakers-select-option-icon-container">
					<Dashicon
						className="wordcamp-speakers-select-option-icon"
						icon={ 'megaphone' }
						size={ 16 }
					/>
				</div>
			);
			content = (
				<span className="wordcamp-speakers-select-option-label">
					{ label }
					<span className="wordcamp-speakers-select-option-label-term-count">
						{ count }
					</span>
				</span>
			);
			break;
	}

	return (
		<div className="wordcamp-speakers-select-option">
			{ image }
			{ content }
		</div>
	);
}

export default SpeakersSelect;
