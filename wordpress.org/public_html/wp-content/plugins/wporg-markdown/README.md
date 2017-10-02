# WPORG Markdown Importer

Imports Markdown from a remote site (like GitHub) into WordPress as pages.

## Configuration

Each importer needs to override the abstract methods:

* `get_base()` - Base URL for imported pages. This will be stripped from the key before comparing.
* `get_manifest_url()` - URL pointing to the manifest.
* `get_post_type()` - Post type to import as.

## Manifest Format

The manifest should be a JSON object, with the keys set to the desired permalink (excluding the base path). Each item should also be a JSON object, containing the following keys:

* `slug` - Post name to insert. (Must match the final path-part of the key.)
* `markdown_source` - URL for the Markdown file to parse into content.
* `parent` - Key for the parent to store under. (Must correspond to the non-final path-parts of the key.)
* `title` - Title to use when creating post. Used temporarily, will be updated from the Markdown file. If not specified, defaults to `slug` (but will be updated from Markdown source).

**Note:** The Handbook index should have the slug `index`.

Example:

```json
{
	"foo": {
		"title": "Temporary Foo Title",
		"slug": "foo",
		"markdown_source": "https://raw.githubusercontent.com/WordPress/doc-repo/master/foo.md",
		"parent": null
	},
	"foo/bar": {
		"title": "Temporary Bar Title",
		"slug": "bar",
		"markdown_source": "https://raw.githubusercontent.com/WordPress/doc-repo/master/foo/bar.md",
		"parent": "foo"
	},
	"foo/bar/quux": {
		"title": "Temporary Quux Title",
		"slug": "quux",
		"markdown_source": "https://raw.githubusercontent.com/WordPress/doc-repo/master/foo/bar/quux.md",
		"parent": "foo/bar"
	}
}
```
