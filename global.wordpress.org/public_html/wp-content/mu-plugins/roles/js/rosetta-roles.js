( function() {
	'use strict';

	var projects, l10n;

	projects = { model: {}, view: {} };

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
	projects.model.SelectableModel = Backbone.Model.extend( {
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
				window.dispatchEvent( new CustomEvent( 'rosetta:uncheck-all-projects' ) );
			} else {
				projects.selection.remove( this.get( 'id' ) );
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

		initialize: function() {
			this.uncheck = this.uncheck.bind( this );

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
			const hasChecked = Boolean( this._subProjects?.some( sub => projects.selection.get( sub.id ) ) );
			this.set( 'checkedSubProjects', hasChecked );
		}
	} );
	
	projects.model.Projects = Backbone.Collection.extend({
		model: projects.model.Project,

		// Listens on the global $window event bus to coordinate active tabs and selection 
		// state between individual category models and the standalone '#project-all' (GTE) toggle.
		initialize: function() {
			this.on( 'change:isActive', this.toggleActiveStates );

			window.addEventListener( 'rosetta:deactivate-other-projects', () => this.disableActiveStates() );
			window.addEventListener( 'rosetta:uncheck-other-projects', () => this.uncheckAllProjects() );
		},

		uncheckAllProjects: function() {
			for ( const project of this.models ) {
				project.uncheck();
				project.get( 'subProjects' ).uncheckAll();
			}
		},

		toggleActiveStates: function( model ) {
			if ( ! model.get( 'isActive' ) ) {
				return;
			}

			for ( const project of this.models ) {
				if ( project.get( 'id' ) !== model.get( 'id' ) ) {
					project.set( 'isActive', false );
				}
			}

			window.dispatchEvent( new CustomEvent( 'rosetta:deactivate-all-projects' ) );
		},

		disableActiveStates: function() {
			for ( const project of this.models ) {
				project.set( 'isActive', false );
			}
		}
	} );

	projects.model.subProject = projects.model.SelectableModel.extend( {
		defaults: {
			id: 0,
			name: '',
			checked: false,
			matchScore: 0
		},

		initialize: function() {
			this.uncheck = this.uncheck.bind( this );

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
	projects.model.subProjects = Backbone.Collection.extend({
		model: projects.model.subProject,

		// Search terms
		terms: '',

		initialize: function( models, options ) {
			this.project = options.project;
			this.on( 'uncheckAll', () => this.uncheckAll() );
		},

		uncheckAll: function() {
			for ( const project of this.models ) {
				project.uncheck();
			}

			if ( this.project?._subProjects ) {
				this.project._subProjects.forEach( sub => projects.selection.remove( sub.id ) );
				this.project.checkForCheckedSubProjects();
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
			// Start with a full collection
			this.reset( this.project._subProjects, { silent: true } );

			// Escape the term string for RegExp meta characters
			const escapedTerm = term.replace( /[-\/\\^\$*+?.()|[\]{}]/g, '\\$&' ).replace( / /g, ')(?=.*' );

			// Step 1 (Filter): Fast regex pre-filter. Positive lookaheads match all query terms 
			// in any order across 'name' and 'slug' without breaking on live space keystrokes.
			const match = new RegExp( `^(?=.*${ escapedTerm }).+`, 'i' );

			const results = this.filter( project => {
				const haystack = [ project.get( 'name' ), project.get( 'slug' ) ].filter( Boolean );

				if ( match.test( haystack.join( ' ' ) ) ) {
					const maxScore = haystack.reduce(
						// Step 2 (Ranking): Calculates 'matchScore' via string_score for sorting.
						( max, word ) => Math.max( max, String( word ).score( term, 0.5 ) ),
						0
					);
					project.set( 'matchScore', maxScore, { silent: true } );
					return true;
				}
				return false;
			} );

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
	} );

	projects.model.Selection = Backbone.Collection.extend( {
		initialize: function() {
			this.field = document.getElementById( 'project-access-list' );
			this.on( 'add remove reset', this.updateInputField );
		},

		updateInputField: function() {
			if ( this.field ) {
				this.field.value = this.pluck( 'id' ).join( ',' );
			}
		}
	} );

	/**
	 * VIEWS
	 */
	projects.view.Frame = wp.Backbone.View.extend( {
		el: '#projects-list',

		render: function() {
			wp.Backbone.View.prototype.render.apply( this, arguments );

			const fragment = document.createDocumentFragment();

			for ( const project of this.collection.models ) {
				const projectView = new projects.view.Project( { model: project } );
				fragment.appendChild( projectView.render().el );
			}

			this.el.append( fragment );

			document.getElementById( 'project-loading' )?.remove();

			// Only visually marks the first item (#project-all) in the template active on initial load.
			const firstActive = this.el.querySelector( 'li.active' )
				|| this.el.querySelector( 'li input[type="radio"]' )?.closest( 'li' )
				|| this.el.querySelector( 'li:first-child' );

			firstActive?.classList.add( 'active' );

			this.views.ready();

			return this;
		}
	} );

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
			const { name, slug, checked, checkedSubProjects } = this.model.toJSON();
			return { name, slug, checked, checkedSubProjects };
		},

		updateChecked: function() {
			this.model.set( 'checked', Boolean( this.el.querySelector( 'input' )?.checked ) );
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
		},

		// Selecting the entire category grants access to all its items,
		// making individual sub-project selections redundant.
		propagateChange: function() {
			if ( this.model.get( 'checked' ) ) {
				this.model.get( 'subProjects' ).trigger( 'uncheckAll' );
			}
		},

		toggleActiveState: function() {
			this.el.classList.toggle( 'active', Boolean( this.model.get( 'isActive' ) ) );
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
			this._subViews.forEach( function( view ) {
				view.remove();
			} );
			this._subViews = [];
		},

		render: function() {
			this.clearSubViews();

			const fragment = document.createDocumentFragment();
			const subProjects = this.collection.first( this.limit );

			this._subViews = subProjects.map( function( model ) {
				const view = new projects.view.SubProject( { model: model } );
				fragment.appendChild( view.render().el );
				return view;
			} );

			this.el.replaceChildren( fragment );

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
			this.doSearch = _.debounce( this.doSearch.bind( this ), 300 );
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
			this.checkbox = this.el.querySelector( 'input' );

			if ( projects.selection.get( 'all' ) ) {
				projects.selection.reset( [ { id: 'all' } ] );
				if ( this.checkbox ) {
					this.checkbox.checked = true;
				}
				this.el.classList.add( 'active' );
			}

			window.addEventListener( 'rosetta:deactivate-all-projects', () => this.onDeactivate() );
			window.addEventListener( 'rosetta:uncheck-all-projects', () => this.onUncheck() );
		},

		onActivate: function() {
			if ( this.el.classList.contains( 'active' ) ) {
				return;
			}

			this.el.classList.add( 'active' );
			window.dispatchEvent( new CustomEvent( 'rosetta:deactivate-other-projects' ) );
		},

		onToggle: function() {
			if ( this.checkbox?.checked ) {
				projects.selection.reset( [ { id: 'all' } ] );
				window.dispatchEvent( new CustomEvent( 'rosetta:uncheck-other-projects' ) );
			} else {
				projects.selection.remove( 'all' );
			}
		},

		onDeactivate: function() {
			this.el.classList.remove( 'active' );
		},

		onUncheck: function() {
			if ( this.checkbox?.checked ) {
				projects.selection.remove( 'all' );
				this.checkbox.checked = false;
			}
		}
	} );

	/**
	 * UTILS
	 */

	// Check if the browser supports localStorage.
	projects.hasStorage = () => {
		try {
			return typeof window.localStorage !== 'undefined' && window.localStorage !== null;
		} catch {
			return false;
		}
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
			throw new Error( 'CompressionStream API not supported' );
		}

		var stream = new CompressionStream( 'gzip' ),
			streamWriter = stream.writable.getWriter(),
			bufferPromise = new Response( stream.readable ).arrayBuffer();

		await streamWriter.write(
			new TextEncoder().encode( data )
		);
		await streamWriter.close();

		var buffer = await bufferPromise;
		// Base64 encode it in chunks so it can be stored in localStorage without blocking the main thread.
		var binary = '';
		var bytes = new Uint8Array( buffer );
		var chunkSize = 8192;

		for ( var i = 0; i < bytes.length; i += chunkSize ) {
			binary += String.fromCharCode.apply( null, bytes.subarray( i, i + chunkSize ) );
		}

		return btoa( binary );
	};

	projects.decompress = async function( data ) {
		if ( 'undefined' === typeof DecompressionStream ) {
			throw new Error( 'DecompressionStream API not supported' );
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
		const initialSelection = ( projects.settings.accessList || [] ).map( function( projectID ) {
			return { id: projectID };
		} );

		projects.selection = new projects.model.Selection( initialSelection );
		projects.selection.updateInputField();

		projects.getProjects().then( function( response ) {
			projects.initFrame( response );
		} ).catch( function() {
			const loadingEl = document.getElementById( 'project-loading' );
			if ( loadingEl ) {
				loadingEl.textContent = l10n.loadingError || 'Error loading projects.';
			}
		} );

		new projects.view.AllProjects();

		// Warn before saving if all project assignments are removed.
		const hadTranslatorStatus = Boolean( projects.settings.accessList && projects.settings.accessList.length );
		const accessField = document.getElementById( 'project-access-list' );

		accessField?.closest( 'form' )?.addEventListener( 'submit', function( e ) {
			if ( hadTranslatorStatus && ! accessField.value ) {
				if ( ! confirm( l10n.removeTranslator ) ) {
					e.preventDefault();
				}
			}
		});
	};

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', projects.init );
	} else {
		projects.init();
	}

} )();
