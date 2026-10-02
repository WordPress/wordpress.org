# WP.org SSO

Agents log in with their WordPress.org account, through login.wordpress.org. Apart from administrators' [break-glass](#break-glass) logins, there's no other way in.

## How it works

- **Logging in:** "Log in with WordPress.org" replaces the password form. The account needs two-factor authentication, and blocked accounts can't log in. Accounts are checked again every hour, so a blocked account loses its session too; if WordPress.org can't be reached, the session goes on until the next check.
- **Users:** every FreeScout user is connected to a WordPress.org account. Administrators add users by WordPress.org username, and the name, email, and avatar come from that account. They're updated at every login, and can't be changed in FreeScout. If the WordPress.org email already belongs to another user or a mailbox, the old email stays and a warning is logged.
- **Existing users:** administrators connect them on their profile, or with `php artisan wporgsso:connect <email> <wporg-username>`.
- **Passwords:** password logins (except break-glass), resets, and invites are closed. Users have no FreeScout password: new users get none, and logging in with WordPress.org clears the one they had. Only break-glass passwords stay.
- **Deleting a mailbox:** instead of your password, you type the mailbox name to confirm, exactly as it's written. Administrators with a break-glass password are asked for both.
- **Administrators and the proxy:** with `WPORG_SSO_REQUIRE_PROXY=true`, administrators are logged out of any request that doesn't come through the proxy, however they logged in, break-glass included. Agents aren't affected. See [the proxy](#the-proxy).

## Break-glass

If login.wordpress.org is down, `WPORG_SSO_PASSWORD_LOGIN=true` lets administrators, and only them, log in with a FreeScout password at `/login?password=1`. `php artisan wporgsso:password <email>` gives them one, which their logins with WordPress.org keep. Every such login is logged.

## The proxy

nginx tells FreeScout which requests are proxied, with the `WPORG_PROXIED_REQUEST` FastCGI param: `1` for the proxy's IP addresses, `0` for everyone else. It's a server variable rather than a header, so clients can't send it themselves. For example:

```nginx
geo $wporg_proxied_request {
	default 0;
	include /path/to/proxy-ips.conf; # One "<address or range> 1;" line each, IPv4 and IPv6.
}

# In the PHP location:
fastcgi_param WPORG_PROXIED_REQUEST $wporg_proxied_request;
```

`geo` matches `$remote_addr`, so behind a load balancer, set it from the client's address with `set_real_ip_from` first. Once nginx passes the param, set `WPORG_SSO_REQUIRE_PROXY=true`. If it doesn't, nobody counts as proxied, so administrators are logged out and an error is logged; set `WPORG_SSO_REQUIRE_PROXY=false` to let them back in.

## Setup

Needs `WPORG_API_SECRET` and `WPORG_SSO_IDP_CERT`; see [configuration](../../README.md#deployment). Until both are set, logins stay as they are, so users can be connected first.
