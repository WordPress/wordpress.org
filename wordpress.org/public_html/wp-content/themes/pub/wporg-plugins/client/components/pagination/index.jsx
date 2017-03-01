/**
 * External dependencies.
 */
import React, { PropTypes } from 'react';
import { connect } from 'react-redux';
import { Link } from 'react-router';

/**
 * Internal dependencies.
 */
import { getPath } from 'state/selectors';

export const Pagination = ( { current, path, total } ) => {
	const getLinks = () => {
		const links = [];
		let dots = false;

		for ( let index = 1; index <= total; index++ ) {
			if ( index === current ) {
				links.push( <span key={ index } className="page-numbers current">{ index }</span> );
				dots = true;
				continue;
			}
			if ( ( index <= 1 || ( current && index >= current - 1 && index <= current + 1 ) || index > total - 1 ) ) {
				links.push(
					<Link key={ index } className="page-numbers" to={ path + 'page/' + index + '/' }>{ index }</Link>
				);
				dots = true;
			} else if ( dots ) {
				links.push( <span key={ index } className="page-numbers dots">&hellip;</span> );
				dots = false;
			}
		}

		return links;
	};

	return (
		<nav className="navigation pagination" role="navigation">
			<h2 className="screen-reader-text">Posts navigation</h2>
			<div className="nav-links">
				{ getLinks() }
			</div>
		</nav>
	);
};

Pagination.propTypes = {
	current: PropTypes.number,
	path: PropTypes.string.isRequired,
	total: PropTypes.number.isRequired,
};

Pagination.defaultProps = {
	current: 1,
};

export default connect(
	( state ) => ( {
		path: getPath( state ),
	} ),
)( Pagination );
