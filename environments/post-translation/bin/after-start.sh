#!/bin/bash
#
# Runs after wp-env start. Sets up permalinks and GlotPress tables.
#

CONFIG="--config post-translation/.wp-env.json"
WP="npx wp-env $CONFIG run cli --"

# Set up permalinks (required by GlotPress).
echo "Setting up permalinks..."
$WP wp rewrite structure '/%postname%/' --hard

# Create GlotPress database tables.
echo "Creating GlotPress tables..."
$WP wp eval "require_once ABSPATH . 'wp-admin/includes/upgrade.php'; require_once WP_PLUGIN_DIR . '/GlotPress/gp-includes/schema.php'; dbDelta( gp_schema_get() );"

echo "Post Translation environment ready."
