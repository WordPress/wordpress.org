import { get, includes, intersection } from 'lodash';

/**
 * WordPress dependencies.
 */
const { decodeEntities } = wp.htmlEntities;
const { __ } = wp.i18n;

/**
 * Internal dependencies.
 */
import { BlockControls, PlaceholderNoContent } from "../shared/block-controls";
import SponsorBlockContent from './block-content';
import ItemSelect from '../shared/item-select'
import { LABEL } from './index';

const { Button, Placeholder } = wp.components;

function SponsorOption( option ) {
	if ( 'post' === option.type ) {
		return SponsorPostOption( option );
	} else {
		return SponsorLevelOption( option );
	}
}

function SponsorPostOption( sponsor ) {
	const imageUrl = get( sponsor.featuredImageData, 'sizes.thumbnail.source_url', false );
	return(
		<span>
			{ sponsor.label }
		</span>
	);
}

function SponsorLevelOption( sponsorLevel ) {
	return(
		<span className="wordcamp-item-select-option-label">
			{ sponsorLevel.label }
			<span className="wordcamp-item-select-option-label-term-count">
				{ sponsorLevel.count }
			</span>
		</span>
	);
}

/**
 * Implements sponsor block controls.
 */
class SponsorBlockControls extends BlockControls {

	constructor( props ) {
		super(props);
		this.state = {
			posts   : [],
			terms   : [],
			loading : true,
			selectedPosts : [],
			sponsorTermOrder : []
		};
	}

	/**
	 * Set selectedPosts in state so that SponsorsContentBlock can use them.
	 */
	setSelectedPosts() {
		const { fetchedPosts, sponsorTermOrder } = this.state;
		const { attributes } = this.props;
		const { post_ids, term_ids, mode } = attributes;

		if ( ! fetchedPosts || ! fetchedPosts.length ) {
			return;
		}

		const selectedPosts = [];
		for ( const post of fetchedPosts ) {
			if ( ! post.hasOwnProperty( 'id' ) ) {
				continue;
			}
			switch ( mode ) {
				case 'all':
					selectedPosts.push( post );
					break;
				case 'specific_posts':
					if ( -1 !== post_ids.indexOf( post.id ) ) {
						selectedPosts.push( post );
					}
					break;
				case 'specific_terms':
					if ( intersection( term_ids, post.sponsor_level || [] ) .length ) {
						selectedPosts.push( post );
					}
					break;
				default :
					break;
			}
		}
		this.setState( { selectedPosts } );
	}

	/**
	 * Initialize posts and terms arrays and sets loading state till promises
	 * are not resolved. We will also set posts and terms in array that we want to display.
	 */
	componentWillMount() {
		this.isStillMounted = true;

		const { sponsorPosts, sponsorLevels, siteSettings } = this.props;

		const parsedPosts = sponsorPosts.then(
			( fetchedPosts ) => {

				const posts = fetchedPosts.map(
					( post ) => {

						return {
							label: decodeEntities(post.title.rendered.trim()) ||
								__('(Untitled)', 'wordcamporg'),
							value: post.id,
							type: 'post',
							featuredImageData: get( post,
							'_embedded.wp:featuredmedia[0].media_details', '' ),
						}
					}
				);
				if ( this.isStillMounted ) {
					this.setState( { fetchedPosts } );
					this.setState( { posts } );
				}
			}
		).catch( (e) => {
			console.error("Error fetching data", e );
		});

		const parsedTerms = sponsorLevels.then(
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
					this.setState( { fetchedTerms } );
					this.setState( { terms } );
				}
			}
		).catch( (e) => {
			console.error("Error fetching data", e );
		});

		const parsedSettings = siteSettings.then(
			( fetchedSettings ) => {
				const sponsorTermOrder = fetchedSettings.wcb_sponsor_level_order;
				this.setState( { sponsorTermOrder } );
			}
		);

		Promise.all( [ parsedPosts, parsedTerms, parsedSettings ] ).then( () => {
			this.setState( { loading: false } );
			// Enqueue set selected posts in next event loop, so that state is up to date when `setSelectedPosts` method actually runs.
			setTimeout( () => this.setSelectedPosts() );
		} );
	}

	componentWillUnmount() {
		this.isStillMounted = false;
	}

	/**
	 * Sets `mode`, `term_ids` and `post_ids` attribute when `Apply` button is
	 * clicked. Pass `onChange` prop to override.
	 *
	 * @param selectedOptions Array of values, type of selected options
	 */
	onChange( selectedOptions = {} ) {
		const { setAttributes } = this.props;
		const newValue = selectedOptions.item_ids;
		const chosen = selectedOptions.mode;

		if ( newValue && chosen ) {

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

		setTimeout( () => this.setSelectedPosts() );
	}

	/**
	 * Generate options array to be passed to select2.
	 */
	buildSelectOptions() {
		const { posts, terms } = this.state;
		const options = [];

		options.push( {
			label   : __( 'Sponsor Levels', 'wordcamporg' ),
			options : terms,
		} );

		options.push( {
			label   : __( 'Sponsors', 'wordcamporg' ),
			options : posts,
		} );

		return options;
	}

	/**
	 * Renders Sponsor Block Control view
	 */
	render() {
		const { icon, attributes, setAttributes, sponsorPosts } = this.props;
		const { mode, post_ids, term_ids } = attributes;
		const { fetchedPosts, posts, terms, selectedPosts, sponsorTermOrder } = this.state;
		const hasPosts = Array.isArray( fetchedPosts ) && fetchedPosts.length;

		// Check if posts are still loading.
		if ( mode && ! hasPosts ) {
			return (
				<PlaceholderNoContent
					label = { LABEL }
					loading = { () => {
						return ! Array.isArray( sponsorPosts );
					} }
				/>
			)
		}

		let selectedOptions = [];

		switch ( mode ) {
			case 'all' :
				break;
			case 'specific_posts' :
				selectedOptions = posts.filter( ( post ) => {
					return includes( post_ids, post.value );
				} );
				break;
			case 'specific_terms' :
				selectedOptions = terms.filter( ( term ) => {
					return includes( term_ids, term.value );
				} );
				break;
			default:
				break;
		}

		return (
			<div>

				<SponsorBlockContent
					selectedPosts={ selectedPosts }
					sponsorTermOrder = { sponsorTermOrder }
					{...this.props}
				/>

				{'all' !== mode &&
					<Placeholder
						icon={ icon }
						label = { __('Sponsors', 'wordcamporg') }
					>
						<div className='' >
							<Button
								isDefault
								isLarge
								onClick = {
									() => {
										setAttributes( { mode: 'all' } );
										setTimeout( () => this.setSelectedPosts() );
									}
								}
							>
								{ __('List all sponsors', 'wordcamporg') }
							</Button>
						</div>
						<div className="wordcamp-block-edit-mode-option">
							<ItemSelect
								buildSelectOptions={
									() => {
										return this.buildSelectOptions()
									}
								}
								isLoading={this.state.loading}
								onChange={
									(selectedOptions) => {
										return this.onChange(selectedOptions);
									}
								}
								selectProps={
									{
										formatOptionLabel: (optionData) => {
											return (
												<SponsorOption {...optionData} />
											);
										}
									}
								}
								label={__('Or, choose specific sponsors or levels',
									'wordcamporg')}
								value={ selectedOptions }
								{...this.props}
							/>
						</div>
					</Placeholder>
				}
			</div>
		);
	}

}

export default SponsorBlockControls;