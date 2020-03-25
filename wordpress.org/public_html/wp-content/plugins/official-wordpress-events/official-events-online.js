/**
 * Get the list of events in day-buckets.
 *
 * @param {Array} events
 */
function getSortedEvents( events ) {
	const sortedEvents = {};
	events.forEach( function( event ) {
		const d = new Date( event.start_timestamp * 1000 );
		const key = wp.date.format( 'Y-m-d', d );
		// Inject a human-readable start time.
		event.startTime = wp.date.format( 'g:i a ', d );
		if ( sortedEvents.hasOwnProperty( key ) ) {
			sortedEvents[ key ].push( event );
		} else {
			sortedEvents[ key ] = [ event ];
		}
	} );
	return sortedEvents;
}

jQuery( function( $ ) {
	var templateTZ = wp.template( 'owe-timezone' );
	var templateList = wp.template( 'official-events' );
	var templateEvent = wp.template( 'official-event' );

	var list = $.map(
		getSortedEvents( OfficialWordPressEvents ),
		function( events, date ) {
			return templateList( {
				date: wp.date.format( 'F j', date ),
				dayOfWeek: wp.date.format( 'l', date ),
				eventMarkup: events.map( templateEvent ).join( '' ),
			} );
		}
	).join( '' );

	var timezone = templateTZ( wp.date.format( '\\U\\T\\CP' ) );

	$( '#official-online-events' ).html( timezone + list );
});
