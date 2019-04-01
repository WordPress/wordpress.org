/**
 * WordPress dependencies
 */
const { registerBlockType } = wp.blocks;

/**
 * Internal dependencies
 */
import * as organizers from './organizers/';
import * as sessions from './sessions/';
import * as speakers from './speakers/';
import * as sponsors from './sponsors/';

[
	organizers,
	sessions,
	speakers,
	sponsors,
].forEach( ( { name, settings } ) => {
	registerBlockType( name, settings );
} );
