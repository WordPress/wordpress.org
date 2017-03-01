/**
 * External dependencies.
 */
import React, { PropTypes } from 'react';
import { identity } from 'lodash';
import { localize } from 'i18n-calypso';
import { withRouter } from 'react-router';

/**
 *
 * @param {Boolean}  router    Router object.
 * @param {Function} translate i18n translation function.
 * @return {*}                 Component or null.
 * @constructor
 */
export const SiteDescription = ( { router, translate } ) => {
	if ( router.isActive( '/', true ) ) {
		return (
			<p className="site-description">
				{ translate( 'Extend your WordPress experience with 40,000 plugins.' ) }
			</p>
		);
	}

	return null;
};

SiteDescription.propTypes = {
	router: PropTypes.object,
	translate: PropTypes.func,
};

SiteDescription.defaultProps = {
	router: {},
	translate: identity,
};

export default withRouter( localize( SiteDescription ) );
