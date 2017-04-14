/*global jQuery, Backbone, _, wp, wordcamp, WordCampMentors, WordCampMentorsTaskData, WordCampMentorsTaskCategoryData, setUserSetting */

( function( window, $ ) {

	'use strict';

	window.wordcamp = window.wordcamp || {};

	wordcamp.mentors = $.extend( {
		views: {}
	}, WordCampMentors );

	var $document = $( document ),
		prefix = wordcamp.mentors.prefix;

	/**
	 * A Backbone view for the list of tasks.
	 */
	wordcamp.mentors.views.List = Backbone.View.extend( {
		/**
		 * Time increment in ms for polling the server.
		 */
		tick: 5000,

		/**
		 * Unix timestamp of the last activity within the app.
		 */
		lastActive: 0,

		/**
		 *
		 */
		hibernating: false,

		/**
		 * Data to submit when fetching a task collection.
		 */
		taskRequest: {
			data: {
				per_page: 300,
				orderby: 'menu_order',
				order: 'asc'
			},
			remove: false
		},

		/**
		 * Initialize the List view.
		 *
		 * @returns {wordcamp.mentors.views.List}
		 */
		initialize: function() {
			var view = this;

			this.setLastActive();

			this.tasks      = new wp.api.collections.Wcm_task( WordCampMentorsTaskData );
			this.categories = new wp.api.collections.Wcm_task_category( WordCampMentorsTaskCategoryData );
			this.filter     = new wordcamp.mentors.views.Filter( { el: '#tasks-filter', list: this } );
			this.reset      = new wordcamp.mentors.views.Reset( { el: '#tasks-reset' } );

			this.listeners();

			if ( this.tasks.length ) {
				view.render();

				view.ticker = setInterval( function() {
					view.trigger( 'tick:' + view.tick );
				}, view.tick );
			}

			return this;
		},

		/**
		 * Render the List view.
		 *
		 * @returns {wordcamp.mentors.views.List}
		 */
		render: function() {
			var view = this;

			this.$el.empty();

			this.tasks.each( function( model ) {
				var categories, task;

				categories = _.filter( view.categories.models, function( category ) {
					return _.contains( model.get( prefix + '_task_category' ), category.get( 'id' ) );
				});

				task = new wordcamp.mentors.views.Task( {
					model: model,
					list: view,
					categories: categories
				});

				view.$el.append( task.$el );
			});

			this.trigger( 'setFilter', { skipHighlight: true } );

			return this;
		},

		/**
		 * Set up event listeners.
		 *
		 * @returns {wordcamp.mentors.views.List}
		 */
		listeners: function() {
			this.listenTo( this, 'tick:' + this.tick,   this.pollCollectionOrHibernate );
			this.listenTo( this, 'hibernate',           this.hibernate );
			this.listenTo( this.filter, 'filter:tasks', this.updateVisibleTasks );

			return this;
		},

		/**
		 * Poll the task collection for changes, or stop polling if the user is inactive.
		 *
		 * @returns {wordcamp.mentors.views.List}
		 */
		pollCollectionOrHibernate: function() {
			var elapsed = Date.now() - this.lastActive;

			if ( elapsed < 30000 ) {
				this.tasks.fetch( this.taskRequest );
			} else if ( ! this.hibernating ) {
				this.trigger( 'hibernate' );
			}

			return this;
		},

		/**
		 * Update the visibility of tasks in the list based on filter parameters.
		 *
		 * @param {object} filter Required parameters to determine which tasks should be visible.
		 * @param {object} data   Optional parameters to pass to the event trigger.
		 *
		 * @returns {wordcamp.mentors.views.List}
		 */
		updateVisibleTasks: function( filter, data ) {
			this.tasks.each( function( task ) {
				var tests = true;

				if ( ! _.isEmpty( filter ) ) {
					tests = _.map( filter, function( value, key ) {
						var attribute = task.get( key );

						if ( _.isArray( attribute ) ) {
							return _.contains( attribute, parseInt( value, 10 ) );
						}

						return attribute === value;
					});
				}

				if ( _.every( tests ) ) {
					task.trigger( 'visibility', 'show', data );
				} else {
					task.trigger( 'visibility', 'hide', data );
				}
			});

			return this;
		},

		/**
		 * Set or update the application as active so that it will poll for changes.
		 *
		 * @returns {wordcamp.mentors.views.List}
		 */
		setLastActive: function() {
			this.lastActive  = Date.now();
			this.hibernating = false;

			// Stop listening for activity
			$document.off( '.' + prefix + '-tasks' );

			return this;
		},

		/**
		 * Set the application as inactive so that it won't poll for changes.
		 *
		 * @returns {wordcamp.mentors.views.List}
		 */
		hibernate: function() {
			var view = this;

			this.hibernating = true;

			$document.on(
				'mouseover.' + prefix + '-tasks keyup.' + prefix + '-tasks touchend.' + prefix + '-tasks',
				function() {
					view.setLastActive();
				}
			);

			return this;
		}
	});

	/**
	 * A Backbone view for an individual task.
	 */
	wordcamp.mentors.views.Task = Backbone.View.extend( {
		/**
		 * HTML element to use as a container.
		 */
		tagName: 'tr',

		/**
		 * HTML element ID attribute.
		 *
		 * @returns {string}
		 */
		id: function() {
			return prefix + '-task-' + this.model.get( 'id' );
		},

		/**
		 * HTML element class attribute.
		 */
		className: prefix + '-task',

		/**
		 * The templating function for rendering the task.
		 */
		template: wp.template( prefix + '-task' ),

		/**
		 * Combine model attributes with other data necessary for rendering.
		 *
		 * @private
		 *
		 * @param {wp.api.models.Wcm_task} model
		 *
		 * @returns {object}
		 */
		_compileData: function( model ) {
			return $.extend( {}, model.attributes, {
				task_category: this.categories,
				stati: wordcamp.mentors.stati
			} );
		},

		/**
		 * Initialize a task view.
		 *
		 * @param {object} options
		 *
		 * @returns {wordcamp.mentors.views.Task}
		 */
		initialize: function( options ) {
			this.list       = options.list;
			this.categories = options.categories;
			this.more       = new wordcamp.mentors.views.TaskMore( { task: this } );
			this.expanded   = false;

			this.listeners();

			this.render( this._compileData( this.model ) );

			return this;
		},

		/**
		 * Render a task.
		 *
		 * @param {object} data
		 *
		 * @returns {wordcamp.mentors.views.Task}
		 */
		render: function( data ) {
			this.$el.html( this.template( data ) );

			return this;
		},

		/**
		 * Set up event listeners.
		 *
		 * @returns {wordcamp.mentors.views.Task}
		 */
		listeners: function() {
			this.listenTo( this.model, 'visibility',      this.changeVisibility );
			this.listenTo( this.model, 'change:status',   this.changeStatus );
			this.listenTo( this.model, 'change:modified', this.changeModified );
			this.listenTo( this.list,  'collapseAll',     this.maybeCollapse );

			return this;
		},

		/**
		 * Change the visibility of this view's element.
		 *
		 * @param {string} action
		 * @param {object} options
		 *
		 * @returns {wordcamp.mentors.views.Task}
		 */
		changeVisibility: function( action, options ) {
			if ( this.expanded ) {
				return this;
			}

			options = _.defaults( options || {}, {
				skipHighlight: false
			} );

			var duration = ( options.skipHighlight ) ? 0 : 500;

			if ( false === options.skipHighlight ) {
				this.$el.addClass( prefix + '-highlight' );
			}

			switch ( action ) {
				case 'show' :
					this.$el.fadeIn( duration );
					break;

				case 'hide' :
					this.$el.fadeOut( duration );
					break;
			}

			this.$el.promise().done( function() {
				$( this ).removeClass( prefix + '-highlight' );
			});

			return this;
		},

		/**
		 * Re-render this task when the status changes.
		 *
		 * @param model
		 */
		changeStatus: function( model ) {
			var list = this.list;

			this.$el.addClass( prefix + '-highlight' );

			this.render( this._compileData( model ) );

			// Slight delay before re-filtering the list
			setTimeout( function() {
				list.trigger( 'setFilter' );
			}, 1000 );
		},

		/**
		 * Re-render this task when the modified timestamp changes.
		 *
		 * @param {wp.api.models.Wcm_task} model
		 */
		changeModified: function( model ) {
			this.render( this._compileData( model ) );
		},

		/**
		 * Toggle this task's more row to hidden if it is currently expanded and if
		 * this isn't the task triggering the collapseAll event.
		 *
		 * @param {int} instigator The ID
		 */
		maybeCollapse: function( instigator ) {
			if ( this.expanded && instigator !== this.model.get( 'id' ) ) {
				this.toggleMore();
			}
		},

		/**
		 * Event binding.
		 */
		events: {
			'click': 'toggleMore',
			'click .column-status select': 'stopPropagation',
			'change .column-status select': 'updateStatus'
		},

		/**
		 * Toggle the visibility of the "more" row for this task. If this expands the task, collapse
		 * all other expanded tasks.
		 */
		toggleMore: function() {
			this.expanded = ! this.expanded;

			if ( this.expanded ) {
				this.list.trigger( 'collapseAll', this.model.get( 'id' ) );
			}

			this.more.trigger( 'toggle', this._compileData( this.model ) );
		},

		/**
		 * Stop the event on a target from propagating up to the target's parent nodes.
		 *
		 * Used to prevent clicks on interactive controls on a task from triggering the "more" row to toggle.
		 *
		 * @param {event} event
		 */
		stopPropagation: function( event ) {
			event.stopPropagation();
		},

		/**
		 * Save a new status value.
		 *
		 * @param {event} event
		 *
		 * @returns {wordcamp.mentors.views.Task}
		 */
		updateStatus: function( event ) {
			var value = $( event.target ).val();

			this.updateTask( {
				status: value
			});

			return this;
		},

		/**
		 * Save new task attribute values to the server.
		 *
		 * @param {object} attributes
		 *
		 * @returns {wordcamp.mentors.views.Task}
		 */
		updateTask: function( attributes ) {
			this.model.save( attributes, {
				patch: true,
				wait:  true
			});

			return this;
		}
	});

	/**
	 * A Backbone view for an individual task's additional info.
	 */
	wordcamp.mentors.views.TaskMore = Backbone.View.extend( {
		/**
		 * HTML element to use as a container.
		 */
		tagName: 'tr',

		/**
		 * HTML element class attribute.
		 */
		className: prefix + '-more',

		/**
		 * The templating function for rendering the task.
		 */
		template: wp.template( prefix + '-more' ),

		/**
		 * Initialize a task's "more" view.
		 *
		 * @param {object} options
		 *
		 * @returns {wordcamp.mentors.views.TaskMore}
		 */
		initialize: function( options ) {
			this.task = options.task;
			this.visible = false;

			this.listeners();

			this.$el.hide();

			return this;
		},

		/**
		 * Render a task's "more" view.
		 *
		 * @param {object} data
		 *
		 * @returns {wordcamp.mentors.views.TaskMore}
		 */
		render: function( data ) {
			this.$el.html( this.template( data ) );

			return this;
		},

		/**
		 * Set up event listeners.
		 *
		 * @returns {wordcamp.mentors.views.TaskMore}
		 */
		listeners: function() {
			this.listenTo( this, 'toggle', this.toggle );

			return this;
		},

		/**
		 * Toggle the visibility of the view element.
		 *
		 * @param {object} data
		 *
		 * @returns {wordcamp.mentors.views.TaskMore}
		 */
		toggle: function( data ) {
			var $more;

			if ( ! $( this.task.$el ).next( '.hidden' ).length ) {
				$more = $( '<tr class="hidden">' ).add( this.$el );
				this.task.$el.after( $more );
			}

			if ( this.visible ) {
				this.$el.hide();
				this.task.$el.removeClass( prefix + '-expanded' );
				this.$el.removeClass( prefix + '-expanded' );
				this.task.list.trigger( 'setFilter' );
			} else {
				this.task.$el.addClass( prefix + '-expanded' );
				this.$el.addClass( prefix + '-expanded' );
				this.render( data );
				this.$el.fadeIn( 300 );
			}

			this.visible = ! this.visible;

			return this;
		}
	});

	/**
	 * A Backbone view for the controls that filter visible tasks.
	 */
	wordcamp.mentors.views.Filter = Backbone.View.extend( {
		/**
		 * Initialize the filter view.
		 *
		 * @param {object} options
		 *
		 * @returns {wordcamp.mentors.views.Filter}
		 */
		initialize: function( options ) {
			this.list = options.list;

			this.listeners();

			return this;
		},

		/**
		 * Set up event listeners.
		 *
		 * @returns {wordcamp.mentors.views.Filter}
		 */
		listeners: function() {
			this.listenTo( this.list, 'setFilter', function( data ) {
				this.$el.trigger( 'submit', data );
			} );

			return this;
		},

		/**
		 * Event binding.
		 */
		events: {
			'submit': 'setFilter'
		},

		/**
		 * Gather the parameters set for the list filter and pass them via event trigger.
		 *
		 * @param {event}  event
		 * @param {object} data
		 *
		 * @returns {wordcamp.mentors.views.Filter}
		 */
		setFilter: function( event, data ) {
			event.preventDefault();

			var filter = {},
				settingPrefix = wordcamp.mentors.prefix;

			$( event.target ).find( 'select' ).each( function() {
				var attribute = $( this ).data( 'attribute' ),
					value     = $( this ).val();

				// Save the filter value as a user setting.
				setUserSetting(
					settingPrefix + '-' + attribute,
					value
				);

				// Don't include attributes set to "any".
				if ( 'any' !== value ) {
					filter[ attribute ] = value;
				}
			});

			this.trigger( 'filter:tasks', filter, data );

			return this;
		}
	});

	/**
	 * A Backbone view for the button to reset task data.
	 */
	wordcamp.mentors.views.Reset = Backbone.View.extend( {
		events: {
			'submit': 'confirm'
		},

		confirm: function( event ) {
			if ( ! window.confirm( wordcamp.mentors.l10n.confirmReset ) ) {
				event.preventDefault();
			}
		}
	});

	// Ensure the Backbone client is loaded before getting started.
	wp.api.loadPromise.done( function () {
		wordcamp.mentors.list = new wordcamp.mentors.views.List( { el: '#the-list' } );
	});

} )( window, jQuery );