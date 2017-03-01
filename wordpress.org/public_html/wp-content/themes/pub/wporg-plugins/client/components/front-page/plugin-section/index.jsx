/**
 * External dependencies.
 */
import React, { Component, PropTypes } from 'react';
import { connect } from 'react-redux';

/**
 * Internal dependencies.
 */
import { fetchSection, fetchSections } from 'state/sections/actions';
import PluginSection from './plugin-section';

class PluginSectionContainer extends Component {
	static propTypes = {
		fetchSection: PropTypes.func,
		type: PropTypes.string.isRequired,
	};

	static defaultProps = {
		fetchBrowse: () => {},
	};

	componentDidMount() {
		this.props.fetchSections();
		this.fetchSection();
	}

	componentDidUpdate( { type } ) {
		if ( this.props.type !== type ) {
			this.fetchSection();
		}
	}

	fetchSection() {
		this.props.fetchSection( this.props.type );
	}

	render() {
		return <PluginSection type={ this.props.type } />;
	}
}

export default connect(
	null,
	{
		fetchSection,
		fetchSections,
	},
)( PluginSectionContainer );
