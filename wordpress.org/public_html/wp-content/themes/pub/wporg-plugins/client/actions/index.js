import Api from 'modules/api';
import {
	FAVORITE_PLUGIN,
	GET_BROWSE,
	GET_FAVORITES,
	GET_PAGE,
	GET_PLUGIN,
	SEARCH_PLUGINS,
	UNFAVORITE_PLUGIN
} from './action-types';

export const getBrowse = ( type ) => ( dispatch ) => {
	Api.get( '/plugins/v1/query-plugins', { browse: type }, ( data, error ) => {
		if ( ! data.plugins.length || error ) {
			return;
		}

		dispatch( {
			type: GET_BROWSE,
			plugins: data.plugins,
			term: type
		} );
	} );
};

export const getFavorites = ( slug ) => ( dispatch ) => {
	Api.get( '/plugins/v1/plugin/' + slug + '/favorite', {}, ( data, error ) => {
		if ( ! data.favorite || error ) {
			return;
		}

		dispatch( {
			type: GET_FAVORITES,
			plugin: slug
		} );
	} );
};

export const favoritePlugin = ( slug ) => ( dispatch ) => {
	Api.get( '/plugins/v1/plugin/' + slug + '/favorite', { favorite: 1 }, ( data, error ) => {
		if ( ! data.favorite || error ) {
			return;
		}

		dispatch( {
			type: FAVORITE_PLUGIN,
			plugin: slug
		} );
	} );
};

export const unfavoritePlugin = ( slug ) => ( dispatch ) => {
	Api.get( '/plugins/v1/plugin/' + slug + '/favorite', { unfavorite: 1 }, ( data, error ) => {
		if ( ! data.favorite || error ) {
			return;
		}

		dispatch( {
			type: UNFAVORITE_PLUGIN,
			plugin: slug
		} );
	} );
};

export const getPage = ( slug ) => ( dispatch ) => {
	Api.get( '/wp/v2/pages', { filter: { name: slug } }, ( data, error ) => {
		if ( ! data.length || error ) {
			return;
		}

		dispatch( {
			type: GET_PAGE,
			page: data[0]
		} );
	} );
};

export const getPlugin = ( slug ) => ( dispatch ) => {
	Api.get( '/plugins/v1/plugin/' + slug, {}, ( data, error ) => {
		if ( ! data || error ) {
			return;
		}

		dispatch( {
			type: GET_PLUGIN,
			plugin: data
		} );
	} );
};

export const searchPlugins = ( searchTerm ) => ( dispatch ) => {
	Api.get( '/wp/v2/plugin', { search: searchTerm }, ( data, error ) => {
		if ( ! data || error ) {
			return;
		}

		dispatch( {
			type: SEARCH_PLUGINS,
			searchTerm: searchTerm,
			plugins: data
		} );
	} );
};
