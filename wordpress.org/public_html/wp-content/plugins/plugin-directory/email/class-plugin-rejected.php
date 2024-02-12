<?php
namespace WordPressdotorg\Plugin_Directory\Email;

use WordPressdotorg\Plugin_Directory\Tools;

class Plugin_Rejected extends Markdown_Base {
	protected $required_args = [
		'slug',
		'submission_date'
	];

	function subject() {
		return sprintf(
			/* translators: 1: Plugin Name */
			__( '%s has been rejected', 'wporg-plugins' ),
			$this->plugin->post_title
		);
	}

	function markdown() {
		$placeholders = [
			// Should be first, to allow placeholders in the rejection reasons too.
			'###REASON###'          => $this->get_rejection_reason(),
			'###NAME###'            => $this->plugin->post_title,
			'###SLUG###'            => $this->plugin->post_name,
			'###SUBMISSION_DATE###' => $this->args['submission_date'],
		];

		/* translators: Text within `#<strong>` should not be translated. */
		$template = __(
			"Unfortunately your plugin submission for ###NAME### (###SLUG###), submitted on ###SUBMISSION_DATE###, has been rejected from the WordPress Plugin Directory.

###REASON###",
			'wporg-plugins'
		);

		$template = str_replace(
			array_keys( $placeholders ),
			array_values( $placeholders ),
			$template
		);

		return $template;
	}

	public function get_rejection_reason() {
		$reason = $this->args['reason'];
		$method = 'reason_' . str_replace( '-', '_', $reason );

		if ( ! $reason || ! method_exists( $this, $method ) ) {
			$reason = 'other';
			$method = 'reason_other';
		}

		return $this->{$method}();
	}

	public function reason_3_month() {
		return __(
			"Your plugin has been rejected because it has been roughly 90 days without significant progress being made on the review we sent, from the email address on record.

https://developer.wordpress.org/plugins/wordpress-org/plugin-developer-faq/#why-was-my-plugin-rejected-after-three-months

<strong>What to do next</strong>

If you no longer wish to have your plugin reviewed, you can simply delete this message. No harm, no foul.

If you do want to finish your review, please reply to this email and let us know. If you don't remember where you were with the review, please email us the latest version of your code and we'll review that.

<strong>Why this happens</strong>

In order to keep the plugin queue manageable, we reject plugins that are not complete after 3 months (90 days). Even with this policy, we have on average 500 plugins waiting on developers to complete their review at any point in time.

All plugins are reviewed within 7 working days of submission, but we understand that emails are sometimes lost or accidentally filed as spam, and it's totally possible you never got our previous emails. Another common cause for this is that you replied from a different email address than you submitted it, causing the email chain to be broken.

Thankfully, a rejection does not mean we can't go forward. If you want to continue, please just reply and let us know.",
			'wporg-plugins'
		);
	}

	public function reason_core_supports() {
		return __(
			"Your plugin has been rejected because we do not feel it is adding any new functionality to WordPress.

Any time your plugin replicates functionality found in WordPress (i.e. the uploader, jquery) is frowned upon, as it presents a possible security risk. The features in WordPress have been tested by many more people than use most plugins, so the built in tools are less likely to have issues.

<strong>What to do next</strong>

Please read this email in it's entirety. We know it's hurtful to be told we're not hosting your code, and there is always the possibility that we've made a mistake.

If you feel that we have, please reply to this email with your plugin zip attached, explain why, and we will re-review.

We ask you <strong>not</strong> resubmit the plugin, and reply to this email instead.",
			'wporg-plugins'
		);
	}

	public function reason_duplicate_copy() {
		return __(
			"Your plugin has been rejected because it is a duplicate of another plugin, already hosted on WordPress.org

Despite the fact that all plugins in our directory are licensed under the GPL or compatible licenses, we do not allow direct copies of other plugins to be re-listed under somebody else's name. \"Forking\" is acceptable only when the resulting fork is of a substantial nature, or when the original plugin is no longer updated or supported. When this is not the case, the plugin is rejected.

<strong>What to do next</strong>

We know it can be hurtful to be told we will not host your code, and we ask you consider the following options:

- contribute back to the original plugin and improve it
- create an add-on to the existing plugin
- contribute a translation via the polyglots team - https://make.wordpress.org/polyglots/handbook/rosetta/theme-plugin-directories/
- If you feel this rejection was in error, please <strong>reply to this email</strong> with a copy of your code attached and let us know. We will re-review your code and proceed from there.

Remember, <strong>do not</strong> resubmit the plugin. If you resubmit the plugin without replying to this email, your account will be suspended.",
			'wporg-plugins'
		);
	}

	public function reason_library_or_framework() {
		return __(
			"Your plugin has been rejected because we no longer accepting frameworks, boilerplates, and libraries as stand-alone plugins.

<strong>What to do next</strong>

If you feel this was in error, please reply to this email with your plugin attached as a zip and explain why you feel your plugin is not a library or boilerplate.

Before you do so, we ask you take the time to read this email in it's entirety. We know it's hurtful to be told we will not host your code, and this particular subject can be contentious, especially because we used to allow library code.

We have chosen not to delete all the existing libraries so we don't break people's sites. However due to a number of reasons (which we will explain below) we do not accepting any <strong>new</strong> ones.

This has nothing to do with the quality or purpose of your code. Most of us on the team are fans of libraries and frameworks.

<strong>Why these kinds of plugins are not accepted</strong>

To explain the terminology here:

- <strong>Framework/Boilerplate:</strong> a template from which more code can be built
- <strong>Library:</strong> requires other plugins or themes to edit themselves in order to be used

We require that plugins be useful in and of themselves (even if only being a portal to an external service). This means that a plugin should either be installed and be fully functional, or it should have some administration panel.

https://make.wordpress.org/plugins/2016/03/01/please-do-not-submit-frameworks/

When a plugin requires either the plugin itself to be edited to work, or can only be used by writing code elsewhere, it ceases to have as much a benefit to end users and is more of a developer tool.

While there are many benefits to frameworks and libraries, WordPress lacks any plugin dependency support at this time, which causes a host of issues.

The parade of likely support issues include (but are not limited to):

- not recognizing the need for the library or and thinking they've been hacked
- not properly forking the boilerplate and editing it in place, resulting in updates erasing code
- not recognizing the need for the library plugin, and thus deleting it (causing others to break)
- updating the library plugin separately from the dependent plugins, leading to breakage
- updating a dependent plugin without updating the library, leading to breakage
- different plugins requiring different versions of a library plugin without proper if-exists checks
- We feel that libraries should be packaged with each plugin (hopefully in a way that doesn't conflict with other plugins using the libraries). At least until core supports plugin dependencies. Frameworks, in and of themselves, have no place in our directory as they are non-functional templates.

If you've gotten all the way down here and still think we should be hosting your code, we ask you not resubmit the plugin, and reply to this email instead.",
			'wporg-plugins'
		);
	}

	public function reason_generic() {
		return __(
			"At this time, we are not accepting plugins of this nature.

While the directory is open for all secure, GPLv2 (or later) compatible plugins, we reserve the right to reject any plugin on any grounds we feel are reasonable, whether or not they are explicitly noted in the guidelines.

<strong>What to do next</strong>

We know that being told we won't host your code is hurtful, and we ask you please read this email in full.

If, at the end, you feel this decision was in error, please reply to this email with your plugin attached as a zip and explain why you feel your plugin should be accepted.

<strong>Why these kinds of plugins are not accepted</strong>

Plugins that reproduce features that are already included in WordPress, without any perceivable additions, such as (but not limited to) duplication of existing short codes ( [embed] and [gallery] ), widgets (rss feed display), or functionality (adding users), will not be accepted.

We do not permit plugins that we feel to be unethical, such as black or grey hat SEO (including plugins that auto post content). Any plugin related to claims like 'Our plugin will help you earn thousands of dollars' will be rejected, as that behavior is scummy and unwelcome.

We also do not accept 'translation' plugins, or copies of plugins that are in another language, as that need is best served by communicating with the original plugin. Please reach out to them and provide a translation properly.

Plugins with obfuscated (i.e. hidden or encrypted) code will never be accepted.

If you've gotten all the way down here and still think we should be hosting your code, we ask you not resubmit the plugin, and reply to this email instead.",
			'wporg-plugins'
		);
	}

	public function reason_duplicate() {
		return __(
			"We have rejected this submission because we do not accept submissions that are new or renamed versions of existing plugins.

<strong>What to do next</strong>

We understand the desire to rename or rebrand a plugin however there are serious logistical issues involved.

1. Read this entire email. There is a lot of information, however it is all important.
2. If, after reading this email, you feel your plugin rename should be allowed, please reply to this email and explain your situation.

Under <strong>no</strong> circumstances should you resubmit the plugin with the new name. If your situation is approved, we will give you explicit directions on how to proceed. Please do not try to jump ahead. We need you to follow the directions carefully.

<strong>If you DID NOT mean to rename…</strong>

There is always a possibility you weren't trying to rename, and instead wanted to update your existing plugin. If that's the case, then you can ignore the rest of this email and instead just go ahead and update your plugin using Subversion (AKA SVN)

https://developer.wordpress.org/plugins/wordpress-org/how-to-use-subversion/

We don't have the ability to do this for you, so you will have to re-learn SVN if you've forgotten

<strong>Why Renames Are Problematic</strong>

It's important you understand that we do not have the technical ability to rename plugin URLs once they've been approved. That's why, since 2017, we have made every reasonable effort to inform developers as to their plugin permalink before the review.

This means the only way to enact something like a rename would be to close your plugin and have you submit a new one. There are significant costs in doing this:

- Visitors will see your plugin is closed \"by author request\" and cannot be redirected to the new plugin.
- All existing links will point to the closed plugin. You will not have access to update ones outside your control, harming your SEO.
- Existing users cannot be automatically updated to the new plugin - the more users you have, the worse this will be.
- Users will leave angry 1-star reviews, feeling they've been abused/abandoned, and those reviews will not be removed.
- In addition, by asking for a re-submission of an existing plugin your account would be flagged in our system and all future requests will be refused. This would be your one and only chance to rename something.

Due to those reasons, we recommend you instead change the plugin Display Name. The majority of users do not care what your permalink is, if they even notice. We do not require your plugin display name match your plugin permalink, so it would be perfectly acceptable to have the permalink 'jumprabbit-apis' but the display name \"Everyone Loves APIs.\"

<strong>Accepted Reasons for renames</strong>

Of course there are some perfectly valid reasons to ask for a rename, they include but are not limited to:

- Trademark owners have come to light and demanded you change the permalink
- There is an egregious misspelling in your permalink that fundamentally changes the nature of the plugin
- A vulgarity exists in your plugin permalink
- Previously unknown legal issues require the change
- If your plugin does not meet any of those reasons, then you will have to explain why you should be an exception to policy.

If, after reading this, you've decided you don't want to rename the permalink, you don't have to reply to this email. It's fine to just go back to your existing plugin and update it. On the other hand, if you do think your plugin should be renmamed, please reply to this email and explain your situation.

Again, <strong>do not</strong> resubmit this plugin. Doing so will result in your account being suspended until you do talk to us about this.",
			'wporg-plugins'
		);
	}

	public function reason_wp_cli() {
		return __(
			"Your plugin has been rejected because we do not currently accept plugins that are only wp-cli add-ons.

<strong>What to do next</strong>

We understand it is hurtful to be told we will not host your code here. This decision has nothing to do with the quality or purpose of your code, and is solely related to the fact that it is, in fact, a wp-cli add on.

We recommend you include this code in your existing plugin instead of as a separate add-on.

If you feel this was in error, please reply to this email with your plugin attached as a zip and explain why you feel your plugin should be hosted here.

Before you do so, we ask you take the time to read this email in it's entirety.

<strong>Why these kinds of plugins are not accepted</strong>

Plugins are required to have some interaction with the blog in a way that is either automatic (eg. activating the plugin allows it to run) or interactive (eg. a settings panel).

Command line plugins do not meet that criteria.

If you've gotten all the way down here and still think we should be hosting your code, we ask you <strong>not</strong> resubmit the plugin, and reply to this email instead.",
			'wporg-plugins'
		);
	}

	public function reason_storefront() {
		return __(
			"Your plugin has been rejected because we do not currently accept storefront plugins.

<strong>What to do next</strong>

We understand it is hurtful to be told we will not host your code here. This decision has nothing to do with the quality or purpose of your code, and is solely related to the fact that it is, in fact, a storefront.

We recommend you host this on your own service, and make use of self-directed updates for your ongoing support and maintenance.

If you feel this was in error, please reply to this email with your plugin attached as a zip and explain why you feel your plugin should be hosted here.

Before you do so, we ask you take the time to read this email in it's entirety.

<strong>Why these kinds of plugins are not accepted</strong>

Plugins that serve to install plugins and themes from places other than WordPress.org are not permitted due to security and user confusion. Historically they have led to users not understanding from whom they acquired a plugin, and where they should go for help.

In addition, if there's a bad update pushed, WordPress is perceived as responsible for any negative outcome.

If you've gotten all the way down here and still think we should be hosting your code, we ask you not resubmit the plugin, and reply to this email instead.",
			'wporg-plugins'
		);
	}

	public function reason_not_owner() {
		return __(
			"We have rejected your plugin submission because this does not appear to be your own, original, work.

The WordPress plugin submission form is meant for you to host your own, original, plugins on WordPress.org, not to upload plugins to your own blog. Instead, it looks like you tried to upload an existing plugin as if this was your website.

<strong>What to do next</strong>

Don't panic! It's okay to make this kind of mistake. However, we cannot fix it for you. You have to upload the code to your site, on your own.

The good news is we have some documentation to help you:

- https://wordpress.org/support/article/managing-plugins/
- http://www.wpbeginner.com/beginners-guide/step-by-step-guide-to-install-a-wordpress-plugin-for-beginners/

Please <strong>do not</strong> resubmit the plugin, even if you believe we are incorrect.

Instead, reply to this email and explain the situation so we can properly direct you forward.

If you resubmit this plugin without replying and communicating with us first, your account will be suspended.",
			'wporg-plugins'
		);
	}

	public function reason_script_insertion() {
		return __(
			"Your plugin has been rejected because we are not accepting plugins of this nature in most cases.

<strong>What to do next</strong>

We understand it is hurtful to be told we will not host your code here and ask you please read this email carefully.

You're welcome to submit a different plugin, but we feel that this is a poor choice to submit. Also there are a handful of plugins out there that already handle this, it's preferred you use those instead.

If you have any questions, please reply to this email. Otherwise it's okay to just move on to something else.

If you feel we've made this decision in error, please reply with a copy of you code attached and explain why you think that is the case.

<strong>Why these kinds of plugins are not accepted</strong>

Script insertion plugins are amazing and powerful. They're also incredibly dangerous and require a high level understanding of sanitization, security, and usage. These are skills that take years to master. WordPress has a highly complicated tool to insert CSS, and it opts not to handle javascript because of the dangers. PHP is even more complicated.

Besides the sanitization issues, allowing arbitrary script insertion leads to users adding scripts that are dangerous without knowing. Users will paste in just anything and your plugin can become the unwitting vector for hacks.

This is why WordPress itself allows you to lock people out of being able to edit theme and plugin files directly (via DEFINES that are used by many managed hosts), but also has post-processing checks that verify the site will still function after any changes.

Because of those reasons, unless a submission demonstrates a solid understanding of the security and usage issues out of the gate, we reject them.

If you've gotten all the way down here and still think we should be hosting your code, we ask you <strong>not</strong> resubmit the plugin, and reply to this email instead.",
			'wporg-plugins'
		);
	}

	public function reason_demo() {
		return __(
			"We have rejected this plugin because it appears to be a demo or test version of a plugin.

<strong>What to do next</strong>

We know that being told we won't host your code is hurtful, and we ask you please read this email in full.

If, at the end, you feel this decision was in error, please reply to this email with your plugin attached as a zip and explain why you feel your plugin should be accepted.

<strong>Why these kinds of plugins are not accepted</strong>

We require that all plugins are fully functional and ready to be used when submitted. When someone submits a plugin with the default readme and no customized or useful code, we assume they have accidentally uploaded a boilerplate version of their plugin, or are testing how the upload system works.

If it was the former, please double check the zip you're trying to upload for review and resubmit.

If it was the latter, we ask you please not use our system for testing. It's harmful to the volunteers to make them sort out real plugins ready for reviews.

If you've gotten all the way down here and still think we should be hosting your code, we ask you <strong>not</strong> resubmit the plugin, and reply to this email instead.",
			'wporg-plugins'
		);
	}

	public function reason_translation() {
		return __(
			"We have rejected this submission because we do not accept submissions that are new or translated versions of existing plugins.

<strong>What to do next</strong>

We understand the desire to make a plugin work in multiple languages, you should not be submitting this as a new plugin.

The correct way to handle this would be to make your plugin translatable, so the multiple languages are handled via our system.

Some helpful links for you:

- https://translate.wordpress.org/projects/meta/plugins/
- https://make.wordpress.org/polyglots/handbook/rosetta/theme-plugin-directories/
- https://make.wordpress.org/polyglots/handbook/frequently-asked-questions/#as-a-plugintheme-author-how-can-my-translators-get-validation-rights

If, after reading this, you feel we have made this judgement in error, please <strong>reply</strong> to this email and explain why.",
			'wporg-plugins'
		);
	}

	public function reason_banned() {
		return __(
			"At this time, we are not accepting this plugin.

While the directory is open for all secure, GPLv2 (or later) compatible plugins, we reserve the right to reject any plugin on any grounds we feel are reasonable, whether or not they are explicitly noted in the guidelines.

<strong>What to do next</strong>

We know that being told we won't host your code is hurtful, and we ask you please read this email in full.

If, at the end, you feel this decision was in error, please reply to this email with your plugin attached as a zip and explain why you feel your plugin should be accepted.

<strong>Why this plugin was not accepted</strong>

This Plugin seems to be associated in some way with a previously banned author.

The Plugin Directory is open to everyone who can and will comply with guidelines. When people demonstrate they cannot, or will not, they are no longer welcome as their actions are detrimental to the community and the volunteers who maintain. This extends to people hired to act in their name (i.e. employees, consultants, etc).

Banned authors are no longer permitted to host their code on WordPress.org, they are certainly welcome to host their code via other venues and services. We strongly recommend them to invest in software to manage self-updates. There are multiple options available to them outside of our hosting.

<strong>Any attempts to circumvent this suspension will be seen as intentionally hostile, and result in further restrictions. Do not make a new account, do not attempt to get around this ban, do not try to hide your identity and resubmit.</strong>

If you've gotten all the way down here and still think we should be hosting your code, we ask you not resubmit the plugin, and reply to this email instead.",
			'wporg-plugins'
		);
	}

	public function reason_author_request() {
		return __(
			"We have received a request from the author of this plugin not to continue with the submission.

Your submission has been successfully rejected.

If, at the end, you feel this decision was in error, please <strong>reply</strong> to this email with your plugin attached as a zip and explain what happened.",
			'wporg-plugins'
		);
	}

	public function reason_security() {
		return __(
			"Your plugin has been rejected because we have serious security concerns regarding this plugin.

<strong>What to do next</strong>

We understand it is hurtful to be told we will not host your code here and ask you please read this email carefully.

You're welcome to submit a different plugin, but we feel that this is a poor choice to submit.

If you have any questions, please reply to this email. Otherwise it's okay to just move on to something else.

If you feel we've made this decision in error, please reply with a copy of you code attached and explain why you think that is the case.

<strong>Why these kinds of plugins are not accepted</strong>

Plugins that create, connect users, expose services, allow editing from external platforms and/or enable other types of administrative actions are amazing and powerful. They're also incredibly dangerous and require a high level understanding of sanitization, security, and usage. These are skills that take years to master.

To safeguard the security of the WordPress ecosystem, it is best to anticipate and prevent possible attack vectors that could compromise users.

Because of those reasons, unless a submission demonstrates a solid understanding of the security and usage issues out of the gate, we reject them.

If you've gotten all the way down here and still think we should be hosting your code, we ask you <strong>not</strong> resubmit the plugin, and reply to this email instead.",
			'wporg-plugins'
		);
	}

	public function reason_other() {
		return __(
			"Plugins are rejected after three months (90 days) when there has not been significant progress made on the review. If this is not the case for your plugin, you will receive a followup email explaining the reason for this decision within the next 24 hours. Please wait for that email before requesting further details.

If you believe this to be in error, please email us with your plugin attached as a zip and explain why you feel your plugin should not have been rejected.

If you're still working on your code, don't panic. You can reply to the original review (or even this email) with your updated code for as long as needed. Even years. All we ask is you do not resubmit your plugin until asked to do so.",
			'wporg-plugins'
		);
	}
}
