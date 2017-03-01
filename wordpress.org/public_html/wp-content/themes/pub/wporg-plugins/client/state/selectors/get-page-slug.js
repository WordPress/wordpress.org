/**
 * Internal dependencies.
 */
import { getPath } from 'state/selectors';

/**
 *
 * @param {Object} state Global state object.
 * @return {String} Page slug.
 */
export const getPageSlug = ( state ) => getPath( state ).replace( /\/$/, '' );
export default getPageSlug;
