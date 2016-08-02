import React from 'react';
import { withRouter } from 'react-router';

/**
 * Internal dependencies.
 */
import SearchForm from './search-form';

const SearchFormContainer = React.createClass( {
	getInitialState() {
		return {
			searchTerm: this.props.searchTerm
		};
	},

	onChange( searchTerm ) {
		this.setState( {
			searchTerm: searchTerm
		} );
	},

	componentWillReceiveProps( nextProps ) {
		this.setState( {
			searchTerm: nextProps.searchTerm
		} );
	},

	onSubmit( event ) {
		var searchTerm = encodeURIComponent( this.state.searchTerm );
		event.preventDefault();

		if ( searchTerm ) {
			this.props.router.push( `/search/${ searchTerm }/` );
		} else {
			this.props.router.push( '/' );
		}
	},

	render() {
		return <SearchForm searchTerm={ this.state.searchTerm } onSubmit={ this.onSubmit } onChange={ this.onChange } />;
	}
} );

export default withRouter( SearchFormContainer );