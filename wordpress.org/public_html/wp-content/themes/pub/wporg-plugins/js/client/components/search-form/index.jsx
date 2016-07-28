import React from 'react';

export default React.createClass( {
	displayName: 'SearchForm',

	render() {
		return (
			<form role="search" method="get" className="search-form" action="/plugins/">
				<label htmlFor="s" className="screen-reader-text">Search for:</label>
				<input type="search" id="s" className="search-field" placeholder="Search plugins" name="s" />
				<button className="button button-primary button-search"><i className="dashicons dashicons-search"></i></button>
			</form>
		)
	}
} );
