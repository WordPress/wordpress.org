/**
 * External dependencies.
 */
import React, { Component, PropTypes } from 'react';
import { connect } from 'react-redux';

/**
 * Internal dependencies.
 */
import Plugin from './plugin';
import { fetchPlugin } from 'state/plugins/actions';

class PluginContainer extends Component {
	static PropTypes = {
		fetchPlugin: PropTypes.func,
		params: PropTypes.object,
	};

	static defaultProps = {
		fetchPlugin: () => {},
		params: {},
	};

	componentDidMount() {
		this.fetchPlugin();
	}

	componentDidUpdate( { params } ) {
		if ( this.props.params.slug !== params.slug ) {
			this.fetchPlugin();
		}
	}

	fetchPlugin() {
		this.props.fetchPlugin( this.props.params.slug );
	}

	render() {
		return <Plugin />;
	}
}

export default connect(
	null,
	{
		fetchPlugin,
	},
)( PluginContainer );
