/**
 * WordPress dependencies
 */
const { registerBlockType } = wp.blocks;

/**
 * Internal dependencies
 */
import { BLOCKS } from './blocks/'; // Trailing slash required to differentiate the folder from the file.

BLOCKS.forEach( ( { NAME, SETTINGS } ) => {
	registerBlockType( NAME, SETTINGS );
} );
