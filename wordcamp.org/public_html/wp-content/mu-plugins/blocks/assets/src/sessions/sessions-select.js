/**
 * External dependencies
 */
import { get, includes } from 'lodash';

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
import ItemSelect from '../shared/item-select';

class SessionsSelect extends Component {
	constructor( props ) {
		super( props );

		this.state = {
			wcb_session          : [],
			wcb_track            : [],
			wcb_session_category : [],
			loading              : true,
		};

		this.buildSelectOptions = this.buildSelectOptions.bind( this );
	}

	componentWillMount() {
		this.isStillMounted = true;

		const { allSessionPosts, allSessionTracks, allSessionCategories } = this.props;
		const promises = [];

		promises.push( allSessionPosts.then(
			( fetchedPosts ) => {
				const posts = fetchedPosts.map( ( post ) => {
					const image = get( post, '_embedded[\'wp:featuredmedia\'].media_details.sizes.thumbnail.source_url', '' );

					return {
						label : decodeEntities( post.title.rendered.trim() ) || __( '(Untitled)', 'wordcamporg' ),
						value : post.id,
						type  : 'wcb_session',
						image : image,
					};
				} );

				if ( this.isStillMounted ) {
					this.setState( { wcb_session: posts } );
				}
			}
		).catch() );

		[ allSessionTracks, allSessionCategories ].forEach( ( promise ) => {
			promises.push( promise.then(
				( fetchedTerms ) => {
					const terms = fetchedTerms.map( ( term ) => {
						return {
							label : decodeEntities( term.name ) || __( '(Untitled)', 'wordcamporg' ),
							value : term.id,
							type  : term.taxonomy,
							count : term.count || 0,
						};
					} );

					if ( this.isStillMounted ) {
						const [ firstTerm ] = terms;
						this.setState( { [ firstTerm.type ]: terms } );
					}
				}
			).catch() );
		} );

		Promise.all( promises ).then( () => {
			this.setState( { loading: false } );
		} );
	}

	componentWillUnmount() {
		this.isStillMounted = false;
	}

	buildSelectOptions( mode ) {
		const { getOwnPropertyDescriptors } = Object;
		const options = [];

		const labels = {
			wcb_session          : __( 'Sessions', 'wordcamporg' ),
			wcb_track            : __( 'Tracks', 'wordcamporg' ),
			wcb_session_category : __( 'Session Categories', 'wordcamporg' ),
		};

		for ( const type in getOwnPropertyDescriptors( this.state ) ) {
			if ( ( ! mode || type === mode ) && this.state[ type ].length ) {
				options.push( {
					label   : labels[ type ],
					options : this.state[ type ],
				} );
			}
		}

		return options;
	}

	render() {
		const { icon, label, attributes, setAttributes } = this.props;
		const { mode, item_ids } = attributes;
		const options = this.buildSelectOptions( mode );

		let value = [];

		if ( mode && item_ids.length ) {
			const modeOptions = get( options, '[0].options', [] );

			value = modeOptions.filter( ( option ) => {
				return includes( item_ids, option.value );
			} );
		}

		return (
			<ItemSelect
				className="wordcamp-sessions-select"
				label={ label }
				value={ value }
				buildSelectOptions={ this.buildSelectOptions }
				onChange={ ( changed ) => setAttributes( changed ) }
				mode={ mode }
				selectProps={ {
					isLoading        : this.state.loading,
					formatGroupLabel : ( groupData ) => {
						return (
							<span className="wordcamp-item-select-option-group-label">
								{ groupData.label }
							</span>
						);
					},
					formatOptionLabel: ( optionData ) => {
						return (
							<SessionsOption
								icon={ icon }
								{ ...optionData }
							/>
						);
					},
				} }
			/>
		);
	}
}

function SessionsOption( { type, icon, label = '', image = '', count = 0 } ) {
	let optImage, optContent;

	switch ( type ) {
		case 'wcb_session' :
			if ( image ) {
				optImage = (
					<img
						className="wordcamp-item-select-option-image"
						src={ image }
						alt={ label }
						width={ 24 }
						height={ 24 }
					/>
				);
			} else {
				optImage = (
					<div className="wordcamp-item-select-option-icon-container">
						<Dashicon
							className="wordcamp-item-select-option-icon"
							icon={ icon }
							size={ 16 }
						/>
					</div>
				);
			}
			optContent = (
				<span className="wordcamp-item-select-option-label">
					{ label }
				</span>
			);
			break;

		case 'wcb_track' :
		case 'wcb_session_category' :
			optImage = (
				<div className="wordcamp-item-select-option-icon-container">
					<Dashicon
						className="wordcamp-item-select-option-icon"
						icon={ icon }
						size={ 16 }
					/>
				</div>
			);
			optContent = (
				<span className="wordcamp-item-select-option-label">
					{ label }
					<span className="wordcamp-item-select-option-label-term-count">
						{ count }
					</span>
				</span>
			);
			break;
	}

	return (
		<div className="wordcamp-item-select-option">
			{ optImage }
			{ optContent }
		</div>
	);
}

export default SessionsSelect;
