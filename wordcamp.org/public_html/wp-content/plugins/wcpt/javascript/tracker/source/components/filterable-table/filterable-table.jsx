import React, { PropTypes } from 'react';
import SearchField          from '../search-field/search-field.jsx';
import Table                from './table.jsx';
import TableStore           from '../../stores/table-store.js';

require( './style.scss' );

export default React.createClass( {
	propTypes : {
		initialSortField : PropTypes.string.isRequired,
		columns          : PropTypes.object,
	},

	getDefaultProps : function() {
		return {
			columns : {},
		};
	},

	getInitialState : function() {
		return {
			searchQuery : '',
			sortField   : this.props.initialSortField,
			sortOrder   : 'asc',
		};
	},

	/**
	 * Event handler that updates state in response to input to the Search field
	 *
	 * @param searchQuery
	 */
	handleSearchEvent : function( searchQuery ) {
		this.setState( {
			searchQuery : searchQuery,
		} );
	},

	/**
	 * Event handler that updates state when the user interacts with sorting fields
	 *
	 * @param {string} newSortField
	 */
	handleSortEvent : function( newSortField ) {
		const previousSortField = this.state.sortField;
		let   newSortOrder      = this.state.sortOrder;

		if ( previousSortField === newSortField ) {
			newSortOrder = 'asc' === this.state.sortOrder ? 'desc' : 'asc';
		} else {
			newSortOrder = 'asc';
		}

		this.setState( {
			sortField : newSortField,
			sortOrder : newSortOrder,
		} );
	},

	render : function() {
		const tableRows = TableStore.getFilteredRows( {
			'searchQuery' : this.state.searchQuery,
			'sortOrder'   : this.state.sortOrder,
			'sortField'   : this.state.sortField,
		} );

		return (
			<div>
				<SearchField
					searchQuery       = { this.state.searchQuery }
					handleSearchEvent = { this.handleSearchEvent }
				/>

				<Table
					columns         = { this.props.columns }
					rows            = { tableRows }
					sortField       = { this.state.sortField }
					sortOrder       = { this.state.sortOrder }
					handleSortEvent = { this.handleSortEvent }
				/>
			</div>
		);
	}
} );
