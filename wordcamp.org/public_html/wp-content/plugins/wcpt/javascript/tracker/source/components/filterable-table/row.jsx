import React, { PropTypes } from 'react';

export default React.createClass( {
	propTypes : {
		columns      : PropTypes.object,
		row          : PropTypes.object,
		customRender : PropTypes.object,
	},

	getDefaultProps : function() {
		return {
			columns      : {},
			row          : {},
			customRender : {},
		};
	},

	render : function() {
		const cells = [];

		/*
		 * Loop through the display columns instead of the row, because the row might have meta data that
		 * shouldn't be displayed, like URLs.
		 */
		for ( let columnName in this.props.columns ) {
			let cellContent = '';

			if ( ! this.props.columns.hasOwnProperty( columnName ) ) {
				continue;
			}

			if ( this.props.row[ columnName + 'Url' ] ) {
				cellContent = <a href={ this.props.row[ columnName + 'Url' ] }>{ this.props.row[ columnName ] }</a>;
			} else if ( this.props.customRender[ columnName ] ) {
				cellContent = this.props.customRender[ columnName ]( this.props.row, this.props.row[ columnName ] );
			} else {
				cellContent = this.props.row[ columnName ];
			}


			cells.push(
				<td className={ columnName } key={ columnName }>
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
