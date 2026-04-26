/**
 * WordPress dependencies
 */
import { isBlobURL } from '@wordpress/blob';
import { getAuthority, isURL } from '@wordpress/url';

// List of blocks to check.
const BLOCKS_TO_CHECK = [
	{
		name: 'core/image',
		urlKey: 'url',
	},
	{
		name: 'core/video',
		urlKey: 'src',
	},
	{
		name: 'core/cover',
		urlKey: 'url',
	},
];

// List of allowed resources.
export const ALLOWED_RESOURCES = [
	{
		authority: 'wordpress.org',
		domainRegex: /^(.*\.)?wordpress\.org$/,
	},
	{
		authority: 'w.org',
		domainRegex: /^(.*\.)?w\.org$/,
	},
	{
		authority: 'wp.com',
		domainRegex: /^(.*\.)?wp\.com$/,
		pathRegex: /^\/wordpress\.org\//,
	},
];

/**
 * Gets the media resource to check for the block.
 *
 * @param {string} blockName The name of the block.
 * @param {Object} attributes The attributes of the block.
 * @return {string|null} The media resource to check, or null if the block is not in the list of blocks to check.
 */
export const getBlockMediaResourceToCheck = ( blockName, attributes ) => {
	const blockToCheck = BLOCKS_TO_CHECK.find(
		( block ) => block.name === blockName
	);
	if ( ! blockToCheck ) {
		return null;
	}
	return attributes?.[ blockToCheck.urlKey ] ?? null;
};

/**
 * Checks whether the block has an invalid resource.
 *
 * The following URLs are allowed; any other URLs will not be
 * recommended as media resource URLs:
 *
 * - https://wordpress.org/image.jpg
 * - https://make.wordpress.org/image.jpg
 * - https://w.org/image.jpg
 * - https://s.w.org/images/core/6.9/image.jpg
 * - https://i0.wp.com/wordpress.org/image.jpg
 *
 * @param {string} mediaUrl The media URL to check.
 * @param {string} siteUrl The site URL.
 * @return {boolean} True if the resource is invalid.
 */
export const isInvalidResource = ( mediaUrl, siteUrl ) => {
	if ( ! siteUrl ) {
		return false;
	}

	// If no URL, cannot determine.
	if ( ! mediaUrl || isBlobURL( mediaUrl ) ) {
		return false;
	}

	// The media URL should normally be a URL, but treat it as invalid if it is not.
	if ( ! isURL( mediaUrl ) ) {
		return true;
	}

	const siteAuthority = getAuthority( siteUrl );
	const mediaAuthority = getAuthority( mediaUrl );

	// If the media authority is not set, it means the resource is not a valid URL.
	if ( ! mediaAuthority ) {
		return true;
	}

	// If the authority is the same, it means the resource is from the site.
	if ( siteAuthority === mediaAuthority ) {
		return false;
	}

	// Check if the authority is from an allowed domain.
	const allowedResource = ALLOWED_RESOURCES.find( ( resource ) =>
		mediaAuthority.match( resource.domainRegex )
	);

	if ( ! allowedResource ) {
		return true;
	}

	// If pathRegex is defined, also check the path.
	if ( allowedResource.pathRegex ) {
		const url = new URL( mediaUrl );
		const path = url.pathname;
		if ( ! path || ! allowedResource.pathRegex.test( path ) ) {
			return true;
		}
	}

	return false;
};
