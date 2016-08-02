import React from 'react';
import { Link } from 'react-router';

/**
 * Internal dependencies.
 */
import PluginIcon from 'components/plugin-icon';
import PluginRatings from 'components/plugin-ratings';

export default React.createClass( {
	displayName: 'PluginCard',

	render() {
		if ( ! this.props.plugin ) {
			return (
				<div />
			);
		}

		return (
			<article className="plugin type-plugin plugin-card">
				<PluginIcon plugin={ this.props.plugin } />
				<div className="entry">
					<header className="entry-header">
						<h2 className="entry-title">
							<Link to={ `${ this.props.plugin.slug }/` } rel="bookmark">{ this.props.plugin.name }</Link>
						</h2>
					</header>

					<PluginRatings rating={ this.props.plugin.rating } ratingCount={ this.props.plugin.num_ratings } />

					<div className="entry-excerpt">{ this.props.plugin.short_description }</div>
				</div>
			</article>
		)
	}
} );
