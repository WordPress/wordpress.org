import React from 'react';

/**
 * Internal dependencies.
 */
import Stars from './stars';

export default React.createClass( {
	displayName: 'PluginRatings',

	render() {
		return (
			<div className="plugin-rating" itemProp="aggregateRating" itemScope="" itemType="http://schema.org/AggregateRating">
				<meta itemProp="ratingCount" content={ this.props.ratingCount } />
				<meta itemProp="ratingValue" content={ this.props.rating } />

				<Stars rating={ this.props.rating } />
				<span className="rating-count">({ this.props.ratingCount })</span>
			</div>
		)
	}
} );
