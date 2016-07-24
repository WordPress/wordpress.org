/* global add_data:object */
import React from 'react';
import { connect } from 'react-redux';
import { Router, Route, IndexRoute, useRouterHistory } from 'react-router';
import createBrowserHistory from 'history/lib/createBrowserHistory';

/**
 * Internal dependencies.
 */
import Page from 'containers/page';
import FrontPage from 'components/front-page';
import PluginDirectory from 'components/plugin-directory';
import SiteHeader from 'components/site-header';
import SiteMain from 'components/site-main';
import ArchiveBrowse from 'components/archive-browse';
import NotFound from 'components/404';

const history = useRouterHistory( createBrowserHistory )( {
	basename: app_data.base
} );

export default (
	<Router history={ history }>
		<Route name="root" component={ PluginDirectory }>
			<Route path="/" components={ { header: SiteHeader, main: SiteMain } }>
				<IndexRoute component={ FrontPage } />
				<Route path="browse/favorites/:username" component={ ArchiveBrowse } />
				<Route path="browse/:type" component={ ArchiveBrowse } />
				<Route path="developers" component={ Page } />
				<Route path=":plugin" component={ FrontPage } />
				<Route path="search/:searchTerm" component={ FrontPage } />
				<Route path="*" component={ NotFound } />
			</Route>
		</Route>
	</Router>
);
