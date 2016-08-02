import React from 'react';
import { connect } from 'react-redux';

import { getBrowse } from 'actions';
import Browse from './browse';

const BrowseContainer = React.createClass( {
	componentDidMount() {
		this.getBrowse();
	},

	componentDidUpdate( previousProps ) {
		if ( this.props.params.type !== previousProps.params.type ) {
			this.getBrowse();
		}
	},

	getBrowse() {
		this.props.dispatch( getBrowse( this.props.params.type ) );
	},

	render() {
		return <Browse { ...this.props } />;
	}
} );

const mapStateToProps = ( state, ownProps ) => ( {
	plugins: state.browse[ ownProps.params.type ]
} );

export default connect( mapStateToProps )( BrowseContainer );


