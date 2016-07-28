import React, { Component } from 'react';
import { connect } from 'react-redux';

/**
 * Internal dependencies.
 */
import { getBrowse } from 'actions';
import ContentNone from 'components/content-none';
import PluginCard from 'components/plugin-card';


const ArchiveBrowse = React.createClass( {
	displayName: 'ArchiveBrowse',

	render() {
		if ( this.props.plugins ) {
			return (
				<div>
					<header className="page-header">
						<h1 className="page-title">Browse: <strong>{ this.props.params.type }</strong></h1>
						<div className="taxonomy-description"></div>
					</header>
					{ this.props.plugins.map( slug =>
						<PluginCard key={ slug } slug={ slug } />
					) }
				</div>
			)
		}

		return <ContentNone { ...this.props } />;
	}
} );

class ArchiveBrowseContainer extends Component {
	componentDidMount() {
		this.getBrowse();
	}

	componentDidUpdate( previousProps ) {
		if ( this.props.params.type !== previousProps.params.type ) {
			this.getBrowse();
		}
	}

	getBrowse() {
		this.props.dispatch( getBrowse( this.props.params.type ) );
	}

	render() {
		return <ArchiveBrowse { ...this.props } />;
	}
}

const mapStateToProps = ( state, ownProps ) => ( {
	plugins: state.browse[ ownProps.params.type ]
} );

export default connect( mapStateToProps )( ArchiveBrowseContainer );


