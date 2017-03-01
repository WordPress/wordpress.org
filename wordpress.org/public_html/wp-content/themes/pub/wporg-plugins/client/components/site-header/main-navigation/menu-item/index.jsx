/**
 * External dependencies.
 */
import React, { PropTypes } from 'react';
import { Link } from 'react-router';

export const MenuItem = ( { item } ) => (
	<li className="page_item">
		<Link to={ item.path } activeClassName="active">{ item.label }</Link>
	</li>
);

MenuItem.propTypes = {
	params: PropTypes.shape( {
		label: PropTypes.string,
		path: PropTypes.string,
	} ),
};

export default MenuItem;
