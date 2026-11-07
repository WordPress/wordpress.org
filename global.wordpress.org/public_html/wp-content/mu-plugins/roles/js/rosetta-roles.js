( function() {
	'use strict';

	/**
	 * @typedef {Object} RosettaSettings
	 * @property {number}               [userId]     - Target user ID (present on edit screens).
	 * @property {string}               [saveAction] - AJAX action hook name for saving.
	 * @property {string}               [saveNonce]  - Security nonce for saving permissions.
	 * @property {string}               nonce        - Security nonce for fetching projects.
	 * @property {number}               lastUpdated  - Timestamp used for IndexedDB cache invalidation.
	 * @property {Array<number|string>} accessList   - IDs of currently assigned projects.
	 *
	 * @typedef {Object} RosettaConfig
	 * @property {RosettaSettings} settings - Runtime configuration settings.
	 * @property {Object}          l10n     - Localized UI strings.
	 */
	const config = window._rosettaConfig || {};
	const settings = config.settings || {};
	const l10n = config.l10n || {};

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
	projects.view.Frame = wp.Backbone.View.extend( {
		el: '#projects-list',

		render() {
			wp.Backbone.View.prototype.render.apply( this, arguments );

			const fragment = document.createDocumentFragment();
			this.collection.models.forEach( project => {
				const projectView = new projects.view.Project( { model: project } );
				fragment.appendChild( projectView.render().el );
			} );

			document.getElementById( 'project-loading' )?.remove();
			this.el.append( fragment );

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

		initialize() {
			this.listenTo( this.model, 'change:checked change:checkedSubProjects', this.syncInput );
		},

		render() {
			wp.Backbone.View.prototype.render.apply( this, arguments );

			this.inputEl = this.el.querySelector( 'input' );

			this.syncInput();
			return this;
		},

		syncInput() {
			if ( ! this.inputEl ) return;

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

		prepare() {
			const { name, slug, checked, checkedSubProjects } = this.model.attributes;
			return { name, slug, checked, checkedSubProjects };
		},

		updateChecked( { target } ) {
			this.model.set( 'checked', target.checked );
		}
	} );

	projects.view.Project = wp.Backbone.View.extend( {
		tagName: 'li',

		events: {
			'click': 'updateIsActive',
			'focusin': 'updateIsActive'
		},

		initialize() {
			this.listenTo( this.model, 'change:isActive', this.toggleActiveState );

			this.views.add( new projects.view.Checkbox( {
				model: this.model
			} ) );

			this.views.add( new projects.view.SubProjects( {
				model: this.model
			} ) );

			this.toggleActiveState();
		},

		toggleActiveState() {
			this.el.classList.toggle( 'active', Boolean( this.model.get( 'isActive' ) ) );
		},

		updateIsActive() {
			this.model.set( 'isActive', true );
		}
	} );

	projects.view.SubProjects = wp.Backbone.View.extend( {
		tagName: 'div',
		className: 'sub-projects-wrapper',

		initialize() {
			const collection = this.model.get( 'subProjects' );

			if ( collection.length > 5 ) {
				this.views.add( new projects.view.Search( { collection } ) );
			}

			this.views.add( new projects.view.SubProjectsList( { collection } ) );
		}
	} );

	projects.view.SubProjectsList = wp.Backbone.View.extend( {
		tagName: 'ul',
		className: 'sub-projects-list',

		// Number of projects which should be rendered to reduce bloat. Unrendered items remain searchable.
		limit: 100,

		initialize() {
			this._subViews = [];
			this.listenTo( this.collection, 'projects:update', this.render );
		},

		clearSubViews() {
			this._subViews.forEach( view => view.remove() );
			this._subViews = [];
		},

		remove() {
			this.clearSubViews();
			return wp.Backbone.View.prototype.remove.apply( this, arguments );
		},

		render() {
			this.clearSubViews();

			if ( ! this.collection.length ) {
				const emptyItem = document.createElement( 'li' );
				emptyItem.className = 'no-results';
				emptyItem.setAttribute( 'role', 'status' );
				emptyItem.textContent = l10n.noProjectsFound;

				this.el.replaceChildren( emptyItem );
				return this;
			}

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

		initialize() {
			this._debouncedSearch = _.debounce( () => {
				this.collection.doSearch( this.el.value );
			}, 200 );
		},

		remove() {
			this._debouncedSearch.cancel();
			return wp.Backbone.View.prototype.remove.apply( this, arguments );
		},

		onInput() {
			this._debouncedSearch();
		},

		onKeydown( event ) {
			if ( event.key === 'Enter' ) {
				event.preventDefault();
				this._debouncedSearch.cancel();
				this.collection.doSearch( this.el.value );
			} else if ( event.key === 'Escape' ) {
				this._debouncedSearch.cancel();
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
	const isValidTime = time => Number.isFinite( time ) && time > 0;

	// Check if the browser supports IndexedDB, Compression Streams, and has a valid cache timestamp.
	projects.hasStorage = () => ! projects._storageDisabled
		&& typeof indexedDB !== 'undefined'
		&& typeof CompressionStream !== 'undefined'
		&& typeof DecompressionStream !== 'undefined'
		&& isValidTime( Number( projects.settings.lastUpdated ) );

	projects.getProjects = async () => {
		try {
			return await projects.getLocalProjects();
		} catch {
			const remoteProjects = await projects.getRemoteProjects();

			if ( projects.hasStorage() ) {
				projects.storeLocalProjects( remoteProjects );
			}

			return remoteProjects;
		}
	};

	projects.getRemoteProjects = () => wp.ajax.post( 'rosetta-get-projects', {
		_ajax_nonce: projects.settings.nonce
	} );

	const DB_NAME = 'rosetta_cache';
	const STORE_NAME = 'projects';
	const DB_VERSION = 1;

	const promisifyReq = ( req, tx = null ) => new Promise( ( resolve, reject ) => {
		req.onsuccess = () => resolve( req.result );
		req.onerror = () => reject( req.error );
		if ( tx ) {
			tx.onabort = () => reject( tx.error || new Error( 'Transaction aborted' ) );
			tx.onerror = () => reject( tx.error || new Error( 'Transaction error' ) );
		}
	} );

	/**
	 * Opens IndexedDB with protection against schema conflicts, blocked upgrades,
	 * and cross-tab lock deadlocks.
	 *
	 * @param {number} attempt - Current recovery attempt count.
	 * @return {Promise<IDBDatabase>} Promise resolving to the open IDB database instance.
	 */
	function openProjectsDB( attempt = 1 ) {
		return new Promise( ( resolve, reject ) => {
			let settled = false;

			// Fall back to remote data if IndexedDB stalls or deadlocks across tabs.
			const timeoutId = setTimeout( () => {
				if ( settled ) return;
				settled = true;
				projects._storageDisabled = true;
				reject( new Error( 'IndexedDB open timed out' ) );
			}, 2000 );

			const request = indexedDB.open( DB_NAME, DB_VERSION );

			request.onupgradeneeded = event => {
				const db = event.target.result;

				if ( ! db.objectStoreNames.contains( STORE_NAME ) ) {
					db.createObjectStore( STORE_NAME );
				}
			};

			// Reject and bypass cache if another tab blocks database access or schema changes.
			request.onblocked = () => {
				if ( settled ) return;
				settled = true;
				clearTimeout( timeoutId );
				projects._storageDisabled = true;
				console.warn( 'IndexedDB upgrade blocked: Falling back to remote data.' );
				reject( new Error( 'IndexedDB upgrade blocked by another tab' ) );
			};

			request.onsuccess = () => {
				if ( settled ) {
					// Close stale connection if opened after timeout/rejection.
					request.result?.close();
					return;
				}
				settled = true;
				clearTimeout( timeoutId );

				const db = request.result;

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

				// Yield connection when another tab initiates an upgrade.
				db.onversionchange = () => db.close();

				resolve( db );
			};

			request.onerror = () => {
				if ( settled ) return;
				settled = true;
				clearTimeout( timeoutId );
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
			const timeoutId = setTimeout( () => reject( new Error( 'IDB delete timed out' ) ), 1500 );
			const deleteReq = indexedDB.deleteDatabase( DB_NAME );
			deleteReq.onsuccess = () => { clearTimeout( timeoutId ); resolve(); };
			deleteReq.onerror = () => { clearTimeout( timeoutId ); reject( deleteReq.error ); };
			deleteReq.onblocked = () => {
				clearTimeout( timeoutId );
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
	projects.getLocalProjects = async () => {
		if ( ! projects.hasStorage() ) {
			throw new Error( 'No IndexedDB support' );
		}

		const db = await openProjectsDB();

		try {
			const tx = db.transaction( STORE_NAME, 'readonly' );
			const store = tx.objectStore( STORE_NAME );

			let timer;
			const timeout = new Promise( ( _, reject ) => {
				timer = setTimeout( () => {
					try { tx.abort(); } catch {}
					reject( new Error( 'IndexedDB transaction timed out' ) );
				}, 500 );
			} );

			const cached = await Promise.race( [
				promisifyReq( store.get( 'payload' ), tx ),
				timeout
			] ).finally( () => clearTimeout( timer ) );

			if ( ! cached?.blob ) {
				throw new Error( 'No cached data' );
			}

			// Check timestamp
			const { timestamp, blob } = cached;
			const cachedTime = Number( timestamp );
			const serverTime = Number( projects.settings.lastUpdated );

			if ( ! isValidTime( cachedTime ) || cachedTime < serverTime ) {
				throw new Error( 'Cache expired' );
			}

			// decompress
			const decompressedStream = blob.stream().pipeThrough( new DecompressionStream( 'gzip' ) );
			const data = await new Response( decompressedStream ).json();

			if ( ! Array.isArray( data ) ) {
				throw new Error( 'Invalid cache payload: expected array' );
			}

			return data;
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
	projects.storeLocalProjects = async data => {
		let db;
		try {
			const stream = new Blob( [ JSON.stringify( data ) ] )
				.stream()
				.pipeThrough( new CompressionStream( 'gzip' ) );
			const blob = await new Response( stream ).blob();

			db = await openProjectsDB();
			const tx = db.transaction( STORE_NAME, 'readwrite' );
			const store = tx.objectStore( STORE_NAME );

			store.put( {
				timestamp: projects.settings.lastUpdated,
				blob
			}, 'payload' );

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

	projects.initFrame = projectData => {
		new projects.view.Frame( {
			collection: new projects.model.Projects( projectData )
		} ).render();
	};

	projects.showNotice = ( text, type = 'info' ) => {
		document.querySelectorAll( '.rosetta-dynamic-notice' ).forEach( el => el.remove() );

		const notice = document.createElement( 'div' );
		notice.className = `notice notice-${ type } is-dismissible rosetta-dynamic-notice`;

		const p = document.createElement( 'p' );
		p.textContent = text;
		notice.appendChild( p );

		const anchor = document.querySelector( '.wrap > h1' ) || document.getElementById( 'projects-list' );
		anchor?.insertAdjacentElement( 'afterend', notice );
	};

	projects.init = () => {
		if ( ! document.getElementById( 'projects-list' ) ) {
			return;
		}

		const rawList = Array.isArray( projects.settings.accessList )
			? projects.settings.accessList.map( normalizeId ).filter( id => id !== '' )
			: [];
		const initialSelection = rawList.includes( 'all' )
			? [ { id: 'all' } ]
			: rawList.map( id => ( { id } ) );

		projects.selection = new Backbone.Collection( initialSelection );

		// Initialize the global #project-all view and ensure it is only initialized once.
		// Omitted from the DOM for CLPTEs.
		if ( document.getElementById( 'project-all' ) ) {
			projects.view.allProjectsInstance ||= new projects.view.AllProjects();
		}

		projects.getProjects()
			.then( response => projects.initFrame( response ) )
			.catch( () => {
				const loadingEl = document.getElementById( 'project-loading' );
				if ( loadingEl ) loadingEl.textContent = l10n.loadingError;
			} );

		let hadTranslatorStatus = Boolean( rawList.length );
		const form = document.getElementById( 'projects-list' )?.closest( 'form' );
		const submitBtn = form?.querySelector( 'input[type="submit"], button[type="submit"]' );

		form?.addEventListener( 'submit', async event => {
			if ( ! projects.settings.saveAction ) return;

			event.preventDefault();

			const selectedIds = projects.selection.pluck( 'id' );

			if ( hadTranslatorStatus && selectedIds.length === 0 && ! confirm( l10n.removeTranslator ) ) {
				return;
			}

			let isRedirecting = false;

			if ( submitBtn ) {
				submitBtn.disabled = true;
				submitBtn.dataset.originalText = submitBtn.value;
				submitBtn.value = l10n.saving;
			}

			try {
				const response = await wp.ajax.post( projects.settings.saveAction, {
					_ajax_nonce: projects.settings.saveNonce,
					user_id:     projects.settings.userId,
					projects:    selectedIds.length ? selectedIds : [ '' ]
				} );

				if ( response?.redirect ) {
					isRedirecting = true;
					window.location.href = response.redirect;
				}
			} catch ( error ) {
				const message = error?.responseJSON?.data?.message || error?.message || l10n.saveError;
				projects.showNotice( message, 'error' );
			} finally {
				if ( submitBtn && ! isRedirecting ) {
					submitBtn.disabled = false;
					submitBtn.value = submitBtn.dataset.originalText;
				}
			}
		} );
	};

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', projects.init );
	} else {
		projects.init();
	}

} )();
