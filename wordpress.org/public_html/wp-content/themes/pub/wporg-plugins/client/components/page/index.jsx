/**
 * External dependencies.
 */
import React, { Component, PropTypes } from 'react';
import { connect } from 'react-redux';

/**
 * Internal dependencies.
 */
import Page from './page';
import { fetchPage } from 'state/pages/actions';

class PageContainer extends Component {
	static propTypes = {
		fetchPage: PropTypes.func,
		route: PropTypes.object.isRequired,
	};

	static defaultProps = {
		fetchPage: () => {},
	};

	componentDidMount() {
		this.fetchPage();
	}

	componentDidUpdate( { route } ) {
		if ( this.props.route.path !== route.path ) {
			this.fetchPage();
		}
	}

	fetchPage() {
		this.props.fetchPage( this.props.route.path );
	}

	render() {
		return <Page />;
	}
}

export default connect(
	null,
	{
		fetchPage,
	}
)( PageContainer );
