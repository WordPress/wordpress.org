import React from 'react';
import { Link } from 'react-router';

export default React.createClass( {
	displayName: 'MenuItem',

	render() {
		return (
			<li className="page_item">
				<Link to={ this.props.item.path } activeClassName="active">{ this.props.item.label }</Link>
			</li>
		)
	}
} );
