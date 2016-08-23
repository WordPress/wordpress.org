import React from 'react';

import MenuItem from './menu-item';
import SearchForm from 'components/search-form';

export default React.createClass( {
	displayName: 'MainNavigation',

	getDefaultProps() {
		return {
			menuItems: [
				{
					path: 'browse/favorites/',
					label: 'My Favorites'
				},
				{
					path: 'browse/beta/',
					label: 'Beta Testing'
				},
				{
					path: 'developers/',
					label: 'Developers'
				}
			]
		}
	},

	render() {
		var menuItems = this.props.menuItems.map( ( menuItem, key ) => <MenuItem key={ key } item={ menuItem } /> );

		return (
			<nav id="site-navigation" className="main-navigation" role="navigation">
				<button className="menu-toggle dashicons dashicons-arrow-down-alt2" aria-controls="primary-menu" aria-expanded="false" aria-label="Primary Menu"></button>
				<div id="primary-menu" className="menu">
					<ul>
						{ menuItems }
						<li>
							<SearchForm searchTerm={ this.props.searchTerm } />
						</li>
					</ul>
				</div>
			</nav>
		)
	}
} );
