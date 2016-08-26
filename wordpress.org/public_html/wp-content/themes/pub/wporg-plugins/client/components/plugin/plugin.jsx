import React from 'react';

import Developers from './sections/developers';
import DonateWidget from 'components/widget-area/widgets/donate';
import DownloadButton from './download-button';
import FAQ from './sections/faq';
import FavoriteButton from './favorite-button';
import MetaWidget from 'components/widget-area/widgets/meta/index';
import PluginBanner from './plugin-banner';
import PluginIcon from 'components/plugin-icon';
import Reviews from './sections/reviews';
import RatingsWidget from 'components/widget-area/widgets/ratings/index';
import Screenshots from './sections/screenshots';
import Section from './sections';
import SupportWidget from 'components/widget-area/widgets/support/index';

export default React.createClass( {
	displayName: 'Plugin',

	render() {
		if ( ! this.props.plugin ) {
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
			)
		}

		return (
			<article className="plugin type-plugin">
				<PluginBanner plugin={ this.props.plugin } />
				<header className="plugin-header">
					<PluginIcon plugin={ this.props.plugin } />
					<div className="plugin-actions">
						<FavoriteButton plugin={ this.props.plugin } />
						<DownloadButton plugin={ this.props.plugin } />
					</div>
					<h1 className="plugin-title">{ this.props.plugin.name }</h1>
					<span className="byline">By <span className="author vcard" dangerouslySetInnerHTML={ { __html: this.props.plugin.author } } /></span>
				</header>
				<div className="entry-content">
					<Section slug="description" title="Description" content={ this.props.plugin.sections.description } />
					<Screenshots />
					<FAQ content={ this.props.plugin.sections.faq } />
					<Reviews slug={ this.props.plugin.slug } content={ this.props.plugin.sections.reviews } numRatings={ this.props.plugin.num_ratings } />
					<Section slug="changelog" title="Changelog" content={ this.props.plugin.sections.changelog } />
					<Developers slug={ this.props.plugin.slug } contributors={ this.props.plugin.contributors } />
				</div>
				<div className="entry-meta">
					<MetaWidget plugin={ this.props.plugin } />
					<RatingsWidget plugin={ this.props.plugin } />
					<SupportWidget plugin={ this.props.plugin } />
					<DonateWidget plugin={ this.props.plugin } />
				</div>
			</article>
		)
	}
} );
