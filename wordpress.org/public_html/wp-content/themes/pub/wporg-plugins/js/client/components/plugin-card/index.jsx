import React from 'react';
import { connect } from 'react-redux';
import find from 'lodash/find';

/**
 * Internal dependencies.
 */
import PluginCard from './plugin-card';
import { getPlugin } from 'actions';

const PluginCardContainer = React.createClass( {
	componentDidMount() {
		this.getPlugin();
	},

	componentDidUpdate( previousProps ) {
		if ( this.props.slug !== previousProps.slug ) {
			this.getPlugin();
		}
	},

	getPlugin() {
		this.props.dispatch( getPlugin( this.props.slug ) );
	},

	render() {
		return <PluginCard { ...this.props } />;
	}
} );

const mapStateToProps = ( state, ownProps ) => ( {
	plugin: find( state.plugins, { slug: ownProps.slug } )
} );

export default connect( mapStateToProps )( PluginCardContainer );


