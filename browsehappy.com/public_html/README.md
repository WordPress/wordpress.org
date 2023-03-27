Browse Happy
===========

Browse Happy is a site that identifies the latest versions of modern web browsers in an effort to raise the awareness of the options available. Its goal is to promote use of the most up-to-date browsers for their better security, support, features, performance, and implementation of web standards.

This repository is the theme used for the site [Browse Happy](https://browsehappy.com). The site is powered by [WordPress](https://wordpress.org) and as such this is just a standard (if specific and custom) WordPress theme.

The official repository is actually located at <https://meta.svn.wordpress.org/sites/trunk/browsehappy.com/>, with an [associated component](https://meta.trac.wordpress.org/query?status=accepted&status=assigned&status=new&status=reopened&status=reviewing&component=Browse+Happy&col=id&col=summary&col=component&col=owner&col=type&col=status&col=priority&order=priority) on the Meta Trac system for bug reports.

That said, the Git repository is an active peer to the meta.svn repository and all changes (ideally) would be committed here first (and if not, synced back over ASAP).

Contribute
----------

### Development

1. Simply clone this repository into the themes directory (typically `wp-content/themes/`) of a standard test WordPress install. If you need to know how to do that, see [Installing WordPress](https://codex.wordpress.org/Installing_WordPress).
2. Activate the theme.
3. Make and test your recommended changes in a branch and make a pull request to the [Github repository](https://github.com/WordPress/browsehappy).

Alternatively, you can checkout the [meta.svn Subversion repository for the theme](https://meta.svn.wordpress.org/sites/trunk/browsehappy.com/public_html/) and contribute your changes back as patches via the [WordPress.org Meta Trac](https://meta.trac.wordpress.org/) after creating a new ticket for your change/fix (using the component "Browse Happy").

### Translations

Localized translations of the site can be contributed via the GlotPress installation at <https://translate.wordpress.org/projects/meta/browsehappy>. Instructions are provided at the link.

API
----------

The Browse Happy API—which detects a browser via user-agent string and determines and returns some basic information about certain browsers, including if it needs an update or is insecure—is developed as a separate project. This API is used by the WordPress.org software for the admin dashboard widget that alerts a user if they are using an outdated or insecure browser. The code for that project can be found at <https://meta.svn.wordpress.org/sites/trunk/api.wordpress.org/public_html/core/browse-happy/>. Bug reports and patches can be submitted at <https://meta.trac.wordpress.org/> with the component "Browse Happy".
