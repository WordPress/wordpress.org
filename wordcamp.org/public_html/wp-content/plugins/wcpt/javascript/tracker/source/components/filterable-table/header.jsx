import React, { PropTypes } from 'react';
import TableHeaderCell      from './header-cell.jsx';

export default React.createClass( {
	propTypes : {
		columns         : PropTypes.object,
		sortField       : PropTypes.string.isRequired,
		sortOrder       : PropTypes.oneOf( [ 'asc', 'desc' ] ),
		handleSortEvent : PropTypes.func.isRequired,
	},

	getDefaultProps : function() {
		return {
			columns   : {},
			sortOrder : 'asc',
		};
	},

	render : function() {
		const columns = [];

		for ( let i in this.props.columns ) {
			if ( this.props.columns.hasOwnProperty( i ) ) {
				columns.push(
					<TableHeaderCell
						key             = { i }
						fieldName       = { this.props.columns[ i ] }
						fieldSlug       = { i }
						isSortedColumn  = { i === this.props.sortField }
						sortOrder       = { this.props.sortOrder }
						handleSortEvent = { this.props.handleSortEvent }
					/>
				);
			}
		}

		return (
			<thead>
				<tr>
					{ columns }
				</tr>
			</thead>
		);
	}
} );
