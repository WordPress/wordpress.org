<?php

namespace WordPressdotorg\GlotPress\Playground\Routes;

use GP;
use GP_Route;
use GP_Locales;

class Route extends GP_Route {
	public function playground( $project_path, $locale_slug, $translation_set_slug ) {
		$project = GP::$project->by_path( $project_path );
		if ( ! $project ) {
			die( 'Project not found' );
		}

		$translation_set = GP::$translation_set->by_project_id_slug_and_locale( $project->id, $translation_set_slug, $locale_slug );
		if ( ! $translation_set ) {
			wp_send_json_error( 'translation_set_found' );
		}

		if ( ! preg_match( '#^wp-plugins/([^/]+)/#', $project_path, $m ) ) {
			die( 'Unknown path' );
		}
		$plugin = $m[1];
		$plugin_project = GP::$project->by_path( 'wp-plugins/' . $plugin );
		if ( ! $project ) {
			die( 'Project not found' );
		}


		$gp_locale = GP_Locales::by_slug( $locale_slug );
		if ( ! $gp_locale ) {
			die( 'Unknown locale' );
		}

		$can_import_current = $this->can( 'approve', 'translation-set', $translation_set->id );
		$can_import_waiting = $can_import_current || $this->can( 'import-waiting', 'translation-set', $translation_set->id );

		?>
<!DOCTYPE html>
<html>
	<head>
		<title>WP GlotPress Playground</title>
		<style>
			body {
				font-family: sans-serif;
				background: rgb(2,0,36);
				background: radial-gradient(circle, rgba(2,0,36,1) 0%, rgba(100,100,172,1) 35%, rgba(0,212,255,1) 100%);
			}
			iframe#wp, div#progress {
				width: 1200px;
				height: 800px;
				background: white;
			}
			div#info {
				position: absolute;
				bottom: 1em;
				display: block;
				text-align: right;
				background: white;
				margin: 2em .1em;
				padding: .5em;
				height: 1em;
			}
			div#progress {
				position: absolute;
				display: flex;
				align-items: center;
				justify-content: center;
				background: white;
				margin: 2em .1em;
				height: 770px;
			}
			div#progressinner {
				width: 600px;
			}
			div#progressbarholder {
				height: 1em;
				border: 1px solid black;
			}
			div#progressbar {
				width: 0;
				height: 1em;
				background: black;
				transition: opacity linear 0.25s, width ease-in .5s;
			}
			div#progresstext {
				text-align: center;
				margin-top: .5em;
			}
		</style>
	</head>
	<body>
		<div id="progress">
			<div id="progressinner">
				<div id="progressbarholder"><div id="progressbar"></div></div>
				<div id="progresstext"></div>
			</div>
		</div>
		<iframe id="wp"></iframe>
		<div id="info">
			This is WordPress running just in your browser, with the plugin <strong><?php echo esc_html( $plugin_project->name ); ?></strong> in <strong><?php echo esc_html( $gp_locale->english_name ); ?></strong> for translation. Logged in as <em><?php echo wp_get_current_user()->display_name; ?> (<?php echo wp_get_current_user()->user_login; ?>)</em> on <a href="<?php echo esc_url( home_url() ); ?>" target="_blank"><?php echo get_bloginfo( 'name' ); ?></a>.
		</div>
		<script type="importmap">
			{
				"imports": {
					"@wp-playground/client": "https://unpkg.com/@wp-playground/client/index.js"
				}
			}
		</script>
		<script type="module">
			import { connectPlayground, login, installPluginsFromDirectory } from '@wp-playground/client';
			let response;
			let totalPercentage = 0;

			function progress( percentage, text ) {
				totalPercentage += percentage;
				if ( totalPercentage >= 100 ) {
					document.getElementById( 'progress' ).style.display = 'none';
					return;
				}
				document.getElementById( 'progressbar' ).style.width = totalPercentage + '%';
				document.getElementById( 'progresstext' ).textContent = text;
			}
			progress( 1, 'Preparing WordPress...' );

			const client = await connectPlayground(
				document.getElementById('wp'),
				{ loadRemote: 'https://playground.wordpress.net/remote.html' }
			);

			const lang = '<?php echo esc_attr( $locale_slug ); ?>';
			progress( 10, 'Logging in...' );
			await client.isReady();

			await login(client, 'admin', 'password');
			await client.mkdirTree('/wordpress/wp-content/languages/plugins');
			const languages = {
				'wp/dev': '',
				'wp/dev/admin': 'admin-',
				'wp-plugins/glotpress/dev': 'plugins/glotpress-',
				'wp-plugins/<?php echo esc_attr( $plugin ); ?>/dev': 'plugins/<?php echo esc_attr( $plugin ); ?>-',
			};
			const filters = {
				'wp': '&filters[term]=wp-admin',
				'wp/admin': '&filters[term]=wp-admin',
			};

			progress( 5, 'Downloading languages...' );

			for ( const path in languages ) {
				for ( const format of [ 'po', 'mo' ] ) {
					progress( 5, 'Downloading languages... (' + languages[path] + '<?php echo esc_attr( $gp_locale->wp_locale ); ?>.' + format + ')' );
					await fetch( 'https://translate.wordpress.org/projects/' + path + '/<?php echo esc_attr( $locale_slug ); ?>/<?php echo esc_attr( $translation_set_slug ); ?>/export-translations?format=' + format + ( path in filters ? filters[path] : '' ) )
					  .then(response => response.arrayBuffer() )
					  .then(response => client.writeFile( '/wordpress/wp-content/languages/' + languages[path] + '<?php echo esc_attr( $gp_locale->wp_locale ); ?>.' + format, new Uint8Array(response) ) );
				}
			}
			response = await client.run({
				code: '<' + '?' + 'php ' + `
include 'wordpress/wp-load.php';
update_option( 'WPLANG', '<?php echo esc_attr( $gp_locale->wp_locale ); ?>' );
update_option( 'permalink_structure','/%year%/%monthnum%/%day%/%postname%/' );
update_option( 'gp_enable_local_translation', 1 );
update_option( 'gp_enable_inline_translation', 1 );
update_option( 'gp_wporg_import_translations_nonce', '<?php echo esc_attr( wp_create_nonce( 'import-translations_' . $project->id ) ); ?>' );
update_option( 'gp_wporg_permissions', array(
	'can_import_current' => <?php echo $can_import_current ? 'true' : 'false'; ?>,
	'can_import_waiting' => <?php echo $can_import_waiting ? 'true' : 'false'; ?>,
) );

update_user_meta( get_current_user_id(), 'show_welcome_panel', '0' );
file_put_contents('/wordpress/wp-content/mu-plugins/gp-sqlite.php','<' . '?' . 'php' . PHP_EOL . <<<'ENDP'
add_filter('query', function( $query ) {
	return str_replace( ' BINARY ', ' ', $query);
});
ENDP
);
			`});
			console.log(response.text);
			progress( 20, 'Installing plugins...' );
			await installPluginsFromDirectory( client, ['glotpress-local', '<?php echo esc_attr( $plugin ); ?>'] );
			progress( 15, 'Making plugins translatable...' );
			response = await client.run({
				code: '<' + '?' + 'php ' + `
include 'wordpress/wp-load.php';
$request = new WP_REST_Request();
$request->set_param( 'name', '<?php echo esc_html( $plugin ); ?>');
$request->set_param( 'path', 'wp-plugins/<?php echo esc_html( $plugin ); ?>');
$request->set_param( 'locale', '<?php echo esc_html( $gp_locale->wp_locale ); ?>');
$request->set_param( 'locale_slug', '<?php echo esc_html( $translation_set_slug ); ?>');
GP::$rest->create_local_project( $request );
			`});
			await client.goTo( '/wp-admin/' );
			progress( 100, 'Finished' );
		</script>
	</body>
</html><?php
	}

	public function plugin_proxy() {
		if ( isset( $_GET['plugin'] ) ) {
			$plugin_name = preg_replace( '#[^a-zA-Z0-9\.\-_]#', '', $_GET['plugin'] );
			if ( $plugin_name === 'glotpress-local' ) {
				$this->download_file( 'https://github.com/GlotPress/GlotPress/archive/refs/heads/local-wasm-wporg.zip' );
			}

			$this->download_file( 'https://downloads.wordpress.org/plugin/' . $plugin_name . '.zip' );
		}

		if ( isset( $_GET['theme'] ) ) {
			$theme_name = preg_replace( '#[^a-zA-Z0-9\.\-_]#', '', $_GET['theme'] );
			$this->download_file( 'https://downloads.wordpress.org/theme/' . $theme_name . '.zip' );
		}

		die( 'Invalid request' );
	}

	private function download_file( $url ) {
		$ch = curl_init( $url );
		curl_setopt( $ch, CURLOPT_HEADER, 1 );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt( $ch, CURLOPT_BINARYTRANSFER, 1 );
		curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, 1 );

		$response = curl_exec( $ch );

		$header_size = curl_getinfo( $ch, CURLINFO_HEADER_SIZE );
		$headers = array();
		$body = substr($response, $header_size);
		foreach ( explode("\n", substr($response, 0, $header_size)) as $header ) {
			if ( false === strpos( $header, ':' ) ) {
				continue;
			}
			list( $key, $value ) = explode( ':', trim( $header ), 2 );
			$headers[ strtolower( $key ) ] = $value;
		}
		$headers['content-length'] = strlen( $body );

		$forward_headers = [
		    'content-length',
		    'content-type',
		    'content-disposition',
		    'x-frame-options',
		    'last-modified',
		    'etag',
		    'date',
		    'age',
		    'vary',
		    'cache-Control'
		];

		foreach ( $headers as $key => $value ) {
		    if ( in_array( $key, $forward_headers, true ) ) {
		        header($key . ':' . $value );
		    }
		}

		echo $body;
		exit;
	}
}
