/**
 * External dependencies.
 */
import React, { Component, PropTypes } from 'react';
import { connect } from 'react-redux';

/**
 * Internal dependencies.
 */
import Search from './search';
import { fetchSearch } from 'state/search/actions';

export class SearchContainer extends Component {
	static propTypes = {
		fetchSearch: PropTypes.func,
		params: PropTypes.object,
	};

	static defaultProps = {
		fetchSearch: () => {},
		params: {},
	};

	componentDidMount() {
		this.fetchSearch();
	}

	componentDidUpdate( { params } ) {
		if ( this.props.params.search !== params.search ) {
			this.fetchSearch();
		}
	}

	fetchSearch() {
		this.props.fetchSearch( this.props.params.search );
	}

	render() {
		return <Search { ...this.props } />;
	}
}

export default connect(
	null,
	{
		fetchSearch,
	},
)( SearchContainer );
