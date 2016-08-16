import React, { PropTypes } from 'react';

export default React.createClass( {
	propTypes : {
		columns : PropTypes.object,
		row     : PropTypes.object,
	},

	getDefaultProps : function() {
		return {
			columns : {},
			row     : {},
		};
	},

	render : function() {
		const cells = [];

		/*
		 * Loop through the display columns instead of the row, because the row might have meta data that
		 * shouldn't be displayed, like URLs.
		 */
		for ( let i in this.props.columns ) {
			let cellContent = '';

			if ( ! this.props.columns.hasOwnProperty( i ) ) {
				continue;
			}

			if ( this.props.row[ i + 'Url' ] ) {
				cellContent = <a href={ this.props.row[ i + 'Url' ] }>{ this.props.row[ i ] }</a>;
			} else {
				cellContent = this.props.row[ i ];
			}

			cells.push(
				<td className={ i } key={ i }>
					{ cellContent }
				</td>
			);
		}

		return (
			<tr>
				{ cells }
			</tr>
		);
	}
} );
