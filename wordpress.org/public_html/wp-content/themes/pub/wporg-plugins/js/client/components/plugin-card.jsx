import React from 'react';
import { Link } from 'react-router';

export default React.createClass( {
	displayName: 'PluginCard',

	render() {
		return (
			<article className="plugin type-plugin">
				<div className="entry-thumbnail"></div>
				<div className="entry">
					<header className="entry-header">
						<h2 className="entry-title">
							<Link to={ this.props.plugin.slug } rel="bookmark">{ this.props.plugin.title.rendered }</Link>
						</h2>
					</header>
					<div className="plugin-rating" itemProp="aggregateRating" itemScope itemType="http://schema.org/AggregateRating">
						<meta itemProp="ratingCount" content={ this.props.plugin.rating_count } />
						<meta itemProp="ratingValue" content={ this.props.plugin.rating } />

						<div className="wporg-ratings"></div>
						<span className="rating-count">({ this.props.plugin.rating_count })</span>
					</div>
					<div className="entry-excerpt">{ this.props.plugin.excerpt }</div>
				</div>
			</article>
		)
	}
} );
