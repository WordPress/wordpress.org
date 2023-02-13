# Slack #props Handler

Automatically adds an activity item to a user profile when they give or receive props in Slack.


## Automated Testing

Run the PHPUnit tests, see the root `README.md` for details.


## Manual Testing / Development

### Setup

1. Setup a private testing channel, like `#{yourname}-testing`
	Don't use a generic name, since we may want that in the future. (e.g., `#testing` would be used by the Test Team).
1. Add the `propsbot-sandbox` app to our Slack workspace
	Visit https://api.slack.com/apps/A03AM1FQ7N2 and click `Install to Workspace`
1. Add the `propsbot-sandbox` app to your private test channel
	View channel > Integrations > Add an app
1. Install ngrok, to temporarily proxy part of your sandbox
	Follow the instructions at https://ngrok.com/ to create an account.
	Download the tarball and extract it to `~/bin/`.
1. Configure ngrok
	Copy/paste the following to `~/.ngrok2/ngrok.yml`, and then edit it to include your ngrok account auth token and a random username/password.

	```
	# See https://ngrok.com/docs/ngrok-agent/config
	authtoken: {your ngrok account auth token}

	tunnels:
	  api:
	    # Use basic auth to prevent hackers from accessing sandbox. HelpScout doesn't support this, but Slack does.
	    # To use, include it in the webhook URL, like: https://{random username}:{random password}@rand-ip-hostname.ngrok.io/
	    auth: "{random username}:{random password}"
	    proto: http
	    addr: 443
	    # hostname - leave this disabled so it'll generate random one each time, for security.
	    host_header: api.wordpress.org
	    bind_tls: true
	```
1. `ngrok start api`
	`api` corresponds to the tunnel name in the config file.
	Note the ngrok.io URL that it's forwarding to your sandbox. It won't contain the username/password, you'll need to add that manually when you use it.
	Every time you start ngrok it will generate a new random subdomain.
1. You should now be able to make requests like `curl {ngrok url}/events/1.0/?location=Seattle&number=1` from your local machine.
	Make sure you include the username/password in the URL.
1. Edit the sandbox app to point it to the ngrok URL.
	https://api.slack.com/apps/A03AM1FQ7N2/event-subscriptions
	https://{username}:{password}@{ngrok URL}/dotorg/slack/props.php
	You'll need to update this every time restart ngrok, because the hostname changes (which is good for security).

### Testing

After running the setup above, you should now be able to test by creating a message in your test channel that mentions someone. You can add `error_log()` statements to `props/lib.php` to get info on the request, etc. Once you identify the problem, it's usually faster to setup a PHPUnit test than to continue manually testing. You should do that anyway to avoid regressions.

You'll probably need to use real Slack usernames in your test messages, but you should make sure that their w.org user accounts aren't impacted. To do that you can modify modifying the data in `add_activity_to_profile()` to hardcode a w.org test account ID. Another way is to use the `wporg_is_valid_activity_request` hook on the profiles.wordpress.org side.

You can view, delete, etc activity entries with `wp bp activity`:

* wp bp activity list --component=slack --count=3 --url=profiles.wordpress.org
* wp bp activity list --component=slack --user-id={user id} --count=3 --url=profiles.wordpress.org
* wp bp activity delete {activity id} --url=profiles.wordpress.org

### After testing

1. `control-c` to stop ngrok. Don't leave it running all the time, since that'd increase the attack surface unnecessarily.
1. Remove `propsbot-sandbox` from our workspace, so it doesn't get used accidentally or confuse anyone.
1. Delete `#{yourname}-testing` when no longer needed.
