/**
 * Internal dependencies.
 */
import React, { Component, PropTypes } from 'react';
import { connect } from 'react-redux';
import { withRouter } from 'react-router';

/**
 * Internal dependencies.
 */
import { getSearchTerm } from 'state/selectors';
import SearchForm from './search-form';

export class SearchFormContainer extends Component {
	static propTypes = {
		router: PropTypes.object,
		search: PropTypes.string,
	};

	static defaultProps = {
		router: {},
		search: '',
	};

	onChange = ( search ) => {
		this.setState( { search } );
	};

	onSubmit = ( event ) => {
		const search = encodeURIComponent( this.state.search );
		event.preventDefault();

		if ( search ) {
			this.props.router.push( `/search/${ search }/` );
		} else {
			this.props.router.push( '/' );
		}
	};

	constructor() {
		super( ...arguments );

		this.state = {
			search: this.props.search,
		};
	}

	componentWillReceiveProps( { search } ) {
		this.setState( { search } );
	}

	render() {
		return <SearchForm onSubmit={ this.onSubmit } onChange={ this.onChange } />;
	}
}

export default withRouter( connect(
	( state ) => ( {
		search: getSearchTerm( state ),
	} ),
)( SearchFormContainer ) );
