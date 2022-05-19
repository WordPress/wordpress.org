/**
 * WordPress dependencies
 */
import { createElement, render } from '@wordpress/element';

/**
 * Internal dependencies
 */
import Block from './block';
import './styles.css';

const init = () => {
	const containers = document.querySelectorAll( '.wporg-chart-block-js' );

	if ( ! containers.length ) {
		return;
	}

	// We may have multiple charts on the same page
	containers.forEach( ( container ) => {
		render(
			createElement( Block, { data: container.dataset } ),
			container
		);
	} );
};

document.addEventListener( 'DOMContentLoaded', init ); // eslint-disable-line
