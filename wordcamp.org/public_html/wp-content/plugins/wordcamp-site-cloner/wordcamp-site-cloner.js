( function( wp, $, Backbone, win, settings ) {
	'use strict';

	if ( ! wp || ! wp.customize ) {
		return;
	}

	wp.customize.WordCamp = wp.customize.WordCamp || {};

	var api  = wp.customize,
	    wcsc = api.WordCamp.SiteCloner = {
			models      : {},
			views       : {},
			collections : {},
			routers     : {},
			settings    : {}
		};

	wcsc.settings = settings || {};

	// Model for a single site
	wcsc.models.Site = Backbone.Model.extend( {
		idAttribute : 'site_id',

		defaults : {
			'active' : false
		}
	} );

	// Model representing the filter state for searching/filtering sites
	wcsc.models.SearchFilter = Backbone.Model.extend( {
		's'                : '',
		'theme_slug'       : '',
		'year'             : '',
		'css_preprocessor' : ''
	} );

	// Top level view for the Site Cloner Control
	wcsc.views.SiteSearch = Backbone.View.extend( {
		el : '#wcsc-cloner .wcsc-search',

		// Index of the currently viewed page of results
		page : 0,

		initialize : function( options ) {
			// Update scroller position
			_.bindAll( this, 'scroller' );

			// Container that will be scrolled within
			this.$container = $( '#wcsc-cloner' ).parents( '.wp-full-overlay-sidebar-content' );
			// Bind scrolling within the container to check for infinite scroll
			this.$container.bind( 'scroll', _.throttle( this.scroller, 300 ) );

			// The model and view for filtering the site results
			this.filterView = new wcsc.views.SearchFilters( {
				model  : this.collection.searchFilter,
				parent : this
			} );

			// View for listing the matching sites
			this.resultsView = new wcsc.views.SearchResults( {
				collection : this.collection,
				parent     : this
			} );
		},

		render : function() {
			this.filterView.render();
			this.resultsView.render();

			this.$el.empty().append( this.resultsView.el );
		},

		/**
		 * Checks if a user has reached the bottom of the list and triggers a scroll event to show more sites if
		 * needed.
		 */
		scroller : function() {
			var visibleBottom, threshold, elementHeight, containerHeight, scrollTop;

			scrollTop       = this.$container.scrollTop();
			containerHeight = this.$container.innerHeight();
			elementHeight   = this.$container.get( 0 ).scrollHeight;

			visibleBottom = scrollTop + containerHeight;
			threshold     = Math.round( elementHeight * 0.9 );

			if ( visibleBottom > threshold ) {
				this.trigger( 'wcsc:scroll' );
			}
		}
	} );

	// Collection representing the list of cloneable sites
	wcsc.collections.Sites = Backbone.Collection.extend( {
		model : wcsc.models.Site,
		url   : wcsc.settings.apiUrl,

		initialize : function( options ) {
			this.searchFilter = options.searchFilter || {};

			this.listenTo( this.searchFilter, 'change', this.applyFilter );
		},

		// Filter this collection by the updated searchFilter attributes
		applyFilter : function() {
			var filters       = this.searchFilter.toJSON(),
			    activeFilters = _.pick( filters, _.identity ),
			    term          = '',
			    sites;

			// Nothing actually changed, so don't update the collection
			if ( _.isEmpty( this.searchFilter.changedAttributes() ) ) {
				return;
			}

			// No active filters. Reset to the full list and bail
			if ( _.isEmpty( activeFilters ) ) {
				this.resetCanonical();
				return;
			}

			this.resetCanonical( { silent: true } );

			// Remove the search query restriction since we already filtered by word matches above
			if ( activeFilters.s ) {
				term = activeFilters.s;

				delete activeFilters.s;
			}

			sites = this.where( activeFilters );

			if ( term ) {
				sites = this.filterBySearch( sites, term );
			}

			this.reset( sites );
		},

		// Internal method for filtering sites by search terms
		filterBySearch : function( sites, term ) {
			var match, name;

			// Escape the term string for RegExp meta characters
			term = term.replace( /[-\/\\^$*+?.()|[\]{}]/g, '\\$&' );

			// Consider spaces as word delimiters and match the whole string
			// so matching terms can be combined
			term  = term.replace( / /g, ')(?=.*' );
			match = new RegExp( '^(?=.*' + term + ').+', 'i' );

			return _.filter( sites, function( site ) {
				name = site.get( 'name' ).replace( /(<([^>]+)>)/ig, '' );

				return match.test( name );
			} );
		},

		paginate : function( pageIndex ) {
			var collection = this,
				perPage    = 20;

			pageIndex  = pageIndex || 0;

			collection = _( collection.rest( perPage * pageIndex ) );
			collection = _( collection.first( perPage ) );

			return collection;
		},

		// Resets the site collection dataset to the canonical list originally pulled from the api
		resetCanonical : function( options ) {
			var activeSite,
			    activeSiteId = api( 'wcsc_source_site_id' ).get();

			options = options || {};

			this.reset( wcsc.settings.siteData, options );

			// Restore the currently active site
			if ( activeSiteId ) {
				activeSite = this.find( { site_id : activeSiteId } );

				if ( 'undefined' !== typeof activeSite ) {
					activeSite.set( { active: true } );
				}
			}
		}
	} );

	// View for a single site
	wcsc.views.Site = Backbone.View.extend( {
		className : 'wcsc-site',
		html      : wp.template( 'wcsc-site-option' ),
		touchDrag : false,

		attributes : function() {
			return {
				'id'           : 'wcsc-site-' + this.model.get( 'site_id' ),
				'data-site-id' : this.model.get( 'site_id' )
			}
		},

		events : {
			'click'     : 'preview',
			'keydown'   : 'preview',
			'touchend'  : 'preview',
			'touchmove' : 'preventPreview'
		},

		initialize : function( options ) {
			this.parent = options.parent;

			this.listenTo( this.model, 'change', this.render );
			this.render();
		},

		render : function() {
			this.$el.html( this.html( this.model.toJSON() ) );
		},

		preventPreview : function() {
			this.touchDrag = true;
		},

		preview : function( event ) {
			event = event || window.event;

			// Ignore touches caused by scrolling
			if ( this.touchDrag === true ) {
				this.touchDrag = false;
			}

			event.preventDefault();

			this.$el.trigger( 'wcsc:previewSite', this.model );
		}
	} );

	// View for the site results list
	wcsc.views.SearchResults = Backbone.View.extend( {
		className: 'wcsc-results',

		initialize : function( options ) {
			var self = this;

			this.parent     = options.parent;
			this.$siteCount = $( '#wcsc-sites-count' );

			// Re-render the view whenever a collection change is complete
			this.listenTo( this.collection, 'reset', function() {
				self.parent.page = 0;
				self.render( this );
			} );

			this.listenTo( this.parent, 'wcsc:scroll', function() {
				self.renderSites( self.parent.page );
			} );
		},

		render : function() {
			this.$el.empty();
			this.renderSites( this.parent.page );
			this.$siteCount.text( this.collection.length );
		},

		renderSites : function( page ) {
			var self = this;

			// Get a collection of just the requested page
			this.instance = this.collection.paginate( page );

			if ( this.instance.size() === 0 ) {
				this.parent.trigger( 'wcsc:end' );
				return;
			}

			this.instance.each( function( site ) {
				var siteView = new wcsc.views.Site( {
					model  : site,
					parent : self
				} );

				siteView.render();

				self.$el.append( siteView.el );
			} );

			this.parent.page++;
		}

	} );

	// View for the search and dropdown filters
	wcsc.views.SearchFilters = Backbone.View.extend( {
		el        : '#wcsc-cloner .filters',
		className : 'wscs-filters',
		html      : wp.template( 'wcsc-site-filters' ),

		events : {
			"input #wcsc-filter-search-input" : "search",
			"keyup #wcsc-filter-search-input" : "search",
			"change select"                   : "applyFilter"
		},

		initialize : function( options ) {
			this.parent = options.parent;
		},

		render : function() {
			var data = {};

			data.themeOptions        = wcsc.settings.themes;
			data.yearOptions         = _.uniq( this.parent.collection.pluck( 'year' ) ).sort();
			data.preprocessorOptions = _.uniq( this.parent.collection.pluck( 'css_preprocessor' ) ).sort();

			this.$el.html( this.html( data ) );

			this.$searchInput        = $( '#wcsc-filter-search-input' );
			this.$themeFilter        = $( '#wcsc-filter-theme_slug' );
			this.$yearFilter         = $( '#wcsc-filter-year' );
			this.$preprocessorFilter = $( '#wcsc-filter-css_preprocessor' );
		},

		search : function( event ) {
			// Clear on escape.
			if ( event.type === 'keyup' && event.which === 27 ) {
				event.target.value = '';
			}

			/**
			 * Since doSearch is debounced, it will only run when user input comes to a rest
			 */
			this.doSearch( event );
		},

		doSearch : _.debounce( function( event ) {
			this.model.set( 's', event.target.value );
		}, 500 ),

		applyFilter : function( event ) {
			var $target = $( event.target ),
			    value   = $target.val(),
			    filter  = $target.data( 'filter' );

			this.model.set( filter, value );
		},

		// Set the inputs to the set of filters as triggered by the router on initial load
		setInputs : function( filters ) {
			this.model.set( filters, { silent : true } );

			this.$searchInput.val(        this.model.get( 's'                ) );
			this.$themeFilter.val(        this.model.get( 'theme_slug'       ) );
			this.$yearFilter.val(         this.model.get( 'year'             ) );
			this.$preprocessorFilter.val( this.model.get( 'css_preprocessor' ) );

			this.model.trigger( 'change', this.model );
		}
	} );

	/**
	 * Sets up a listener to store the user's selected filters and search, so that a user's position can be
	 * restored as well as possible after a theme changes causes a full refresh.
	 */
	wcsc.routers.FilterState = Backbone.Router.extend( {
		routes : {
			'wcsc?*filters' : 'applyFilters'
		},

		initialize : function( options ) {
			this.parent = options.parent;

			// Any time the collection is reset, we need to update the displayed route
			this.listenTo( this.parent.view.collection, 'reset', this.updateLocation );
		},

		// Applies the filters set in the query string to the view
		applyFilters : function( queryString ) {
			var filters = deserializeQueryString( queryString );

			this.parent.view.filterView.setInputs( filters );
		},

		updateLocation : function() {
			var filters       = this.parent.view.collection.searchFilter.toJSON(),
			    activeFilters = _.pick( filters, _.identity ),
			    queryString   = $.param( activeFilters );

			this.navigate( 'wcsc?' + queryString );
		}
	} );

	// Customizer Control wrapping the site search applet
	api.controlConstructor.wcscSearch = api.Control.extend( {
		ready : function() {
			var filter = new wcsc.models.SearchFilter(); // Top level model representing the current filter applied to the collection

			this.siteCollection = new wcsc.collections.Sites( { searchFilter : filter } );

			// Fill the site collection and setup search when complete
			this.siteCollection.fetch( {
				success : this.setupSearch.bind( this )
			} );
		},

		// Initialize the site search instance for cloning other sites
		setupSearch : function() {
			var currentSite,
			    control   = this,
			    urlParams = getUrlParams( win.location.href );

			// Set a canonical array of all sites prior to filtering
			wcsc.settings.siteData = this.siteCollection.toJSON();

			// If the wcsc_source_site_id is set, it;s most likely from a user previewing a site, so bring them back
			if ( urlParams.hasOwnProperty( 'wcsc_source_site_id' ) ) {
				api.section( this.section() ).expand();

				currentSite = this.siteCollection.find( { site_id : urlParams.wcsc_source_site_id } );

				if ( currentSite ) {
					this.setActiveSite( currentSite );
				}
			}

			$( '#wcsc-cloner' ).on( 'wcsc:previewSite', '.wcsc-site', function( event, site ) {
				control.previewSite( site );
			} );

			// Setup the top level Site Search View
			this.view = new wcsc.views.SiteSearch( {
				parent     : this,
				collection : this.siteCollection
			} );

			this.view.render();

			// Initialize the router to allow state to be restored after a full refresh
			wcsc.router = new wcsc.routers.FilterState( { parent : this } );
			Backbone.history.start();
		},

		previewSite : function( site ) {
			var queryString, routerFragment;

			if ( api( 'wcsc_source_site_id' ).get() == site.get( 'site_id' ) ) {
				// We're already previewing this site
				return;
			}

			if ( api.settings.theme.stylesheet === site.get( 'theme_slug' ) ) {
				this.setActiveSite( site );
			} else {
				// We have to do a full refresh when changing themes or other controls won't correlate to the current theme.
				queryString = $.param( {
					'theme'               : site.get( 'theme_slug' ),
					'wcsc_source_site_id' : site.get( 'site_id' )
				} );

				routerFragment      = Backbone.history.getFragment();
				win.parent.location = wcsc.settings.customizerUrl + '?' + queryString + '#' + routerFragment;
			}
		},

		// Set the active site and update the model to reflect the change
		setActiveSite : function( site ) {
			var site_id = site.get( 'site_id' );

			this.siteCollection.each( function( _site ) {
				_site.set( { active : false } );
			} );

			site.set( { active : true } );
			api( 'wcsc_source_site_id' ).set( site.get( 'site_id' ) );
		}
	} );

	/**
	 * Parse the URL parameters
	 *
	 * Based on https://stackoverflow.com/a/2880929/450127
	 *
	 * @param {string} url
	 *
	 * @returns {object}
	 */
	function getUrlParams( url ) {
		var questionMarkIndex, query, hashIndex;

		// Strip hash first
		hashIndex = url.indexOf( '#' );

		if ( hashIndex > -1 ) {
			url = url.substring( 0, hashIndex );
		}

		questionMarkIndex = url.indexOf( '?' );

		if ( -1 === questionMarkIndex ) {
			return {};
		} else {
			query = url.substring( questionMarkIndex + 1 );
		}

		return deserializeQueryString( query );
	}

	/**
	 * Deserialize a query string into an object
	 *
	 * @param queryString
	 * @returns {object}
	 */
	function deserializeQueryString( queryString ) {
		var match,
		    urlParams = {},
		    pl        = /\+/g,  // Regex for replacing addition symbol with a space
		    search    = /([^&=]+)=?([^&]*)/g,
		    decode    = function( s ) {
			    return decodeURIComponent( s.replace( pl, " " ) );
		    };

		while ( match = search.exec( queryString ) ) {
			urlParams[ decode( match[ 1 ] ) ] = decode( match[ 2 ] );
		}

		return urlParams;
	}
})( wp, jQuery, Backbone, window, _wcscSettings );
