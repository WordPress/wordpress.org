<?php
/**
 * MCP authorization for the application password flow on wp-login.php.
 *
 * Registers the WordPress.org MCP server as an allowed application and
 * renders the client configuration after password creation.
 *
 * Authorization link:
 * https://login.wordpress.org/wp-login.php?action=authorize_application&app_id=c4c73a54-96d7-47b9-9bdc-1a66b9b04505
 *
 * @package login-application-passwords
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

/**
 * MCP_Authorization class.
 */
class MCP_Authorization {

	/**
	 * The MCP application UUID.
	 *
	 * @var string
	 */
	const APP_ID = 'c4c73a54-96d7-47b9-9bdc-1a66b9b04505';

	/**
	 * Initialize hooks.
	 */
	public static function init(): void {
		add_filter( 'wporg_login_application_passwords_allowed_apps', array( __CLASS__, 'register' ) );
		add_action( 'wp_authorize_application_password_form_approved_no_js', array( __CLASS__, 'render_config' ), 10, 3 );
		add_action( 'login_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	/**
	 * Registers the WordPress.org MCP application.
	 *
	 * @param array $apps Allowed applications.
	 * @return array
	 */
	public static function register( array $apps ): array {
		$apps[ self::APP_ID ] = array(
			'name'  => 'WordPress.org MCP',
			'hosts' => array( '127.0.0.1' ),
		);

		return $apps;
	}

	/**
	 * Renders the MCP client configuration after application password creation.
	 *
	 * @param string  $new_password The newly created application password.
	 * @param array   $request      The request data.
	 * @param WP_User $user         The user who authorized the application.
	 */
	public static function render_config( string $new_password, array $request, WP_User $user ): void {
		if ( ( $request['app_id'] ?? '' ) !== self::APP_ID ) {
			return;
		}

		// Credentials were already sent via the success_url redirect.
		if ( ! empty( $request['success_url'] ) ) {
			return;
		}

		$mcp_config = array(
			'mcpServers' => array(
				'wporg-mcp-server' => array(
					'command' => 'npx',
					'args'    => array( '-y', '@automattic/mcp-wordpress-remote@^0.2' ),
					'env'     => array(
						'WP_API_URL'      => get_rest_url( 1, 'mcp/wporg' ),
						'WP_API_USERNAME' => $user->user_login,
						'WP_API_PASSWORD' => WP_Application_Passwords::chunk_password( $new_password ),
					),
				),
			),
		);

		$json = wp_json_encode( $mcp_config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		$json = str_replace( '    ', '  ', $json );

		?>
		<div class="mcp-config-display">
			<h2><?php esc_html_e( 'Set Up Your MCP Client' ); ?></h2>
			<label for="mcp-config" class="authorize-application-details">
				<?php esc_html_e( 'Copy the configuration below and add it to your MCP client.' ); ?>
			</label>
			<p class="description">
				<strong><?php esc_html_e( 'This configuration contains your application password. Do not commit it to source control.' ); ?></strong>
			</p>
			<textarea id="mcp-config" readonly rows="14"><?php echo esc_textarea( $json ); ?></textarea>
			<button type="button" class="button button-primary button-large" id="copy-mcp-config" data-copied="<?php esc_attr_e( 'Copied!' ); ?>"><?php esc_html_e( 'Copy' ); ?></button>
			<ul class="client-notes">
				<li><strong>Claude Desktop</strong> &mdash; <?php esc_html_e( 'Settings &rarr; Developer &rarr; Edit Config.' ); ?></li>
				<li><strong>Claude Code</strong> &mdash; 
				<?php
					/* translators: %s: file name */
					printf( esc_html__( 'Add to %s in your project directory.' ), '<code>.mcp.json</code>' );
				?>
				</li>
				<li><strong>Cursor</strong> &mdash; <?php esc_html_e( 'Settings &rarr; Tools and MCP &rarr; Add Custom MCP.' ); ?></li>
				<li><strong>VS Code</strong> &mdash; 
				<?php
					printf(
						/* translators: 1: file path, 2: old key, 3: new key */
						esc_html__( 'Save as %1$s &mdash; use %2$s instead of %3$s.' ),
						'<code>.vscode/mcp.json</code>',
						'<code>servers</code>',
						'<code>mcpServers</code>'
					);
				?>
				</li>
			</ul>
		</div>
		<?php
	}

	/**
	 * Enqueues MCP-specific assets on the authorize_application login page.
	 */
	public static function enqueue_assets(): void {
		global $action;

		if ( 'authorize_application' !== $action ) {
			return;
		}

		wp_enqueue_style(
			'login-mcp-authorization',
			plugins_url( 'style.css', __FILE__ ),
			array( 'login-authorize-application' ),
			filemtime( __DIR__ . '/style.css' )
		);

		wp_enqueue_script(
			'login-mcp-copy-config',
			plugins_url( 'copy-config.js', __FILE__ ),
			array(),
			filemtime( __DIR__ . '/copy-config.js' ),
			true
		);
	}
}
