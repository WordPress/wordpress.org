var camptix = camptix || {};

jQuery(document).ready(function($){

	camptix.models = camptix.models || {};
	camptix.views = camptix.views || {};
	camptix.collections = camptix.collections || {};

	/**
	 * Attendee Model
	 *
	 * This model represents an attendee and their attendance status.
	 */
	camptix.models.Attendee = Backbone.Model.extend({
		defaults: function() {
			return {
				status: false,
				avatar: '',
				name: '',
			}
		},

		/**
		 * Set the attendance status and save on server.
		 */
		toggle: function( attended ) {
			this.save({ status: attended });
		},

		/**
		 * Sync attendance status with the server.
		 */
		sync: function( method, model, options ) {
			var model = this;
			model.trigger( 'camptix:sync:start' );

			options = options || {};
			options.context = this;
			options.type = 'GET';

			options.data = _.extend( options.data || {}, {
				action: 'camptix-attendance',
				camptix_secret: _camptixAttendanceSecret
			});

			if ( method == 'read' ) {
				options.data = _.extend( options.data || {}, {
					camptix_action: 'sync-model',
					camptix_id: this.id
				});

				return wp.ajax.send( options ).done( function() { model.trigger( 'camptix:sync:end' ); } );

			} else if ( method == 'update' ) {
				options.data = _.extend( options.data || {}, {
					camptix_action: 'sync-model',
					camptix_set_attendance: this.get( 'status' ),
					camptix_id: this.id
				});

				return wp.ajax.send( options ).done( function() { model.trigger( 'camptix:sync:end' ) } );
			}
		}
	});

	/**
	 * Attendees Collection
	 *
	 * A collection to query and hold lists of attendees.
	 */
	camptix.collections.AttendeesList = Backbone.Collection.extend({

		model: camptix.models.Attendee,

		initialize: function( models, options ) {
			this._hasMore = true;
			this.query = options.query;
			this.controller = options.controller;
		},

		/**
		 * Talk to the server for more items.
		 */
		sync: function( method, model, options ) {
			if ( method == 'read' ) {
				options = options || {};
				options.context = this;
				options.type = 'GET';
				options.data = _.extend( options.data || {}, {
					action: 'camptix-attendance',

					camptix_action: 'sync-list',
					camptix_paged: Math.floor( this.length / 50 ) + 1,
					camptix_secret: _camptixAttendanceSecret
				});

				if ( this.query.search )
					options.data.camptix_search = this.query.search;

				if ( this.query.filters )
					options.data.camptix_filters = this.query.filters;

				return wp.ajax.send( options );
			}
		},

		/**
		 * Returns true if this collection (potentially) has more items.
		 */
		hasMore: function() {
			return this._hasMore;
		},

		/**
		 * Get more items with this query.
		 */
		more: function( options ) {
			var that = this;

			if ( ! this.hasMore() ) {
				return $.Deferred().resolveWith( this ).promise();
			}

			if ( this._more && 'pending' === this._more.state() ) {
				return this._more;
			}

			return this._more = this.fetch({ remove: false }).done( function( resp ) {
				if ( _.isEmpty( resp ) || resp.length < 50 ) {
					that._hasMore = false;
					this.controller.trigger( 'more:toggle', this._hasMore );
				}
			});
		}
	});

	/**
	 * Attendee View
	 *
	 * A view of a single attendee in a list.
	 */
	camptix.views.AttendeeView = Backbone.View.extend({
		tagName: 'li',
		className: 'item',

		template: wp.template( 'attendee' ),

		events: {
			'fastClick': 'toggle'
		},

		initialize: function( options ) {
			this.controller = options.controller;

			this.listenTo( this.model, 'change', this.render );
			this.listenTo( this.model, 'destroy', this.remove );
			this.listenTo( this.model, 'camptix:sync:start', this.syncStart );
			this.listenTo( this.model, 'camptix:sync:end', this.syncEnd );
		},

		/**
		 * Render the attendee list item.
		 */
		render: function() {
			this.$el.html( this.template( this.model.toJSON() ) );
			return this;
		},

		/**
		 * Show a spinner.
		 */
		syncStart: function() {
			this.$el.addClass( 'camptix-loading' );
		},
		
		/**
		 * Hide the spinner.
		 */
		syncEnd: function() {
			this.$el.removeClass( 'camptix-loading' );
		},

		/**
		 * Open the Attendee Toggle modal.
		 */
		toggle: function() {
			// This touch was to stop a scroll.
			if ( +new Date() - this.controller.lastScroll < 200 )
				return;

			var toggleView = new camptix.views.AttendeeToggleView({ model: this.model, controller: this.controller });
			$(document.body).append( toggleView.render().el );
		}
	});

	/**
	 * Attendee Toggle View
	 *
	 * The modal that pops up when an attendee is selected
	 * from the list. Here you can toggle their status.
	 */
	camptix.views.AttendeeToggleView = Backbone.View.extend({
		className: 'attendee-toggle-wrap',

		template: wp.template( 'attendee-toggle' ),

		events: {
			'fastClick .yes': 'yes',
			'fastClick .no': 'no',
			'fastClick .close': 'close'
		},

		initialize: function( options ) {
			this.controller = options.controller;
			this.$overlay = $('.overlay');
		},

		/**
		 * Render modal.
		 */
		render: function() {
			this.$el.html( this.template( this.model.toJSON() ) );
			this.$overlay.show();
			return this;
		},

		/**
		 * Set to attending.
		 */
		yes: function() {
			this.controller.trigger( 'flush' );
			this.model.toggle( true );
			return this.close();
		},

		/**
		 * Set to not attending.
		 */
		no: function() {
			this.controller.trigger( 'flush' );
			this.model.toggle( false );
			return this.close();
		},

		/**
		 * Close modal without changing any settings.
		 */
		close: function() {
			this.$overlay.hide();
			this.remove();
			return false;
		}
	});

	/**
	 * Search View
	 *
	 * A search view invoked via Menu - Search.
	 */
	camptix.views.AttendeeSearchView = Backbone.View.extend({
		className: 'attendee-search-view',
		template: wp.template( 'attendee-search' ),

		events: {
			'input input':  'search',
			'keyup input':  'search',
			'change input': 'search',
			'search input': 'search',
			'fastClick .close': 'close'
		},

		initialize: function( options ) {
			if ( options && options.controller ) {
				this.controller = options.controller;
			}

			this.search = _.debounce( this.search, 500 );
		},

		/**
		 * Render Search view.
		 */
		render: function() {
			this.$el.html( this.template() );
			return this;
		},

		/**
		 * Ask the controller to perform a new search.
		 */
		search: function( event ) {
			if ( event.keyCode == 13 ) {
				this.$el.find( 'input' ).blur();
			}

			var keyword = event.target.value || '';
			this.controller.trigger( 'search', keyword );
		},

		/**
		 * Close the view and reset search.
		 */
		close: function() {
			this.controller.trigger( 'search', '' );
			this.remove();
		}
	});

	/**
	 * Filter View
	 *
	 * Invoked via Menu - Filters.
	 */
	camptix.views.AttendeeFilterView = Backbone.View.extend({
		className: 'attendee-filter-view',
		template: wp.template( 'attendee-filter' ),

		events: {
			'fastClick .close': 'close',
			'fastClick .filter-attendance li': 'toggleAttendance',
			'fastClick .filter-tickets li': 'toggleTickets'
		},

		initialize: function( options ) {
			this.controller = options.controller;
			this.filterSettings = options.filterSettings || {};
		},

		/**
		 * Render the filters menu.
		 */
		render: function() {
			this.$el.html( this.template( this.filterSettings ) );
			return this;
		},

		/**
		 * Close the filter screen.
		 */
		close: function() {
			this.remove();
		},

		/**
		 * Toggle items in the attendance status list.
		 */
		toggleAttendance: function( event ) {
			var selection = $( event.target ).data( 'attendance' );
			this.filterSettings.attendance = selection;
			this.render();

			this.controller.trigger( 'filter', this.filterSettings );
		},

		/**
		 * Toggle items in the tickets list.
		 */
		toggleTickets: function( event ) {
			var ticket_id = $( event.target ).data( 'ticket-id' );

			// Remove or append the ticket_id to the filter settings.
			if ( _.contains( this.filterSettings.tickets, ticket_id ) ) {
				this.filterSettings.tickets = _.without( this.filterSettings.tickets, ticket_id );
			} else {
				this.filterSettings.tickets.push( ticket_id );
			}

			this.render();
			this.controller.trigger( 'filter', this.filterSettings );
		},
	});

	/**
	 * Main Application View and controller.
	 */
	camptix.views.Application = Backbone.View.extend({
		template: wp.template( 'application' ),

		/**
		 * Main Application events/controls.
		 */
		events: {
			'fastClick .dashicons-menu': 'menu',
			'fastClick .submenu .search': 'searchView',
			'fastClick .submenu .refresh': 'refresh',
			'fastClick .submenu .filter': 'filterView'
		},

		/**
		 * Initialize the application.
		 */
		initialize: function() {
			this.cache = [];
			this.query = {};
			this.requests = [];
			this.lastScroll = 0;

			this.filterSettings = {
				'attendance': 'none',
				'tickets': _camptixAttendanceTickets,
				'search': ''
			};

			this.render();

			this.$header = this.$el.find( 'header' );
			this.$menu = this.$header.find( '.menu' );

			this.scroll = _.chain( this.scroll ).bind( this ).value();
			this.$list = this.$el.find( '.attendees-list' );
			this.$list.on( 'scroll', this.scroll );
			this.$loading = this.$list.find( '.loading' );

			this.on( 'search', this.search, this );
			this.on( 'flush', this.flush, this );
			this.on( 'more:toggle', this.moreToggle, this );
			this.on( 'filter', this.filter, this );

			this.setupCollection();
		},

		/**
		 * Runs when hasMore is toggled in the current collection.
		 */
		moreToggle: function( hasMore ) {
			this.$loading.toggle( hasMore );
		},

		/**
		 * Setup a collection (or retrieve one from cache)
		 */
		setupCollection: function( query ) {
			var collection,
				options = {};

			// Dispose of the current collection and cache it for later use.
			if ( 'undefined' != typeof this.collection ) {
				this.collection.off( null, null, this );
				this.cache.push( this.collection );
			}

			query = _.defaults( query || {}, {
				search: '',
				filters: _.clone( this.filterSettings )
			});

			options.query = query;
			options.controller = this;

			collection = _.find( this.cache, function( collection ) {
				return _.isEqual( collection.query, options.query );
			} );

			if ( ! collection ) {
				collection = new camptix.collections.AttendeesList( [], options );
			}

			this.query = query;
			this.collection = collection;
			this.collection.on( 'add', this.add, this );
			this.collection.on( 'reset', this.reset, this );

			// Clear the list before adding things back.
			this.$list.find( 'li.item' ).remove();

			if ( this.collection.length ) {
				this.collection.trigger( 'reset' );
			} else {
				this.collection.more().done( this.scroll );
			}

			this.trigger( 'more:toggle', collection.hasMore() );
		},

		/**
		 * Scroll event handler.
		 */
		scroll: function() {
			var view = this,
				el = this.$list[0];

			this.lastScroll = +new Date();

			if ( ! this.collection.hasMore() )
				return;

			if ( el.scrollHeight < el.scrollTop + ( el.clientHeight * 3 ) ) {
				this.collection.more().done(function() {
					view.scroll();
				});
			}
		},

		/**
		 * Render the application view.
		 */
		render: function() {
			this.$el.html( this.template() );
			$(document.body).append( this.el );
			return this;
		},

		/**
		 * Append a single AttendeeView item (from a model) to the list.
		 */
		add: function( item ) {
			var view = new camptix.views.AttendeeView({ model: item, controller: this });
			this.$loading.before( view.render().el );
		},

		/**
		 * A collection is reset. Make sure everything is added back to the view.
		 */
		reset: function() {
			this.collection.each( this.add, this );
		},

		/**
		 * Toggle nav menu.
		 */
		menu: function( event ) {
			this.$menu.toggleClass( 'dropdown' );
		},

		/**
		 * Show the Search view.
		 */
		searchView: function() {
			this.$menu.removeClass( 'dropdown' );
			this.searchView = new camptix.views.AttendeeSearchView({ controller: this });
			this.$header.append( this.searchView.render().el );

			this.searchView.$el.find('input').focus();
			return false;
		},

		/**
		 * Show the Filter Settings view.
		 */
		filterView: function() {
			this.$menu.removeClass( 'dropdown' );
			this.filterView = new camptix.views.AttendeeFilterView({ controller: this, filterSettings: this.filterSettings });
			this.$el.append( this.filterView.render().el );
			return false;
		},

		/**
		 * Remove everything from the list, flush all caches
		 * and setup a new collection with the current settings.
		 */
		refresh: function() {
			this.$menu.removeClass( 'dropdown' );
			delete this.collection;
			this.flush();
			this.setupCollection();
			return false;
		},

		/**
		 * Re-initialize a calloction with a search term.
		 */
		search: function( keyword ) {
			this.keyword = this.keyword || '';
			if ( keyword == this.keyword )
				return;

			this.keyword = keyword;
			this.setupCollection({ search: this.keyword });
		},

		/**
		 * Re-initialize a collection with (possibly) new filter settings.
		 */
		filter: function( settings ) {
			this.filterSettings = settings;
			delete this.collection;
			this.flush();
			this.setupCollection();
		},

		/**
		 * Remove all queries from cache.
		 */
		flush: function() {
			this.cache = [];
		}
	});

	// Initialize application.
	camptix.app = new camptix.views.Application();
});