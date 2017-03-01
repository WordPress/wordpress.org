/**
 * Internal dependencies.
 */
import Api from 'modules/api';
import {
	PLUGINS_RECEIVE,
	PLUGINS_REQUEST,
	PLUGINS_REQUEST_SUCCESS,
	PLUGINS_REQUEST_FAILURE,
} from 'state/action-types';

export const fetchPlugin = ( slug ) => ( dispatch ) => {
	dispatch( {
		type: PLUGINS_REQUEST,
		slug,
	} );

	Api.plugin().param( 'slug', slug )
		.then( ( plugins ) => {
			dispatch( {
				type: PLUGINS_REQUEST_SUCCESS,
				slug,
			} );
			dispatch( {
				type: PLUGINS_RECEIVE,
				plugins,
			} );
		} )
		.catch( dispatch( {
			type: PLUGINS_REQUEST_FAILURE,
			slug,
		} ) );
};
