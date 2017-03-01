/**
 * Internal dependencies.
 */
import { getSection, getPluginsForTerm } from 'state/selectors';

/**
 *
 * @param {Object} state Global state object.
 * @param {String} slug  Section slug
 * @return {Array} Plugins.
 */
export const getSectionPlugins = ( state, slug ) => {
	const { id, taxonomy } = getSection( state, slug );

	return getPluginsForTerm( state, taxonomy, id );
};

export default getSectionPlugins;
