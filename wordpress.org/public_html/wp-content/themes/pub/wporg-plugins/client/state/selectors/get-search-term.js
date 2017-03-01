/**
 * Internal dependencies.
 */
import { getParams } from 'state/selectors';

/**
 *
 * @param {Object} state Global state object.
 * @return {String} Search term.
 */
export const getSearchTerm = ( state ) => getParams( state ).search;
export default getSearchTerm;
