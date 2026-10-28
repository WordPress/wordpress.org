Browse Happy
===========

Browse Happy is a site that identifies the latest versions of modern web browsers in an effort to raise the awareness of the options available. Its goal is to promote use of the most up-to-date browsers for their better security, support, features, performance, and implementation of web standards.

This repository is the theme used for the site [Browse Happy](https://browsehappy.com). The site is powered by [WordPress](https://wordpress.org) and as such this is just a standard (if specific and custom) WordPress theme.

Contribute
----------

### Development

1. Clone the [Meta Git mirror](https://github.com/WordPress/wordpress.org) and start the Browse Happy environment from its `environments` directory with `npm install && npm run browsehappy:env start`. See the [Environments Guide](https://github.com/WordPress/wordpress.org/blob/trunk/environments/README.md) for prerequisites.
2. Make and test your changes in a branch, then open a pull request against the Meta Git mirror that references a [Meta Trac](https://meta.trac.wordpress.org/newticket?component=Browse+Happy) ticket. A Meta committer commits the patch to SVN, which closes the pull request.

Alternatively, you can check out the [meta.svn Subversion repository for the theme](https://meta.svn.wordpress.org/sites/trunk/browsehappy.com/public_html/) and contribute your changes back as patches via the [WordPress.org Meta Trac](https://meta.trac.wordpress.org/) after creating a new ticket for your change/fix (using the component "Browse Happy").

### Translations

Localized translations of the site can be contributed via the GlotPress installation at <https://translate.wordpress.org/projects/meta/browsehappy>. Instructions are provided at the link.

API
----------

The Browse Happy API—which detects a browser via user-agent string and determines and returns some basic information about certain browsers, including if it needs an update or is insecure—is developed as a separate project. This API is used by the WordPress.org software for the admin dashboard widget that alerts a user if they are using an outdated or insecure browser. The code for that project can be found at <https://meta.svn.wordpress.org/sites/trunk/api.wordpress.org/public_html/core/browse-happy/>. Bug reports and patches can be submitted at <https://meta.trac.wordpress.org/> with the component "Browse Happy".
