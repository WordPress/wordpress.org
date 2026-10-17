( function( $ ) {

	var projects, l10n, $window = $(window);

	projects = { model: {}, view: {}, controller: {} };

	/**
	 * @typedef {Object} RosettaSettings
	 * @property {Array<number|string>} accessList  - IDs of currently assigned projects.
	 * @property {number}               lastUpdated - Timestamp used for localStorage cache invalidation.
	 * @property {Object}               l10n        - Localized UI strings.
	 */
	projects.settings = window._rosettaProjectsSettings || {};
	l10n = projects.settings.l10n || {};
	delete projects.settings.l10n;

	/**
	 * MODELS
	 */

	/**
	 * Mixin providing shared selection and synchronization logic across project models.
	 * Applied via _.extend to Project.prototype and subProject.prototype.
	 */
	projects.model.SelectableMixin = {
		/**
		 * Unchecks the model.
		 */
		uncheck: function() {
			this.set( 'checked', false );
		},

		/**
		 * Checks if the model ID is already present in projects.selection upon initialization
		 * and pre-sets the 'checked' attribute silently.
		 */
		syncInitialSelection: function() {
			if ( projects.selection.get( this.get( 'id' ) ) ) {
				this.set( 'checked', true );
			}
		},

		/**
		 * Change listener for 'change:checked'.
		 * Keeps the global projects.selection synchronized and triggers global uncheck events.
		 *
		 * @param {Backbone.Model} model   - The modified model instance.
		 * @param {boolean}        checked - The updated selection state.
		 */
		updateSelection: function( model, checked ) {
			if ( checked ) {
				projects.selection.add( { id: this.get( 'id' ) } );
				$window.trigger( 'uncheck-all-projects.rosetta' );
			} else {
				projects.selection.remove( this.get( 'id' ) );
			}
		}
	};

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

			this.syncInitialSelection();

			this.listenTo( this.get( 'subProjects' ), 'change:checked', this.updateChecked );
			this.on( 'change:checked', this.updateSelection );
			this.checkForCheckedSubProjects();
		},

		updateChecked: function( model ) {
			if ( model.get( 'checked' ) ) {
				this.set( 'checked', false );
			}

			this.checkForCheckedSubProjects();
		},

		// Check the raw data array rather than the collection so active selections
		// are still detected even when the collection is filtered by an active search.
		checkForCheckedSubProjects: function() {
			var self = this;
			var hasChecked = _.some( self._subProjects, function( sub ) {
				return projects.selection.get( sub.id );
			} );
			self.set( 'checkedSubProjects', hasChecked );
		}
	});
	_.extend( projects.model.Project.prototype, projects.model.SelectableMixin );
	
	projects.model.Projects = Backbone.Collection.extend({
		model: projects.model.Project,

		// Listens on the global $window event bus to coordinate active tabs and selection 
		// state between individual category models and the standalone '#project-all' (GTE) toggle.
		initialize: function() {
			_.bindAll( this, 'disableActiveStates', 'uncheckAllProjects' );

			this.on( 'change:isActive', this.toggleActiveStates );

			$window.on( 'deactivate-other-projects.rosetta', this.disableActiveStates );
			$window.on( 'uncheck-other-projects.rosetta', this.uncheckAllProjects );
		},

		uncheckAllProjects: function() {
			this.each( function( project ) {
				project.uncheck();
				project.get( 'subProjects' ).uncheckAll();
			} );
		},

		toggleActiveStates: function( model ) {
			if ( ! model.get( 'isActive' ) ) {
				return;
			}

			this.each( function( project ) {
				if ( project.get( 'id' ) !== model.get( 'id' ) ) {
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

			this.syncInitialSelection();
			this.on( 'change:checked', this.updateSelection );
		}
	});
	_.extend( projects.model.subProject.prototype, projects.model.SelectableMixin );

	/**
	 * Collection representing sub-projects with integrated relevance-based filtering.
	 *
	 * @property {string} terms - Most recently executed search query.
	 * @property {Backbone.Model} project - Reference to the owning parent Project model.
	 */
	projects.model.subProjects = Backbone.Collection.extend({
		model: projects.model.subProject,

		// Search terms
		terms: '',

		initialize: function( models, options ) {
			this.project = options.project;
			_.bindAll( this, 'uncheckAll' );

			this.on( 'uncheckAll', this.uncheckAll );
		},

		uncheckAll: function() {
			var self = this;

			this.each( function( project ) {
				project.set( 'checked', false );
			});

			if ( self.project && self.project._subProjects ) {
				_.each( self.project._subProjects, function( sub ) {
					projects.selection.remove( sub.id );
				} );
				self.project.checkForCheckedSubProjects();
			}
		},

		doSearch: function( value ) {
			// Don't do anything if we've already done this search
			if ( this.terms === value ) {
				return;
			}

			this.terms = value;

			if ( this.terms.length > 0 ) {
				this.search( this.terms );
			} else {
				// If search is blank, show all projects.
				this.reset( this.project._subProjects );
			}

			// Trigger a 'projects:update' event
			this.trigger( 'projects:update' );
		},

		// Performs a search within the collection
		// @uses RegExp
		search: function( term ) {
			var match, results, escapedTerm;

			// Start with a full collection
			this.reset( this.project._subProjects, { silent: true } );

			// Escape the term string for RegExp meta characters
			escapedTerm = term.replace( /[-\/\\^$*+?.()|[\]{}]/g, '\\$&' );

			// Step 1 (Filter): Fast regex pre-filter. Positive lookaheads match all query terms 
			// in any order across 'name' and 'slug' without breaking on live space keystrokes.
			escapedTerm = escapedTerm.replace( / /g, ')(?=.*' );
			match = new RegExp( '^(?=.*' + escapedTerm + ').+', 'i' );

			results = this.filter( function( project ) {
				var haystack = _.compact( [ project.get( 'name' ), project.get( 'slug' ) ] );
				if ( match.test( haystack.join( ' ' ) ) ) {
					var maxScore = 0;
					_.each( haystack, function( word ) {
						// Step 2 (Ranking): Calculates 'matchScore' via string_score for sorting.
						maxScore = Math.max( maxScore, String( word ).score( term, 0.5 ) );
					});
					project.set( 'matchScore', maxScore, { silent: true } );

					return true;
				}
				return false;
			});

			this.reset( results );
		},

		comparator: function( project ) {
			if ( this.terms && this.terms.length > 0 ) {
				return - project.get( 'matchScore' );
			}
			// Default view: Floats assigned permissions to the top
			// Search view: Overridden to prioritize search relevance over assignment state.
			return - project.get( 'checked' );
		}
	});

	projects.model.Selection = Backbone.Collection.extend( {
		initialize: function() {
			this.$field = $( '#project-access-list' );
			this.on( 'add remove reset', this.updateInputField );
			this.updateInputField();
		},

		updateInputField: function() {
			this.$field.val( this.pluck( 'id' ).join( ',' ) );
		}
	} );

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

			// Only visually marks the first item (#project-all) in the template active on initial load.
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

		// Listen to 'change' instead of 'click' for keyboard accessibility
		// and to ensure DOM state is committed before re-rendering.
		events: {
			'change .input-checkbox': 'updateChecked',
			'change .input-radio': 'setChecked'
		},

		prepare: function() {
			return _.pick( this.model.toJSON(), 'name', 'slug', 'checked', 'checkedSubProjects' );
		},

		updateChecked: function() {
			this.model.set( 'checked', this.$el.find( 'input' ).prop( 'checked' ) );
		},

		// When active, the category toggle renders as a radio button representing "all items in this category".
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

		// Selecting the entire category grants access to all its items,
		// making individual sub-project selections redundant.
		propagateChange: function() {
			if ( this.model.get( 'checked' ) ) {
				this.model.get( 'subProjects' ).trigger( 'uncheckAll' );
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
		}
	});

	projects.view.SubProjectsList = wp.Backbone.View.extend({
		tagName: 'ul',
		className: 'sub-projects-list',

		// Number of projects which should be rendered to reduce bloat. Unrendered items remain searchable.
		limit: 100,

		initialize: function() {
			this._subViews = [];
			this.listenTo( this.collection, 'projects:update', this.render );
		},

		clearSubViews: function() {
			_.each( this._subViews, function( view ) {
				view.remove();
			} );
			this._subViews = [];
		},

		render: function() {
			this.clearSubViews();

			var fragment = document.createDocumentFragment();
			var subProjects = this.collection.first( this.limit );

			this._subViews = _.map( subProjects, function( model ) {
				var view = new projects.view.SubProject( { model: model } );
				fragment.appendChild( view.render().el );
				return view;
			} );

			this.$el.empty().append( fragment );

			return this;
		}
	} );

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
			'input': 'onInput',
			'keydown': 'onKeydown'
		},

		initialize: function() {
			this.doSearch = _.debounce( this.doSearch.bind( this ), 500 );
		},

		onInput: function() {
			this.doSearch();
		},

		onKeydown: function( event ) {
			if ( event.key === 'Enter' ) {
				event.preventDefault();
			} else if ( event.key === 'Escape' ) {
				if ( this.doSearch.cancel ) {
					this.doSearch.cancel();
				}
				this.el.value = '';
				this.collection.doSearch( '' );
			}
		},

		doSearch: function() {
			this.collection.doSearch( this.el.value );
		}
	});

	/**
	 * View handling the standalone global permission toggle (#project-all).
	 * Coordinates radio and checkbox toggles and synchronizes with
	 * $window events ('deactivate-all-projects.rosetta', 'uncheck-all-projects.rosetta').
	 */
	projects.view.AllProjects = wp.Backbone.View.extend( {
		el: '#project-all',

		events: {
			'click': 'onActivate',
			'change input': 'onToggle'
		},

		initialize: function() {
			this.$checkbox = this.$el.find( 'input' );

			if ( projects.selection.get( 'all' ) ) {
				projects.selection.reset( [ { id: 'all' } ] );
				this.$checkbox.prop( 'checked', true );
				this.$el.addClass( 'active' );
			}

			$window.on( 'deactivate-all-projects.rosetta', _.bind( this.onDeactivate, this ) );
			$window.on( 'uncheck-all-projects.rosetta', _.bind( this.onUncheck, this ) );
		},

		onActivate: function() {
			if ( this.$el.hasClass( 'active' ) ) {
				return;
			}

			this.$el.addClass( 'active' );
			$window.trigger( 'deactivate-other-projects.rosetta' );
		},

		onToggle: function() {
			if ( this.$checkbox.prop( 'checked' ) ) {
				projects.selection.reset( [ { id: 'all' } ] );
				$window.trigger( 'uncheck-other-projects.rosetta' );
			} else {
				projects.selection.remove( 'all' );
			}
		},

		onDeactivate: function() {
			this.$el.removeClass( 'active' );
		},

		onUncheck: function() {
			if ( this.$checkbox.prop( 'checked' ) ) {
				projects.selection.remove( 'all' );
				this.$checkbox.prop( 'checked', false );
			}
		}
	} );

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

	projects.getProjects = async function() {
		try {
			return await projects.getLocalProjects();
		} catch ( e ) {
			var remoteProjects = await projects.getRemoteProjects();

			if ( projects.hasStorage() ) {
				projects.storeLocalProjects( remoteProjects );
			}

			return remoteProjects;
		}
	};

	projects.getRemoteProjects = function() {
		return wp.ajax.post( 'rosetta-get-projects' );
	};

	/**
	 * Retrieves and decompresses cached project data from localStorage.
	 *
	 * @async
	 * @throws {Error} Throws when storage is unavailable, timestamps are invalid/expired,
	 *                 or data is corrupt, delegating fallback to projects.getRemoteProjects().
	 * @return {Promise<ProjectData[]>} Parsed project data array.
	 */
	projects.getLocalProjects = async function() {
		if ( ! projects.hasStorage() ) {
			throw new Error( 'No storage' );
		}

		var lastUpdated = window.localStorage.getItem( 'projectsLastUpdated' );
		if ( ! lastUpdated || 'undefined' === lastUpdated ) {
			throw new Error( 'Invalid cache timestamp' );
		}

		var cachedTime = Number( lastUpdated );
		var serverTime = Number( projects.settings.lastUpdated );

		// Ensure timestamps are valid, positive, and finite numbers; rejects missing (0) or invalid cache timestamps.
		var isValidTime = function( time ) {
			return Number.isFinite( time ) && time > 0;
		};

		if ( ! isValidTime( serverTime ) || ! isValidTime( cachedTime ) || cachedTime < serverTime ) {
			throw new Error( 'Cache expired' );
		}

		var json = window.localStorage.getItem( 'projects' );
		if ( ! json ) {
			throw new Error( 'No cached data' );
		}

		// Plain-JSON fallback for caches written by earlier versions.
		if ( '[' === json.substring( 0, 1 ) ) {
			return JSON.parse( json );
		}

		// decompress
		var decompressed = await projects.decompress( json );
		return JSON.parse( decompressed );
	};

	projects.storeLocalProjects = function( data ) {
		projects.compress( JSON.stringify( data ) ).then( function( compressedData ) {
			// Stores only compressed data to prevent exceeding the 5 MB localStorage quota.
			window.localStorage.setItem( 'projects', compressedData );
			window.localStorage.setItem( 'projectsLastUpdated', projects.settings.lastUpdated );
		} ).catch( function( error ) {
			console.warn( "Caching projects locally failed.", error );
		} );
	};

	projects.compress = async function( data ) {
		// Reject if Compression Streams API is unavailable; gracefully falls back to direct remote fetching.
		if ( 'undefined' === typeof CompressionStream ) {
			return Promise.reject();
		}

		var stream = new CompressionStream( 'gzip' ),
			streamWriter = stream.writable.getWriter(),
			bufferPromise = new Response( stream.readable ).arrayBuffer();

		await streamWriter.write(
			new TextEncoder().encode( data )
		);
		await streamWriter.close();

		return bufferPromise.then( function( buffer ) {
			// Base64 encode it in chunks so it can be stored in localStorage without blocking the main thread.
			var binary = '';
			var bytes = new Uint8Array( buffer );
			var chunkSize = 8192;

			for ( var i = 0; i < bytes.length; i += chunkSize ) {
				binary += String.fromCharCode.apply( null, bytes.subarray( i, i + chunkSize ) );
			}

			return btoa( binary );
		} );
	};

	projects.decompress = async function( data ) {
		if ( 'undefined' === typeof DecompressionStream ) {
			return Promise.reject();
		}

		// Base64 decode
		var binary = atob( data );
		var buffer = Uint8Array.from( binary, function( char ) {
			return char.charCodeAt( 0 );
		} );

		var stream = new DecompressionStream( 'gzip' ),
			streamWriter = stream.writable.getWriter(),
			arrayBufferPromise = new Response( stream.readable ).arrayBuffer();

		await streamWriter.write( buffer );
		await streamWriter.close();

		var arrayBuffer = await arrayBufferPromise;
		return new TextDecoder().decode( arrayBuffer );
	};

	projects.initFrame = function( projectData ) {
		projects.view.frame = new projects.view.Frame({
			collection: new projects.model.Projects( projectData )
		}).render();
	};

	projects.init = function() {
		var initialSelection = _.map( projects.settings.accessList || [], function( projectID ) {
			return { id: projectID };
		} );

		projects.selection = new projects.model.Selection( initialSelection );

		projects.getProjects().then( function( response ) {
			projects.initFrame( response );
		} ).catch( function() {
			$( '#project-loading' ).text( l10n.loadingError || 'Error loading projects.' );
		} );

		new projects.view.AllProjects();

		// Warn before saving if all project assignments are removed.
		var hadTranslatorStatus = projects.settings.accessList && projects.settings.accessList.length > 0;

		$( '#project-access-list' ).closest( 'form' ).on( 'submit', function( e ) {
			if ( hadTranslatorStatus && ! $( '#project-access-list' ).val() ) {
				if ( ! confirm( l10n.removeTranslator ) ) {
					e.preventDefault();
				}
			}
		});
	};

	$( projects.init );

} )( jQuery );
