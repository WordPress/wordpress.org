import React, { PropTypes } from 'react';

export default React.createClass( {
	propTypes : {
		searchQuery       : PropTypes.string,
		handleSearchEvent : PropTypes.func.isRequired,
	},

	getDefaultProps : function() {
		return {
			searchQuery : '',
		};
	},

	/**
	 * Event handler that is called when the user types into the Search field
	 */
	handleSearchEvent : function() {
		this.props.handleSearchEvent( this.refs.searchQueryInput.value );
	},

	render : function() {
		return (
			<form>
				<p>
					<input
						type        = "text"
						placeholder = "Search..."
						value       = { this.props.searchQuery }
						ref         = "searchQueryInput"
						onChange    = { this.handleSearchEvent }
					/>
				</p>
			</form>
		);
	}
} );
