( function( $ ) {

	var projects, l10n, $window = $(window);

	projects = {
		settings: window._rosettaProjectsSettings || {},
		model: {},
		view: {},
		events: _.extend( {}, Backbone.Events )
	};

	projects.settings = window._rosettaProjectsSettings || {};
	l10n = projects.settings.l10n || {};
	delete projects.settings.l10n;

	/**
	 * Normalizes project IDs to ensure consistent type matching across sets and collections.
	 *
	 * @param {number|string} id - Raw project ID.
	 * @return {number|string} Normalized ID.
	 */
	const normalizeId = id => {
		if ( id == null || typeof id === 'boolean' ) return '';
		const trimmed = typeof id === 'string' ? id.trim() : id;
		if ( trimmed === '' ) return '';
		const num = Number( trimmed );
		return Number.isNaN( num ) ? String( trimmed ) : num;
	};

	/**
	 * Calculates a relevance score based on match position and density.
	 *
	 * @param {string} lowerText - Target text to evaluate (e.g. name or slug).
	 * @param {string} query     - Lowercase query term.
	 * @return {number} Calculated relevance score between 0 and 1.
	 */
	const getMatchScore = ( lowerText, query ) => {
		if ( ! lowerText || ! query ) return 0;

		const index = lowerText.indexOf( query );
		if ( index === -1 ) return 0;

		// 0.5 for prefix match, 0.4 for word boundary, 0.1 for mid-word matches.
		const positionBonus = index === 0 ? 0.5 : ( ' -_'.includes( lowerText[ index - 1 ] ) ? 0.4 : 0.1 );

		// Density bonus: prefers shorter, more concise matches.
		return positionBonus + ( 0.5 * ( query.length / lowerText.length ) );
	};

	/**
	 * MODELS
	 */

	/**
	 * Base model providing shared selection and synchronization logic across project models.
	 * Extended by Project and subProject.
	 */
	projects.model.SelectableModel = Backbone.Model.extend( {
		/**
		 * Unchecks the model.
		 */
		uncheck() {
			this.set( 'checked', false );
		},

		/**
		 * Checks if the model ID is already present in projects.selection upon initialization
		 * and pre-sets the 'checked' attribute silently.
		 */
		syncInitialSelection() {
			const id = normalizeId( this.get( 'id' ) );
			this.set( 'id', id, { silent: true } );

			if ( projects.selection.get( id ) ) {
				this.set( 'checked', true, { silent: true } );
			}
		},

		/**
		 * Change listener for 'change:checked'.
		 * Keeps the global projects.selection synchronized and triggers global uncheck events.
		 *
		 * @param {Backbone.Model} model   - The modified model instance.
		 * @param {boolean}        checked - The updated selection state.
		 */
		updateSelection( model, checked ) {
			const id = model.get( 'id' );
			if ( checked ) {
				projects.selection.add( { id } );
				projects.events.trigger( 'uncheck:all-projects' );
			} else {
				projects.selection.remove( id );
			}
		}
	} );

	projects.model.Project = projects.model.SelectableModel.extend( {
		defaults: {
			id: 0,
			name: '',
			checked: false,
			isActive: false,
			checkedSubProjects: false
		},

		initialize() {
			const subProjects = this.get( 'sub_projects' ) || [];
			this.unset( 'sub_projects', { silent: true } );

			// Populate sub-project IDs without allocating an intermediate array.
			this._subProjectIds = new Set();
			for ( const sub of subProjects ) {
				this._subProjectIds.add( normalizeId( sub.id ) );
			}

			const subProjectsCollection = new projects.model.SubProjects( subProjects, {
				project: this
			} );

			this.set( 'subProjects', subProjectsCollection, { silent: true } );

			this.syncInitialSelection();
			if ( this.get( 'checked' ) ) {
				subProjectsCollection.uncheckAll();
			}

			this.listenTo( subProjectsCollection, 'change:checked', this.updateChecked );
			this.on( 'change:checked', this.onCheckedChange );
			this.checkForCheckedSubProjects();
		},

		onCheckedChange( model, checked ) {
			this.updateSelection( model, checked );

			// Selecting the entire category grants access to all its items,
			// making individual sub-project selections redundant.
			if ( checked ) this.get( 'subProjects' ).uncheckAll();
		},

		updateChecked( model ) {
			if ( this._isUncheckingAll ) return;

			if ( model.get( 'checked' ) ) this.set( 'checked', false );

			this.checkForCheckedSubProjects();
		},

		// Check against the ID set rather than the collection so active selections
		// are still detected even when the collection is filtered by an active search.
		checkForCheckedSubProjects() {
			const hasChecked = projects.selection.some( model => this._subProjectIds.has( model.id ) );
			this.set( 'checkedSubProjects', hasChecked );
		}
	} );

	projects.model.Projects = Backbone.Collection.extend( {
		model: projects.model.Project,

		// Listens on the global event bus to coordinate active tabs and selection
		// state between individual category models and the standalone '#project-all' (GTE) toggle.
		initialize() {
			this.on( 'change:isActive', this.toggleActiveStates );
			this.listenTo( projects.events, 'deactivate:other-projects', this.disableActiveStates );
			this.listenTo( projects.events, 'uncheck:other-projects', this.uncheckAllProjects );
		},

		uncheckAllProjects() {
			this.models.forEach( project => {
				project.uncheck();
				project.get( 'subProjects' ).uncheckAll();
			} );
		},

		toggleActiveStates( model ) {
			if ( ! model.get( 'isActive' ) ) return;

			this.models.forEach( project => {
				if ( project !== model ) {
					project.set( 'isActive', false );
				}
			} );

			projects.events.trigger( 'deactivate:all-projects' );
		},

		disableActiveStates() {
			this.models.forEach( project => project.set( 'isActive', false ) );
		}
	} );

	projects.model.SubProject = projects.model.SelectableModel.extend( {
		defaults: {
			id: 0,
			name: '',
			checked: false
		},

		initialize() {
			// Initialize all properties immediately to ensure identical, monomorphic hidden classes.
			this.matchScore = 0;
			this._lowerName = String( this.attributes.name || '' ).toLowerCase();
			this._lowerSlug = String( this.attributes.slug || '' ).toLowerCase();
			this._searchHaystack = `${ this._lowerName } ${ this._lowerSlug }`;

			this.syncInitialSelection();
			this.on( 'change:checked', this.updateSelection );
		}
	} );

	/**
	 * Collection representing sub-projects with integrated relevance-based filtering.
	 *
	 * @property {string} terms - Most recently executed search query.
	 * @property {Backbone.Model} project - Reference to the owning parent Project model.
	 */
	projects.model.SubProjects = Backbone.Collection.extend( {
		model: projects.model.SubProject,

		// Search terms
		terms: '',

		initialize( models, options ) {
			this.project = options.project;
		},

		getAllModels() {
			return ( this._allModels ||= this.models.slice() );
		},

		uncheckAll() {
			this.project._isUncheckingAll = true;

			try {
				this.getAllModels().forEach( model => model.get( 'checked' ) && model.uncheck() );
			} finally {
				this.project._isUncheckingAll = false;
				this.project.set( 'checkedSubProjects', false );
			}
		},

		doSearch( value ) {
			value = value.trim();

			// Don't do anything if we've already done this search
			if ( this.terms === value ) return;

			this.terms = value;

			if ( this.terms ) {
				this.search( this.terms );
			} else {
				// If search is blank, show all projects.
				this.reset( this.getAllModels() );
			}

			// Trigger a 'projects:update' event
			this.trigger( 'projects:update' );
		},

		/**
		 * Performs a search within the collection and ranks results.
		 * Uses imperative loops to avoid closure allocations and GC pressure during live typing.
		 *
		 * @param {string} term - Search query.
		 */
		search( term ) {
			// Split into tokens; ignore empty tokens.
			const tokens = term.toLowerCase().match( /\S+/g ) || [];
			const tokenCount = tokens.length;
			const allModels = this.getAllModels();
			const results = [];

			for ( let i = 0; i < allModels.length; i++ ) {
				const project = allModels[ i ];
				const haystack = project._searchHaystack;

				// Step 1 (Filter): Ensure all search terms are present in name or slug.
				let matchesAll = true;
				for ( let j = 0; j < tokenCount; j++ ) {
					if ( ! haystack.includes( tokens[ j ] ) ) {
						matchesAll = false;
						break;
					}
				}

				if ( ! matchesAll ) continue;

				// Step 2 (Ranking): Calculate 'matchScore' for comparator sorting.
				// Cache properties to avoid repeated heap lookups across multiple search tokens.
				const { _lowerName, _lowerSlug } = project;
				let score = 0;
				for ( let j = 0; j < tokenCount; j++ ) {
					score += Math.max( getMatchScore( _lowerName, tokens[ j ] ), getMatchScore( _lowerSlug, tokens[ j ] ) );
				}
				project.matchScore = score;
				results.push( project );
			}

			results.sort( ( a, b ) => ( b.matchScore - a.matchScore ) || ( b.attributes.checked - a.attributes.checked ) );
			this.reset( results, { sort: false } );
		},

		comparator( project ) {
			// Default view: Floats assigned permissions to the top
			// Search view: Overridden to prioritize search relevance over assignment state.
			return this.terms ? - project.matchScore : - project.get( 'checked' );
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
		}, 200 )
	});

	/**
	 * View handling the standalone global permission toggle (#project-all).
	 * Coordinates checkbox toggles and synchronizes with
	 * internal events ('deactivate:all-projects', 'uncheck:all-projects').
	 */
	projects.view.AllProjects = wp.Backbone.View.extend( {
		el: '#project-all',

		events: {
			'click': 'onActivate',
			'change input': 'onToggle'
		},

		initialize() {
			this.checkbox = this.el.querySelector( 'input' );

			if ( projects.selection.get( 'all' ) ) {
				if ( this.checkbox ) this.checkbox.checked = true;
				this.el.classList.add( 'active' );
			}

			this.listenTo( projects.events, 'deactivate:all-projects', this.onDeactivate );
			this.listenTo( projects.events, 'uncheck:all-projects', this.onUncheck );
		},

		onActivate() {
			if ( this.el.classList.contains( 'active' ) ) return;

			this.el.classList.add( 'active' );
			projects.events.trigger( 'deactivate:other-projects' );
		},

		onToggle() {
			if ( this.checkbox?.checked ) {
				projects.selection.reset( [ { id: 'all' } ] );
				projects.events.trigger( 'uncheck:other-projects' );
			} else {
				projects.selection.remove( 'all' );
			}
		},

		onDeactivate() {
			this.el.classList.remove( 'active' );
		},

		onUncheck() {
			if ( this.checkbox?.checked ) {
				projects.selection.remove( 'all' );
				this.checkbox.checked = false;
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

	projects.getProjects = function() {
		return projects.getLocalProjects().then(
			function( localProjects ) {
				return localProjects;
			},
			function() {
				return projects.getRemoteProjects().then( function( remoteProjects ) {
					if ( projects.hasStorage() ) {
						projects.storeLocalProjects( remoteProjects );
					}

					return remoteProjects;
				} );
			}
		);
	};

	projects.getRemoteProjects = function() {
		return wp.ajax.post( 'rosetta-get-projects' );
	};

	projects.getLocalProjects = function() {
		if ( ! projects.hasStorage() ) {
			return Promise.reject();
		}

		var lastUpdated = window.localStorage.getItem( 'projectsLastUpdated' );
		if ( ! lastUpdated || 'undefined' === lastUpdated ) {
			return Promise.reject();
		}

		if ( lastUpdated < projects.settings.lastUpdated ) {
			return Promise.reject();
		}

		var json = window.localStorage.getItem( 'projects' );
		if ( ! json ) {
			return Promise.reject();
		}

		if ( '[' === json.substring( 0, 1 ) ) {
			return Promise.resolve( JSON.parse( json ) );
		}

		// decompress
		return projects.decompress( json ).then( function( data ) {
			return JSON.parse( data );
		} );
	};

	projects.storeLocalProjects = function( data ) {
		window.localStorage.setItem( 'projectsLastUpdated', projects.settings.lastUpdated );

		projects.compress( JSON.stringify( data ) ).then( function( data ) {
			window.localStorage.setItem( 'projects', data );
		} ).catch( function() {
			console.log( "Caching projects locally failed." );
		} );
	};

	projects.compress = async function( data ) {
		if ( 'undefined' === typeof CompressionStream ) {
			return Promise.reject();
		}

		var stream = new CompressionStream( 'gzip' ),
			streamWriter = stream.writable.getWriter();

		streamWriter.write(
			new TextEncoder().encode( data )
		);
		streamWriter.close();

		return new Response( stream.readable ).arrayBuffer().then( function( buffer ) {
			// Base64 encode it, so it can be stored in localStorage
			return btoa(
				new Uint8Array( buffer )
					.reduce( ( data, byte ) => data + String.fromCharCode( byte ), '' )
			);
		} );		
	};

	projects.decompress = async function( data ) {
		if ( 'undefined' === typeof DecompressionStream ) {
			return Promise.reject();
		}

		// Base64 decode
		var buffer = Uint8Array.from( atob( data ), c => c.charCodeAt(0) ),
			stream = new DecompressionStream( 'gzip' ),
			streamWriter = stream.writable.getWriter();

		streamWriter.write( buffer );
		streamWriter.close();

		return new Response( stream.readable ).arrayBuffer().then( function( arrayBuffer ) {
			return new TextDecoder().decode( arrayBuffer );
		} );
	};

	projects.initFrame = function( projectData ) {
		projects.view.frame = new projects.view.Frame({
			collection: new projects.model.Projects( projectData )
		}).render();
	};

	projects.selection = new projects.model.Selection();

	projects.init = function() {
		if ( ! document.getElementById( 'projects-list' ) ) {
			return;
		}

		_.each( projects.settings.accessList, function( projectID ) {
			projects.selection.add( { id: projectID } );
		});

		// Initialize the global #project-all view and ensure it is only initialized once.
		// Omitted from the DOM for CLPTEs.
		if ( document.getElementById( 'project-all' ) ) {
			projects.view.allProjectsInstance ||= new projects.view.AllProjects();
		}

		projects.getProjects().then( function( response ) {
			projects.initFrame( response );
		} );
	};

	$( projects.init );

	// Confirm removal of CLPTE status.
	$( '#project-access-list' ).parents( 'form' ).on( 'submit', function( e ) {
		if ( ! $( '#project-access-list' ).val() ) {
			if ( ! confirm( l10n.removeTranslator ) ) {
				e.preventDefault();
			}
		}
	} );

} )( jQuery );
