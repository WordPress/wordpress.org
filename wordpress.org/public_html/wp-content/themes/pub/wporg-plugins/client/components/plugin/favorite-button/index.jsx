import React from 'react';

import FavoriteButton from './button';
import {
	getFavorites,
	favoritePlugin,
	unfavoritePlugin
} from 'actions/index';

export default React.createClass( {
	componentDidMount() {
		this.getFavorites();
	},

	componentDidUpdate( previousProps ) {
		if ( this.props.plugin.slug !== previousProps.plugin.slug ) {
			this.getFavorites();
		}
	},

	getFavorites() {
		this.props.dispatch( getFavorites( this.props.plugin.slug ) );
	},

	toggleFavorite( event ) {
		if ( event.target.classList.contains( 'favorited' ) ) {
			this.props.dispatch( unfavoritePlugin( this.props.plugin.slug ) );
		} else {
			this.props.dispatch( favoritePlugin( this.props.plugin.slug ) );
		}
	},

	render() {
		return <FavoriteButton toggleFavorite={ this.toggleFavorite } />;
	}
} );
