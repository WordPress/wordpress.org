/**
 * External dependencies.
 */
import React, { PropTypes } from 'react';
import { connect } from 'react-redux';
import { identity } from 'lodash';
import { localize } from 'i18n-calypso';
import { values } from 'lodash';

/**
 * Internal dependencies.
 */
import Developers from './sections/developers';
import DonateWidget from 'components/widget-area/widgets/donate';
import DownloadButton from './download-button';
import FAQ from './sections/faq';
import FavoriteButton from './favorite-button';
import { getPlugin } from 'state/selectors';
import MetaWidget from 'components/widget-area/widgets/meta/index';
import PluginBanner from './plugin-banner';
import PluginIcon from 'components/plugin-icon';
import Reviews from './sections/reviews';
import RatingsWidget from 'components/widget-area/widgets/ratings/index';
import Screenshots from './sections/screenshots';
import Section from './sections';
import SupportWidget from 'components/widget-area/widgets/support/index';

export const Plugin = ( { plugin, translate } ) => {
	if ( ! plugin ) {
		return (
			<article className="plugin type-plugin">
				<header className="entry-header">
					<h1 className="entry-title"> </h1>
				</header>
				<div className="entry-content">
					<section>
						<div className="container"> LOADING </div>
					</section>
				</div>
			</article>
		);
	}

	return (
		<article className="plugin type-plugin">
			<PluginBanner />
			<header className="plugin-header">
				<PluginIcon />
				<div className="plugin-actions">
					<FavoriteButton plugin={ plugin } />
					<DownloadButton />
				</div>
				<h1 className="plugin-title" dangerouslySetInnerHTML={ { __html: plugin.title.rendered } } />
				<span className="byline">
					{ translate( 'By {{span/}}', { components: {
						span: <span className="author vcard" dangerouslySetInnerHTML={ { __html: plugin.author } } />,
					} } ) }
				</span>
			</header>
			<div className="entry-content">
				<Section slug="description" title="Description" content={ plugin.sections.description } />
				<Screenshots screenshots={ values( plugin.screenshots ) } />
				<FAQ content={ plugin.sections.faq } />
				<Reviews
					slug={ plugin.slug }
					content={ plugin.sections.reviews }
					numRatings={ plugin.ratings.length } />
				<Section slug="changelog" title="Changelog" content={ plugin.sections.changelog } />
				<Developers />
			</div>
			<div className="entry-meta">
				<MetaWidget />
				<RatingsWidget />
				<SupportWidget />
				<DonateWidget />
			</div>
		</article>
	);
};

Plugin.propTypes = {
	plugin: PropTypes.object,
	translate: PropTypes.func,
};

Plugin.defaultProps = {
	plugin: {},
	translate: identity,
};

export default connect(
	( state ) => ( {
		plugin: getPlugin( state ),
	} ),
)( localize( Plugin ) );
