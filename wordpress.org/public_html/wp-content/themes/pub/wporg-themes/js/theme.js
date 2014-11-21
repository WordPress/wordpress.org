( function ( $, wp ) {

	_.extend( wp.themes.view.Appearance.prototype, {
		el: '#themes .theme-browser',
		searchContainer: ''
	});

	_.extend( wp.themes.view.Installer.prototype, {
		el: '#themes'
	});

	_.extend( wp.themes.view.Theme.prototype, {
		events: {
			'click': 'expand',
			'keydown': 'expand',
			'touchend': 'expand',
			'keyup': 'addFocus',
			'touchmove': 'preventExpand'
		}
	});

	wp.themes.view.Preview.prototype = wp.themes.view.Details.prototype;

	_.extend( wp.themes.InstallerRouter.prototype, {
		routes: {
			'/:slug/': 'preview',
			'/browse/:sort/': 'sort',
			'/?upload': 'upload',
			'/search.php?q=:query': 'search',
			'': 'sort'
		},

		baseUrl: function( url ) {
			return '/' + url;
		},

		themePath: 'themes/',
		browsePath: 'browse/',
		searchPath: 'search.php?q='
	});

}( jQuery, wp ) );