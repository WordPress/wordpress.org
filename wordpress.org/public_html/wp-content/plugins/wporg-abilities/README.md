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
