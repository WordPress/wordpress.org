( function() {
	'use strict';

	/**
	 * @typedef {Object} RosettaSettings
	 * @property {Array<number|string>} accessList  - IDs of currently assigned projects.
	 * @property {number}               lastUpdated - Timestamp used for IndexedDB cache invalidation.
	 * @property {Object}               l10n        - Localized UI strings.
	 */
	const settings = window._rosettaProjectsSettings || {};
	const l10n = settings.l10n || {};

	const projects = {
		settings,
		model: {},
		view: {},
		events: _.extend( {}, Backbone.Events )
	};

	/**
	 * Normalizes project IDs to ensure consistent type matching across sets and collections.
	 *
	 * @param {number|string} id - Raw project ID.
	 * @return {number|string} Normalized ID.
	 */
	const normalizeId = id => {
		if ( id === null || id === undefined || id === '' || typeof id === 'boolean' ) {
			return '';
		}
		return ( id === 'all' || Number.isNaN( Number( id ) ) ) ? String( id ) : Number( id );
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
		uncheck: function() {
			this.set( 'checked', false );
		},

		/**
		 * Checks if the model ID is already present in projects.selection upon initialization
		 * and pre-sets the 'checked' attribute silently.
		 */
		syncInitialSelection: function() {
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
		updateSelection: function( model, checked ) {
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

		initialize: function() {
			const subProjects = this.get( 'sub_projects' ) || [];
			this.unset( 'sub_projects', { silent: true } );

			this._subProjectIds = new Set( subProjects.map( sub => normalizeId( sub.id ) ) );

			const subProjectsCollection = new projects.model.subProjects( subProjects, {
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

		onCheckedChange: function( model, checked ) {
			this.updateSelection( model, checked );

			// Selecting the entire category grants access to all its items,
			// making individual sub-project selections redundant.
			if ( checked ) {
				this.get( 'subProjects' ).uncheckAll();
			}
		},

		updateChecked: function( model ) {
			if ( this._isUncheckingAll ) {
				return;
			}

			if ( model.get( 'checked' ) ) {
				this.set( 'checked', false );
			}

			this.checkForCheckedSubProjects();
		},

		// Check against the ID set rather than the collection so active selections
		// are still detected even when the collection is filtered by an active search.
		checkForCheckedSubProjects: function() {
			const hasChecked = projects.selection.models.some( model => this._subProjectIds.has( model.id ) );
			this.set( 'checkedSubProjects', hasChecked );
		}
	} );

	projects.model.Projects = Backbone.Collection.extend( {
		model: projects.model.Project,

		// Listens on the global event bus to coordinate active tabs and selection
		// state between individual category models and the standalone '#project-all' (GTE) toggle.
		initialize: function() {
			this.on( 'change:isActive', this.toggleActiveStates );

			this.listenTo( projects.events, 'deactivate:other-projects', this.disableActiveStates );
			this.listenTo( projects.events, 'uncheck:other-projects', this.uncheckAllProjects );
		},

		uncheckAllProjects: function() {
			this.models.forEach( project => {
				project.uncheck();
				project.get( 'subProjects' ).uncheckAll();
			} );
		},

		toggleActiveStates: function( model ) {
			if ( ! model.get( 'isActive' ) ) {
				return;
			}

			this.models.forEach( project => {
				if ( project !== model ) {
					project.set( 'isActive', false );
				}
			} );

			projects.events.trigger( 'deactivate:all-projects' );
		},

		disableActiveStates: function() {
			this.models.forEach( project => project.set( 'isActive', false ) );
		}
	} );

	projects.model.subProject = projects.model.SelectableModel.extend( {
		defaults: {
			id: 0,
			name: '',
			checked: false
		},

		// Stored as direct instance property instead of Backbone attribute to avoid .set() overhead during search.
		matchScore: 0,
		_searchHaystack: null,

		initialize: function() {
			this.syncInitialSelection();
			this.on( 'change:checked', this.updateSelection );
		},

		/**
		 * Lazily builds and caches the lowercase search haystack on first search.
		 *
		 * @return {string} Lowercase combination of name and slug.
		 */
		getSearchHaystack: function() {
			if ( this._searchHaystack === null ) {
				this._searchHaystack = `${ this.attributes.name || '' } ${ this.attributes.slug || '' }`.toLowerCase();
			}
			return this._searchHaystack;
		}
	} );

	/**
	 * Collection representing sub-projects with integrated relevance-based filtering.
	 *
	 * @property {string} terms - Most recently executed search query.
	 * @property {Backbone.Model} project - Reference to the owning parent Project model.
	 */
	projects.model.subProjects = Backbone.Collection.extend( {
		model: projects.model.subProject,

		// Search terms
		terms: '',

		initialize: function( models, options ) {
			this.project = options?.project;
		},

		getAllModels: function() {
			return ( this._allModels ||= this.models.slice() );
		},

		uncheckAll: function() {
			if ( this.project ) {
				this.project._isUncheckingAll = true;
			}

			try {
				const allModels = this.getAllModels();
				for ( let i = 0; i < allModels.length; i++ ) {
					if ( allModels[ i ].get( 'checked' ) ) {
						allModels[ i ].uncheck();
					}
				}
			} finally {
				if ( this.project ) {
					this.project._isUncheckingAll = false;
					this.project.set( 'checkedSubProjects', false );
				}
			}
		},

		doSearch: function( value ) {
			value = value.trim();

			// Don't do anything if we've already done this search
			if ( this.terms === value ) {
				return;
			}

			this.terms = value;

			if ( this.terms.length > 0 ) {
				this.search( this.terms );
			} else {
				// If search is blank, show all projects.
				this.reset( this.getAllModels() );
			}

			// Trigger a 'projects:update' event
			this.trigger( 'projects:update' );
		},

		// Performs a search within the collection
		search: function( term ) {
			// Split into tokens; ignore empty tokens.
			const tokens = term.toLowerCase().split( /\s+/ ).filter( Boolean );
			const hasScoreFn = typeof String.prototype.score === 'function';

			// Step 1 (Filter): Token-based pre-filter. Ensures all search terms
			// are present in either 'name' or 'slug' regardless of word order.
			const results = this.getAllModels().filter( project => {
				const haystack = project.getSearchHaystack();

				if ( tokens.every( token => haystack.includes( token ) ) ) {
					// Step 2 (Ranking): Calculates 'matchScore' via string_score for sorting.
					const name = project.attributes.name;
					const nameScore = ( name && hasScoreFn ) ? String( name ).score( term, 0.5 ) : 0;
					const slug = project.attributes.slug;
					const slugScore = ( slug && hasScoreFn ) ? String( slug ).score( term, 0.5 ) : 0;
					project.matchScore = Math.max( nameScore, slugScore );
					return true;
				}
				return false;
			} );

			this.reset( results );
		},

		comparator: function( project ) {
			if ( this.terms ) {
				return - project.matchScore;
			}
			// Default view: Floats assigned permissions to the top
			// Search view: Overridden to prioritize search relevance over assignment state.
			return - project.get( 'checked' );
		}
	} );

	projects.model.Selection = Backbone.Collection.extend( {
		initialize: function() {
			this.field = document.getElementById( 'project-access-list' );
			this._debouncedUpdate = _.debounce( this.updateInputField.bind( this ), 10 );
			this.on( 'update reset', this._debouncedUpdate );
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

		initialize: function() {
			this._subViews = [];
		},

		clearSubViews: function() {
			for ( const view of this._subViews ) {
				view.remove();
			}
			this._subViews = [];
		},

		render: function() {
			wp.Backbone.View.prototype.render.apply( this, arguments );

			this.clearSubViews();

			const fragment = document.createDocumentFragment();
			this._subViews = this.collection.models.map( project => {
				const projectView = new projects.view.Project( { model: project } );
				fragment.appendChild( projectView.render().el );
				return projectView;
			} );

			document.getElementById( 'project-loading' )?.remove();
			this.el.append( fragment );

			// Initialize the global #project-all view once it is rendered in the DOM.
			if ( ! this._allProjectsView && document.getElementById( 'project-all' ) ) {
				this._allProjectsView = new projects.view.AllProjects();
			}

			// Activate the first relevant category tab if #project-all is not active.
			if ( ! projects.selection.get( 'all' ) ) {
				const activeProject = this.collection.find( p => p.get( 'isActive' ) || p.get( 'checkedSubProjects' ) || p.get( 'checked' ) )
					|| this.collection.first();

				activeProject?.set( 'isActive', true );
			}

			return this;
		}
	} );

	projects.view.Checkbox = wp.Backbone.View.extend( {
		className: 'project-checkbox',
		template: wp.template( 'project-checkbox' ),

		events: {
			'change input': 'updateChecked'
		},

		initialize: function() {
			this.listenTo( this.model, 'change:checked change:checkedSubProjects', this.syncInput );
		},

		render: function() {
			wp.Backbone.View.prototype.render.apply( this, arguments );

			this.inputEl = this.el.querySelector( 'input' );

			this.syncInput();
			return this;
		},

		syncInput: function() {
			if ( ! this.inputEl ) {
				return;
			}

			const isChecked = Boolean( this.model.get( 'checked' ) );
			const hasCheckedSubProjects = Boolean( this.model.get( 'checkedSubProjects' ) );
			const isIndeterminate = ! isChecked && hasCheckedSubProjects;

			this.inputEl.checked = isChecked;
			this.inputEl.indeterminate = isIndeterminate;

			if ( isIndeterminate ) {
				this.inputEl.setAttribute( 'aria-checked', 'mixed' );
			} else {
				this.inputEl.removeAttribute( 'aria-checked' );
			}
		},

		prepare: function() {
			const { name, slug, checked, checkedSubProjects } = this.model.attributes;
			return { name, slug, checked, checkedSubProjects };
		},

		updateChecked: function( event ) {
			this.model.set( 'checked', Boolean( event.target.checked ) );
		}
	} );

	projects.view.Project = wp.Backbone.View.extend( {
		tagName: 'li',

		events: {
			'click': 'updateIsActive',
			'focusin': 'updateIsActive'
		},

		initialize: function() {
			this.listenTo( this.model, 'change:isActive', this.toggleActiveState );

			this.views.add( new projects.view.Checkbox( {
				model: this.model
			} ) );

			this.views.add( new projects.view.SubProjects( {
				model: this.model
			} ) );

			this.toggleActiveState();
		},

		toggleActiveState: function() {
			this.el.classList.toggle( 'active', Boolean( this.model.get( 'isActive' ) ) );
		},

		updateIsActive: function() {
			this.model.set( 'isActive', true );
		}
	} );

	projects.view.SubProjects = wp.Backbone.View.extend( {
		tagName: 'div',
		className: 'sub-projects-wrapper',

		initialize: function() {
			const collection = this.model.get( 'subProjects' );

			if ( collection.length > 5 ) {
				this.views.add( new projects.view.Search( {
					collection: collection
				} ) );
			}

			this.views.add( new projects.view.SubProjectsList( {
				collection: collection
			} ) );
		}
	} );

	projects.view.SubProjectsList = wp.Backbone.View.extend( {
		tagName: 'ul',
		className: 'sub-projects-list',

		// Number of projects which should be rendered to reduce bloat. Unrendered items remain searchable.
		limit: 100,

		initialize: function() {
			this._subViews = [];
			this.listenTo( this.collection, 'projects:update', this.render );
		},

		clearSubViews: function() {
			for ( const view of this._subViews ) {
				view.remove();
			}
			this._subViews = [];
		},

		remove: function() {
			this.clearSubViews();
			return wp.Backbone.View.prototype.remove.apply( this, arguments );
		},

		render: function() {
			this.clearSubViews();

			const fragment = document.createDocumentFragment();
			const subProjects = this.collection.first( this.limit );

			this._subViews = subProjects.map( model => {
				const view = new projects.view.Checkbox( { model, tagName: 'li' } );
				fragment.appendChild( view.render().el );
				return view;
			} );

			this.el.replaceChildren( fragment );

			return this;
		}
	} );

	projects.view.Search = wp.Backbone.View.extend( {
		tagName: 'input',
		className: 'sub-projects-search',

		attributes: {
			placeholder: l10n.searchPlaceholder,
			type: 'search',
			'aria-label': l10n.searchLabel,
			autocomplete: 'off'
		},

		events: {
			'input': 'onInput',
			'keydown': 'onKeydown'
		},

		initialize: function() {
			this._debouncedSearch = _.debounce( () => {
				this.collection.doSearch( this.el.value );
			}, 300 );
		},

		remove: function() {
			this._debouncedSearch?.cancel();
			return wp.Backbone.View.prototype.remove.apply( this, arguments );
		},

		onInput: function() {
			this._debouncedSearch();
		},

		onKeydown: function( event ) {
			if ( event.key === 'Enter' ) {
				event.preventDefault();
				this._debouncedSearch?.cancel();
				this.collection.doSearch( this.el.value );
			} else if ( event.key === 'Escape' ) {
				this._debouncedSearch?.cancel();
				this.el.value = '';
				this.collection.doSearch( '' );
			}
		}
	} );

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

		initialize: function() {
			this.checkbox = this.el.querySelector( 'input' );

			if ( projects.selection.get( 'all' ) ) {
				if ( this.checkbox ) {
					this.checkbox.checked = true;
				}
				this.el.classList.add( 'active' );
			}

			this.listenTo( projects.events, 'deactivate:all-projects', this.onDeactivate );
			this.listenTo( projects.events, 'uncheck:all-projects', this.onUncheck );
		},

		onActivate: function() {
			if ( this.el.classList.contains( 'active' ) ) {
				return;
			}

			this.el.classList.add( 'active' );
			projects.events.trigger( 'deactivate:other-projects' );
		},

		onToggle: function() {
			if ( this.checkbox?.checked ) {
				projects.selection.reset( [ { id: 'all' } ] );
				projects.events.trigger( 'uncheck:other-projects' );
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

	const isValidTime = time => Number.isFinite( time ) && time > 0;

	// Check if the browser supports IndexedDB, Compression Streams, and has a valid cache timestamp.
	projects.hasStorage = () => ! projects._storageDisabled
		&& typeof indexedDB !== 'undefined'
		&& typeof CompressionStream !== 'undefined'
		&& typeof DecompressionStream !== 'undefined'
		&& isValidTime( Number( projects.settings.lastUpdated ) );

	projects.getProjects = async function() {
		try {
			return await projects.getLocalProjects();
		} catch ( e ) {
			const remoteProjects = await projects.getRemoteProjects();

			if ( projects.hasStorage() ) {
				projects.storeLocalProjects( remoteProjects );
			}

			return remoteProjects;
		}
	};

	projects.getRemoteProjects = function() {
		return wp.ajax.post( 'rosetta-get-projects' );
	};

	const DB_NAME = 'rosetta_cache';
	const STORE_NAME = 'projects';
	const DB_VERSION = 1;

	const promisifyReq = req => new Promise( ( resolve, reject ) => {
		req.onsuccess = () => resolve( req.result );
		req.onerror = () => reject( req.error );
	} );

	/**
	 * Opens IndexedDB with protection against schema conflicts and blocked upgrades.
	 *
	 * @return {Promise<IDBDatabase>} Promise resolving to the open IDB database instance.
	 */
	function openProjectsDB( attempt = 1 ) {
		return new Promise( ( resolve, reject ) => {
			const request = indexedDB.open( DB_NAME, DB_VERSION );

			request.onupgradeneeded = event => {
				const db = event.target.result;

				// Before creating a store, always check whether it already exists.
				if ( ! db.objectStoreNames.contains( STORE_NAME ) ) {
					db.createObjectStore( STORE_NAME );
				}
			};

			// Prevent open tabs from freezing the upgrade process.
			request.onblocked = () => {
				console.warn( 'IndexedDB upgrade blocked: Please close other Rosetta tabs.' );
			};

			request.onsuccess = () => {
				const db = request.result;

				// Consistency Check: Does the store actually exist?
				if ( ! db.objectStoreNames.contains( STORE_NAME ) ) {
					db.close();
					if ( attempt > 2 ) {
						projects._storageDisabled = true;
						reject( new Error( `Store ${ STORE_NAME } could not be created.` ) );
						return;
					}
					recoverCorruptDB( attempt ).then( resolve ).catch( reject );
					return;
				}

				// Close the connection when another tab requests an upgrade.
				db.onversionchange = () => {
					db.close();
				};

				resolve( db );
			};

			request.onerror = () => {
				projects._storageDisabled = true;
				reject( request.error );
			};
		} );
	}

	/**
	 * Fixes an inconsistent db state by completely deleting and rebuilding it.
	 */
	async function recoverCorruptDB( attempt ) {
		await new Promise( ( resolve, reject ) => {
			const deleteReq = indexedDB.deleteDatabase( DB_NAME );
			deleteReq.onsuccess = () => resolve();
			deleteReq.onerror = () => reject( deleteReq.error );
			deleteReq.onblocked = () => {
				console.warn( 'IndexedDB deletion blocked: The cache will be disabled for this session.' );
				projects._storageDisabled = true;
				reject( new Error( 'Database deletion blocked' ) );
			};
		} );

		return openProjectsDB( attempt + 1 );
	}

	/**
	 * Retrieves and decompresses cached project data from IndexedDB.
	 *
	 * @async
	 * @throws {Error} Throws when storage is unavailable, timestamps are invalid/expired,
	 *                 or data is corrupt, delegating fallback to projects.getRemoteProjects().
	 * @return {Promise<ProjectData[]>} Parsed project data array.
	 */
	projects.getLocalProjects = async function() {
		if ( ! projects.hasStorage() ) {
			throw new Error( 'No IndexedDB support' );
		}

		const db = await openProjectsDB();

		try {
			if ( ! db.objectStoreNames.contains( STORE_NAME ) ) {
				throw new Error( `Store ${ STORE_NAME } not found in database.` );
			}

			const store = db.transaction( STORE_NAME, 'readonly' ).objectStore( STORE_NAME );

			const [ cachedTimeRaw, blob ] = await Promise.all( [
				promisifyReq( store.get( 'projects_last_updated' ) ),
				promisifyReq( store.get( 'projects_data' ) )
			] );

			// Check timestamp
			const cachedTime = Number( cachedTimeRaw );
			const serverTime = Number( projects.settings.lastUpdated );

			if ( ! isValidTime( cachedTime ) || cachedTime < serverTime ) {
				throw new Error( 'Cache expired' );
			}

			if ( ! blob ) {
				throw new Error( 'No cached data' );
			}

			// decompress
			const decompressedStream = blob.stream().pipeThrough( new DecompressionStream( 'gzip' ) );
			const jsonText = await new Response( decompressedStream ).text();

			return JSON.parse( jsonText );
		} finally {
			db.close();
		}
	};

	/**
	 * Compresses and caches remote project data into IndexedDB as a binary gzip Blob.
	 *
	 * @async
	 * @param {ProjectData[]} data - Array of project objects to serialize and cache.
	 * @return {Promise<void>}
	 */
	projects.storeLocalProjects = async function( data ) {
		let db;
		try {
			const stream = new Blob( [ JSON.stringify( data ) ] )
				.stream()
				.pipeThrough( new CompressionStream( 'gzip' ) );
			const blob = await new Response( stream ).blob();

			db = await openProjectsDB();
			const tx = db.transaction( STORE_NAME, 'readwrite' );
			const store = tx.objectStore( STORE_NAME );

			store.put( blob, 'projects_data' );
			store.put( projects.settings.lastUpdated, 'projects_last_updated' );

			await new Promise( ( resolve, reject ) => {
				tx.oncomplete = () => resolve();
				tx.onerror = () => reject( tx.error );
				tx.onabort = () => reject( tx.error );
			} );
		} catch ( error ) {
			console.warn( 'Caching projects in IndexedDB failed.', error );
		} finally {
			db?.close();
		}
	};

	projects.initFrame = function( projectData ) {
		new projects.view.Frame( {
			collection: new projects.model.Projects( projectData )
		} ).render();
	};

	projects.init = function() {
		const rawList = ( projects.settings.accessList || [] )
			.map( normalizeId )
			.filter( id => id !== '' );
		const initialSelection = rawList.includes( 'all' )
			? [ { id: 'all' } ]
			: rawList.map( id => ( { id } ) );

		projects.selection = new projects.model.Selection( initialSelection );
		projects.selection.updateInputField();

		projects.getProjects()
			.then( response => projects.initFrame( response ) )
			.catch( () => {
				const loadingEl = document.getElementById( 'project-loading' );
				if ( loadingEl ) {
					loadingEl.textContent = l10n.loadingError;
				}
			} );

		const hadTranslatorStatus = Boolean( rawList.length );
		const accessField = projects.selection.field;

		accessField?.closest( 'form' )?.addEventListener( 'submit', event => {
			projects.selection._debouncedUpdate?.cancel();
			projects.selection.updateInputField();

			if ( hadTranslatorStatus && ! accessField.value && ! confirm( l10n.removeTranslator ) ) {
				event.preventDefault();
			}
		} );
	};

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', projects.init );
	} else {
		projects.init();
	}

} )();
