/**
 * External dependencies.
 */
import React, { PropTypes } from 'react';
import { connect } from 'react-redux';
import { identity } from 'lodash';
import { localize } from 'i18n-calypso';

/**
 * Internal dependencies.
 */
import MenuItem from './menu-item';
import SearchForm from 'components/search-form';

export const MainNavigation = ( { menuItems, translate } ) => (
	<nav id="site-navigation" className="main-navigation" role="navigation">
		<button
			className="menu-toggle dashicons dashicons-arrow-down-alt2"
			aria-controls="primary-menu"
			aria-expanded="false"
			aria-label={ translate( 'Primary Menu' ) }
		/>
		<div id="primary-menu" className="menu">
			<ul>
				{ menuItems.map( ( menuItem, key ) => <MenuItem key={ key } item={ menuItem } /> ) }
				<li><SearchForm /></li>
			</ul>
		</div>
	</nav>
);

MainNavigation.propTypes = {
	menuItems: PropTypes.arrayOf( PropTypes.object ),
	translate: PropTypes.func,
};

MainNavigation.defaultProps = {
	menuItems: [],
	translate: identity,
};

export default localize( connect(
	( state, { translate } ) => ( {
		menuItems: [
			{
				path: 'browse/favorites/',
				label: translate( 'My Favorites' ),
			},
			{
				path: 'browse/beta/',
				label: translate( 'Beta Testing' ),
			},
			{
				path: 'developers/',
				label: translate( 'Developers' ),
			},
		],
	} ),
)( MainNavigation ) );
