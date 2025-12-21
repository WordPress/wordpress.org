/**
 * WordPress dependencies
 */
import { isBlobURL } from '@wordpress/blob';
import { getAuthority } from '@wordpress/url';

// List of blocks to check.
const BLOCKS_TO_CHECK = [
	{
		name: 'core/image',
		idKey: 'id',
		urlKey: 'url',
	},
	{
		name: 'core/video',
		idKey: 'id',
		urlKey: 'src',
	},
	{
		name: 'core/cover',
		idKey: 'id',
		urlKey: 'url',
	},
];

// List of allowed domain regexes.
export const ALLOWED_DOMAINS = [
	{
		authority: 'wordpress.org',
		regex: /^(.*\.)?wordpress\.org$/,
	},
	{
		authority: 'wp.com',
		regex: /^(.*\.)?wp\.com$/,
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
	return attributes[ blockToCheck.urlKey ];
};

/**
 * Checks whether the block has an invalid resource.
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

	const siteAuthority = getAuthority( siteUrl );
	const mediaAuthority = getAuthority( mediaUrl );

	// If the authority is the same, it means the resource is from the site.
	if ( siteAuthority === mediaAuthority ) {
		return false;
	}

	// Check if the authority is from an allowed domain.
	if (
		ALLOWED_DOMAINS.some( ( domain ) =>
			mediaAuthority.match( domain.regex )
		)
	) {
		return false;
	}

	// The media is not from an allowed domain.
	return true;
};
