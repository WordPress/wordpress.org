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

			var bar_headers = [],
				bar_values = [],
				version_list = [],
				to_combine = [],
				older_versions = 0,
				combine_limit = 6,
				combine_percentage = 1,
				version,
				index,
				bar_table_versions,
				formatter;

			// Start of the Google Datatable requirements.
			bar_headers.push( '' );
			bar_values.push( '' );

			// Gather and sort the list of versions.
			for ( version in versions ) {
				version_list.push( version );
			}

			// Sort the version list by version.
			version_list.sort( function( a, b ) {
				a = a.split( '.' );
				b = b.split( '.' );
				return ( a[0] != b[0] ) ? a[0]-b[0] : a[1]-b[1];
			} );

			// Move any versions (old or new) with < combine_percentage into the combined list.
			for ( index in version_list ) {
				version = version_list[ index ];
				if ( versions[ version ] <= combine_percentage ) {
					to_combine.push( version );
				}
			}

			// If a plugin has more than 6 versions, combine the older ones into a "Older Versions" group.
			if ( version_list.length > combine_limit ) {
				for ( index in version_list ) {
					version = version_list[ index ];
					if ( ( version_list.length - to_combine.length ) <= combine_limit ) {
						break;
					}
					if ( to_combine.indexOf( version ) > -1 ) {
						continue; // Next.
					}
					to_combine.push( version );
				}
			}

			// Only combine when there's multiples in there
			if ( to_combine.length > 1 ) {
				to_combine.forEach( function( version ) {
					older_versions += versions[ version ];

					delete versions[ version ];
					delete version_list[ version_list.indexOf( version ) ];
				} );
			}

			if ( older_versions ) {
				bar_headers.push( settings.l10n.otherVersions );
				bar_values.push( Math.round( older_versions * 100 ) / 100 );
			}

			// Add all the versions
			version_list.forEach( function( version ) {
				bar_headers.push( version );
				bar_values.push( versions[ version ] );
			} );

			bar_headers.push( { role: 'annotation' } );
			bar_values.push( '' );

			bar_table_versions = google.visualization.arrayToDataTable([
				bar_headers,
				bar_values
			]);

			// Format it as percentages
			formatter = new google.visualization.NumberFormat({
				fractionDigits: 1,
				suffix: '%'
			});

			index = 0;
			bar_values.forEach( function( value ) {
				if ( bar_values[ value ] ) {
					formatter.format( bar_table_versions, index );
				}
				index++;
			} );

			new google.visualization.BarChart( document.getElementById( 'plugin-version-stats' ) ).draw( bar_table_versions, {
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
