# Setup Instructions


## General

1. `rm -rf .git/hooks`
1. `ln -s ../path/to/.githooks .git/hooks`
1. If you have multiple Git checkouts (e.g., one for `mu-plugins`, and another for `mu-plugins-private`), then
  you'll need to repeat the above steps for each of them.

Aside: Why not use native Subversion hooks? Because they run on the server, which prevents a developer from choosing to force the push anyway. It would also require work from the Systems team, who doesn't have the time.


## git svn dcommit

git-svn doesn't support hooks natively, but we can use an alias instead.

1. Add the following to your `.gitconfig`:

	```
	[alias]
		svnpush = !sh .git/hooks/pre-svn-dcommit && git svn dcommit --interactive
	```

1. When you're ready to push, call `git svnpush` instead of `git svn dcommit`.
