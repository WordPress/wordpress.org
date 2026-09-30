# WordPress.org Abilities

Provides the MCP server infrastructure for WordPress.org abilities. Abilities registered with the WordPress Abilities API (prefixed `wporg/`) are automatically discovered and exposed as MCP tools, resources, or prompts via the `wporg-mcp-server`.

Abilities are organized by domain — each domain groups related tools, resources, and prompts together. New domains can be added alongside existing ones to extend the server's capabilities.

## Ability types

Set `meta['mcp']['type']` in `wp_register_ability()` to control how the ability is exposed:

| Value      | MCP role   | Use for                                    |
|------------|------------|--------------------------------------------|
| `tool`     | Tool       | Actions that take input and return output   |
| `resource` | Resource   | Reference content (docs, specs, guidelines) |
| `prompt`   | Prompt     | Multi-step workflows and instructions       |

Defaults to `tool` if omitted.

## Adding an ability to an existing domain

Use the existing ability classes as reference — e.g. `Validate_Readme` for a tool, `Plugin_Guidelines` for a resource, `Prepare_Plugin` for a prompt.

1. Create a class with a static `register()` method that calls `wp_register_ability()`. The ability name must start with `wporg/` and the `category` must match the domain's registered category.

2. Add a static `execute()` (or equivalent) callback for the ability's logic.

3. Register it in `Registrar::register_abilities()`.

The autoloader resolves classes automatically — just follow the file naming convention (see below).

## Adding a new ability domain

For example, to add a theme directory domain under `themes/`:

1. Create a new folder under `plugins/`, e.g. `themes/theme-directory/`.

2. Register a category in `Registrar::register_categories()`:

```php
wp_register_ability_category(
    'wporg-themes-theme-directory',
    array(
        'label'       => 'Theme Directory',
        'description' => 'Tools, resources, and prompts for the WordPress.org theme directory.',
    )
);
```

3. Add ability classes under `tools/`, `resources/`, and `prompts/` subdirectories, then register them in `Registrar::register_abilities()`.

The MCP server auto-discovers all abilities whose name starts with `wporg/`.

## Sandbox testing

To test abilities against a dotorg sandbox, configure the MCP server to point at your sandbox IP. Create a `.mcp.json` in your project root:

```json
{
  "mcpServers": {
    "wporg-sandbox": {
      "command": "npx",
      "args": ["-y", "@automattic/mcp-wordpress-remote@latest"],
      "env": {
        "WP_API_URL": "https://<sandbox-ip>/wp-json/mcp/wporg",
        "WP_API_USERNAME": "<your-wp-username>",
        "WP_API_PASSWORD": "<your-application-password>",
        "CUSTOM_HEADERS": "{\"Host\": \"wordpress.org\"}",
        "SOCKS_PROXY": "socks5://127.0.0.1:8080",
        "USE_SYSTEM_PROXY": "true",
        "NODE_TLS_REJECT_UNAUTHORIZED": "0"
      }
    }
  }
}
```

- `CUSTOM_HEADERS` sets the Host header so the sandbox routes the request correctly.
- `SOCKS_PROXY` and `USE_SYSTEM_PROXY` route traffic through the proxy SOCKS5 tunnel. `USE_SYSTEM_PROXY` must be `true` or the proxy env var is ignored.
- `NODE_TLS_REJECT_UNAUTHORIZED` disables TLS verification since the certificate won't match the sandbox IP.
- Follow the [Using the MCP Server](https://developer.wordpress.org/plugins/wordpress-org/using-the-mcp-server/) guide to connect your WordPress.org account and use the application password from that setup.
