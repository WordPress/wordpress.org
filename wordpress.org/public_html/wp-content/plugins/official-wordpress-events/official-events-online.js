/**
 * Get the list of events in day-buckets.
 *
 * @param {Array} events
 */
function getSortedEvents( events ) {
	const timezone = wp.date.format( '(\\U\\T\\CP)' );
	const sortedEvents = {};
	events.forEach( function( event ) {
		const d = new Date( event.start_timestamp * 1000 );
		const key = wp.date.format( 'Y-m-d', d );
		// Inject a human-readable start time.
		event.startTime = wp.date.format( 'g:i a', d );
		event.timezone = timezone;
		event.type = event?.type === 'wordcamp' ? 'WordCamp' : toTitleCase(event.type);
		if ( sortedEvents.hasOwnProperty( key ) ) {
			sortedEvents[ key ].push( event );
		} else {
			sortedEvents[ key ] = [ event ];
		}
	} );
	return sortedEvents;
}

/**
 * Convert a string to title case.
 *
 * @param {string} str
 */
function toTitleCase(str = '') {
    return str.replace(/\w\S*/g, function(word) {
        return word.charAt(0).toUpperCase() + word.substring(1).toLowerCase();
    });
}

jQuery( function( $ ) {
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

	$( '#official-online-events' ).html( list );
});
