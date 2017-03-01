/**
 * Internal dependencies.
 */
import { getSearchTerm } from 'state/selectors';

/**
 *
 * @param {Object} state Global state object.
 * @param {String} search Search term.
 * @return {String} Search results.
 */
export const getSearchResults = ( state, search = getSearchTerm( state ) ) => state.search[ search ];
export default getSearchResults;
