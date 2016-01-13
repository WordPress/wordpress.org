#!/bin/bash

GITHUB_URL=$1;
SVN_PLUGIN=$2;

# Expect ENV vars: $PHP_SVN_USER, $PHP_SVN_PASSWORD

# Split by newlines only in bash arrays
IFS=$'\r\n'

# Ensure that we're running with a UTF8 character set for commits with UTF8 characters
locale | grep LC_CTYPE | grep -qie 'utf-\?8';
if [ $? -eq 1 ]; then
        export LC_ALL=$( locale -a | grep -ie 'utf-\?8' | head -1 );
fi;

# Validate all Parameters are present
if [ ! $GITHUB_URL ] || [ ! $SVN_PLUGIN ] || [ ! $PHP_SVN_USER ] || [ ! $PHP_SVN_PASSWORD ]; then
	echo "Invalid Input, missing parameter"; exit 1
fi

SVN_URL="https://plugins.svn.wordpress.org/$SVN_PLUGIN/trunk/"
ASSETS_SVN_URL="https://plugins.svn.wordpress.org/$SVN_PLUGIN/assets/"

WORKING_DIR=$(mktemp -d /tmp/gh-plugin-sync-XXXXXXX)
ASSETS_WORKING_DIR=$(mktemp -d /tmp/gh-plugin-assets-XXXXXXX)
COMMIT_MESSAGE=$(mktemp /tmp/gh-plugin-sync-commit-XXXXXX)
function finish {
	rm -rf "$WORKING_DIR"
	rm -rf "$COMMIT_MESSAGE"
	rm -rf "$ASSETS_WORKING_DIR"
	echo Sync Done.
}
trap finish EXIT

cd $WORKING_DIR

echo Merging from $GITHUB_URL to $SVN_URL

# Checkout
svn co --non-interactive --force -q $SVN_URL $WORKING_DIR 2>&1

# Get the last GIT Rev sync
LAST_GIT_SYNC_REV=$(svn propget github:lastSync $WORKING_DIR)

# Clear out working directory to account for removes/adds
rm -rf $WORKING_DIR/*

# Change into Git working directory
mkdir $WORKING_DIR/_temp-github/
cd $WORKING_DIR/_temp-github/

# Create a temporary Github clone to work from
# We don't specify --recursive as submodules are usually huge external libs used for development
git clone -q $GITHUB_URL $WORKING_DIR/_temp-github/

# Get the latest Git rev
LATEST_GIT_REV=$(git rev-parse HEAD)

if [ ! $LAST_GIT_SYNC_REV ]; then
	echo Initial Github to SVN sync:  > $COMMIT_MESSAGE
	git log -1 -c $LATEST_GIT_REV | tee $COMMIT_MESSAGE
fi
if [ $LAST_GIT_SYNC_REV ]; then
	# Get the Commit messages
	GIT_LOG=$( git log $LAST_GIT_SYNC_REV..$LATEST_GIT_REV )

	# Split the Git Commit logs up
	GIT_LOG_LINES=($GIT_LOG)
	LF=$'\n'

	# Write the Commit Message to a file, "First Commit\nAll Commits"
	echo "${GIT_LOG_LINES[3]:4}$LF$LF$GIT_LOG$LF" > $COMMIT_MESSAGE
	echo Synced from $GITHUB_URL >> $COMMIT_MESSAGE

	cat $COMMIT_MESSAGE
fi

# Back to SVN
cd $WORKING_DIR

# Update the latest sync rev
svn propset -q github:lastSync $LATEST_GIT_REV $WORKING_DIR

# Copy Github over SVN
# Process any Assets first (This is effectively this entire file boiled down to a few lines)
if [ -d $WORKING_DIR/_temp-github/assets/ ]; then
	cd $ASSETS_WORKING_DIR
	svn co --non-interactive --force -q $ASSETS_SVN_URL $ASSETS_WORKING_DIR
	rm -rf $ASSETS_WORKING_DIR/*
	mv -f $WORKING_DIR/_temp-github/assets/* $ASSETS_WORKING_DIR/
	rmdir $WORKING_DIR/_temp-github/assets
	svn st $ASSETS_WORKING_DIR | grep ^? | awk '{print $2}' | xargs -I % svn add --force %
	svn st $ASSETS_WORKING_DIR | grep ^! | awk '{print $2}' | xargs -I % svn rm --force %
	cd $WORKING_DIR
fi

# Next copy the rest of the plugin files
mv -f $WORKING_DIR/_temp-github/* $WORKING_DIR/
rm -rf $WORKING_DIR/_temp-github

# Do the version number bump with $date (0.1-20150125)
# Will error if no .php files exist
MAIN_PLUGIN_FILE=$(grep 'Plugin Name:' $WORKING_DIR/*.php -l | head -n1)
if [ $MAIN_PLUGIN_FILE ]; then
	DATE=$(date +%Y%m%d)
	sed -e  "/^[ *#/]*Version:/ s/$/-$DATE/" -i $MAIN_PLUGIN_FILE
fi

# Add/Delete the world
svn st $WORKING_DIR | grep ^? | awk '{print $2}' | xargs -I % svn add --force %
svn st $WORKING_DIR | grep ^! | awk '{print $2}' | xargs -I % svn rm --force %

# Check it in!
# Asset commit will only go through if there's new assets, trunk commit will always happen (at a minimum to bump lastSync)
svn ci --non-interactive $ASSETS_WORKING_DIR -F $COMMIT_MESSAGE --username $PHP_SVN_USER --password $PHP_SVN_PASSWORD
svn ci --non-interactive $WORKINGDIR -F $COMMIT_MESSAGE --username $PHP_SVN_USER --password $PHP_SVN_PASSWORD

