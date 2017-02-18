( function( $ ) {

	var projects, l10n, $window = $(window);

	projects = { model: {}, view: {}, controller: {} };

	projects.settings = window._rosettaProjectsSettings || {};
	l10n = projects.settings.l10n || {};
	delete projects.settings.l10n;

	/**
	 * MODELS
	 */
	projects.model.Project = Backbone.Model.extend({
		defaults: {
			id: 0,
			name: '',
			checked: false,
			isActive: false,
			checkedSubProjects: false
		},

		initialize: function() {
			_.bindAll( this, 'uncheck' );

			// Store the original sub-projects data, it's used to reset the collection on searches.
			this._subProjects = this.get( 'sub_projects' );
			this.unset( 'sub_projects' );

			this.set( 'subProjects', new projects.model.subProjects( this._subProjects, {
				project: this,
			} ) );

			var isChecked = projects.selection.get( this.get( 'id' ) );
			if ( isChecked ) {
				this.set( 'checked', true );
			}

			this.listenTo( this.get( 'subProjects' ), 'change:checked', this.updateChecked );

			$window.on( 'uncheck-other-projects.rosetta', this.uncheck );

			this.on( 'change:checked', this.updateSelection );

			this.checkForCheckedSubProjects();
		},

		uncheck: function() {
			this.set( 'checked', false );
		},

		updateChecked: function( model ) {
			if ( model.get( 'checked' ) ) {
				this.set( 'checked', false );
			}

			this.checkForCheckedSubProjects();
		},

		updateSelection: function( model, checked ) {
			if ( checked ) {
				projects.selection.add( { id: this.get( 'id' ) } );
				$window.trigger( 'uncheck-all-projects.rosetta' );
			} else {
				projects.selection.remove( { id: this.get( 'id' ) } );
			}
		},

		checkForCheckedSubProjects: function() {
			var checked = this.get( 'subProjects' ).findWhere({ 'checked': true });
			if ( checked ) {
				this.set( 'checkedSubProjects', true );
			} else {
				this.set( 'checkedSubProjects', false );
			}
		}
	});

	projects.model.Projects = Backbone.Collection.extend({
		model: projects.model.Project,

		initialize: function() {
			_.bindAll( this, 'disableActiveStates' );

			this.on( 'change:isActive', this.toggleActiveStates );

			$window.on( 'deactivate-other-projects.rosetta', this.disableActiveStates );
		},

		toggleActiveStates: function( model ) {
			if ( ! model.get( 'isActive' ) ) {
				return;
			}

			this.each( function( project ) {
				if ( project.get( 'id' ) != model.get( 'id' ) ) {
					project.set( 'isActive', false );
				}
			});

			$window.trigger( 'deactivate-all-projects.rosetta' );
		},

		disableActiveStates: function() {
			this.each( function( project ) {
				project.set( 'isActive', false );
			});
		}
	});

	projects.model.subProject = Backbone.Model.extend({
		defaults: {
			id: 0,
			name: '',
			checked: false,
			matchScore: 0
		},

		initialize: function() {
			_.bindAll( this, 'uncheck' );

			var isChecked = projects.selection.get( this.get( 'id' ) );
			if ( isChecked ) {
				this.set( 'checked', true );
			}

			$window.on( 'uncheck-other-projects.rosetta', this.uncheck );

			this.on( 'change:checked', this.updateSelection );
		},

		uncheck: function() {
			this.set( 'checked', false );
		},

		updateSelection: function( model, checked ) {
			if ( checked ) {
				projects.selection.add( { id: this.get( 'id' ) } );
				$window.trigger( 'uncheck-all-projects.rosetta' );
			} else {
				projects.selection.remove( { id: this.get( 'id' ) } );
			}
		}
	});

	projects.model.subProjects = Backbone.Collection.extend({
		model: projects.model.subProject,

		// Search terms
		terms: '',

		_sortBy: 'checked',

		initialize: function( models, options ) {
			this.project = options.project;
			this.on( 'uncheckall', this.uncheckall );
		},

		uncheckall: function() {
			this.each( function( project ) {
				project.set( 'checked', false );
			});
		},

		doSearch: function( value ) {
			// Don't do anything if we've already done this search
			if ( this.terms === value ) {
				return;
			}

			this.terms = value;

			if ( this.terms.length > 0 ) {
				this.search( this.terms );
			}

			// If search is blank, show all projects.
			if ( '' === this.terms ) {
				this.reset( this.project._subProjects );
			}

			// Trigger a 'projects:update' event
			this.trigger( 'projects:update' );
		},

		// Performs a search within the collection
		// @uses RegExp
		search: function( term ) {
			var match, results;

			// Start with a full collection
			this.reset( this.project._subProjects, { silent: true } );

			// Escape the term string for RegExp meta characters
			term = term.replace( /[-\/\\^$*+?.()|[\]{}]/g, '\\$&' );

			// Consider spaces as word delimiters and match the whole string
			// so matching terms can be combined
			term = term.replace( / /g, ')(?=.*' );
			match = new RegExp( '^(?=.*' + term + ').+', 'i' );

			results = this.filter( function( project ) {
				var haystack = _.union( [ project.get( 'name' ), project.get( 'slug' ) ] );
				if ( match.test( haystack ) ) {
					_.each( haystack, function( word ) {
						var score = word.score( term );
						project.set( 'matchScore', Math.max( score, project.get( 'matchScore' ) ) );
					});
					return true;
				}
				return false;
			});

			this._sortBy = 'matchScore';
			this.reset( results );
			this._sortBy = 'checked';
		},

		comparator: function( project ) {
			return - project.get( this._sortBy );
		}
	});

	projects.model.Selection = Backbone.Collection.extend({

		initialize: function() {
			this.$field = $( '#project-access-list' );
			this.on( 'add remove reset', this.updateInputField );
		},

		updateInputField: function() {
			this.$field.val( _.pluck( this.toJSON(), 'id' ).join() );
		}
	});

	/**
	 * VIEWS
	 */
	projects.view.Frame = wp.Backbone.View.extend({
		el: '#projects-list',

		render: function() {
			var view = this;
			wp.Backbone.View.prototype.render.apply( this, arguments );

			this.collection.each( function( project ) {
				var projectView = new projects.view.Project({
					model: project
				});

				projectView.render();
				view.$el.append( projectView.el );
			});

			$( '#project-loading' ).remove();

			// Mark the first item as active.
			var $firstActive = view.$el.find( 'li.active' );
			if ( ! $firstActive.length ) {
				$firstActive = view.$el.find( 'li input[type=radio]' );
			}
			if ( ! $firstActive.length ) {
				$firstActive = view.$el.find( 'li:first-child' );
			}
			$firstActive.closest( 'li' ).addClass( 'active' );

			this.views.ready();

			return this;
		}
	});

	projects.view.Checkbox = wp.Backbone.View.extend({
		className: 'project-checkbox',
		template: wp.template( 'project-checkbox' ),

		initialize: function() {
			this.listenTo( this.model, 'change', this.render );
		},

		events: {
			'click .input-checkbox': 'updateChecked',
			'click .input-radio': 'setChecked'
		},

		prepare: function() {
			return _.pick( this.model.toJSON(), 'name', 'slug', 'checked', 'checkedSubProjects' );
		},

		updateChecked: function() {
			this.model.set( 'checked', this.$el.find( 'input' ).prop( 'checked' ) );
		},

		setChecked: function() {
			this.model.set( 'checked', true );
		}
	});

	projects.view.Project = wp.Backbone.View.extend({
		tagName: 'li',

		events: {
			'click': 'updateIsActive'
		},

		initialize: function() {
			this.listenTo( this.model, 'change:checked', this.propagateChange );
			this.listenTo( this.model, 'change:isActive', this.toggleActiveState );

			this.views.add( new projects.view.Checkbox({
				model: this.model
			}) );

			this.views.add( new projects.view.SubProjects({
				model: this.model
			}) );

			this.toggleActiveState();

			this.views.ready();
		},

		propagateChange: function() {
			if ( this.model.get( 'checked' ) ) {
				this.model.get( 'subProjects' ).trigger( 'uncheckall' );
			}
		},

		toggleActiveState: function() {
			if ( this.model.get( 'isActive' ) ) {
				this.$el.addClass( 'active' );
			} else {
				this.$el.removeClass( 'active' );
			}
		},

		updateIsActive: function() {
			this.model.set( 'isActive', true );
		}
	});

	projects.view.SubProjects = wp.Backbone.View.extend({
		tagName: 'div',
		className: 'sub-projects-wrapper',

		initialize: function() {
			var collection = this.model.get( 'subProjects' );

			if ( collection.length > 5 ) {
				this.views.add( new projects.view.Search({
					collection: collection
				}) );
			}

			this.views.add( new projects.view.SubProjectsList({
				collection: collection
			}) );
		},

	});

	projects.view.SubProjectsList = wp.Backbone.View.extend({
		tagName: 'ul',
		className: 'sub-projects-list',

		// Number of projects which should be rendered.
		limit: 100,

		initialize: function( options ) {
			var self = this;

			this.listenTo( self.collection, 'projects:update', function() {
				self.render( this );
			} );
		},

		render: function() {
			var self = this, subProjects;

			self.$el.empty();

			subProjects = self.collection.first( self.limit );
			_.each( subProjects, function( model ) {
				var subProjectView = new projects.view.SubProject({
					model: model
				});

				subProjectView.render();
				self.$el.append( subProjectView.el );
			});
		}
	});

	projects.view.SubProject = wp.Backbone.View.extend({
		tagName: 'li',

		initialize: function() {
			this.views.add( new projects.view.Checkbox({
				model: this.model
			}) );
		}
	});

	projects.view.Search = wp.Backbone.View.extend({
		tagName: 'input',
		className: 'sub-projects-search',

		attributes: {
			placeholder: l10n.searchPlaceholder,
			type: 'search'
		},

		events: {
			'input': 'search',
			'keyup': 'search',
			'keydown': 'search'
		},

		search: function( event ) {
			// Prevent form submit
			if ( event.type === 'keydown' && event.which === 13 ) {
				event.preventDefault();
			} else if ( event.type === 'keydown' ) {
				return;
			}

			// Clear on escape.
			if ( event.type === 'keyup' && event.which === 27 ) {
				event.target.value = '';
			}

			this.doSearch( event );
		},

		doSearch: _.debounce( function( event ) {
			this.collection.doSearch( event.target.value );
		}, 500 )
	});

	/**
	 * UTILS
	 */

	projects._hasStorage = null;

	// Check if the browser supports localStorage.
	projects.hasStorage = function() {
		if ( null !== projects._hasStorage ) {
			return projects._hasStorage;
		}

		var result = false;

		try {
			window.localStorage.setItem( 'test', 'rosetta' );
			result = window.localStorage.getItem( 'test' ) === 'rosetta';
			window.localStorage.removeItem( 'test' );
		} catch( e ) {}

		projects._hasStorage = result;

		return projects._hasStorage;
	};

	projects.getRemoteProjects = function() {
		return wp.ajax.post( 'rosetta-get-projects' );
	};

	projects.getLocalProjects = function() {
		var lastUpdated = window.localStorage.getItem( 'projectsLastUpdated' );
		if ( ! lastUpdated || 'undefined' === lastUpdated ) {
			return false;
		}

		if ( lastUpdated < projects.settings.lastUpdated ) {
			return false;
		}

		var json = window.localStorage.getItem( 'projects' );
		if ( ! json ) {
			return false;
		}

		return JSON.parse( json );
	};

	projects.storeLocalProjects = function( data ) {
		window.localStorage.setItem( 'projectsLastUpdated', projects.settings.lastUpdated );
		window.localStorage.setItem( 'projects', JSON.stringify( data ) );
	};

	projects.initFrame = function( projectData ) {
		projects.view.frame = new projects.view.Frame({
			collection: new projects.model.Projects( projectData )
		}).render();
	};

	projects.selection = new projects.model.Selection();

	projects.init = function() {
		_.each( projects.settings.accessList, function( projectID ) {
			projects.selection.add( { id: projectID } );
		});

		var data = null;

		if ( projects.hasStorage() ) {
			data = projects.getLocalProjects();
		}

		if ( data && data.length ) {
			projects.initFrame( data );
			return;
		}

		projects.getRemoteProjects().done( function( response ) {
			projects.initFrame( response );
			if ( projects.hasStorage() ) {
				projects.storeLocalProjects( response );
			}
		});
	};

	$( projects.init );

	var $projectAll = $( '#project-all' ), $projectAllCheckbox = $projectAll.find( 'input' );

	$projectAll.on( 'click', function() {
		var $el = $( this );

		if ( $el.hasClass( 'active' ) ) {
			return;
		}

		$el.addClass( 'active' );
		$window.trigger( 'deactivate-other-projects.rosetta' );
	});

	$projectAll.find( 'input' ).on( 'change', function() {
		var checked = $( this ).prop( 'checked' );

		if ( checked ) {
			projects.selection.add( { id: 'all' } );
			$window.trigger( 'uncheck-other-projects.rosetta' );
		} else {
			projects.selection.remove( { id: 'all' } );
		}
	});

	$window.on( 'deactivate-all-projects.rosetta', function() {
		$projectAll.removeClass( 'active' );
	} );

	$window.on( 'uncheck-all-projects.rosetta', function() {
		var checked =  $projectAllCheckbox.prop( 'checked' );

		if ( checked ) {
			projects.selection.remove( { id: 'all' } );
			$projectAllCheckbox.prop( 'checked', false );
		}
	} );
} )( jQuery );
