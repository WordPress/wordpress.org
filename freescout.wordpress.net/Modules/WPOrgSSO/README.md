# WP.org SSO

Agents log in with their WordPress.org account, through login.wordpress.org. There's no other way in.

## How it works

- **Logging in:** "Log in with WordPress.org" replaces the password form. The account needs two-factor authentication, and blocked accounts can't log in. Accounts are checked again every hour, so a blocked account loses its session too.
- **Users:** every FreeScout user is connected to a WordPress.org account. Administrators add users by WordPress.org username, and the name, email, and avatar come from that account. They're updated at every login, and can't be changed in FreeScout.
- **Existing users:** administrators connect them on their profile, or with `php artisan wporgsso:connect <email> <wporg-username>`.
- **Passwords:** password logins, resets, and invites are closed.

## Break-glass

If login.wordpress.org is down, `WPORG_SSO_PASSWORD_LOGIN=true` lets administrators, and only them, log in with a FreeScout password at `/login?password=1`. `php artisan wporgsso:password <email>` gives them one. Every such login is logged.

## Setup

Needs `WPORG_API_SECRET` and `WPORG_SSO_IDP_CERT`; see [configuration](../../README.md#deployment). Until both are set, logins stay as they are, so users can be connected first.
