/**
 * External dependencies.
 */
import React, { Component, PropTypes } from 'react';
import { connect } from 'react-redux';

/**
 * Internal dependencies.
 */
import PluginCard from './plugin-card';
import { fetchPlugin } from 'state/plugins/actions';
import { getPlugin } from 'state/selectors';

export class PluginCardContainer extends Component {
	static propTypes = {
		fetchPlugin: PropTypes.func,
		plugin: PropTypes.object,
		slug: PropTypes.string,
	};

	static defaultProps = {
		fetchPlugin: () => {},
		plugin: {},
		slug: '',
	};

	componentDidMount() {
		this.fetchPlugin();
	}

	componentDidUpdate( { plugin, slug } ) {
		if ( this.props.slug !== slug || this.props.plugin !== plugin ) {
			this.fetchPlugin();
		}
	}

	fetchPlugin() {
	//	this.props.fetchPlugin( this.props.slug );
	}

	render() {
		return <PluginCard plugin={ this.props.plugin } />;
	}
}

export default connect(
	( state, { plugin, slug } ) => ( {
		plugin: plugin || getPlugin( state, slug ),
	} ),
	{
		fetchPlugin,
	},
)( PluginCardContainer );
