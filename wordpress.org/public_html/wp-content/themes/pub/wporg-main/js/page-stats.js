/* global wporgPageStats */
( function( $, google ) {
	google.charts.load( '44', { 'packages': [ 'corechart' ] } );
	google.charts.setOnLoadCallback( drawCharts );

	window.drawWpVersionsGraph = function( data ) {
		// Remove trunk from display.
		delete data[ wporgPageStats.trunk ];
		delete data[ wporgPageStats.beta ];

		drawGraph( data, 'wp_versions', wporgPageStats.wpVersions, 'Version', 'versions' );
	};
	window.drawPhpVersionsGraph = function( data ) {
		drawGraph( data, 'php_versions', wporgPageStats.phpVersions, 'Version', 'versions' );
	};
	window.drawMysqlVersionsGraph = function( data ) {
		delete data[ '12.0' ];
		drawGraph( data, 'mysql_versions', wporgPageStats.mysqlVersions, 'Version', 'versions' );
	};
	window.drawLocalesGraph = function( data ) {
		drawGraph( data, 'locales', wporgPageStats.locales, 'Locale', 'alphabeticaly' );
	};

	function drawCharts() {
		function getStatsData( endpoint, callback ) {
			$.ajax({
				url: endpoint,
				cache: true,
				jsonpCallback: callback,
				dataType: 'jsonp',
			});
		}
		getStatsData( 'https://api.wordpress.org/stats/wordpress/1.0/', 'drawWpVersionsGraph' );
		getStatsData( 'https://api.wordpress.org/stats/php/1.0/', 'drawPhpVersionsGraph' );
		getStatsData( 'https://api.wordpress.org/stats/mysql/1.0/', 'drawMysqlVersionsGraph' );
		getStatsData( 'https://api.wordpress.org/stats/locale/1.0/', 'drawLocalesGraph' );
	}

	function drawGraph( data, id, title, colName, sort ) {
		var tableData = [], others = null, chart, chartData, chartOptions;

		for ( var type in data ) {
			if ( 'Others' === type ) { // Make sure "Others" is always the last element.
				others = [ type, Number( data[ type ] ) ];
			} else {
				tableData.push( [ type, Number( data[ type ] ) ] );
			}
		}

		if ( 'versions' === sort ) {
			tableData.sort( function ( a, b ) {
				return a[0] - b[0];
			} );
			tableData.reverse();
		} else if ( 'alphabeticaly' === sort ) {
			tableData.sort();
		}

		if ( others ) {
			tableData.push( others );
		}

		// Table headers.
		tableData.unshift( [ { label: colName, type: 'string' }, { label: 'Usage', type: 'number' } ] );

		chartData = google.visualization.arrayToDataTable( tableData );

		chartOptions = {
			colors: [
				'#f9a87e',
				'#00b9eb',
				'#e35b5b',
				'#826eb4',
				'#6bc373',
				'#ffc733',
				'#bf461d',
				'#cdc5e1',
				'#46b450',
				'#007cb2',
				'#f78b53',
				'#b02828',
				'#008ec2',
				'#9b8bc3',
				'#685890',
				'#bfe7f3',
				'#006799',
				'#ea8484',
				'#f1adad',
				'#0073aa',
				'#389547',
				'#b4b9be',
				'#b5e1b9',
				'#82878c',
				'#00a0d2',
				'#0085ba',
				'#32373c',
				'#72777C',
				'#33b3db',
				'#9a2323',
				'#f56e28',
				'#ca4a1f',
				'#ffb900',
				'#ffd566',
				'#d54e21',
				'#f6a306',
				'#c62d2d',
				'#b4a8d2',
				'#fbc5a9',
				'#555d66',
				'#66c6e4',
				'#a0a5aa',
				'#4e426c',
				'#90d296',
				'#c7e8ca',
				'#31843f',
				'#99d9ed',
				'#dc3232',
				'#cbcdce',
				'#ee8e0d',
				'#ffe399',
			],
			height: 450,
			is3D: false,
			title: title,
			chartArea: {
				top: 65,
				bottom: 60,
				width: '100%',
				height: '100%',
			},
			titleTextStyle: {
				fontSize: 14,
				color: '#23282d',
				bold: true
			},
			legend: {
				position: 'right',
				alignment: 'center',
				textStyle: {
					color: '#444',
					fontSize: 13
				},
			},
			sliceVisibilityThreshold: 0,
			pieSliceTextStyle: {
				color: '#fff',
				fontSize: 12,
			},
			tooltip: {
				text: 'percentage',
				textStyle: {
					color: '#444'
				},
				showColorCode: true
			}
		};

		var $el = $( '#' + id ).removeClass( 'loading' );
		chart = new google.visualization.PieChart( $el[0] );
		chart.draw( chartData, chartOptions );
	}
} )( window.jQuery, window.google );
