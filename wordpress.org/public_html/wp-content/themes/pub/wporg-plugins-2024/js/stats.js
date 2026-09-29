/* globals google:object */
google.charts.load( 'current', {
	packages: [ 'corechart' ],
} );

( function ( $, settings ) {
	$( function () {
		$.ajax( {
			url:
				'https://api.wordpress.org/stats/plugin/1.0/downloads.php?slug=' +
				settings.slug +
				'&limit=267',
			dataType: 'jsonp',
			jsonpCallback: 'wporgPluginStatsDownloads',
		} ).done( function ( downloads ) {
			google.charts.setOnLoadCallback( function () {
				var data = new google.visualization.DataTable(),
					count = 0,
					sml;

				data.addColumn( 'date', settings.l10n.date );
				data.addColumn( 'number', settings.l10n.downloads );

				$.each( downloads, function ( key, value ) {
					data.addRow();
					data.setValue( count, 0, new Date( key + 'T00:00:00' ) );
					data.setValue( count, 1, Number( value ) );
					count++;
				} );

				sml = data.getNumberOfRows() < 225;

				new google.visualization.LineChart(
					document.getElementById( 'plugin-download-stats' )
				).draw( data, {
					colors: [ '#253578' ],
					legend: { position: 'none' },
					chartArea: {
						height: 280,
						left: sml ? 50 : 0,
						right: 0,
					},
					hAxis: {
						textStyle: { color: 'black', fontSize: 9 },
						format: 'MMM y',
					},
					vAxis: {
						format: '###,###',
						textPosition: sml ? 'out' : 'in',
						viewWindowMode: 'explicit',
						viewWindow: { min: 0 },
					},
					height: 350,
					curveType: 'function',
					trendlines: {
						0: {
							type: 'linear',
							color: '#ffc733',
							lineWidth: 1,
							opacity: 0.4,
							showR2: false,
							visibleInLegend: false,
							tooltip: false,
						},
					},
				} );
			} );
		} );

		$.ajax( {
			url:
				'https://api.wordpress.org/stats/plugin/1.0/downloads.php?slug=' +
				settings.slug +
				'&historical_summary=1',
			dataType: 'jsonp',
			jsonpCallback: 'wporgPluginStatsSummary',
		} ).done( function ( summary ) {
			var $tbody = $( '#plugin-download-history-stats' ).find(
					'tbody:last-child'
				),
				$row,
				count,
				field;

			for ( field in summary ) {
				if ( ! summary.hasOwnProperty( field ) ) {
					continue;
				}

				count = parseInt( Number( summary[ field ] ) ).toLocaleString();
				$row = $( '<tr><th scope="row"></th><td>0</td></tr>' );

				$row.find( 'th' ).text( settings.l10n[ field ] );
				$row.find( 'td' ).text( count );
				$tbody.append( $row );
			}
		} );

		$.ajax( {
			url:
				'https://api.wordpress.org/stats/plugin/1.0/?slug=' +
				settings.slug,
			dataType: 'jsonp',
			jsonpCallback: 'wporgPluginStatsVersions',
		} ).done( function ( versions ) {
			if ( 0 === versions.length ) {
				$( '#plugin-version-stats' ).text( settings.l10n.noData );
				return;
			}

			google.charts.setOnLoadCallback( function () {
				var barHeaders = [ '' ],
					barValues = [ '' ],
					versionList = [],
					data,
					formatter,
					index;

				// Gather and sort the list of versions.
				$.each( versions, function ( version ) {
					if ( 'other' !== version ) {
						versionList.push( version );
					}
				} );

				// Sort the version list by version.
				versionList.sort( function ( a, b ) {
					a = a.split( '.' );
					b = b.split( '.' );
					return a[ 0 ] !== b[ 0 ]
						? a[ 0 ] - b[ 0 ]
						: a[ 1 ] - b[ 1 ];
				} );

				// Put 'other' versions at the beginning.
				if ( versions.hasOwnProperty( 'other' ) ) {
					versionList.unshift( 'other' );
				}

				// Add all the versions
				versionList.forEach( function ( version ) {
					barHeaders.push( version );
					barValues.push( versions[ version ] );
				} );

				data = google.visualization.arrayToDataTable( [
					barHeaders,
					barValues,
				] );

				// Format it as percentages
				formatter = new google.visualization.NumberFormat( {
					fractionDigits: 1,
					suffix: '%',
				} );

				for ( index = 1; index < data.getNumberOfColumns(); index++ ) {
					formatter.format( data, index );
				}

				new google.visualization.BarChart(
					document.getElementById( 'plugin-version-stats' )
				).draw( data, {
					legend: {
						position: 'bottom',
					},
					chartArea: {
						left: '0',
						width: '100%',
						height: '70%',
						top: '20%',
					},
					hAxis: {
						gridlines: {
							color: 'transparent',
						},
					},
					isStacked: true,
				} );
			} );
		} );
	} );
} )( window.jQuery, window.pluginStats );
