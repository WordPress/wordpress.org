#!/usr/bin/env node
/**
 * Runs after wp-env start. Sets up permalinks, creates pages, and imports plugins.
 *
 * Cross-platform replacement for after-start.sh (Windows lacks /bin/bash in wp-env's shell).
 */

const { spawnSync } = require( 'child_process' );
const path = require( 'path' );

const ROOT = path.resolve( __dirname, '..', '..' );
const CONFIG = '--config plugin-directory/.wp-env.json';

function run( args, options = {} ) {
	const result = spawnSync( 'npx', [ 'wp-env', ...CONFIG.split( ' ' ), ...args ], {
		cwd: ROOT,
		shell: true,
		encoding: 'utf8',
		...options,
	} );

	return result;
}

function wp( ...wpArgs ) {
	return run( [ 'run', 'cli', '--', 'wp', ...wpArgs ], {
		stdio: [ 'pipe', 'pipe', 'pipe' ],
	} );
}

function wpQuiet( ...wpArgs ) {
	return wp( ...wpArgs );
}

function tryWp( message, ...wpArgs ) {
	const result = wpQuiet( ...wpArgs );

	if ( result.status === 0 ) {
		console.log( message );
	}

	return result;
}

function wpOutput( ...wpArgs ) {
	const result = wp( ...wpArgs );
	return ( result.stdout || '' ).trim();
}

// Install CLI tools needed by the plugin directory (svn, unzip, etc.).
console.log( 'Installing CLI tools...' );
run( [
	'run',
	'wordpress',
	'sudo',
	'bash',
	'-c',
	'command -v svn > /dev/null || (apt-get -qy update && apt-get -qy install subversion unzip zip) > /dev/null 2>&1',
], { stdio: 'inherit' } );

run( [
	'run',
	'cli',
	'sudo',
	'sh',
	'-c',
	'command -v svn > /dev/null || apk add --no-cache -q subversion unzip zip coreutils > /dev/null 2>&1',
], { stdio: 'inherit' } );

// Set up permalinks.
wp( 'rewrite', 'structure', '/%postname%/', '--hard' );

// Create pages that exist on wordpress.org/plugins (if they don't already exist).
console.log( 'Creating pages...' );
tryWp(
	'  Created page: /developers/',
	'post',
	'create',
	'--post_type=page',
	'--post_status=publish',
	'--post_title=Developer Information',
	'--post_name=developers',
	'--porcelain'
);

const developersId = wpOutput(
	'post',
	'list',
	'--post_type=page',
	'--name=developers',
	'--field=ID'
);

if ( developersId ) {
	tryWp(
		'  Created page: /developers/add/',
		'post',
		'create',
		'--post_type=page',
		'--post_status=publish',
		'--post_title=Add your Plugin',
		'--post_name=add',
		`--post_parent=${ developersId }`,
		'--porcelain'
	);
	tryWp(
		'  Created page: /developers/readme-validator/',
		'post',
		'create',
		'--post_type=page',
		'--post_status=publish',
		'--post_title=Readme Validator',
		'--post_content=[readme-validator]',
		'--post_name=readme-validator',
		`--post_parent=${ developersId }`,
		'--porcelain'
	);
	tryWp(
		'  Created page: /developers/block-plugin-validator/',
		'post',
		'create',
		'--post_type=page',
		'--post_status=publish',
		'--post_title=Block Plugin Checker',
		'--post_content=[block-validator]',
		'--post_name=block-plugin-validator',
		`--post_parent=${ developersId }`,
		'--porcelain'
	);
	tryWp(
		'  Created page: /developers/releases/',
		'post',
		'create',
		'--post_type=page',
		'--post_status=publish',
		'--post_title=Release Management',
		'--post_content=[release-confirmation]',
		'--post_name=releases',
		`--post_parent=${ developersId }`,
		'--porcelain'
	);
}

// Create stub database tables that exist outside WordPress on production.
wp( 'db', 'import', 'wp-content/env-bin/database-tables.sql' );

// Create browse section terms with proper display names.
console.log( 'Creating browse sections...' );
const sections = {
	featured: 'Featured',
	popular: 'Popular',
	beta: 'Beta',
	blocks: 'Block-Enabled',
	new: 'New',
	updated: 'Recently Updated',
	favorites: 'Favorites',
	'dashboard-widgets': 'Dashboard Widgets',
};

for ( const [ slug, name ] of Object.entries( sections ) ) {
	tryWp(
		`  Created section: ${ name } (${ slug })`,
		'term',
		'create',
		'plugin_section',
		name,
		`--slug=${ slug }`
	);
}

// Import plugins from wordpress.org.
wp( 'eval-file', 'wp-content/env-bin/import-plugins.php' );
