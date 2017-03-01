/**
 * External dependencies.
 */
import React, { PropTypes } from 'react';
import { map } from 'lodash';

/**
 *
 * @param {Object} contributors Plugin contributors.
 * @return {*} React Element
 * @constructor
 */
export const DeveloperList = ( { contributors } ) => {
	if ( contributors ) {
		return (
			<ul className="plugin-developers">
				{ map( contributors, ( contributor, index ) =>
					<li key={ index }>
						<a href={ contributor.profile }>
							<img className="avatar avatar-32 photo" height="32" width="32" src={ contributor.avatar } />
							{ contributor.display_name }
						</a>
					</li>
				) }
			</ul>
		);
	}

	return null;
};

DeveloperList.propTypes = {
	contributors: PropTypes.object,
};

DeveloperList.defaultProps = {
	contributors: {},
};

export default DeveloperList;
