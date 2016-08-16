let filters = {
	'searchQuery' : '',
	'sortOrder'   : 'asc',
};

module.exports = {
	/**
	 * Apply all filters to the given rows
	 *
	 * @param {object} options
	 *
	 * @returns {array}
	 */
	getFilteredRows : function( options ) {
		if ( ! options.sortField ) {
			return [];
		}

		filters = Object.assign( filters, options );

		const filteredRows = this.searchRows( wpcApplicationTracker.applications, filters.searchQuery );

		filteredRows.sort( this.sortRows );

		return filteredRows;
	},

	/**
	 * Filter the given rows by the current search query
	 *
	 * @param {array}  rows
	 * @param {string} searchQuery
	 *
	 * @returns {array}
	 */
	searchRows : function( rows, searchQuery ) {
		const hits = [];

		if ( '' === searchQuery ) {
			return rows;
		}

		rows.forEach( function( row ) {
			for ( let field in row ) {
				if ( ! row.hasOwnProperty( field ) ) {
					continue;
				}

				if ( -1 !== row[ field ].toString().toLowerCase().indexOf( searchQuery.toLowerCase() ) ) {
					hits.push( row );
					break;
				}
			}
		}.bind( this ) );

		return hits;
	},

	/**
	 * Callback for Array.prototype.sort() that sorts alphabetically by a field
	 *
	 * @param {object} a
	 * @param {object} b
	 *
	 * @returns {number}
	 */
	sortRows : function( a, b ) {
		a = a[ filters.sortField ].toLowerCase();
		b = b[ filters.sortField ].toLowerCase();

		if ( a > b ) {
			return 'asc' == filters.sortOrder ? 1 : -1;
		}

		if ( a < b ) {
			return 'asc' == filters.sortOrder ? -1 : 1;
		}

		return 0;
	},
};
