/**
 * External dependencies.
 */
import { filter, includes } from 'lodash';

/**
 *
 * @param {Object} state    Global state object.
 * @param {String} taxonomy Taxonomy slug
 * @param {Number} termId   Term Id.
 * @return {Array} Plugins.
 */
export const getPluginsForTerm = ( state, taxonomy, termId ) => (
	filter( state.plugins, ( plugin ) => includes( plugin[ taxonomy ], termId ) )
);

export default getPluginsForTerm;
