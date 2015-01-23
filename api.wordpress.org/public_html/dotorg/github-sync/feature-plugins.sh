#!/bin/bash

GITHUB_URL=$1;
SVN_PLUGIN=$2;

# ENV vars: $PHP_SVN_USER, $PHP_SVN_PASSWORD

if [ ! $GITHUB_URL ] || [ ! $SVN_PLUGIN ] || [ ! $PHP_SVN_USER ] || [ ! $PHP_SVN_PASSWORD ]; then
	echo "Invalid Input, missing parameter"; exit 1
fi

SVN_URL="https://plugins.svn.wordpress.org/$SVN_PLUGIN/trunk/"

WORKING_DIR=$(mktemp -d /tmp/gh-plugin-sync-XXXXXXX)
COMMIT_MESSAGE=$(mktemp /tmp/gh-plugin-sync-commit-XXXXXX)
function finish {
	rm -rf "$WORKING_DIR"
	rm -rf "$COMMIT_MESSAGE"
	echo Script Done.
}
trap finish EXIT

cd $WORKING_DIR

echo $(uname -n | sed 's/.wordpress.org$//') Merging from $GITHUB_URL to $SVN_URL

# Checkout
svn co --non-interactive --force $SVN_URL $WORKING_DIR 2>&1

# Get the last GIT Rev sync
LAST_GIT_SYNC_REV=$(svn propget github:lastSync $WORKING_DIR)

# Clear out working directory to account for removes/adds
rm -rf $WORKING_DIR/*

# Change into Git working directory
mkdir $WORKING_DIR/_temp-github/
cd $WORKING_DIR/_temp-github/

# Create a temporary Github clone to work from
git clone -q --recursive $GITHUB_URL $WORKING_DIR/_temp-github/

# Get the latest Git rev
LATEST_GIT_REV=$(git rev-parse HEAD)

if [ ! $LAST_GIT_SYNC_REV ]; then
	echo Initial Github to SVN sync:  > $COMMIT_MESSAGE
	git log -1 -c $LATEST_GIT_REV | tee $COMMIT_MESSAGE
fi
if [ $LAST_GIT_SYNC_REV ]; then
	# Get the Commit messages
	echo Syncing $GITHUB_URL to $SVN_URL > $COMMIT_MESSAGE
	git log $LAST_GIT_SYNC_REV..$LATEST_GIT_REV | tee $COMMIT_MESSAGE
fi

# Back to SVN
cd $WORKING_DIR

# Update the latest sync rev
svn propset github:lastSync $LATEST_GIT_REV $WORKING_DIR

# Copy Github over SVN
mv -f $WORKING_DIR/_temp-github/* $WORKING_DIR/
rm -rf $WORKING_DIR/_temp-github

# Do the version number bump with $date (0.1-2015-01-25)
# Will error if no .php files exist
MAIN_PLUGIN_FILE=$(grep 'Plugin Name:' $WORKING_DIR/*.php -l | head -n1)
if [ $MAIN_PLUGIN_FILE ]; then
	DATE=$(date +%Y%m%d)
	sed -e  "/^[ *#/]*Version:/ s/$/-$DATE/" -i $MAIN_PLUGIN_FILE
fi

# Add/Delete the world
svn st  | grep ^? | awk '{print $2}' | xargs -I % svn add --force %
svn st  | grep ^! | awk '{print $2}' | xargs -I % svn rm --force %

# Check it in!
svn ci --non-interactive $WORKINGDIR -F $COMMIT_MESSAGE --username $PHP_SVN_USER --password $PHP_SVN_PASSWORD

