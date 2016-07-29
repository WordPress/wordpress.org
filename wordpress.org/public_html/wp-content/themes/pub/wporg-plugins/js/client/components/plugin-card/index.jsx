import React, { Component } from 'react';
import { connect } from 'react-redux';
import find from 'lodash/find';
import { Link } from 'react-router';

/**
 * Internal dependencies.
 */
import { getPlugin } from 'actions';
import PluginIcon from 'components/plugin-icon';
import PluginRatings from 'components/plugin-ratings';

const PluginCard = React.createClass( {
	displayName: 'PluginCard',

	render() {
		if ( ! this.props.plugin ) {
			return (
				<div />
			);
		}

		return (
			<article className="plugin type-plugin">
				<PluginIcon plugin={ this.props.plugin } />
				<div className="entry">
					<header className="entry-header">
						<h2 className="entry-title">
							<Link to={ this.props.plugin.slug } rel="bookmark">{ this.props.plugin.name }</Link>
						</h2>
					</header>

					<PluginRatings rating={ this.props.plugin.rating } ratingCount={ this.props.plugin.num_ratings } />

					<div className="entry-excerpt">{ this.props.plugin.short_description }</div>
				</div>
			</article>
		)
	}
} );

class PluginCardContainer extends Component {
	componentDidMount() {
		this.getPlugin();
	}

	componentDidUpdate( previousProps ) {
		if ( this.props.slug !== previousProps.slug ) {
			this.getPlugin();
		}
	}

	getPlugin() {
		this.props.dispatch( getPlugin( this.props.slug ) );
	}

	render() {
		return <PluginCard { ...this.props } />;
	}
}

const mapStateToProps = ( state, ownProps ) => ( {
	plugin: find( state.plugins, { slug: ownProps.slug } )
} );

export default connect( mapStateToProps )( PluginCardContainer );


