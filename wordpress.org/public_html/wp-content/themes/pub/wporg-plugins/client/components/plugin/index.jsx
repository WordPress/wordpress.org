import React from 'react';
import { connect } from 'react-redux';
import find from 'lodash/find';

import Plugin from './plugin';
import { getPlugin } from 'actions';

const PluginContainer = React.createClass( {
	componentDidMount() {
		this.getPlugin();
	},

	componentDidUpdate( previousProps ) {
		if ( this.props.route.path !== previousProps.route.path ) {
			this.getPlugin();
		}
	},

	getPlugin() {
		this.props.dispatch( getPlugin( this.props.params.slug ) );
	},

	render() {
		return <Plugin { ...this.props } />;
	}
} );

const mapStateToProps = ( state, ownProps ) => ( {
	plugin: find( state.plugins, { slug: ownProps.params.slug } )
} );

export default connect( mapStateToProps )( PluginContainer );
