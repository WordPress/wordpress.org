/**
 * Internal dependencies.
 */
import { getPageSlug } from 'state/selectors';

/**
 *
 * @param {Object} state Global state object.
 * @param {String} slug Page slug.
 * @return {Object} Page.
 */
export const getPage = ( state, slug = getPageSlug( state ) ) => state.pages[ slug ];
export default getPage;
