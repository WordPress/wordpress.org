# Common Shared Library

This directory holds shared configurations, libraries, and utilities that are loaded across various WordPress.org subdomains, network sites, and APIs.

---

## Directory Overview

The code is organized under `includes/`:

### 1. Unified Single Sign-On (`includes/wporg-sso/`)
This is the client and server integration code enabling users to log in once and remain authenticated across WordPress.org, forums, translations, and meta blogs.
- `class-wporg-sso.php`: The primary SSO authentication class containing the login flow, validation logic, cookie management, and user data mapping.
- `wp-plugin.php`: Client plugin that hooks into WordPress user lifecycle hooks (login, logout, authenticate) to synchronize session status with the SSO server.
- `bb-plugin.php`: Client plugin that hooks into bbPress configurations for user profile/session management.

### 2. Slack Integration (`includes/slack/`)
Provides unified functions to alert Slack channels of repository events.
- `send.php` & `helpers.php`: High-level messaging client functions executing Slack webhook payloads.
- `announce/` & `trac/` & `props/`: Specific sub-modules to compile commit messages, ticket changes, and contributor credit alerts format.
- `user.php`: Defines the `Dotorg\Slack\User` interface representing users in notifications.

### 3. User Profiles (`includes/profiles/`)
- `profiles.php`: Shared profile logger interface. Connects meta site user actions to the profiles activity timeline (profiles.wordpress.org).

### 4. Tests (`includes/tests/`)
- Contains global base test cases and testing frameworks.
