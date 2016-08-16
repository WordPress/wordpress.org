import React, { PropTypes } from 'react';
import SortingIndicator     from '../sorting-indicator/sorting-indicator.jsx';

export default React.createClass( {
	propTypes : {
		isSortedColumn  : PropTypes.bool,
		sortOrder       : PropTypes.oneOf( [ 'asc', 'desc' ] ),
		fieldSlug       : PropTypes.string.isRequired,
		fieldName       : PropTypes.string.isRequired,
		handleSortEvent : PropTypes.func.isRequired,
	},

	getDefaultProps : function() {
		return {
			isSortedColumn : false,
			sortOrder      : 'asc',
		};
	},

	/**
	 * Get the CSS classes for the `th` element
	 *
	 * @returns {string}
	 */
	getClassNames : function() {
		let sortClasses = '';

		if ( this.props.isSortedColumn ) {
			sortClasses = ' sorted ' + this.props.sortOrder;
		}

		return this.props.fieldSlug + sortClasses;
	},

	render : function() {
		const onClick = this.props.handleSortEvent.bind( null, this.props.fieldSlug );

		return (
			<th className={ this.getClassNames() }>
				<button onClick={ onClick } value={ this.props.fieldSlug }>
					{ this.props.fieldName }
				</button>

				{ this.props.isSortedColumn ? <SortingIndicator sortOrder={ this.props.sortOrder } /> : '' }
			</th>
		);
	}
} );
