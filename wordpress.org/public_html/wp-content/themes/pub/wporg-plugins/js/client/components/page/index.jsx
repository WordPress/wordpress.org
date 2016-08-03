import React from 'react';
import { connect } from 'react-redux';
import find from 'lodash/find';

/**
 * Internal dependencies.
 */
import Page from './page';
import { getPage } from 'actions';

const PageContainer = React.createClass( {
	componentDidMount() {
		this.getPage();
	},

	componentDidUpdate( previousProps ) {
		if ( this.props.route.path !== previousProps.route.path ) {
			this.getPage();
		}
	},

	getPage() {
		this.props.dispatch( getPage( this.props.route.path ) );
	},

	render() {
		return <Page { ...this.props } />;
	}
} );

const mapStateToProps = ( state, ownProps ) => ( {
	page: find( state.pages, { slug: ownProps.route.path } )
} );

export default connect( mapStateToProps )( PageContainer );
