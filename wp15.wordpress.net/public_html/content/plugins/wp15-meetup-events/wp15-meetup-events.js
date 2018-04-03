/**
 * WP15MeetupEvents
 *
 * Displays a Google Map with the provided markers.
 *
 * This is mostly copied from the `wordcamp-central-2012` theme.
 */
var WP15MeetupEvents = ( function( $ ) {
	// `templateOptions` is copied from Core in order to avoid an extra HTTP request just to get `wp.template`.
	var events,
	    options,
	    strings,
	    templateOptions = {
			evaluate:    /<#([\s\S]+?)#>/g,
			interpolate: /\{\{\{([\s\S]+?)\}\}\}/g,
			escape:      /\{\{([^\}]+?)\}\}(?!\})/g
		};

	/**
	 * Initialization that runs when the document has fully loaded.
	 */
	function init( data ) {
		events  = data.events;
		options = data.map_options;
		strings = data.strings;

		try {
			$( '#wp15-events-query' ).keyup( filterEventList );

			if ( options.hasOwnProperty( 'mapContainer' ) ) {
				loadMap( options.mapContainer, events );
			}
		} catch ( exception ) {
			log( exception );
		}
	}

	/**
	 * Build a Google Map in the given container with the given marker data.
	 *
	 * @param {string} container
	 * @param {object} markers
	 */
	function loadMap( container, markers ) {
		if ( ! $( '#' + container ).length ) {
			throw "Map container element isn't present in the DOM.";
		}

		if ( 'undefined' === typeof( google ) || ! google.hasOwnProperty( 'maps' ) ) {
			throw 'Google Maps library is not loaded.';
		}

		var map, markerCluster,
			mapOptions = {
				center            : new google.maps.LatLng( 15.000, 7.000 ),
				zoom              : 2,
				zoomControl       : true,
				mapTypeControl    : false,
				streetViewControl : false
		};

		map           = new google.maps.Map( document.getElementById( container ), mapOptions );
		markers       = createMarkers(  map, markers );
		markerCluster = clusterMarkers( map, markers );
	}

	/**
	 * Create markers on a map with the given marker data.
	 *
	 * Normally the markers would be assigned to the map at this point, but we'll run them through MarkerClusterer
	 * later on, so adding them to the map now is unnecessary and negatively affects performance.
	 *
	 * @param {google.maps.Map} map
	 * @param {object}          markers
	 *
	 * @return {object}
	 */
	function createMarkers( map, markers ) {
		var markerID,
			infoWindowTemplate = _.template( $( '#tmpl-wp15-map-marker' ).html(), null, templateOptions ),
			infoWindow         = new google.maps.InfoWindow( {
				pixelOffset: new google.maps.Size( -options.markerIconAnchorXOffset, 0 )
			} );

		for ( markerID in markers ) {
			if ( ! markers.hasOwnProperty( markerID ) ) {
				continue;
			}

			markers[ markerID ] = new google.maps.Marker( {
				id        : markerID,
				group     : markers[ markerID ].group,
				name      : markers[ markerID ].name,
				time      : markers[ markerID ].time,
				url       : markers[ markerID ].event_url,

				icon : {
					url        : options.markerIconBaseURL + options.markerIcon,
					size       : new google.maps.Size(  options.markerIconHeight,        options.markerIconWidth ),
					anchor     : new google.maps.Point( options.markerIconAnchorXOffset, options.markerIconWidth / 2 ),
					scaledSize : new google.maps.Size(  options.markerIconHeight / 2,    options.markerIconWidth / 2 )
				},

				position : new google.maps.LatLng(
					markers[ markerID ].latitude,
					markers[ markerID ].longitude
				)
			} );

			google.maps.event.addListener( markers[ markerID ], 'click', function() {
				try {
					infoWindow.setContent( infoWindowTemplate( { 'event': markers[ this.id ] } ) );
					infoWindow.open( map, markers[ this.id ] );
				} catch ( exception ) {
					log( exception );
				}
			} );
		}

		return markers;
	}

	/**
	 * Cluster the markers into groups for improved performance and UX.
	 *
	 * options.clusterIcon is just 1x size, because MarkerClusterer doesn't support retina images.
	 * MarkerClusterer Plus does, but it doesn't seem as official, so I'm not as confident that it's secure,
	 * stable, etc.
	 *
	 * @param {google.maps.Map} map
	 * @param {object}          markers
	 *
	 * @return MarkerClusterer
	 */
	function clusterMarkers( map, markers ) {
		var clusterOptions,
			markersArray = [];

		/*
		 * We're storing markers in an object so that they can be accessed directly by ID, rather than having to
		 * loop through them to find one. MarkerClusterer requires them to be passed in as an object, though, so
		 * we need to convert them here.
		 */
		for ( var m in markers ) {
			markersArray.push( markers[ m ] );
		}

		clusterOptions = {
			maxZoom:  11,
			gridSize: 20,
			styles: [
				{
					url:       options.markerIconBaseURL + options.clusterIcon,
					height:    options.clusterIconHeight,
					width:     options.clusterIconWidth,
					anchor:    [ 0, -0 ],
					textColor: '#ffffff',
					textSize:  18
				}
			]
		};

		return new MarkerClusterer( map, markersArray, clusterOptions );
	}

	/**
	 * Filter the list of events based on a user's search query.
	 */
	function filterEventList() {
		var query  = this.value,
		    events = $( '.wp15-events-list' ).children( 'li' );
		    speak  = _.debounce( wp.a11y.speak, 1000 );

		if ( '' === query ) {
			events.attr( 'aria-hidden', false );
			speak( strings.search_cleared );
			return;
		}

		events.each( function( index, event ) {
			var groupName = $( event ).children( '.wp15-event-group' ).text().trim(),
			    location  = $( event ).data( 'location' );

			if ( -1 === groupName.search( new RegExp( query, 'i' ) ) && -1 === location.search( new RegExp( query, 'i' ) ) ) {
				$( event ).attr( 'aria-hidden', true );
			} else {
				$( event ).attr( 'aria-hidden', false );
			}
		} );

		speak( strings.search_match.replace( '%s', query ) );
	}

	/**
	 * Log a message to the console.
	 *
	 * @param {*} message
	 */
	function log( message ) {
		if ( ! window.console ) {
			return;
		}

		if ( 'string' === typeof( message ) ) {
			console.log( 'WP15MeetupEvents: ' + message );
		} else {
			console.log( 'WP15MeetupEvents: ', message );
		}
	}

	return {
		init: init
	};
} )( jQuery );

jQuery( document ).ready( WP15MeetupEvents.init( wp15MeetupEventsData ) );
