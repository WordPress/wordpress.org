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
		},
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

			preview.$el.addClass( 'wp-full-overlay expanded' );

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
		}
	});

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