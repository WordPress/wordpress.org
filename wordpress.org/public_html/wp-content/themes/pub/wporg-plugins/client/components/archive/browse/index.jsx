/**
 * External dependencies.
 */
import React, { Component, PropTypes } from 'react';
import { connect } from 'react-redux';

/**
 * Internal dependencies.
 */
import { fetchSection, fetchSections } from 'state/sections/actions';
import { getSection } from 'state/selectors';
import Browse from './browse';

export class BrowseContainer extends Component {
	static propTypes = {
		fetchSection: PropTypes.func,
		params: PropTypes.object,
	};

	static defaultProps = {
		fetchSection: () => {},
		params: {},
	};

	componentDidMount() {
		this.fetchSection();
	}

	componentDidUpdate( { params } ) {
		if ( this.props.params.type !== params.type ) {
			this.fetchSection();
		}
	}

	fetchSection() {
		if ( ! this.props.section ) {
			this.props.fetchSections();
		}
		this.props.fetchSection( this.props.params.type );
	}

	render() {
		return <Browse type={ this.props.params.type } />;
	}
}

export default connect(
	( state, { params } ) => ( {
		section: getSection( state, params.type ),
	} ),
	{
		fetchSection,
		fetchSections,
	},
)( BrowseContainer );
