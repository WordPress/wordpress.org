/**
 * External dependencies.
 */
import React, { PropTypes } from 'react';
import { Link } from 'react-router';

/**
 * Internal dependencies.
 */
import PluginIcon from 'components/plugin-icon';
import PluginRatings from 'components/plugin-ratings';

export const PluginCard = ( { plugin } ) => (
	<article className="plugin type-plugin plugin-card">
		<PluginIcon slug={ plugin.slug } />
		<div className="entry">
			<header className="entry-header">
				<h2 className="entry-title">
					<Link
						to={ `${ plugin.slug }/` }
						rel="bookmark"
						dangerouslySetInnerHTML={ { __html: plugin.title.rendered } }
					/>
				</h2>
			</header>

			{ plugin.ratings && <PluginRatings rating={ plugin.meta.rating } ratingCount={ plugin.ratings.length } /> }

			<div className="entry-excerpt" dangerouslySetInnerHTML={ { __html: plugin.excerpt.rendered } } />
		</div>
	</article>
);

PluginCard.propTypes = {
	plugin: PropTypes.object,
};

export default PluginCard;
