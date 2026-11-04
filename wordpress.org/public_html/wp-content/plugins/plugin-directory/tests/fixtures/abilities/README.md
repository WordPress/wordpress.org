# Ability detection scenarios

Each directory here is one scenario for `Import_Abilities_Test`. It's laid out like a plugin: put PHP files anywhere inside it, including subdirectories and `vendor/`.

Add an `expected.json` next to them:

```json
{
	"description": "What this scenario covers.",
	"abilities": [
		{ "name": "my-plugin/do-thing", "label": "Do thing", "description": "Does the thing." }
	]
}
```

- `abilities` lists every ability the importer should find. Order doesn't matter. Leave out `label` or `description` (or set them to `null`) when they can't be read statically, for example when they come from a variable.
- Use `"abilities": []` for code that shouldn't produce any listing.
- Add `"todo": "why"` for a pattern that isn't supported yet. The test is then reported as incomplete instead of failing, and it fails once detection starts matching so the `todo` gets removed.

Run the scenarios from `environments/`:

```bash
WP_ENV_PORT=8890 WP_ENV_MYSQL_PORT=32790 npm run plugins:test:env -- run cli --env-cwd=wp-content/plugins/plugin-directory phpunit -- --filter Import_Abilities_Test
```
