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
			var subProjects = this.get( 'sub_projects' );
			this.unset( 'sub_projects' );

			this.set( 'subProjects', new projects.model.subProjects( subProjects ) );
			this.set( 'checked', _.contains( projects.settings.accessList, parseInt( this.get( 'id' ), 10 ) ) );

			this.listenTo( this.get( 'subProjects' ), 'change:checked', this.updateChecked );

			this.checkForCheckedSubProjects();
		},

		updateChecked: function( model ) {
			if ( model.get( 'checked' ) ) {
				this.set( 'checked', false );
			}

			this.checkForCheckedSubProjects();
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
			$window.on( 'deactivate-all-projects.rosetta', this.disableActiveStates );
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

			$window.trigger( 'deactivate-other-projects.rosetta' );
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
			isVisible: true
		},

		initialize: function() {
			this.set( 'checked', _.contains( projects.settings.accessList, parseInt( this.get( 'id' ), 10 ) ) );
		}
	});

	projects.model.subProjects = Backbone.Collection.extend({
		model: projects.model.subProject,

		// Search terms
		terms: '',

		initialize: function() {
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

			if ( this.terms === '' ) {
				this.each( function( project ) {
					project.set( 'isVisible', true );
				});
			}
		},

		// Performs a search within the collection
		// @uses RegExp
		search: function( term ) {
			var match, name;

			// Escape the term string for RegExp meta characters
			term = term.replace( /[-\/\\^$*+?.()|[\]{}]/g, '\\$&' );

			// Consider spaces as word delimiters and match the whole string
			// so matching terms can be combined
			term = term.replace( / /g, ')(?=.*' );
			match = new RegExp( '^(?=.*' + term + ').+', 'i' );

			// Find results
			this.each( function( project ) {
				name = project.get( 'name' );
				project.set( 'isVisible', match.test( name ) );
			});
		},
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
			return _.pick( this.model.toJSON(), 'id', 'name', 'checked', 'checkedSubProjects' );
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
		},
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

		initialize: function() {
			var view = this;
			this.collection.each( function( project ) {
				view.views.add( new projects.view.SubProject({
					model: project
				}) );
			});
		}
	});

	projects.view.SubProject = wp.Backbone.View.extend({
		tagName: 'li',

		initialize: function() {
			this.views.add( new projects.view.Checkbox({
				model: this.model
			}) );

			this.listenTo( this.model, 'change:isVisible', this.changeVisibility );
		},

		changeVisibility: function() {
			if ( this.model.get( 'isVisible' ) ) {
				this.$el.removeClass( 'hidden' );
			} else {
				this.$el.addClass( 'hidden' );
			}
		}
	});

	projects.view.Search = wp.Backbone.View.extend({
		tagName: 'input',
		className: 'sub-projects-search',

		attributes: {
			placeholder: l10n.searchPlaceholder,
			type: 'search',
		},

		events: {
			'input': 'search',
			'keyup': 'search',
			'keydown': 'search',
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
		}, 200 )
	});

	projects.init = function() {
		projects.view.frame = new projects.view.Frame({
			collection: new projects.model.Projects( projects.settings.data )
		}).render();
	};

	$( projects.init );

	$( '#project-all' ).on( 'click', function() {
		var $el = $( this );

		if ( $el.hasClass( 'active' ) ) {
			return;
		}

		$el.addClass( 'active' );
		$window.trigger( 'deactivate-all-projects.rosetta' );
	});

	$window.on( 'deactivate-other-projects.rosetta', function() {
		$( '#project-all' ).removeClass( 'active' );
	} );
} )( jQuery );
