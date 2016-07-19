import React from 'react';
import { Link } from 'react-router';

/**
 * Internal dependencies.
 */
import FrontPagePlugin from './front-page-plugin';

export default React.createClass( {
	displayName: 'PluginSection',

	render() {
		return (
			<section className="plugin-section">
				<header className="section-header">
					<h1 className="section-title">{ this.props.section.title }</h1>
					<Link className="section-link" to={ this.props.section.path }>See all</Link>
				</header>
				{ this.props.plugins.map( ( plugin ) =>
					<FrontPagePlugin key={ plugin.id } plugin={ plugin } />
				) }
			</section>
		)
	}
} );
