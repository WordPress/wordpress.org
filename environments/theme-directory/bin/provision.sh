#!/bin/bash
#
# Provisions the theme directory frontend theme, which lives inside the
# WordPress/wporg-theme-directory repository rather than in this repo. wp-env
# cannot mount a subdirectory of a GitHub repo, so we clone it into a
# git-ignored vendor directory and map the theme from there in .wp-env.json.
#

set -e

VENDOR_DIR="theme-directory/vendor/wporg-theme-directory"

if [ ! -d "$VENDOR_DIR" ]; then
	echo "Cloning wporg-theme-directory (build branch)..."
	git clone --depth 1 --branch build \
		https://github.com/WordPress/wporg-theme-directory.git "$VENDOR_DIR"
else
	echo "Updating wporg-theme-directory..."
	git -C "$VENDOR_DIR" pull --ff-only || true
fi
