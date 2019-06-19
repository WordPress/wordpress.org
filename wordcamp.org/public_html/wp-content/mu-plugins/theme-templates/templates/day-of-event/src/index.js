/**
 * Internal dependencies
 */
import { MainController as DayOfEvent } from './components/main-controller';

/**
 * WordPress dependencies
 */
import { render } from '@wordpress/element';

render(
	<DayOfEvent config={ window.dayOfEventConfig } />,
	document.getElementById( 'day-of-event' )
);
