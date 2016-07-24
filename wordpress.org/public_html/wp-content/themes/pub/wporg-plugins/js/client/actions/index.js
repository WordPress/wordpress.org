
/**
 * Internal dependencies.
 */
import Api from 'api';
import { GET_PAGE } from './action-types';

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
