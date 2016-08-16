import React, { PropTypes } from 'react';
import TableHeader          from './header.jsx';
import TableRow             from './row.jsx';

export default React.createClass( {
	propTypes : {
		columns         : PropTypes.object,
		rows            : PropTypes.array,
		sortField       : PropTypes.string.isRequired,
		sortOrder       : PropTypes.oneOf( [ 'asc', 'desc' ] ),
		handleSortEvent : PropTypes.func.isRequired,
	},

	getDefaultProps : function() {
		return {
			columns   : {},
			rows      : [],
			sortOrder : 'asc',
		};
	},

	render : function() {
		const rows = this.props.rows.map( function( row, index ) {
			return <TableRow columns={ this.props.columns } row={ row } key={ index } />;
		}.bind( this ) );

		return (
			<table className="filterable-table fixed striped">
				<TableHeader
					columns         = { this.props.columns }
					sortField       = { this.props.sortField }
					sortOrder       = { this.props.sortOrder }
					handleSortEvent = { this.props.handleSortEvent }
				/>

				<tbody>
					{ rows }
				</tbody>
			</table>
		);
	}
} );
