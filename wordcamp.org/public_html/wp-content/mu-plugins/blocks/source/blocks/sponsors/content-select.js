/**
 * External dependencies
 */
import { every, flatMap, includes } from 'lodash';

/**
 * WordPress dependencies
 */
const { Component } = wp.element;
const { __ }        = wp.i18n;

/**
 * Internal dependencies
 */
import { buildOptions, ItemSelect, Option } from '../../components/item-select';

/**
 * Component for selecting posts/terms for populating the block content.
 */
export class ContentSelect extends Component {
	/**
	 * Run additional operations during component initialization.
	 *
	 * @param {Object} props
	 */
	constructor( props ) {
		super( props );

		this.buildSelectOptions    = this.buildSelectOptions.bind( this );
		this.getCurrentSelectValue = this.getCurrentSelectValue.bind( this );
		this.isLoading             = this.isLoading.bind( this );
	}

	/**
	 * Build or retrieve the options that will populate the Select dropdown.
	 *
	 * @return {Array}
	 */
	buildSelectOptions() {
		const { entities } = this.props;
		const { wcb_sponsor, wcb_sponsor_level } = entities;

		const optionGroups = [
			{
				entityType : 'post',
				type       : 'wcb_sponsor',
				label      : __( 'Sponsors', 'wordcamporg' ),
				items      : wcb_sponsor,
			},
			{
				entityType : 'term',
				type       : 'wcb_sponsor_level',
				label      : __( 'Sponsors Levels', 'wordcamporg' ),
				items      : wcb_sponsor_level,
			},
		];

		return buildOptions( optionGroups );
	}

	/**
	 * Determine the currently selected options in the Select dropdown based on block attributes.
	 *
	 * @return {Array}
	 */
	getCurrentSelectValue() {
		const { attributes } = this.props;
		const { mode, item_ids } = attributes;

		const options = flatMap( this.buildSelectOptions(), ( group ) => {
			return group.options;
		} );

		let value = [];

		if ( mode && item_ids.length ) {
			value = options.filter( ( option ) => {
				return mode === option.type && includes( item_ids, option.value );
			} );
		}

		return value;
	}

	/**
	 * Check if all of the entity groups have finished loading.
	 *
	 * @return {boolean}
	 */
	isLoading() {
		const { entities } = this.props;

		return ! every( entities, ( value ) => {
			return Array.isArray( value );
		} );
	}

	/**
	 * Render an ItemSelect component with block-specific settings.
	 *
	 * @return {Element}
	 */
	render() {
		const { label, icon, setAttributes } = this.props;

		return (
			<ItemSelect
				className="wordcamp-sponsors__select"
				label={ label }
				value={ this.getCurrentSelectValue() }
				onChange={ ( changed ) => setAttributes( changed ) }
				selectProps={ {
					options           : this.buildSelectOptions(),
					isLoading         : this.isLoading(),
					formatOptionLabel : ( optionData ) => {
						return (
							<Option
								icon={ 'wcb_sponsor_level' === optionData.type ? icon : null }
								{ ...optionData }
							/>
						);
					},
				} }
			/>
		);
	}
}
