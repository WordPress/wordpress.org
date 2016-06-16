google.load( "visualization", "1", { packages: ["corechart"] } );

( function( $, settings ) {
	$( function () {
		jQuery.getJSON('https://api.wordpress.org/stats/plugin/1.0/downloads.php?slug=' + settings.slug + '&limit=267&callback=?', function( downloads ) {
			var data = new google.visualization.DataTable(),
				count = 0,
				sml;

			data.addColumn( 'date', settings.l10n.date );
			data.addColumn( 'number', settings.l10n.downloads );

			$.each( downloads, function( key, value ) {
				data.addRow();
				data.setValue( count, 0, new Date( key ) );
				data.setValue( count, 1, Number( value ) );
				count++;
			} );

			sml = data.getNumberOfRows() < 225;

			new google.visualization.LineChart( document.getElementById( 'plugin-download-stats' ) ).draw( data, {
				colors: ['#253578'],
				legend: { position: 'none' },
				titlePosition: 'in',
				axisTitlesPosition: 'in',
				chartArea: {
					height: 280,
					left: ( sml ? 50 : 0 ),
					width: ( sml ? 482 : '100%' )
				},
				hAxis: {
					textStyle: { color: 'black', fontSize: 9 },
					format: 'MMM y'
				},
				vAxis: {
					format: '###,###',
					textPosition: ( sml ? 'out' : 'in' ),
					viewWindowMode: 'explicit',
					viewWindow: { min: 0 }
				},
				bar: { groupWidth: ( data.getNumberOfRows() > 100 ? "100%" : null ) },
				height: 350,
				width: 532,
				curveType: 'function'
			} );
		} );

		$.getJSON( 'https://api.wordpress.org/stats/plugin/1.0/?slug=' + settings.slug + '&callback=?', function ( versions ) {
			if ( 0 === versions.length ) {
				$( '#plugin-version-stats' ).text( settings.l10n.noData );
				return;
			}

			var barHeaders  = [ '' ],
				barValues   = [ '' ],
				versionList = [],
				index       = 0,
				version, data, formatter;

			// Gather and sort the list of versions.
			$.each( versions, function( version ) {
				versionList.push( version );
			} );

			// Sort the version list by version.
			versionList.sort( function( a, b ) {
				a = a.split( '.' );
				b = b.split( '.' );
				return ( a[0] != b[0] ) ? a[0]-b[0] : a[1]-b[1];
			} );

			// Move 'other' versions to the beginning.
			versionList.unshift( versionList.pop() );

			// Add all the versions
			versionList.forEach( function( version ) {
				barHeaders.push( version );
				barValues.push( versions[ version ] );
			} );

			data = google.visualization.arrayToDataTable([
				barHeaders,
				barValues
			]);

			// Format it as percentages
			formatter = new google.visualization.NumberFormat( {
				fractionDigits: 1,
				suffix: '%'
			} );

			$.each( barValues, function( value ) {
				if ( barValues[ value ] ) {
					formatter.format( data, ++index );
				}
			} );

			new google.visualization.BarChart( document.getElementById( 'plugin-version-stats' ) ).draw( data, {
				legend: {
					position: 'bottom'
				},
				chartArea: {
					left: '0',
					width: '100%',
					height: '80%',
					top: '10%'
				},
				hAxis: {
					gridlines: {
						color: 'transparent'
					}
				},
				isStacked: true
			} );
		} );
	} );
} )( window.jQuery, window.pluginStats );
