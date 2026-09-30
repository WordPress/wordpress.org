/**
 * WordPress dependencies
 */
import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';

/**
 * Internal dependencies
 */
import { getBlockMediaResourceToCheck, isInvalidResource } from './utils';

/**
 * Custom hook to check if a block has an invalid media resource.
 *
 * @param {string} name The block name.
 * @param {Object} attributes The block attributes.
 * @return {Object} Object containing hasInvalidResource, siteUrl, and mediaUrl.
 */
export const useHasInvalidResource = ( name, attributes ) => {
	const siteUrl = useSelect( ( select ) => {
		const siteData = select( coreStore ).getEntityRecord(
			'root',
			'__unstableBase'
		);
		return siteData?.home || siteData?.url || null;
	}, [] );

	const mediaUrl = getBlockMediaResourceToCheck( name, attributes );

	const hasInvalidResource =
		!! mediaUrl && isInvalidResource( mediaUrl, siteUrl );

	return {
		hasInvalidResource,
		siteUrl,
		mediaUrl,
	};
};
