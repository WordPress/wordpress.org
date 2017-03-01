/**
 * External dependencies.
 */
import { find } from 'lodash';

/**
 * Internal dependencies.
 */
import { getPlugins, getSlug } from 'state/selectors';

/**
 *
 * @param {Object} state Global state object.
 * @param {String} slug Plugin slug.
 * @return {Object} Plugin.
 */
export const getPlugin = ( state, slug = getSlug( state ) ) => find( getPlugins( state ), { slug } ) || null;
export default getPlugin;
