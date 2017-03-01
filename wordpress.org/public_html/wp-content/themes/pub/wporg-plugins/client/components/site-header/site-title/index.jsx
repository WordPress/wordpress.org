/**
 * External dependencies.
 */
import React, { PropTypes } from 'react';
import { identity } from 'lodash';
import { IndexLink } from 'react-router';
import { localize } from 'i18n-calypso';
import { withRouter } from 'react-router';

export const SiteTitle = ( { router, translate } ) => (
	router.isActive( '/', true )
		? <h1 className="site-title"><IndexLink to="/" rel="home">{ translate( 'Plugins' ) }</IndexLink></h1>
		: <p className="site-title"><IndexLink to="/" rel="home">{ translate( 'Plugins' ) }</IndexLink></p>
);

SiteTitle.propTypes = {
	router: PropTypes.object,
	translate: PropTypes.func,
};

SiteTitle.defaultProps = {
	router: {},
	translate: identity,
};

export default withRouter( localize( SiteTitle ) );
