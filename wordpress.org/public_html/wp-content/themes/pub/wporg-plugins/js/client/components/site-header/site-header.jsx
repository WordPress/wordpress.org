import React from 'react';

/**
 * Internal dependencies.
 */
import SearchForm from 'components/search-form';
import SiteDescription from './site-description';
import SiteTitle from './site-title';
import MainNavigation from './main-navigation';

export default React.createClass( {
	displayName: 'SiteHeader',

	render() {
		const classes = ['site-header'];
		classes.push( this.props.isHome ? 'home' : '' );

		return (
			<header id="masthead" className={ classes.join( ' ' ) } role="banner">
				<div className="site-branding">
					<SiteTitle isHome={ this.props.isHome } />
					<SiteDescription isHome={ this.props.isHome } />
					{ this.props.isHome ? <SearchForm /> : <MainNavigation /> }
				</div>
			</header>
		)
	}
} );