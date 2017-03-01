import Api from 'modules/api';
import {
	FAVORITE_PLUGIN,
	GET_FAVORITES,
	UNFAVORITE_PLUGIN,
} from 'state/action-types';

export const getFavorites = ( plugin ) => ( dispatch ) => {
	// TODO: Move to WPAPI
	Api.get( '/plugins/v1/plugin/' + plugin + '/favorite' )
		.then( () => dispatch( {
			type: GET_FAVORITES,
			plugin,
		} ) );
};

export const favoritePlugin = ( plugin ) => ( dispatch ) => {
	Api.plugin().id( plugin.id ).update( { favorite: plugin.slug } )
		.then( () => dispatch( {
			type: FAVORITE_PLUGIN,
			plugin,
		} ) );
};

export const unfavoritePlugin = ( plugin ) => ( dispatch ) => {
	// TODO: Move to WPAPI
	Api.get( '/plugins/v1/plugin/' + plugin + '/favorite', { unfavorite: 1 } )
		.then( () => dispatch( {
			type: UNFAVORITE_PLUGIN,
			plugin,
		} ) );
};
