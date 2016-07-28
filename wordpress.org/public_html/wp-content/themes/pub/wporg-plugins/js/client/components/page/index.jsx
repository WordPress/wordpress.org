import React, { Component } from 'react';
import { connect } from 'react-redux';
import find from 'lodash/find';

/**
 * Internal dependencies.
 */
import Page from './page';
import { getPage } from 'actions';

class PageContainer extends Component {
	componentDidMount() {
		this.getPage();
	}

	componentDidUpdate( previousProps ) {
		if ( this.props.route.name !== previousProps.route.name ) {
			this.getPage();
		}
	}

	getPage() {
		this.props.dispatch( getPage( this.props.route.name ) );
	}

	render() {
		return <Page { ...this.props } />;
	}
}

const mapStateToProps = ( state, ownProps ) => ( {
	page: find( state.pages, { slug: ownProps.route.name } )
} );

export default connect( mapStateToProps )( PageContainer );
