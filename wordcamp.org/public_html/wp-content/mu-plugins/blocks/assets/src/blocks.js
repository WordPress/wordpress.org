/**
 * WordPress dependencies
 */
const { registerBlockType } = wp.blocks;

/**
 * Internal dependencies
 */
import * as speakers from './speakers/';

[
	speakers,
].forEach( ( { name, settings } ) => {
	registerBlockType( name, settings );
} );
