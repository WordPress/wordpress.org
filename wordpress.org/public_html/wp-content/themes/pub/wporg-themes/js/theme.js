/* global _wpThemeSettings, google */
window.wp = window.wp || {};

( function($) {

	// Set up our namespace...
	var themes = wp.themes = wp.themes || {},
		l10n;

	// Store the theme data and settings for organized and quick access
	// themes.data.settings, themes.data.themes, themes.data.l10n
	themes.data = _wpThemeSettings;
	l10n = themes.data.l10n;

	// Setup app structure
	_.extend( themes, { model: {}, view: {}, routes: {}, router: {}, template: wp.template });

	themes.utils = {
		title: function ( item, type ) {
			var format = themes.data.settings.title.default;

			if ( 'author' === type ) {
				format = themes.data.settings.title.author;
			} else if ( 'tags' === type || 'browse' === type ) {
				format = themes.data.settings.title.tax;
			} else if ( 'search' === type ) {
				format = themes.data.settings.title.search;
			} else if ( 'theme' === type ) {
				format = themes.data.settings.title.theme;
			} else if ( '404' === type || 'notfound' === type ) {
				format = themes.data.settings.title.notfound;
			} else if ( 'home' === type || ( 'home' === item && undefined === type ) ) {
				format = themes.data.settings.title.home;
			}

			var title  = $( '<div/>' ).html( format.replace( '%s', $( '<div/>' ).text( item ).html() ) ).text();

			if ( document.title !== title && title.length ) {
				document.title = title;
			}
		}
	};

	themes.Model = Backbone.Model.extend({
		// Adds attributes to the default data coming through the .org themes api
		// Map `id` to `slug` for shared code
		initialize: function() {
			var description;

			// Set the attributes
			this.set({
				// slug is for installation, id is for existing.
				id: this.get( 'slug' ) || this.get( 'id' )
			});

			// Map `section.description` to `description`
			// as the API sometimes returns it differently
			if ( this.has( 'sections' ) ) {
				description = this.get( 'sections' ).description;
				this.set({ description: description });
			}

			/*
			 * Mark whether the current user can edit this theme.
			 * is_admin is set based on `edit_posts`, where as the rest api cap is `edit_post $id`.
			 */
			this.set( {
				can_configure_categorization_options: (
					themes.data.settings.currentUser?.is_admin ||
					( this.get('author')?.user_nicename === themes.data.settings.currentUser?.slug )
				)
			} );

		}
	});

	// Main view controller for themes.php
	// Unifies and renders all available views
	themes.view.Appearance = wp.Backbone.View.extend({
		el: '#themes .theme-browser',

		window: $( window ),

		// Pagination instance
		page: 0,

		loadMore: $('.js-load-more-themes'),

		initialize: function( options ) {
			this.SearchView = options.SearchView ? options.SearchView : themes.view.Search;

			this.loadMoreThemes = this.loadMoreThemes.bind( this );
			this.loadMore.bind( 'click', this.loadMoreThemes );
		},

		// Main render control
		render: function() {
			// Setup the main theme view
			// with the current theme collection
			this.view = new themes.view.Themes({
				collection: this.collection,
				parent: this
			});

			// Render search form.
			this.search();

			// Render and append
			this.view.render();
			this.$el.find( '.themes' ).remove();
			this.$el.append( this.view.el ).addClass( 'rendered' );
		},

		// Defines search element container
		searchContainer: '',

		// Search input and view
		// for current theme collection
		search: function() {
			var view,
				self = this;

			view = new this.SearchView({
				collection: self.collection,
				parent: this
			});

			// Render and append after screen title
			view.render();
			this.searchContainer
				.append( $.parseHTML( '<label class="screen-reader-text" for="wp-filter-search-input">' + l10n.search + '</label>' ) )
				.append( view.el );
		},

		// Trigger loading additional themes
		loadMoreThemes: function () {
			this.trigger( 'theme:loadMore' );
		},
	});

	// Set up the Collection for our theme data
	// @has 'id' 'name' 'screenshot' 'author' 'authorURI' 'version' ...
	themes.Collection = Backbone.Collection.extend({
		model: themes.Model,

		// Search terms
		terms: '',

		// Local cache array for API queries
		queries: [],

		// Keep track of current query so we can handle pagination
		currentQuery: {
			page: 1,
			request: {}
		},

		count: false,

		// Static status controller for when we are loading themes.
		loadingThemes: false,

		// Controls searching on the current theme collection
		// and triggers an update event
		doSearch: function( value ) {

			// Don't do anything if we've already done this search
			// Useful because the Search handler fires multiple times per keystroke
			if ( this.terms === value ) {
				return;
			}

			// Updates terms with the value passed
			this.terms = value;

			// If we have terms, run a search...
			if ( this.terms.length > 0 ) {
				this.search( this.terms );
			}

			// If search is blank, show all themes
			// Useful for resetting the views when you clean the input
			if ( this.terms === '' ) {
				this.reset( themes.data.themes );
			}

			// Trigger an 'themes:update' event
			this.trigger( 'themes:update' );
		},

		// Performs a search within the collection
		// @uses RegExp
		search: function( term ) {
			var match, results, haystack, name, description, author;

			// Start with a full collection
			this.reset( themes.data.themes, { silent: true } );

			// Escape the term string for RegExp meta characters
			term = term.replace( /[-\/\\^$*+?.()|[\]{}]/g, '\\$&' );

			// Consider spaces as word delimiters and match the whole string
			// so matching terms can be combined
			term = term.replace( / /g, ')(?=.*' );
			match = new RegExp( '^(?=.*' + term + ').+', 'i' );

			// Find results
			// _.filter and .test
			results = this.filter( function( data ) {
				name        = data.get( 'name' ).replace( /(<([^>]+)>)/ig, '' );
				description = data.get( 'description' ).replace( /(<([^>]+)>)/ig, '' );
				author      = data.get( 'author' ).replace( /(<([^>]+)>)/ig, '' );

				haystack = _.union( [ name, data.get( 'id' ), description, author, data.get( 'tags' ) ] );

				if ( match.test( data.get( 'author' ) ) && term.length > 2 ) {
					data.set( 'displayAuthor', true );
				}

				return match.test( haystack );
			});

			if ( results.length === 0 ) {
				this.trigger( 'query:empty' );
			} else {
				$( 'body' ).removeClass( 'no-results' );
			}

			this.reset( results );
		},

		// Paginates the collection with a helper method
		// that slices the collection
		paginate: function( instance ) {
			var collection = this;
			instance = instance || 0;

			// Themes per instance are set via posts_per_page option in WP admin.
			collection = _( collection.rest( themes.data.settings.postsPerPage * instance ) );
			collection = _( collection.first( themes.data.settings.postsPerPage ) );

			return collection;
		},

		// Handles requests for more themes
		// and caches results
		//
		// When we are missing a cache object we fire an apiCall()
		// which triggers events of `query:success` or `query:fail`
		query: function( request ) {
			/**
			 * @static
			 * @type Array
			 */
			var queries = this.queries,
				self = this,
				query, isPaginated, count;

			// Store current query request args
			// for later use with the event `theme:end`
			this.currentQuery.request = request;

			// Search the query cache for matches.
			query = _.find( queries, function( query ) {
				return _.isEqual( query.request, request );
			});

			// If the request matches the stored currentQuery.request
			// it means we have a paginated request.
			isPaginated = _.has( request, 'page' );

			// Reset the internal api page counter for non paginated queries.
			if ( ! isPaginated ) {
				this.currentQuery.page = 1;
			}

			// Otherwise, send a new API call and add it to the cache.
			if ( ! query && ! isPaginated ) {
				query = this.apiCall( request ).done( function( data ) {

					// Update the collection with the queried data.
					if ( data.themes ) {
						self.reset( data.themes );
						count = data.info.results;
						// Store the results and the query request
						queries.push( { themes: data.themes, request: request, total: count } );
					}

					// Trigger a collection refresh event
					// and a `query:success` event with a `count` argument.
					self.trigger( 'themes:update' );
					self.trigger( 'query:success', count );

					if ( data.themes && data.themes.length === 0 ) {
						self.trigger( 'query:empty' );
					}

				}).fail( function() {
					self.trigger( 'query:fail' );
				});
			} else {
				// If it's a paginated request we need to fetch more themes...
				if ( isPaginated ) {
					return this.apiCall( request, isPaginated ).done( function( data ) {
						// Add the new themes to the current collection
						// @todo update counter
						self.add( data.themes );

						self.trigger( 'themes:rerender' );

						self.trigger( 'query:success', data.info.results );

						// We are done loading themes for now.
						self.loadingThemes = false;

					}).fail( function() {
						self.trigger( 'query:fail' );
					});
				}

				if ( query.themes.length === 0 ) {
					self.trigger( 'query:empty' );
				} else {
					$( 'body' ).removeClass( 'no-results' );
				}

				// Only trigger an update event since we already have the themes
				// on our cached object
				if ( _.isNumber( query.total ) ) {
					this.count = query.total;
				}

				this.reset( query.themes );
				if ( ! query.total ) {
					this.count = this.length;
				}

				this.trigger( 'themes:update' );
				this.trigger( 'query:success', this.count );
			}
		},

		// Send request to api.wordpress.org/themes
		apiCall: function( request, paginated ) {
			var url = themes.data.settings.apiEndpoint,
				data, options;

			data = _.extend( {
				action: 'query_themes',
				// Fields which are not set as true in theme-directory/class-themes-api.php for the API endpoint used.
				fields: {
					active_installs: true,
					downloadlink: true,
					last_updated: true,
					ratings: true,
					photon_screenshots: true,
					tags: true,
					theme_url: true,
				},
				per_page: themes.data.settings.postsPerPage,
				locale: themes.data.settings.locale
			}, request);

			options = {
				type: 'GET',
				url: url,
				dataType: 'json',
				data: data,

				beforeSend: function() {
					$('.js-load-more-themes').hide();

					if ( ! paginated ) {
						// Spin it
						$( 'body' ).addClass( 'loading-content' ).removeClass( 'no-results' );
					}
				}
			};

			return $.Deferred( function( deferred ) {
				$.ajax( options ).done( function( response ) {
					deferred.resolveWith( this, [ response ] );
				}).fail( function() {
					deferred.rejectWith( this, arguments );
				});
			}).promise();

		}
	});

	// This is the view that controls each theme item
	// that will be displayed on the screen
	themes.view.Theme = wp.Backbone.View.extend({

		// Wrap theme data on a div.theme element
		className: 'theme',

		// Reflects which theme view we have
		// 'grid' (default) or 'detail'
		state: 'grid',

		// The HTML template for each element to be rendered
		html: wp.themes.template( 'theme' ),

		events: {
			'click': 'expand',
			'keydown': 'expand',
			'touchend': 'expand',
			'keyup': 'addFocus',
			'touchmove': 'preventExpand'
		},

		touchDrag: false,

		render: function() {
			var data = this.model.toJSON();

			data.permalink = themes.data.settings.path + themes.router.baseUrl( data.slug );
			data.path = themes.data.settings.path;

			// Render themes using the html template
			this.$el.html( this.html( data ) ).attr({
				tabindex: 0,
				'aria-describedby' : data.id + '-action ' + data.id + '-name'
			});
		},

		// Add class of focus to the theme we are focused on.
		addFocus: function() {
			var $themeToFocus = ( $( ':focus' ).hasClass( 'theme' ) ) ? $( ':focus' ) : $(':focus').parents('.theme');

			$('.theme.focus').removeClass('focus');
			$themeToFocus.addClass('focus');
		},

		// Single theme overlay screen
		// It's shown when clicking a theme
		expand: function( event ) {
			var self = this;

			event = event || window.event;

			// Don't handle click if command/Ctrl are pressed to open the link in a new tab.
			if ( true === event.metaKey|event.ctrlKey && 'click' === event.type ) {
				return;
			}

			// 'enter' and 'space' keys expand the details view when a theme is :focused
			if ( event.type === 'keydown' && ( event.which !== 13 && event.which !== 32 ) ) {
				return;
			}

			// Bail if the user scrolled on a touch device
			if ( this.touchDrag === true ) {
				return this.touchDrag = false;
			}

			// Prevent the modal from showing when the user clicks
			// one of the direct action buttons
			if ( $( event.target ).is( '.theme-actions a' ) ) {
				return;
			}

			// Set focused theme to current element
			themes.focusedTheme = this.$el;

			this.trigger( 'theme:expand', self.model.cid );
			event.preventDefault();
		},

		preventExpand: function() {
			this.touchDrag = true;
		}
	});

	// Theme Details view
	// Set ups a modal overlay with the expanded theme data
	themes.view.Details = wp.Backbone.View.extend({
		// Wrap theme data on a div.theme element
		className: 'theme-overlay',

		events: {
			'click': 'collapse',
			'click .left': 'previousTheme',
			'click .right': 'nextTheme',
			'click .screenshot': 'preview',
			'click .theme-actions .button-secondary': 'preview',
			'keydown .theme-actions .button-secondary': 'preview',
			'touchend .theme-actions .button-secondary': 'preview',
			'click .favorite': 'favorite_toggle',
			// This is provided by a Third Party
			'click #theme-patterns-grid-js .wporg-screenshot-card': 'preview', 
			'click .wporg-horizontal-slider-js .wporg-screenshot-card': 'thumbnailPreview', 
			'keydown .wporg-horizontal-slider-js .wporg-screenshot-card': 'thumbnailPreview', 
		},

		// The HTML template for the theme overlay
		html: themes.template( 'theme-single' ),

		render: function() {
			var data = this.model.toJSON(),
				updated = new Date();

			// format 2012-12-25 into the date object in a cross-browser compatible way.
			updated.setUTCFullYear(
				data.last_updated.substring( 0, 4 ),
				data.last_updated.substring( 5, 7 ) - 1, // 0 indexed
				data.last_updated.substring( 8, 10 )
			);

			// Format the Last Updated date, prefering
			data.last_updated = updated.toLocaleDateString( l10n.locale, {
				day:   'numeric',
				month: 'long',
				year:  'numeric',
			} );

			// If last updated plus 2 years is in the past, it's outdated.
			data.is_outdated = updated.setYear(updated.getYear() + 1902).valueOf() < new Date().valueOf();

			// Make tags click-able and separated by a comma.
			data.tags = _.map( data.tags, function( tag, slug ) {
				var translated_tag = l10n.tags[ slug ] || tag;
				return '<a href="' + themes.data.settings.path + themes.router.baseUrl( 'tags/' + slug ) + '">' + translated_tag + '</a>';
			}).join( ', ' );

			data.path = themes.data.settings.path;

			// Active Installs text
			if ( data.active_installs < 10 ) {
				data.active_installs = l10n.active_installs_less_than_10;
			} else if ( data.active_installs >= 1000000 ) {
				data.active_installs = l10n.active_installs_1_million;
			} else {
				data.active_installs = data.active_installs.toLocaleString() + '+';
			}

			data.show_favorites = !! themes.data.settings.currentUser?.login;
			data.is_favorited   = ( themes.data.settings.favorites.themes.indexOf( data.slug ) !== -1 );
			data.current_user   = themes.data.settings.currentUser?.login;

			this.$el.html( this.html( data ) );
			// Set up navigation events
			this.navigation();
			// Checks screenshot size
			this.screenshotCheck( this.$el );
			// Contain "tabbing" inside the overlay
			this.containFocus( this.$el );
			this.renderDownloadsGraph();
			this.renderPatterns();

			// Currently this feature is in beta
			this.renderStyleVariations();
		},

		favorite_toggle: function() {
			var $heart = this.$el.find( '.favorite' ),
				favorited = ! $heart.hasClass( 'favorited' ),
				slug = this.model.get('slug'),
				pos;

			$heart.toggleClass( 'favorited' );

			// Update it in the current settings
			if ( ! favorited ) {
				pos = themes.data.settings.favorites.themes.indexOf( slug );
				if ( pos > -1 ) {
					delete themes.data.settings.favorites.themes[ pos ];
				}
			} else {
				themes.data.settings.favorites.themes.push( slug );
			}

			// Update the server with the changed data
			var options = {
				type: 'GET',
				url: themes.data.settings.favorites.api,
				dataType: 'json',
				xhrFields: {
					withCredentials: true
				},
				data: {
					action: favorited ? 'add-favorite' : 'remove-favorite',
					theme: this.model.get('slug'),
					_wpnonce: themes.data.settings.favorites.nonce
				},
			};

			$.ajax( options ).done( function( result ) {
				// If the user is no longer logged in, stop showing the favorite heart
				if ( 'undefined' !== typeof result.error && 'not_logged_in' === result.error ) {
					themes.data.settings.favorites.themes = [];
					themes.data.settings.currentUser = false;
				}
			} );
		},

		preview: function( event ) {
			var self = this,
				current, preview;

			// We will replace this if we have a pattern click
			var cachePreviewUrl = this.model.attributes.preview_url;
			var lastFocusedElement = document.activeElement;

			// Bail if the user scrolled on a touch device
			if ( this.touchDrag === true ) {
				return this.touchDrag = false;
			}

			// Allow direct link path to installing a theme.
			if ( $( event.target ).hasClass( 'button-primary' ) ) {
				return;
			}

			// 'enter' and 'space' keys expand the details view when a theme is :focused
			if ( event.type === 'keydown' && ( event.which !== 13 && event.which !== 32 ) ) {
				return;
			}

			// pressing enter while focused on the buttons shouldn't open the preview
			if ( event.type === 'keydown' && event.which !== 13 && $( ':focus' ).hasClass( 'button' ) ) {
				return;
			}

			event = event || window.event;
			event.preventDefault();

			// Set focus to current theme.
			themes.focusedTheme = this.$el;

			// Show the pattern
			var $target = $(event.target).closest('a')[0];
			if( $target && $target.classList.contains( 'wporg-screenshot-card' ) ) {
				this.model.attributes.preview_url = $target.href.replace('&preview', '');
			} 

			// Construct a new Preview view.
			preview = new themes.view.Preview({
				model: this.model
			});

			// Render the view and append it.
			preview.render();
			this.setNavButtonsState();

			if ( themes.data.settings.isMobile ) {
				preview.$el.addClass( 'wp-full-overlay collapsed' );
			} else {
				preview.$el.addClass( 'wp-full-overlay expanded' );
			}

			// Append preview
			$( '.theme-install-overlay' ).append( preview.el );

			// Listen to our preview object
			// for `theme:next` and `theme:previous` events.
			this.listenTo( preview, 'theme:next', function() {
				this.trigger( 'theme:next' );

				// Keep local track of current theme model.
				current = self.model;

				// If we have ventured away from current model update the current model position.
				if ( ! _.isUndefined( self.current ) ) {
					current = self.current;
				}

				// Get next theme model.
				self.current = self.model.collection.at( self.model.collection.indexOf( current ) + 1 );

				// If we have no more themes, bail.
				if ( _.isUndefined( self.current ) ) {
					self.options.parent.parent.trigger( 'theme:end' );
					return self.current = current;
				}

				preview.model = self.current;

				// Render and append.
				preview.render();
				this.setNavButtonsState();
				$( '.next-theme' ).focus();
			})
			.listenTo( preview, 'theme:previous', function() {
				this.trigger( 'theme:previous' );

				// Keep track of current theme model.
				current = self.model;

				// Bail early if we are at the beginning of the collection
				if ( self.model.collection.indexOf( self.current ) === 0 ) {
					return;
				}

				// If we have ventured away from current model update the current model position.
				if ( ! _.isUndefined( self.current ) ) {
					current = self.current;
				}

				// Get previous theme model.
				self.current = self.model.collection.at( self.model.collection.indexOf( current ) - 1 );

				// If we have no more themes, bail.
				if ( _.isUndefined( self.current ) ) {
					return;
				}

				preview.model = self.current;

				// Render and append.
				preview.render();
				this.setNavButtonsState();
				$( '.previous-theme' ).focus();
			});

			this.listenTo( preview, 'preview:close', function() {
				self.model.attributes.preview_url = cachePreviewUrl;
				self.current = self.model;

				// Restore to the element that was focused last before opening
				if ( lastFocusedElement ) {
					lastFocusedElement.focus();
				}
			});
		},

		thumbnailPreview: function ( event ) {
			// 'enter' and 'space' keys expand the details view when a theme is :focused
			if ( event.type === 'keydown' && ( event.which !== 13 && event.which !== 32 ) ) {
				return;
			}

			event.preventDefault();

			var $anchorLink = $( event.target );

			if ( $anchorLink.prop( 'tagName' ).toLowerCase() !== 'a' ) {
				$anchorLink = $( $anchorLink.parent( 'a' )[0] );
			}

			// Get the theme's main thumbnail
			var $thumbnailContainer = $('.screenshot');
			var CARD_ACTIVE_CLASS = 'wporg-screenshot-card__active';
			var SCREENSHOT_PREVIEW_CLASS = 'wporg-thumbnail-screenshot-preview-js';
			var STYLE_VARIATION_CLASS = 'style-variation';

			/**
			 * Determine the index, we need to restore the original thumbnail if it's the first item.
			 */
			var cards = $( '.wporg-horizontal-slider-js .wporg-screenshot-card' );
			cards.attr( 'aria-selected', false );
			$anchorLink.attr( 'aria-selected', true );

			if( cards.index( $anchorLink ) === 0 ) {
				$thumbnailContainer.find( 'picture' ).show();
				$thumbnailContainer.removeClass( STYLE_VARIATION_CLASS );
				$( '.' + SCREENSHOT_PREVIEW_CLASS ).remove();
			} else {
				// Create element
				var newEl = $( '<div class="'+ SCREENSHOT_PREVIEW_CLASS +'" role="tabpanel"></div>' )
				.attr( 'data-link', $anchorLink.attr( 'href' ) )
				.attr( 'data-preview-link', $anchorLink.attr( 'href' ) + '&v=' + this.model.attributes.version + '-betaV2' )
				.attr( 'data-caption', _wpThemeSettings.l10n.pattern_caption_template.replace( '%s', $anchorLink.find( 'img' ).attr( 'alt' ) ) )
				.attr( 'data-height', $thumbnailContainer.height() + 'px' )
				.attr( 'data-aspect-ratio', 3 / 4 )
				.attr( 'data-query-string', '?vpw=1200&vph=900' )
				.attr( 'id', $anchorLink.attr( 'aria-controls' ) );

			   $thumbnailContainer.find( 'picture' ).hide();

			   /**
				* The screenshot container uses a padding system to prevent image size change.
				* We don't want the padding when we preview our image.
			   */
			   $thumbnailContainer.addClass( STYLE_VARIATION_CLASS );

				// Remove if one exists, we can't just replace the source.
				$( '.' + SCREENSHOT_PREVIEW_CLASS ).remove();
				$thumbnailContainer.append( newEl );

				if ( window.__wporg_screenshot_preview_render ) {
					window.__wporg_screenshot_preview_render( SCREENSHOT_PREVIEW_CLASS );
				}
			}

			/**
			 * Add an active class on the current style variation.
			 */
			$( '.' + CARD_ACTIVE_CLASS ).removeClass( CARD_ACTIVE_CLASS );
			$anchorLink.addClass( CARD_ACTIVE_CLASS );

			// Update the preview url so uses get style variation when the previewer is opened
			this.model.attributes.preview_url = $anchorLink.attr( 'href' );
		},

		// Handles .disabled classes for previous/next buttons in theme installer preview
		setNavButtonsState: function() {
			var $themeInstaller = $( '.theme-install-overlay' ),
				current = _.isUndefined( this.current ) ? this.model : this.current;

			// Disable previous at the zero position
			if ( 0 === this.model.collection.indexOf( current ) ) {
				$themeInstaller.find( '.previous-theme' ).addClass( 'disabled' );
			}

			// Disable next if the next model is undefined
			if ( _.isUndefined( this.model.collection.at( this.model.collection.indexOf( current ) + 1 ) ) ) {
				$themeInstaller.find( '.next-theme' ).addClass( 'disabled' );
			}
		},

		// Keeps :focus within the theme details elements.
		containFocus: function( $el ) {
			var ev = window.event,
				$target;

			// On first load of the modal, move focus to the primary action.
			if ( typeof ev === 'undefined' || 1 === $( ev.target ).closest( '.theme' ).length ) {
				_.delay( function() {
					$( '.theme-wrap a.button-primary:visible' ).focus();
				}, 500 );
			}

			$el.on( 'keydown.wp-themes', function( event ) {

				// Tab key
				if ( event.which === 9 ) {
					$target = $( event.target );

					// Keep focus within the overlay by making the last link on theme actions
					// switch focus to button.left on tabbing and vice versa
					if ( $target.is( 'button.close' ) && event.shiftKey ) {
						$el.find( '.theme-tags a:last-child' ).focus();
						event.preventDefault();
					}
				}
			});
		},

		// Single theme overlay screen
		// It's shown when clicking a theme
		collapse: function( event ) {
			var self = this,
				args = {},
				scroll, author, search, tags, sorter;

			// Prevent collapsing detailed view when there is only one theme available
			if ( themes.data.themes.length === 1 ) {
				return;
			}

			event = event || window.event;

			// Detect if the click is inside the overlay
			// and don't close it unless the target was
			// the div.back button
			if ( $( event.target ).is( '.close' ) || event.keyCode === 27 ) {

				// Add a temporary closing class while overlay fades out
				$( 'body' ).addClass( 'closing-overlay' );

				self.unmountReactAssets();

				// With a quick fade out animation
				this.$el.fadeOut( 1, function() {
					// Clicking outside the modal box closes the overlay
					$( 'body' ).removeClass( 'closing-overlay' );
					// Handle event cleanup
					self.closeOverlay();

					// Get scroll position to avoid jumping to the top
					scroll = document.body.scrollTop;

					// Clean the url structure
					if ( author = themes.Collection.prototype.currentQuery.request.author ) {
						themes.router.navigate( themes.router.baseUrl( 'author/' + author ) );
						themes.utils.title( author, 'author' );
					}
					else if ( search = themes.Collection.prototype.currentQuery.request.search ) {
						themes.router.navigate( themes.router.baseUrl( themes.router.searchPath + search ) );
						themes.utils.title( search, 'search' );
					}
					else if ( tags = themes.view.Installer.prototype.filtersChecked() ) {
						themes.router.navigate( themes.router.baseUrl( 'tags/' + tags.join( '+' ) ) );
						themes.utils.title(
							_.each( tags, function( tag, i ) {
								tags[ i ] = $( 'label[for="filter-id-' + tag + '"]' ).text();
							})[0],
							'tags'
						);
					}
					else if ( sorter = $( '.filter-links .current' ) ) {
						if ( ! sorter.length ) {
							sorter = $( '.filter-links [data-sort="' + themes.data.settings.browseDefault + '"]' );
							args   = { trigger: true };
						}
						if ( themes.data.settings.browseDefault === sorter.data( 'sort' ) ) {
							themes.router.navigate( themes.router.baseUrl( '/' ), args );
							themes.utils.title( 'home' );
						} else {
							const data = sorter.data() || {};
							const section = data.sort || data.model; 
							themes.router.navigate( themes.router.baseUrl( themes.router.browsePath + section ) );
							themes.utils.title( sorter.text(), 'browse' );
						}
					}

					// Restore scroll position
					document.body.scrollTop = scroll;

					// Return focus to the theme div
					if ( themes.focusedTheme ) {
						themes.focusedTheme.focus();
					}
				});
			}
		},

		renderDownloadsGraph: function() {
			var self = this;

			$.getJSON( 'https://api.wordpress.org/stats/themes/1.0/downloads.php?slug=' + self.model.get( 'id' ) + '&limit=260&callback=?', function( downloads ) {
				google.charts.setOnLoadCallback( function() {
					var data = new google.visualization.DataTable(),
						count = 0;

					data.addColumn('string', _wpThemeSettings.l10n.date);
					data.addColumn('number', _wpThemeSettings.l10n.downloads);

					$.each(downloads, function (key, value) {
						data.addRow();
						data.setValue(count, 0, new Date(key).toLocaleDateString() );
						data.setValue(count, 1, Number(value));
						count++;
					});

					new google.visualization.ColumnChart(document.getElementById('theme-download-stats-' + self.model.get( 'id' ) )).draw(data, {
						colors: ['#253578'],
						legend: {
							position: 'none'
						},
						titlePosition: 'in',
						axisTitlesPosition: 'in',
						chartArea: {
							height: 280,
							left: 35,
							width: '98%'
						},
						hAxis: {
							textStyle: {color: 'black', fontSize: 9}
						},
						vAxis: {
							format: '###,###',
							textPosition: 'out',
							viewWindowMode: 'explicit',
							viewWindow: {min: 0}
						},
						bar: {
							groupWidth: ( data.getNumberOfRows() > 100 ) ? '100%' : null
						},
						height: 350
					});
				});
			});
		},

		renderPatterns: function() {
			var options = {
				type: 'GET',
				url: 'https://wp-themes.com/' + this.model.get( 'slug' ) + '/?rest_route=/wporg-patterns/v1/patterns',
			};
			var self = this.model;

			function bindPatterns( $container, patterns ) {
				$.each( patterns, function ( key, value ) {
					/**
					 * This is a duplicate of wporg-screenshot-preview `render_block` function.
					 * We have to do this because of how backbone controls state on the theme view page.
					 */
					 var newEl = $( '<div class="wporg-screenshot-preview-js"></div>' )
					 .attr( 'data-link', value.link )
					 .attr( 'data-preview-link', value.preview_link + '&v=' + self.get( 'version' ) + '-betaV4.0.4'  )
					 .attr( 'data-caption', _wpThemeSettings.l10n.pattern_caption_template.replace( '%s', value.title ) );

					$container.append( newEl );
				});

				if ( window.__wporg_screenshot_preview_render ) {
					window.__wporg_screenshot_preview_render();
				}
			}

			$.ajax( options ).done( function( data ){
				var $container = $( '#theme-patterns-grid-js' );
				var $showAllBtn = $( '#theme-patterns-button-js' );
				var patterns = JSON.parse( data );
				var previewSize = 9;

				if( patterns.length ) {
					// Show the pattern section
					$( '#theme-patterns-js' ).show();

					var firstSet = patterns.slice( 0, previewSize );
					bindPatterns( $container, firstSet );

					if( patterns.length > previewSize ) {
						$showAllBtn.show();

						$showAllBtn.on( 'click', function ( event ) {
							event.preventDefault();
							
							var remainingSet = patterns.slice( previewSize );
							bindPatterns( $container, remainingSet );

							// Find the next card that was just added
							$container.find( '.wporg-screenshot-card' )[previewSize].focus();

							$showAllBtn.hide();
						} );
					}
				}
			} );
		},

		renderStyleVariations: function() {
			var options = {
				type: 'GET',    
				url: 'https://wp-themes.com/' + this.model.get( 'slug' ) + '/?rest_route=/wporg-styles/v1/variations',
			};
			var self = this.model;

			/**
			 * Map it to be compatible with the wporg/screenshot-preview block
			 * 
			 * See: https://github.com/WordPress/wporg-mu-plugins/tree/trunk/mu-plugins/blocks/screenshot-preview
			 */
			function mapVariations( variations ) {
				var out = [];

				$.each( variations, function ( key, value ) {
					 out.push( {
						title: value.title,
						link: value.link,
						previewLink: value.preview_link + '&v=' + self.get( 'version' ) + '-betaV1.1.2',
						caption:  _wpThemeSettings.l10n.style_variation_caption_template.replace( '%s', value.title ),
					 } );
				} );

				return out;
			}

			$.ajax( options ).done( function( data ) {
				var $container = $( '.wporg-horizontal-slider-js' );
				var variations = JSON.parse( data );

				if ( variations.length ) {
					var mapped = mapVariations( variations );

					$container.attr( 'data-items', JSON.stringify( mapped ) );
					$container.attr( 'data-title', _wpThemeSettings.l10n.style_variations_title );
					
					if ( window.__wporg_horizontal_slider_render ) {
						window.__wporg_horizontal_slider_render();
					}        
				}
			} );
		},
		/* jshint ignore:start */
		/* Turns off ReactDOM undefined */
		unmountReactAssets: function () {
			if( ! ReactDOM || ! ReactDOM.unmountComponentAtNode ) {
				return;
			}

			$( '.wporg-horizontal-slider-js' ).each( function () {
				ReactDOM.unmountComponentAtNode( this );
			} );

			$( '.wporg-screenshot-preview-js' ).each( function () {
				ReactDOM.unmountComponentAtNode( this );
			} );

			$( '.wporg-thumbnail-screenshot-preview-js' ).each( function () {
				ReactDOM.unmountComponentAtNode( this );
			} )  ;
		},
		/* jshint ignore:end */

		// Handles .disabled classes for next/previous buttons
		navigation: function() {

			// Disable Left/Right when at the start or end of the collection
			if ( this.model.cid === this.model.collection.at(0).cid ) {
				this.$el.find( '.left' ).addClass( 'disabled' );
			}
			if ( this.model.cid === this.model.collection.at( this.model.collection.length - 1 ).cid ) {
				this.$el.find( '.right' ).addClass( 'disabled' );
			}
		},

		// Performs the actions to effectively close
		// the theme details overlay
		closeOverlay: function() {
			$( 'body' ).removeClass( 'modal-open' );
			this.remove();
			this.unbind();
			this.trigger( 'theme:collapse' );
		},

		nextTheme: function() {
			var self = this;
			self.trigger( 'theme:next', self.model.cid );
			return false;
		},

		previousTheme: function() {
			var self = this;
			self.trigger( 'theme:previous', self.model.cid );
			return false;
		},

		screenshotCheck: function( el ) {
			var image = new Image();
			image.src = el.find( '.screenshot img' ).attr( 'src' );
		}
	});

	// Theme Preview view
	// Set ups a modal overlay with the expanded theme data
	themes.view.Preview = themes.view.Details.extend({

		className: 'wp-full-overlay expanded',
		el: '.theme-install-overlay',

		events: {
			'click .close-full-overlay': 'close',
			'click .collapse-sidebar': 'collapse',
			'click .previous-theme': 'previousTheme',
			'click .next-theme': 'nextTheme',
			'click .wp-full-overlay-footer .devices button': 'devicePreview',
			'keyup': 'keyEvent'
		},

		// The HTML template for the theme preview
		html: themes.template( 'theme-preview' ),


		render: function() {
			var data = this.model.toJSON();

			this.$el.html( this.html( data ) );

			themes.router.navigate( themes.router.baseUrl( themes.router.themePath + this.model.get( 'id' ) ) );

			this.$el.fadeIn( 200, function() {
				$( 'body' ).addClass( 'theme-installer-active full-overlay-active' );
				$( '.close-full-overlay' ).focus();
			});

			if ( themes.activeDevicePreview ) {
				this.setDevicePreview( themes.activeDevicePreview );
			}
		},

		close: function() {
			this.$el.fadeOut( 200, function() {
				$( 'body' ).removeClass( 'theme-installer-active full-overlay-active' );

				// Return focus to the theme div
				if ( themes.focusedTheme ) {
					themes.focusedTheme.focus();
				}
			});

			this.trigger( 'preview:close' );
			this.undelegateEvents();
			this.unbind();
			themes.router.navigate( themes.router.baseUrl( themes.router.themePath + this.model.get( 'id' ) ) );
			return false;
		},

		collapse: function() {
			this.$el.toggleClass( 'collapsed' );

			if ( themes.data.settings.isMobile ) {
				this.$el.removeClass( 'expanded' );
			} else {
				this.$el.toggleClass( 'expanded' );
			}

			return false;
		},

		devicePreview: function( event ) {
			return this.setDevicePreview( event.target.dataset.device );
		},

		setDevicePreview: function ( device ) {
			// Make the correct button appear active
			var $footer = this.$el.find( '.wp-full-overlay-footer' );
			$footer.find( '.active' ).removeClass( 'active' );
			$footer.find( '.' + device ).addClass( 'active' );

			// Update the iframe with the device class to change the iframe width
			this.$el.find( '.wp-full-overlay-main iframe' )
			.removeClass( themes.activeDevicePreview ) // Remove the previous device class
			.addClass( device );

			// Store our active viewport to reuse if theme changes
			themes.activeDevicePreview = device;

			return false;
		},

		keyEvent: function() {
			// The escape key closes the preview
			if ( event.keyCode === 27 ) {
				this.undelegateEvents();
				this.close();
			}
			// The right arrow key, next theme
			if ( event.keyCode === 39 ) {
				_.once( this.nextTheme() );
			}

			// The left arrow key, previous theme
			if ( event.keyCode === 37 ) {
				this.previousTheme();
			}

			// Prevent the underlying modal to advance too.
			return false;
		}
	});

	// Controls the rendering of div.themes,
	// a wrapper that will hold all the theme elements
	themes.view.Themes = wp.Backbone.View.extend({

		className: 'themes',
		$overlay: $( 'div.theme-overlay' ),

		// Number to keep track of scroll position
		// while in theme-overlay mode
		index: 0,

		// The theme count element
		count: $( '.wp-filter .theme-count' ),

		initialize: function( options ) {
			var self = this;

			// Set up parent
			this.parent = options.parent;

			// Set current view to [grid]
			this.setView( 'grid' );

			// When the collection is updated by user input...
			this.listenTo( self.collection, 'themes:update', function() {
				self.parent.page = 0;
				self.render( this );
			});

			// Update theme count to full result set when available.
			this.listenTo( self.collection, 'query:success', function( count ) {
				if ( _.isNumber( count ) ) {
					self.count.text( count.toLocaleString() );
				} else {
					self.count.text( self.collection.length.toLocaleString() );
				}
			});

			this.listenTo( self.collection, 'query:empty', function() {
				$( 'body' ).addClass( 'no-results' );
			});

			this.listenTo( this.parent, 'theme:loadMore', function() {
				self.renderThemes( self.parent.page );
			});

			this.listenTo( self.collection, 'themes:rerender', function() {
				self.renderThemes( self.parent.page );
			});

			this.listenTo( this.parent, 'theme:close', function() {
				if ( self.overlay ) {
					self.overlay.closeOverlay();
				}
			} );

			// Bind keyboard events.
			$( 'body' ).on( 'keyup', function( event ) {
				if ( ! self.overlay ) {
					return;
				}

				// Pressing the right arrow key fires a theme:next event
				if ( event.keyCode === 39 ) {
					self.overlay.nextTheme();
				}

				// Pressing the left arrow key fires a theme:previous event
				if ( event.keyCode === 37 ) {
					self.overlay.previousTheme();
				}

				// Pressing the escape key fires a theme:collapse event
				if ( event.keyCode === 27 ) {
					self.overlay.collapse( event );
				}
			});
		},

		// Manages rendering of theme pages
		// and keeping theme count in sync
		render: function() {
			// Clear the DOM, please
			this.$el.empty();

			// If the user doesn't have switch capabilities
			// or there is only one theme in the collection
			// render the detailed view of the active theme
			if ( themes.data.themes.length === 1 ) {

				// Constructs the view
				this.singleTheme = new themes.view.Details({
					model: this.collection.models[0]
				});

				// Render and apply a 'single-theme' class to our container
				this.singleTheme.render();
				this.$el.addClass( 'single-theme' );
				this.$el.append( this.singleTheme.el );
			}

			// Generate the themes
			// Using page instance
			// While checking the collection has items
			if ( this.options.collection.size() > 0 ) {
				this.renderThemes( this.parent.page );
			}

			// Display a live theme count for the collection
			this.count.text( this.collection.count ? this.collection.count : this.collection.length );
		},

		// Iterates through each instance of the collection
		// and renders each theme module
		renderThemes: function( page ) {
			var self = this;

			self.instance = self.collection.paginate( page );

			// If we have no more themes bail
			if ( self.instance.size() === 0 ) {
				// Fire a no-more-themes event.
				this.parent.trigger( 'theme:end' );
				return;
			}

			// Make sure the add-new stays at the end
			if ( page >= 1 ) {
				$( '.add-new-theme' ).remove();
			}

			// Loop through the themes and setup each theme view
			self.instance.each( function( theme ) {
				self.theme = new themes.view.Theme({
					model: theme,
					parent: self
				});

				// Render the views...
				self.theme.render();
				// and append them to div.themes
				self.$el.append( self.theme.el );

				// Binds to theme:expand to show the modal box
				// with the theme details
				self.listenTo( self.theme, 'theme:expand', self.expand, self );
			});

			this.parent.page++;
		},

		// Sets current view
		setView: function( view ) {
			return view;
		},

		// Renders the overlay with the ThemeDetails view.
		// Uses the current model data.
		expand: function( id ) {
			var self = this;

			// Set the current theme model
			this.model = self.collection.get( id );

			if ( _.isUndefined( this.model ) ) {
				return;
			}

			// Trigger a route update for the current model
			themes.router.navigate( themes.router.baseUrl( themes.router.themePath + this.model.id ) );
			themes.utils.title( this.model.attributes.name, 'theme' );

			// Sets this.view to 'detail'
			this.setView( 'detail' );
			$( 'body' ).addClass( 'modal-open' );

			// Set up the theme details view
			this.overlay = new themes.view.Details({
				model: self.model
			});

			this.overlay.render();
			this.$overlay.html( this.overlay.el );

			// Bind to theme:next and theme:previous
			// triggered by the arrow keys
			//
			// Keep track of the current model so we
			// can infer an index position
			this.listenTo( this.overlay, 'theme:next', function() {
				// Renders the next theme on the overlay
				self.next( [ self.model.cid ] );
				$( '.theme-header' ).find( '.right' ).focus();

			})
			.listenTo( this.overlay, 'theme:previous', function() {
				// Renders the previous theme on the overlay
				self.previous( [ self.model.cid ] );
				$( '.theme-header' ).find( '.left' ).focus();
			});
		},

		// This method renders the next theme on the overlay modal
		// based on the current position in the collection
		// @params [model cid]
		next: function( args ) {
			var self = this,
				model, nextModel;

			// Get the current theme
			model = self.collection.get( args[0] );
			// Find the next model within the collection
			nextModel = self.collection.at( self.collection.indexOf( model ) + 1 );

			// Sanity check which also serves as a boundary test
			if ( nextModel !== undefined ) {
				// Trigger a route update for the current model
				self.theme.trigger( 'theme:expand', nextModel.cid );
			}
		},

		// This method renders the previous theme on the overlay modal
		// based on the current position in the collection
		// @params [model cid]
		previous: function( args ) {
			var self = this,
				model, previousModel;

			// Get the current theme
			model = self.collection.get( args[0] );
			// Find the previous model within the collection
			previousModel = self.collection.at( self.collection.indexOf( model ) - 1 );

			if ( previousModel !== undefined ) {
				// Trigger a route update for the current model
				self.theme.trigger( 'theme:expand', previousModel.cid );
			}
		}
	});

	// Search input view controller.
	themes.view.Search = wp.Backbone.View.extend({

		tagName: 'input',
		className: 'wp-filter-search',
		id: 'wp-filter-search-input',
		searching: false,

		attributes: {
			placeholder: l10n.searchPlaceholder,
			type: 'search'
		},

		events: {
			'keyup':  'search',
			'search': 'search'
		},

		initialize: function( options ) {

			this.parent = options.parent;

			this.listenTo( this.parent, 'theme:close', function() {
				this.searching = false;
			} );

		},

		// Handles Ajax request for searching through themes in public repo
		search: function( event ) {
			// Tabbing or reverse tabbing into the search input shouldn't trigger a search
			if ( event.type === 'keyup' && ( event.which === 9 || event.which === 16 ) ) {
				return;
			}

			this.collection = this.options.parent.view.collection;

			// Clear on escape.
			if ( event.type === 'keyup' && event.which === 27 ) {
				event.target.value = '';
			}

			this.doSearch.call( this, event.target.value );
		},

		doSearch: function( value ) {
			var request = {};

			themes.view.Installer.prototype.clearFilters( jQuery.Event( 'click' ) );

			request.search = value;

			// Intercept an [author] search.
			//
			// If input value starts with `author:` send a request
			// for `author` instead of a regular `search`
			if ( value.substring( 0, 7 ) === 'author:' ) {
				request.search = '';
				request.author = value.slice( 7 );
			}

			// Intercept a [tag] search.
			//
			// If input value starts with `tag:` send a request
			// for `tag` instead of a regular `search`
			if ( value.substring( 0, 4 ) === 'tag:' ) {
				request.search = '';
				request.tag = [ value.slice( 4 ) ];
			}

			$( '.filter-links li > a.current' ).removeClass( 'current' );
			$( 'body' ).removeClass( 'show-filters filters-applied' );

			// Set route
			if ( value ) {
				themes.utils.title( value, 'search' );
				themes.router.navigate( themes.router.baseUrl( themes.router.searchPath + value ), { replace: true } );
			} else {
				delete request.search;
				request.browse = themes.data.settings.browseDefault;

				themes.utils.title( 'home' );
				themes.router.navigate( themes.router.baseUrl( '/' ), { replace: true } );
			}

			// Get the themes by sending Ajax POST request to api.wordpress.org/themes
			// or searching the local cache
			this.runQuery( request );
		},

		runQuery: _.debounce( function ( request ) {
			this.collection.query( request );

			// In case we are viewing the single page, close it
			this.parent.trigger( 'theme:close' );
		}, 300 )
	});

	themes.view.Installer = themes.view.Appearance.extend({
		el: '#themes',

		// Register events for sorting and filters in theme-navigation
		events: {
			'click .filter-links li > a:not(.drawer-toggle)': 'onLinkClick',
			'click .theme-filter': 'onFilter',
			'click .drawer-toggle': 'moreFilters',
			'click .filter-drawer .apply-filters': 'applyFilters',
			'click .filter-group [type="checkbox"]': 'addFilter',
			'click .filter-drawer .clear-filters': 'clearFilters',
			'click .filtered-by a': 'backToFilters'
		},

		activeClass: 'current',

		// Overwrite search container class to append search
		// in new location
		searchContainer: $( '.site-branding .search-form' ),

		initialize: function() {
			themes.view.Appearance.prototype.initialize.apply( this, arguments );

			this.sortValues = [ 'popular', 'new' ];
		},

		// Initial render method
		render: function() {
			var self = this;

			this.search();

			this.collection = new themes.Collection();

			// Bump `collection.currentQuery.page` and request more themes if we hit the end of the page.
			this.listenTo( this, 'theme:end', function() {

				// Make sure we are not already loading
				if ( self.collection.loadingThemes ) {
					return;
				}

				if ( $( 'body' ).hasClass( 'modal-open' ) ) {
					return;
				}

				if ( self.collection.length < themes.data.settings.postsPerPage ) {
					return;
				}

				// Set loadingThemes to true and bump page instance of currentQuery.
				self.collection.loadingThemes = true;
				self.collection.currentQuery.page++;

				// Use currentQuery.page to build the themes request.
				_.extend( self.collection.currentQuery.request, { page: self.collection.currentQuery.page } );
				self.collection.query( self.collection.currentQuery.request );
			});

			this.listenTo( this.collection, 'query:success', function( count ) {
				$( 'body' ).removeClass( 'loading-content' );
				$( '.theme-browser' ).find( 'div.error' ).remove();

				// If we've loaded another page, set focus to the first of the new themes.
				if ( self.page > 1 ) {
					var nextTheme = 1 + ( ( self.page - 1 ) * themes.data.settings.postsPerPage );
					this.$el.find( '.theme:nth-child(' + nextTheme + ')' ).focus();
				}

				if ( ! _.isNumber( count ) ) {
					count = self.collection.count;
				}

				// Hide the load more button when all themes matching this
				// collection query are on the page.
				if ( count <= self.collection.length ) {
					self.loadMore.hide();
				} else {
					self.loadMore.show();
				}
			});

			this.listenTo( this.collection, 'query:fail', function() {
				$( 'body' ).removeClass( 'loading-content' );
				$( '.theme-browser' ).find( 'div.error' ).remove();
				$( '.theme-browser' ).find( 'div.themes' ).before( '<div class="error"><p>' + l10n.error + '</p></div>' );
			});

			if ( this.view ) {
				this.view.remove();
			}

			// Set ups the view and passes the section argument
			this.view = new themes.view.Themes({
				collection: this.collection,
				parent: this
			});

			// Reset pagination every time the install view handler is run
			this.page = 0;

			// Render and append
			this.$el.find( '.themes' ).remove();
			this.view.render();
			this.$el.find( '.theme-browser' ).append( this.view.el ).addClass( 'rendered' );
		},

		// Handles all the rendering of the public theme directory
		browse: function( section ) {
			// Create a new collection with the proper theme data
			// for each section
			if ( 'favorites' === section ) {
				this.collection.query( {
					browse: section,
					user: themes.data.settings.currentUser?.login
				} );
			} else {
				this.collection.query( { browse: section } );
			}
		},

		// Handle clicks on link navigation.
		onLinkClick: function( event ) {
			const $el = $( event.target );
			const data = $el.data() || {};

			event.preventDefault();

			// Special handling for any tags present within the menu, such as full-site-editing.
			if ( ! data.sort && data.tag ) {
				themes.router.trigger( 'route:tag', data.tag );
				return;
			}

			$( 'body' ).removeClass( 'filters-applied show-filters' );

			// Bail if this is already active
			if ( $el.hasClass( this.activeClass ) ) {
				return;
			}

			// Use the sort function for both sort and model queries, as it
			// also handles resetting fitlers and active classes.
			const section = data.sort || data.model; 
			this.sort( section );

			// Trigger a router.navigate update.
			if ( themes.data.settings.browseDefault === section ) {
				themes.router.navigate( themes.router.baseUrl( '/' ) );
			} else {
				themes.router.navigate( themes.router.baseUrl( themes.router.browsePath + section ) );
			}
		},

		sort: function( sort ) {
			const self = this;
			const isSort = ( -1 !== _.indexOf( this.sortValues, sort ) );

			self.clearSearch();

			// Clear filters.
			_.each( $( '.filter-group' ).find( ':checkbox' ).filter( ':checked' ), function( item ) {
				$( item ).prop( 'checked', false );
				return self.filtersChecked();
			} );

			$( '.filter-links li > a, .theme-filter' ).removeClass( this.activeClass );

			if ( isSort ) {
				// Highlight the active tab.
				const $activeTab = $( '.filter-links li > a[data-sort="' + sort + '"]' );
				$activeTab.addClass( this.activeClass );

				// Update the page title.
				if ( themes.data.settings.browseDefault === sort ) {
					themes.utils.title( 'home' );
				} else {
					themes.utils.title( $activeTab.text(), 'browse' );
				}

				this.browse( sort );
			} else if ( 'favorites' === sort || 'commercial' === sort || 'community' === sort ) {
				// Grab the current link. Note, favorites uses a different data attribute.
				const $link = 'favorites' === sort ?
					$( '.filter-links li > a[data-sort="' + sort + '"]' ) :
					$( '.filter-links li > a[data-model="' + sort + '"]' );

				// Highlight the current link.
				$link.addClass( this.activeClass );

				// Update the page title.
				themes.utils.title( $link.text(), 'browse' );

				this.browse( sort );
			} else {
				themes.utils.title( '404', 'notfound' );
			}
		},

		// Filters and Tags
		onFilter: function( event ) {
			var request,
				$el = $( event.target ),
				filter = $el.data( 'filter' );

			// Bail if this is already active
			if ( $el.hasClass( this.activeClass ) ) {
				return;
			}

			$( '.filter-links li > a, .theme-section' ).removeClass( this.activeClass );
			$el.addClass( this.activeClass );

			if ( ! filter ) {
				return;
			}

			// Construct the filter request
			// using the default values
			filter = _.union( [ filter, this.filtersChecked() ] );
			request = { tag: [ filter ] };

			// Get the themes by sending Ajax POST request to api.wordpress.org/themes
			// or searching the local cache
			this.collection.query( request );
		},

		// Clicking on a checkbox to add another filter to the request
		addFilter: function() {
			this.filtersChecked();
		},

		// Applying filters triggers a tag request.
		applyFilters: function( event ) {
			var names = [],
				name,
				tags = this.filtersChecked(),
				request = { tag: tags },
				filteringBy = $( '.filtered-by .tags' );

			if ( event ) {
				event.preventDefault();
			}

			if ( ! tags ) {
				return;
			}

			$( 'body' ).addClass( 'filters-applied' );
			$( '.filter-links li > a.current' ).removeClass( 'current' );
			filteringBy.empty();

			_.each( tags, function( tag ) {
				name = $( 'label[for="filter-id-' + tag + '"]' ).text();
				names.push( name );
				filteringBy.append( '<span class="tag">' + name + '</span>' );

				$( '.filter-links li > a[data-tag="' + tag + '"]' ).addClass( 'current' );
			});

			themes.router.navigate( themes.router.baseUrl( 'tags/' + tags.join( '+' ) ) );
			themes.utils.title( names[0], 'tags' );

			// Get the themes by sending Ajax POST request to api.wordpress.org/themes
			// or searching the local cache
			this.collection.query( request );
		},

		// Get the checked filters.
		// @return {array} of tags or false.
		filtersChecked: function() {
			var items  = $( '.filter-group' ).find( ':checkbox' ).filter( ':checked' ),
				drawer = $( '.filter-drawer' ),
				tags   = [];

			_.each( items, function( item ) {
				tags.push( $( item ).prop( 'value' ) );
			});

			// When no filters are checked, restore initial state and return.
			if ( 0 === tags.length ) {
				drawer.find( '.apply-filters' ).prop( 'disabled', true ).find( 'span' ).text( '' );
				drawer.find( '.clear-filters' ).hide();
				$( 'body' ).removeClass( 'filters-applied' );
				return false;
			}

			drawer.find( '.apply-filters' ).prop( 'disabled', false ).find( 'span' ).text( tags.length );
			drawer.find( '.clear-filters' ).css( 'display', 'inline-block' );

			return tags;
		},

		// Toggle the full filters navigation.
		moreFilters: function( event ) {
			event.preventDefault();

			if ( $( 'body' ).hasClass( 'filters-applied' ) ) {
				return this.backToFilters();
			}

			// If the filters section is opened and filters are checked
			// run the relevant query collapsing to filtered-by state
			if ( $( 'body' ).hasClass( 'show-filters' ) && this.filtersChecked() ) {
				return this.addFilter();
			}

			this.clearSearch();

			$( 'body' ).toggleClass( 'show-filters' );
		},

		// Clears all the checked filters
		// @uses filtersChecked()
		clearFilters: function( event ) {
			var items = $( '.filter-group' ).find( ':checkbox' ),
				self = this;

			event.preventDefault();

			_.each( items.filter( ':checked' ), function( item ) {
				$( item ).prop( 'checked', false );
				return self.filtersChecked();
			});
		},

		backToFilters: function( event ) {
			if ( event ) {
				event.preventDefault();
			}

			$( 'body' ).removeClass( 'filters-applied' );
		},

		clearSearch: function() {
			$( '#wp-filter-search-input').val( '' );
		}
	});

	themes.Router = Backbone.Router.extend({
		routes: {
			'browse/:sort(/page/:page)(/)'   : 'sort',
			'tags/:tag(/page/:page)(/)'      : 'tag',
			'search/:query(/page/:page)(/)'  : 'search',
			'author/:author(/page/:page)(/)' : 'author',
			':slug(/)'                       : 'preview',
			''                               : 'sort'
		},

		baseUrl: function( url ) {
			if ( '/' === url ) {
				// Bad workaround for https://github.com/jashkenas/backbone/issues/3391
				url = '/#';
			} else if ( 0 !== url.length ) {
				url += '/';
			}
			return url;
		},

		themePath: '',
		browsePath: 'browse/',
		searchPath: 'search/',

		search: function( query ) {
			$( '.wp-filter-search' ).val( query );
		},

		navigate: function() {
			if ( Backbone.history._hasPushState ) {
				Backbone.Router.prototype.navigate.apply( this, arguments );
			}
		}
	});

	themes.History = Backbone.History.extend({
		getFragment: function() {
			// We don't use query params, make Backbone work when they're present.
			return Backbone.History.prototype.getFragment.apply(this, arguments).replace(/\?.*/, '');
		}
	});

	themes.Run = {
		init: function() {
			// Set up the view
			// Passes the default 'section' as an option
			this.view = new themes.view.Installer({
				section: themes.data.settings.browseDefault,
				SearchView: themes.view.Search
			});

			// Replace Backbones history with our overrides.
			Backbone.history = new themes.History();

			// Render results
			this.render();
		},

		render: function() {

			// Render results
			this.view.render();
			this.routes();

			Backbone.history.start({
				root: themes.data.settings.path,
				pushState: true,
				hashChange: false
			});
		},

		routes: function() {
			var self = this,
				request = {};

			// Bind to our global `themes` object
			// so that the router is available to sub-views
			themes.router = new themes.Router();

			// Handles `theme` route event
			// Queries the API for the passed theme slug
			themes.router.on( 'route:preview', function( slug ) {
				self.view.collection.queries.push( themes.data.query );

				request.theme = slug;
				self.view.collection.query( request );
				self.view.view.expand( slug );
			});

			// Handles sorting / browsing routes
			// Also handles the root URL triggering a sort request
			// for `featured`, the default view
			themes.router.on( 'route:sort', function( sort, page ) {
				if ( page ) {
					themes.router.navigate( 'browse/' + sort + '/', { replace: true } );
				}

				self.view.collection.queries.push( themes.data.query );

				if ( ! sort ) {
					sort = themes.data.settings.browseDefault;
				}
				self.view.sort( sort );
				self.view.trigger( 'theme:close' );
			});

			// The `search` route event. The router populates the input field.
			themes.router.on( 'route:search', function( query, page ) {
				if ( page ) {
					themes.router.navigate( 'search/' + query + '/', { replace: true } );
				}

				self.view.collection.queries.push( themes.data.query );

				$( '.wp-filter-search' ).focus().trigger( 'keyup' );
				self.view.trigger( 'theme:close' );
			});

			themes.router.on( 'route:tag', function( tag, page ) {
				if ( page ) {
					themes.router.navigate( 'tags/' + tag + '/', { replace: true } );
				}

				self.view.collection.queries.push( themes.data.query );

				_.each( tag.split( '+' ), function( tag ) {
					tag = tag.toLowerCase().replace( /[^a-z-]/g, '' );
					$( '#filter-id-' + tag ).prop( 'checked', true );
				});
				$( 'body' ).removeClass( 'show-filters' ).addClass( 'show-filters' );
				self.view.applyFilters();
				self.view.trigger( 'theme:close' );
			});

			themes.router.on( 'route:author', function( author, page ) {
				if ( page ) {
					themes.router.navigate( 'author/' + author + '/', { replace: true } );
				}

				self.view.collection.queries.push( themes.data.query );

				request.author = author;
				self.view.collection.query( request );
				themes.utils.title( author, 'author' );
				self.view.trigger( 'theme:close' );
			});
		}
	};

	// Ready...
	$( function() {
		themes.Run.init();
	});

})( jQuery );

( function( google ) {
	google.charts.load( 'current', {
		packages: [ 'corechart' ]
	});
})( google );

( function( wp, themeDir ) {
	const logError = function( result ) {
		document.querySelector( '.spinner' )?.classList.remove( 'spinner' );
		const error = result.status + ': ' + result.statusText;
		//result = JSON.parse( result.responseText );
		//if ( typeof result.message !== 'undefined' ) {
			alert( error );
		//}
	};
	
	document.addEventListener( 'submit', event => {
		const form = event.target.closest('form');

		if ( ! form || ! ['commercial', 'community'].includes(form.id) ) {
			return;
		}

		event.preventDefault();

		const submitButton = form.querySelector('button[type="submit"]');
		const successMsg = form.querySelector('.success-msg');
		const themeSlug = form.closest('.theme-about')?.dataset.slug;

		successMsg?.classList.remove( 'saved' );

		let field_name = '';

		if ( 'commercial' === form.id ) {
			field_name = 'external_support_url';
			rest_name  = 'supportURL';
		} else {
			field_name = 'external_repository_url';
			rest_name  = 'repositoryURL';
		}

		let fieldInput = form.querySelector( 'input[name="' + field_name + '"]' ),
			button = form.querySelector( '.button-small' )?.classList.add( 'spinner' ),
			url = themeDir.restUrl + 'themes/v1/theme/' + themeSlug + '/' + form.id + '/?_wpnonce=' + themeDir.restNonce;
			originalValue = fieldInput.dataset.originalValue ?? '';

		submitButton.disabled = true;

		fetch( url, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
			},
			body: JSON.stringify({
				[rest_name]: fieldInput.value,
			}),
		})
			.then((response) => {
				if ( !response.ok ) {
					logError(response);
				}
				return response;
			})
			.then((response) => {
				return response.json();
			})
			.then((data) => {
				let fieldValue;
				if ( typeof data[rest_name] !== 'undefined' ) {
					successMsg?.classList.add( 'saved' );
					// Use value sanitized and saved by server.
					fieldValue = data[rest_name];
				} else {
					// Restore original value.
					fieldValue = originalValue;
				}
				fieldInput.value = fieldValue;
				// Update widget.
				const widgetLink = document.querySelector('.categorization-widget .widget-head a');
				if ( widgetLink ) {
					widgetLink.attributes.href.value = fieldValue;
				}
				// TODO: Update the in-memory data relating to the post so navigating between themes doesn't
				// continue to use the original cached value for the URL.
				submitButton.disabled = false;
				button?.classList.remove( 'spinner' );
			})
			.catch((error) => {
				logError(error);
				fieldInput.value = originalValue;
				submitButton.disabled = false;
			})
	} );
} )( window.wp, _wpThemeSettings.rest );
