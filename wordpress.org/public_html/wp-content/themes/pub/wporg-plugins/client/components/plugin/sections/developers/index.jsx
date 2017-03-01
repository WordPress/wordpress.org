/**
 * External dependencies.
 */
import React, { PropTypes } from 'react';
import { connect } from 'react-redux';
import { identity } from 'lodash';
import { localize } from 'i18n-calypso';

/**
 * Internal dependencies.
 */
import DeveloperList from './list';
import { getPlugin } from 'state/selectors';

export const Developers = ( { plugin, translate } ) => (
	<div>
		<div id="developers" className="section read-more plugin-developers">
			<h2>{ translate( 'Contributors & Developers' ) }</h2>
			<p>
				{ translate( 'This is open source software. The following people have contributed to this plugin.' ) }
			</p>
			<DeveloperList contributors={ plugin.contributors } />

			<h5>{ translate( 'Interested in development?' ) }</h5>
			<p>
				{
					/* eslint-disable max-len */
					translate( '{{code}}Browse the code{{/code}} or subscribe to the {{log}}development log{{/log}} by {{rss}}RSS{{/rss}}.', {
						components: {
							code: <a href={ `https://plugins.svn.wordpress.org/${ plugin.slug }/` } rel="nofollow" />,
							log: <a href={ `https://plugins.trac.wordpress.org/log/${ plugin.slug }/` } rel="nofollow" />,
							rss: <a href={ `https://plugins.trac.wordpress.org/log/${ plugin.slug }/?limit=100&mode=stop_on_copy&format=rss` } rel="nofollow" />,
						},
					} )
					/* eslint-enable max-len */
				}
			</p>
		</div>
		<button type="button" className="button-link section-toggle" aria-controls="developers" aria-expanded="false">
			{ translate( 'Read more' ) }
		</button>
	</div>
);

Developers.propTypes = {
	plugin: PropTypes.object,
	translate: PropTypes.func,
};

Developers.defaultProps = {
	plugin: {},
	translate: identity,
};

export default connect(
	( state ) => ( {
		plugin: getPlugin( state ),
	} ),
)( localize( Developers ) );
