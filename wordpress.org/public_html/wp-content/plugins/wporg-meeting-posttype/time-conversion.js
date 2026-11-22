/**
 * Converts meeting times in `<time class="date">` elements to the visitor's time zone.
 */
( () => {
	/**
	 * Parses a UTC date in the format the [meeting_time] shortcode prints.
	 *
	 * @param {string} text Date, e.g. `2026-10-14T15:00:00+00:00`.
	 * @return {Date|null} The date, or null if the text doesn't match.
	 */
	const parseDate = ( text ) => {
		const m =
			/^([0-9]{4})-([0-9]{2})-([0-9]{2})T([0-9]{2}):([0-9]{2}):([0-9]{2})\+00:00$/.exec(
				text
			);
		if ( ! m ) {
			return null;
		}
		return new Date(
			Date.UTC( +m[ 1 ], +m[ 2 ] - 1, +m[ 3 ], +m[ 4 ], +m[ 5 ], +m[ 6 ] )
		);
	};

	/**
	 * Formats a date as a weekday and time in the visitor's locale.
	 *
	 * @param {Date} d Date.
	 * @return {string} Formatted time.
	 */
	const formatTime = ( d ) =>
		d.toLocaleTimeString( navigator.language, {
			weekday: 'long',
			hour: '2-digit',
			minute: '2-digit',
			timeZoneName: 'short',
		} );

	for ( const node of document.getElementsByTagName( 'time' ) ) {
		if ( node.className === 'date' ) {
			const d = parseDate( node.getAttribute( 'datetime' ) );
			if ( d ) {
				node.textContent = formatTime( d );
			}
		}
	}
} )();
