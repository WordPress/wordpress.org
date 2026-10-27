<?php
/**
 * The replies the Plugin Review panel copies for flags.
 *
 * @package WordPressdotorg\FreeScout\WPOrgPluginReview
 */

declare( strict_types = 1 );

namespace Modules\WPOrgPluginReview\Services;

/**
 * What the team replies to an author whose review has a flag, as the team writes it.
 */
final class FlagReplies {

	/**
	 * Replies, as HTML, by flag.
	 *
	 * @var string[]
	 */
	public const REPLIES = array(
		'UPD' => 'Hi,<br>
<br>
To continue with the review, we need you to update your plugin code.<br>
<br>
Please <strong>upload the updated version directly to the plugin submission page</strong>, making sure you are logged in with the same account as the current plugin owner:<br>
<br>
https://wordpress.org/plugins/developers/add/<br>
<br>
Additionally, if you haven’t already done so, please review and address all the points mentioned in our previous review and check all the steps to follow before sending us a new update.<br>
<br>
Once the update is complete, reply to this email to let us know. If there is anything specific we should be aware of, feel free to mention it. There’s no need to list the changes, as we will review the entire plugin again.<br>
<br>
Regards.',
		'TRM' => 'Hi, thanks for the changes.<br>
<br>
However, you will need to <strong>modify the name</strong> you have chosen for your plugin.<br>
<br>
The chosen name is either <strong>too generic</strong>, <strong>similar to the names of other plugins</strong> in the directory or uses a <strong>trademark/project name in a way that it can be confusing</strong>. <br>
<br>
This includes using <strong>similar naming patterns</strong>. Names that are close to each other could cause confusion for users and prevent your plugin from standing out.<br>
<br>
To avoid any kind of confusion, please ensure that your plugin name is unique, clearly distinguishable and prevent confusion with other plugins, projects, trademarks, etc. This expectation is applied uniformly, regardless of the names of other existing plugins. Similar cases are regularly reviewed to ensure consistency.<br>
<br>
For further information, please <strong>refer to our previous email</strong>. As you will see, <strong>it explains almost everything</strong>. Yes, we know, it is long. It contains all the information and experience gathered from the <strong>hundreds</strong> of reviews that this team performs each week, and your case is most probably mentioned.<br>
<br>
If you would like some extra help, you can make use of the <strong>"Plugin namer"</strong> included in <a href="https://wordpress.org/plugins/plugin-check/">Plugin Check</a>, this tool uses AI to help you understanding common issues in your chosen plugin name.<br>
<br>
Regards.',
		'OWN' => 'Hi, thanks for the changes.<br>
<br>
Before we can continue with the review, <strong>we still need you to address the ownership clarification mentioned in our previous email</strong>.<br>
<br>
Our message outlines <strong>several acceptable ways to demonstrate or clarify ownership</strong>. Please follow <strong>one</strong> of those methods.<br>
<br>
Please note:
<ul>
<li>Do not resubmit this plugin using a different account. If needed, ask us to change the owner of this submission instead.</li>
<li>Simply stating that you are the owner is not sufficient proof. Anyone can make such a claim; we require verification using one of the methods previously described.</li>
</ul>
<br>
Once this has been resolved, we can proceed with the review. Please also make sure you have addressed any remaining issues, if applicable, as this will help avoid unnecessary delays and additional effort for both of us.<br>
<br>
Regards.',
	);
}
