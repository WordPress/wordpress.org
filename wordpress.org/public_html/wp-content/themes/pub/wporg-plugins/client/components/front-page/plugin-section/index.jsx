import React, { Component } from 'react';
import { connect } from 'react-redux';

/**
 * Internal dependencies.
 */
import { getBrowse } from 'actions';
import PluginSection from './plugin-section';

class PluginSectionContainer extends Component {
	componentDidMount() {
		this.getBrowse();
	}

	componentDidUpdate( previousProps ) {
		if ( this.props.section.type !== previousProps.section.type ) {
			this.getBrowse();
		}
	}

	getBrowse() {
		this.props.dispatch( getBrowse( this.props.section.type ) );
	}

	render() {
		return <PluginSection { ...this.props } />;
	}
}

const mapStateToProps = ( state, ownProps ) => ( {
	plugins: state.browse[ ownProps.section.type ].slice( 0, 4 )
} );

export default connect( mapStateToProps )( PluginSectionContainer );
