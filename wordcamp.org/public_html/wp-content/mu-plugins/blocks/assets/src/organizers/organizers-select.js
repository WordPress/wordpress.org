/**
 * External dependencies
 */
import { get, includes } from 'lodash';

/**
 * WordPress dependencies
 */
const { Dashicon }       = wp.components;
const { Component }      = wp.element;
const { decodeEntities } = wp.htmlEntities;
const { __ }             = wp.i18n;

/**
 * Internal dependencies
 */
import AvatarImage from '../shared/avatar';
import ItemSelect  from '../shared/item-select';

class OrganizersSelect extends Component {
	constructor( props ) {
		super( props );

		this.state = {
			wcb_organizer      : [],
			wcb_organizer_team : [],
			loading            : true,
		};

		this.buildSelectOptions = this.buildSelectOptions.bind( this );
	}

	componentWillMount() {
		this.isStillMounted = true;

		const { allOrganizerPosts, allOrganizerTerms } = this.props;

		const parsedPosts = allOrganizerPosts.then(
			( fetchedPosts ) => {
				const posts = fetchedPosts.map( ( post ) => {
					return {
						label  : decodeEntities( post.title.rendered.trim() ) || __( '(Untitled)', 'wordcamporg' ),
						value  : post.id,
						type   : 'wcb_organizer',
						avatar : post.avatar_urls[ '24' ],
					};
				} );

				if ( this.isStillMounted ) {
					this.setState( { wcb_organizer: posts } );
				}
			}
		);

		const parsedTerms = allOrganizerTerms.then(
			( fetchedTerms ) => {
				const terms = fetchedTerms.map( ( term ) => {
					return {
						label : decodeEntities( term.name ) || __( '(Untitled)', 'wordcamporg' ),
						value : term.id,
						type  : 'wcb_organizer_team',
						count : term.count,
					};
				} );

				if ( this.isStillMounted ) {
					this.setState( { wcb_organizer_team: terms } );
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
		const options = [];

		const labels = {
			wcb_organizer      : __( 'Organizers', 'wordcamporg' ),
			wcb_organizer_team : __( 'Teams',      'wordcamporg' ),
		};

		for ( const type in this.state ) {
			if ( ! this.state[ type ].length ) {
				continue;
			}

			if ( mode && type !== mode ) {
				continue;
			}

			options.push( {
				label   : labels[ type ],
				options : this.state[ type ],
			} );
		}

		return options;
	}

	render() {
		const { label, attributes, setAttributes } = this.props;
		const { mode, item_ids }                   = attributes;
		const options                              = this.buildSelectOptions( mode );

		let value = [];

		if ( mode && item_ids.length ) {
			const modeOptions = get( options, '[0].options', [] );

			value = modeOptions.filter( ( option ) => {
				return includes( item_ids, option.value );
			} );
		}

		return (
			<ItemSelect
				className="wordcamp-organizer-select"
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
							<OrganizersOption { ...optionData } />
						);
					},
				} }
			/>
		);
	}
}

function OrganizersOption( { type, label = '', avatar = '', count = 0 } ) {
	let image, content;

	switch ( type ) {
		case 'wcb_organizer' :
			image = (
				<AvatarImage
					className="wordcamp-item-select-option-avatar"
					name={ label }
					size={ 24 }
					url={ avatar }
				/>
			);
			content = (
				<span className="wordcamp-item-select-option-label">
					{ label }
				</span>
			);
			break;

		case 'wcb_organizer_team' :
			image = (
				<div className="wordcamp-item-select-option-icon-container">
					<Dashicon
						className="wordcamp-item-select-option-icon"
						icon={ 'megaphone' }
						size={ 16 }
					/>
				</div>
			);
			content = (
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
			{ image }
			{ content }
		</div>
	);
}

export default OrganizersSelect;
