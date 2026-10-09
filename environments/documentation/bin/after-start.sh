#!/bin/bash
#
# Runs after wp-env start. Activates the theme, sets up permalinks, and imports
# the Documentation content from WordPress.org.
#

set -euo pipefail

CONFIG="--config documentation/.wp-env.json"
WP="npx wp-env $CONFIG run cli --"

echo "Activating theme..."
$WP wp theme activate wporg-documentation-2022

$WP wp option update blogname "Documentation"
$WP wp option update blogdescription "We’ve got a variety of resources to help you get the most out of WordPress."

# Reapplied on every start because wp-env resets the structure whenever it
# reconfigures WordPress.
echo "Setting up permalinks..."
$WP wp rewrite structure '/%postname%/' --hard

# Idempotent — the import gates itself on the wporg_docs_env_imported option,
# which `npm run documentation:refresh` clears.
$WP wp eval-file wp-content/env-bin/import-content.php --user=admin

echo "Documentation environment ready!"
