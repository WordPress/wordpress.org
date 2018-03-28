# Setup Instructions


## General

1. `rm -rf .git/hooks`
1. `ln -s ../path/to/.githooks .git/hooks`
1. If you have multiple Git checkouts (e.g., one for `mu-plugins`, and another for `mu-plugins-private`), then
  you'll need to repeat the above steps for each of them.

Aside: Why not use native Subversion hooks? Because they run on the server, which prevents a developer from choosing to force the push anyway. It would also require work from the Systems team, who doesn't have the time.


## git svn dcommit

git-svn doesn't support hooks natively, but there are a few ways around that.

1. The simplest way is to add an alias to your `.gitconfig`:

	```
	[alias]
		svnpush = !sh .git/hooks/pre-svn-dcommit && git stash && git svn dcommit --interactive && git stash pop
	```

1. That isn't very flexible, though, since it'd run for every site. To run it only for WordCamp.org, you can create [a `git-svnpush` script](https://github.com/iandunn/dotfiles/blob/ebdb5d3d7b6d335680cf9e44544cbcbea49cf85e/bin/git-svnpush) in your `$PATH`, with executable permissions.

1. When you're ready to push, call `git svnpush` instead of `git svn dcommit`.
