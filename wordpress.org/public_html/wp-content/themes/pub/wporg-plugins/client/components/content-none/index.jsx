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
import { getParams, getPath } from 'state/selectors';
import SearchForm from 'components/search-form';

export const ContentNone = ( { params, path, translate } ) => {
	let helpText, userHelp;

	if ( -1 !== path.indexOf( 'search' ) ) {
		helpText = (
			<div className="page-content">
				<p>{ translate( 'Sorry, but nothing matched your search terms.' ) }</p>
				<p>{ translate( 'Please try again with some different keywords.' ) }</p>
				<SearchForm />
			</div>
		);
	} else if ( -1 !== path.indexOf( 'browse/favorites' ) ) {
		if ( pluginDirectory.userId > 0 ) {
			helpText = <p>{ translate( 'No favorites have been added, yet.' ) }</p>;

			if ( -1 !== path.indexOf( 'browse/favorites/' + params.username ) ) {
				userHelp = (
					<div>
						<p>{ translate( 'Find a plugin and mark it as a favorite to see it here.' ) }</p>
						<p>
							{ translate( 'Your favorite plugins are also shared on {{a}}your profile{{/a}}', {
								components: { a: <a href={ 'https://profile.wordpress.org/' + params.username } /> },
							} ) }
						</p>
					</div>
				);
			}

			helpText = <div className="page-content">{ helpText }{ userHelp }</div>;
		} else {
			helpText = (
				<div className="page-content">
					<p>
						{ translate( '{{a}}Log into WordPress.org{{/a}} to mark plugins as favorites.', {
							components: { a: <a href="https://login.wordpress.org/" /> },
						} ) }
					</p>
				</div>
			);
		}
	} else {
		helpText = (
			<div className="page-content">
				<div className="page-content">
					<p>
						{ translate(
							'It seems we can&#8217;t find what you&#8217;re looking for. Perhaps searching can help.'
						) }
					</p>
				</div>
				<SearchForm />
			</div>
		);
	}

	return (
		<section className="no-results not-found">
			<header className="page-header">
				<h1 className="page-title">{ translate( 'Nothing Found' ) }</h1>
			</header>
			{ helpText }
		</section>
	);
};

ContentNone.propTypes = {
	params: PropTypes.object,
	path: PropTypes.string,
	translate: PropTypes.func,
};

ContentNone.defaultProps = {
	params: {},
	path: '',
	translate: identity,
};

export default connect(
	( state ) => ( {
		params: getParams( state ),
		path: getPath( state ),
	} ),
)( localize( ContentNone ) );
