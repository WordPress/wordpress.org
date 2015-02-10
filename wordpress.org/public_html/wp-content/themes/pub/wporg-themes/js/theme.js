( function ( $, wp ) {
	google.load("visualization", "1", {packages:["corechart"]});

	_.extend( wp.themes.view.Appearance.prototype, {
		el: '#themes .theme-browser',
		searchContainer: ''
	});

	_.extend( wp.themes.view.Installer.prototype, {
		el: '#themes',

		// Applying filters triggers a tag request.
		applyFilters: function( event ) {
			var name,
				tags = this.filtersChecked(),
				request = { tag: tags },
				filteringBy = $( '.filtered-by .tags' );

			if ( event ) {
				event.preventDefault();
			}

			$( 'body' ).addClass( 'filters-applied' );
			$( '.filter-links li > a.current' ).removeClass( 'current' );
			filteringBy.empty();

			_.each( tags, function( tag ) {
				name = $( 'label[for="filter-id-' + tag + '"]' ).text();
				filteringBy.append( '<span class="tag">' + name + '</span>' );
			});

			wp.themes.router.navigate( wp.themes.router.baseUrl( 'tag/' + tags.join( '+' ) ), { replace: true } );

			// Get the themes by sending Ajax POST request to api.wordpress.org/themes
			// or searching the local cache
			this.collection.query( request );
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

	});

	_.extend( wp.themes.view.Theme.prototype, {
		events: {
			'click': 'expand',
			'keydown': 'expand',
			'touchend': 'expand',
			'keyup': 'addFocus',
			'touchmove': 'preventExpand'
		},

		render: function() {
			var data = this.model.toJSON();

			data.permalink = wp.themes.router.baseUrl( data.slug );

			// Render themes using the html template
			this.$el.html( this.html( data ) ).attr({
				tabindex: 0,
				'aria-describedby' : data.id + '-action ' + data.id + '-name'
			});
		},

		// Single theme overlay screen
		// It's shown when clicking a theme
		expand: function( event ) {
			var self = this;

			event = event || window.event;

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
		}
	});

//	wp.themes.view.Preview.prototype = wp.themes.view.Details.prototype;

	_.extend( wp.themes.view.Details.prototype, {
		events: {
			'click': 'collapse',
			'click .delete-theme': 'deleteTheme',
			'click .left': 'previousTheme',
			'click .right': 'nextTheme',
			'click .button-secondary': 'preview',
			'keydown .button-secondary': 'preview',
			'touchend .button-secondary': 'preview'
		},

		render: function() {
			var data = this.model.toJSON(),
				updated = new Date(data.last_updated);

			// If last updated plus 2 years is in the past, it's outdated.
			data.is_outdated = updated.setYear(updated.getYear() + 1902).valueOf() < new Date().valueOf();

			// Make tags click-able and separated by a comma.
			data.tags = _.map( data.tags, function( tag ) {
				return '<a href="' + wp.themes.router.baseUrl( 'tag/' + tag ) + '">' + tag + '</a>';
			}).join( ', ' );

			this.$el.html( this.html( data ) );
			// Renders active theme styles
			this.activeTheme();
			// Set up navigation events
			this.navigation();
			// Checks screenshot size
			this.screenshotCheck( this.$el );
			// Contain "tabbing" inside the overlay
			this.containFocus( this.$el );
			this.renderDownloadsGraph();
		},

		preview: function( event ) {
			var self = this,
				current, preview;

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

			event.preventDefault();

			event = event || window.event;

			// Set focus to current theme.
			wp.themes.focusedTheme = this.$el;

			// Construct a new Preview view.
			preview = new wp.themes.view.Preview({
				model: this.model
			});

			// Render the view and append it.
			preview.render();
			this.setNavButtonsState();

			// Hide previous/next navigation if there is only one theme
			if ( this.model.collection.length === 1 ) {
				preview.$el.addClass( 'no-navigation' );
			} else {
				preview.$el.removeClass( 'no-navigation' );
			}

			if ( wp.themes.data.settings.isMobile ) {
				preview.$el.addClass( 'wp-full-overlay collapsed' );
			} else {
				preview.$el.addClass( 'wp-full-overlay expanded' );
			}

			// Append preview
			$( '.theme-install-overlay' ).append( preview.el );

			// Listen to our preview object
			// for `theme:next` and `theme:previous` events.
			this.listenTo( preview, 'theme:next', function() {

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
				self.current = self.model;
			});
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

		screenshotCheck: function( el ) {
			var image = new Image();
			image.src = el.find( '.screenshot img' ).attr( 'src' );
		},

		renderDownloadsGraph: function() {
			var self = this;

			$.getJSON( 'https://api.wordpress.org/stats/themes/1.0/downloads.php?slug=' + self.model.get( 'id' ) + '&limit=730&callback=?', function( downloads ) {
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
						groupWidth: ( data.getNumberOfRows() > 100 ) ? "100%" : null
					},
					height: 350
				});
			});
		}
	});

	_.extend( wp.themes.view.Preview.prototype, {

		close: function() {
			this.$el.fadeOut( 200, function() {
				$( 'body' ).removeClass( 'theme-installer-active full-overlay-active' );

				// Return focus to the theme div
				if ( wp.themes.focusedTheme ) {
					wp.themes.focusedTheme.focus();
				}
			});

			this.trigger( 'preview:close' );
			this.undelegateEvents();
			this.unbind();
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

	_.extend( wp.themes.view.InstallerSearch.prototype, {
		doSearch: _.debounce( function( value ) {
			var request = {};

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

			// Get the themes by sending Ajax POST request to api.wordpress.org/themes
			// or searching the local cache
			this.collection.query( request );

			// Set route
			if ( value ) {
				wp.themes.router.navigate( wp.themes.router.baseUrl( wp.themes.router.searchPath + value ), { replace: true } );
			} else {
				wp.themes.router.navigate( wp.themes.router.baseUrl( '' ) );
			}
		}, 300 )
	});

	_.extend( wp.themes.InstallerRouter.prototype, {
		routes: {
			'browse/:sort/': 'sort',
			'tag/:tag/': 'tag',
			'search/:query/': 'search',
			'author/:author/': 'author',
			':slug/': 'preview',
			'': 'sort'
		},

		baseUrl: function( url ) {
			if ( 0 !== url.length ) {
				url += '/';
			}
			return '/' + url;
		},

		themePath: '',
		browsePath: 'browse/',
		searchPath: 'search/'
	});

	_.extend( wp.themes.RunInstaller, {
		extraRoutes: function() {
			var self = this,
			request = {};

			// Open the modal when matching the route for a single themes.
			wp.themes.router.on( 'route:preview', function( slug ) {
				this.listenTo( self.view.collection, 'query:success', function() {
					self.view.view.expand( slug );
				});
			});

			wp.themes.router.on( 'route:tag', function( tag ) {
				_.each( tag.split( '+' ), function( tag ) {
					$( '#filter-id-' + tag ).prop( 'checked', true );
				});
				$( 'body' ).toggleClass( 'show-filters' );
				self.view.applyFilters();
			});

			wp.themes.router.on( 'route:author', function( author ) {
				request.author = author;
				self.view.collection.query( request );
			});
		}
	});

}( jQuery, wp ) );