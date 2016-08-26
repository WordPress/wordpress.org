/* global add_data:object */
import React from 'react';
import { connect } from 'react-redux';
import { Router, Route, IndexRoute, useRouterHistory } from 'react-router';
import createBrowserHistory from 'history/lib/createBrowserHistory';

import ArchiveBrowse from 'components/archive/browse';
import FrontPage from 'components/front-page';
import NotFound from 'components/404';
import Page from 'components/page';
import Plugin from 'components/plugin';
import PluginDirectory from 'components';
import Search from 'components/search';
import SiteHeader from 'components/site-header';
import SiteMain from 'components/site-main';

const history = useRouterHistory( createBrowserHistory )( {
	/** @type {object} app_data Description */
	basename: app_data.base
} );

export default (
	<Router history={ history } onUpdate={ () => window.scrollTo( 0, 0 ) }>
		<Route name="root" component={ PluginDirectory }>
			<Route path="/" components={ { header: SiteHeader, main: SiteMain } }>
				<IndexRoute component={ FrontPage } />
				<Route path="browse/favorites/:username" component={ ArchiveBrowse } />
				<Route path="browse/:type" component={ ArchiveBrowse } />
				<Route path="developers" component={ Page } />
				<Route path="search/:searchTerm" component={ Search } />
				<Route path=":slug" component={ Plugin } />
				<Route path="*" component={ NotFound } />
			</Route>
		</Route>
	</Router>
);
