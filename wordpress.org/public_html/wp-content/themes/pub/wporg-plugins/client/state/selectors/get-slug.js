/**
 * Internal dependencies.
 */
import { getParams } from 'state/selectors';

/**
 *
 * @param {Object} state Global state object.
 * @return {String} Slug.
 */
export const getSlug = ( state ) => getParams( state ).slug;
export default getSlug;
