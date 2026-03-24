# Login Application Passwords

Provides the application password authorization flow on `wp-login.php`. Users visit an authorization URL, log in, approve access, and receive credentials — either via a callback redirect or displayed on-screen with client-specific setup instructions.

Clients register through the `wporg_login_application_passwords_allowed_apps` filter. Each client lives in its own subdirectory under `clients/`. The MCP client (`clients/mcp/`) is the current reference implementation.

## Adding a new client

1. Generate a UUID for your client. You can use `wp_generate_uuid4()` in WP-CLI:

```
wp eval 'echo wp_generate_uuid4();'
```

2. Create a directory under `clients/` and add an authorization class with your UUID as the `APP_ID` constant. See `MCP_Authorization` for a complete example:

```php
class My_App_Authorization {
    const APP_ID = 'your-generated-uuid-here';

    public static function init(): void {
        add_filter( 'wporg_login_application_passwords_allowed_apps', array( __CLASS__, 'register' ) );
        add_action( 'wp_authorize_application_password_form_approved_no_js', array( __CLASS__, 'render_config' ), 10, 3 );
    }

    public static function register( array $apps ): array {
        $apps[ self::APP_ID ] = array(
            'name'  => 'My Application',
            'hosts' => array( 'myapp.example.com' ), // Allowed callback domains.
        );

        return $apps;
    }

    public static function render_config( string $new_password, array $request, WP_User $user ): void {
        if ( ( $request['app_id'] ?? '' ) !== self::APP_ID ) {
            return;
        }

        // Render client-specific configuration with the password.
    }
}
```

3. Load the client in the main plugin file:

```php
require_once __DIR__ . '/clients/my-app/class-my-app-authorization.php';
add_action( 'login_init', array( My_App_Authorization::class, 'init' ) );
```

4. The authorization URL for your client is:

```
https://login.wordpress.org/wp-login.php?action=authorize_application&app_id=YOUR-UUID
```

## Registration details

- `name` — Displayed to the user during authorization. This overrides any user-supplied `app_name` to prevent phishing.
- `hosts` — Array of domains allowed in `success_url`/`reject_url` callbacks. Set to an empty array if your client doesn't use callback URLs (credentials will be displayed on-screen instead).

## Hooks

| Hook | Type | When |
|------|------|------|
| `wporg_login_application_passwords_allowed_apps` | Filter | Register your app with UUID, name, and allowed hosts |
| `wp_authorize_application_password_form_approved_no_js` | Action | After approval, when no `success_url` was provided — render custom config/instructions |
| `wp_authorize_application_password_form` | Action | Before the approve/reject buttons — add custom content to the authorization form |
| `wp_authorize_application_password_request_errors` | Action | During validation — add custom error checks |
