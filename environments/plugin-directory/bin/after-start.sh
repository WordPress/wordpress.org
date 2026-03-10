#!/bin/bash
#
# Runs after wp-env start. Sets up permalinks and imports plugins.
#

CONFIG="--config plugin-directory/.wp-env.json"

npx wp-env $CONFIG run cli -- wp rewrite structure '/%postname%/' --hard
npx wp-env $CONFIG run cli wp eval-file wp-content/env-bin/import-plugins.php
