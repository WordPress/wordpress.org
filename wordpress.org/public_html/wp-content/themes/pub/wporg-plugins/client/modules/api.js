import WPAPI from 'wpapi';

import routes from 'default-routes.json';

/** @type {Object} pluginDirectory Config variable */
const pluginDirectory = window.pluginDirectory || {};
pluginDirectory.routes = routes;

const api = new WPAPI( pluginDirectory );
api.sections = api.registerRoute( 'wp/v2', '/plugin_section/(?P<id>)' );

export default api;
