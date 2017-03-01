/**
 * External dependencies.
 */
import { includes } from 'lodash';

/**
 * Internal dependencies.
 */
import { getSlug } from 'state/selectors';

/**
 *
 * @param {Object} state Global state object.
 * @param {String} slug Plugin slug.
 * @return {Boolean} Whether plugin is favorited.
 */
export const isFavorite = ( state, slug = getSlug( state ) ) =>
	pluginDirectory.userid && includes( state.user.items[ pluginDirectory.userid ].favorites, slug );

export default isFavorite;
