import React from 'react';

import SiteTitle from './site-title';
import SiteDescription from './site-description';
import MainNavigation from './main-navigation';
import SearchForm from './search-form';

export default React.createClass( {
	displayName: 'SiteHeader',

	render() {
		return (
			<header id="masthead" className="site-header" role="banner">
				<div className="site-branding">
					<SiteTitle isHome={ this.props.isHome } />
					<SiteDescription isHome={ this.props.isHome } />
					{ this.props.isHome ? <SearchForm /> : <MainNavigation /> }
				</div>
			</header>
		)
	}
} );