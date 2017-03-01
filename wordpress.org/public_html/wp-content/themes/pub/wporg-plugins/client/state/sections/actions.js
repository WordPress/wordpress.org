/**
 * External dependencies.
 */
import { isEmpty } from 'lodash';

/**
 * Internal dependencies.
 */
import Api from 'modules/api';
import {
	PLUGINS_RECEIVE,
	PLUGINS_REQUEST,
	PLUGINS_REQUEST_SUCCESS,
	PLUGINS_REQUEST_FAILURE,
	SECTIONS_RECEIVE,
	SECTIONS_REQUEST,
	SECTIONS_REQUEST_SUCCESS,
	SECTIONS_REQUEST_FAILURE,
} from 'state/action-types';
import { getSection, hasSections, isFetchingSections } from 'state/selectors';

const pluginsForSections = [];

export const fetchSections = () => ( dispatch, getState ) => {
	const state = getState();
	if ( hasSections( state ) || isFetchingSections( state ) ) {
		return;
	}

	dispatch( {
		type: SECTIONS_REQUEST,
	} );

	Api.sections()
		.then( ( sections ) => {
			dispatch( {
				type: SECTIONS_REQUEST_SUCCESS,
			} );
			dispatch( {
				type: SECTIONS_RECEIVE,
				sections,
			} );

			// eslint-disable-next-line no-use-before-define
			pluginsForSections.map( ( slug ) => dispatch( fetchSection( slug ) ) );
		} )
		.catch( ( error ) => dispatch( {
			type: SECTIONS_REQUEST_FAILURE,
			error,
		} ) );
};

export const fetchSection = ( slug ) => ( dispatch, getState ) => {
	const section = getSection( getState(), slug );

	if ( isEmpty( section ) ) {
		pluginsForSections.push( slug );
		return dispatch( fetchSections() );
	}

	dispatch( {
		type: PLUGINS_REQUEST,
		slug,
	} );

	Api.plugin().param( 'plugin_section', section.id )
		.then( ( plugins ) => {
			dispatch( {
				type: PLUGINS_REQUEST_SUCCESS,
				slug,
			} );
			dispatch( {
				type: PLUGINS_RECEIVE,
				plugins,
				slug,
			} );
		} )
		.catch( ( error ) => dispatch( {
			type: PLUGINS_REQUEST_FAILURE,
			error,
			slug,
		} ) );
};
