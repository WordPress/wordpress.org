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
			var match, results, escapedTerm;

			// Start with a full collection
			this.reset( this.project._subProjects, { silent: true } );

			// Escape the term string for RegExp meta characters
			escapedTerm = term.replace( /[-\/\\^$*+?.()|[\]{}]/g, '\\$&' );

			// Consider spaces as word delimiters and match the whole string
			// so matching terms can be combined
			escapedTerm = escapedTerm.replace( / /g, ')(?=.*' );
			match = new RegExp( '^(?=.*' + escapedTerm + ').+', 'i' );

			results = this.filter( function( project ) {
				var haystack = _.union( [ project.get( 'name' ), project.get( 'slug' ) ] );
				if ( match.test( haystack ) ) {
					_.each( haystack, function( word ) {
						var score = word.score( term, .5 );
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

	projects.getRemoteProjects = function() {
		return wp.ajax.post( 'rosetta-get-projects' );
	};

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

		projects.getProjects().then( function( response ) {
			projects.initFrame( response );
		} );
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
		var checked = $projectAllCheckbox.prop( 'checked' );

		if ( checked ) {
			projects.selection.remove( { id: 'all' } );
			$projectAllCheckbox.prop( 'checked', false );
		}
	} );

	// Confirm removal of CLPTE status.
	$( '#project-access-list' ).parents( 'form' ).on( 'submit', function( e ) {
		if ( ! $( '#project-access-list' ).val() ) {
			if ( ! confirm( l10n.removeTranslator ) ) {
				e.preventDefault();
			}
		}
	} );

} )( jQuery );
