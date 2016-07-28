import React from 'react';
import { Link } from 'react-router';

/**
 * Internal dependencies.
 */
import PluginCard from 'components/plugin-card';

export default React.createClass( {
	displayName: 'PluginSection',

	render() {
		if ( ! this.props.plugins ) {
			return <div />;
		}

		return (
			<section className="plugin-section">
				<header className="section-header">
					<h1 className="section-title">{ this.props.section.title }</h1>
					<Link className="section-link" to={ this.props.section.path }>See all</Link>
				</header>
				{ this.props.plugins.map( slug =>
					<PluginCard key={ slug } slug={ slug } />
				) }
			</section>
		)
	}
} );
