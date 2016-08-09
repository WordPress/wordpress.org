import React from 'react';

export default React.createClass( {
	displayName: 'SearchForm',

	onChange() {
		this.props.onChange( this.refs.search.value );
	},

	render() {
		return (
			<form onSubmit={ this.props.onSubmit } role="search" method="get" className="search-form">
				<label htmlFor="s" className="screen-reader-text">Search for:</label>
				<input
					className="search-field"
					id="s"
					name="s"
					onChange={ this.onChange }
					placeholder="Search plugins"
					ref="search"
					type="search"
					value={ this.props.searchTerm }
				/>
				<button className="button button-primary button-search">
					<i className="dashicons dashicons-search"></i>
					<span className="screen-reader-text">Search plugins</span>
				</button>
			</form>
		)
	}
} );
