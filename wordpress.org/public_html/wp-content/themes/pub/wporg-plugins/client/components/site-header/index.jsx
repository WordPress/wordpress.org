/**
 * External dependencies.
 */
import React, { PropTypes } from 'react';
import { withRouter } from 'react-router';

/**
 * Internal dependencies.
 */
import SearchForm from 'components/search-form';
import SiteDescription from './site-description';
import SiteTitle from './site-title';
import MainNavigation from './main-navigation';

export const SiteHeader = ( { router } ) => {
	const classes = [ 'site-header' ];
	const isHome = router.isActive( '/', true );

	if ( isHome ) {
		classes.push( 'home' );
	}

	return (
		<header id="masthead" className={ classes.join( ' ' ) } role="banner">
			<div className="site-branding">
				<SiteTitle />
				<SiteDescription />
				{ isHome ? <SearchForm /> : <MainNavigation /> }
			</div>
		</header>
	);
};

SiteHeader.propTypes = {
	router: PropTypes.object,
};

SiteHeader.defaultProps = {
	router: {},
};

export default withRouter( SiteHeader );
