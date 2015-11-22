<?php
gp_title( __('Help &lt; GlotPress') );
gp_tmpl_header();
?>
<h2>Getting Started with translate.wordpress.org</h2>
<div class="text">
<?php
echo wptexturize(<<<HTML
<p><a href="http://glotpress.org/">GlotPress</a> is the open-source engine that powers the translation of WordPress, BuddyPress, and the WordPress apps for Android and BlackBerry. It is a collaborative tool, meant to replace the sometimes tedious process of translating .pot files with other tools.</p>

<p>Here's a short introduction on how to use GlotPress for the best and speediest results.</p>

<h3>Organization</h3>

<p>GlotPress organizes translations in projects and sub projects, so that you could have, say, the "WordPress" project and a sub-project for every version.</p>

<p>Every project uses an underlying strings file, which is no more than the file that stores the collected strings from the source code for that particular project. This file only contains strings in english. When users translate strings, the corresponding final files can then be generated and exported (formats vary according to platform) and are the ones actually used by the corresponding applications' (i.e. PHP application, Android or BlackBerry) localisation functions to dynamically show content in other languages.</p>

<h3>Users</h3>

<p>GlotPress users have one of three profiles: guest, contributor or validator. Guests can see the projects and their translations, while contributors can suggest translations. Validators can do a bit more, but their role consists mostly of approving or discarding suggestions. If you login to the translation platform with your WordPress.org user credentials, you are automatically a contributor (you can <a href="http://wordpress.org/support/register.php">register a username here</a>, if you don't already have one).</p>

<h3>Getting Started</h3>

<p>To contribute, start by logging in to GlotPress. Choose the project (and sub-project) you will be working on, and after that the language to which you will be translating (called a "translation set", in GlotPress).</p>

<p>You will see a list of strings and their translations. Across the top of that list you will see links to the filtering and sorting functions which will help you narrow down the strings you want to work on. (users with a validator profile will see additional options, more on that further down)</p>

<p>Strings have "statuses": they can be untranslated, suggested, approved (or current) and fuzzy. Each of these states can have a "warning" flag, meaning that there's something potentially wrong with the translation (missing or unmatched HTML tags are an example of a situation where a warning is triggered)</p>

<h3>Translating</h3>

<p>You can now start translating strings, simply by double-clicking on them (or clicking "Details"). The string's line will expand and you'll be presented with a text box where the translation can be written, and also with some more information to help you understand that particular string's context, such as the source code file where it is used, its status and priority. Type your suggestion for the translation and click the "Suggest new translation" button. That's it! You have just contributed your first translation. Once your suggestion is sent, a box will open for the next string, and so on.</p>

<p>A cool feature, next to the "Suggest new translation" button, is the "Translation from Google" link. Clicking it will query Google's automatic translation API and place its suggestion on the box. Make sure to check it for correctness as Google Translate isn't always 100% accurate. Finally, the "Copy from original" link will do just that, in case you find it easier to just write over the original string. In both cases, don't forget to click the "Suggest new translation" button.</p>

<p>Suggest as many or as little strings as you want. Be aware that the same string can have any number of different suggestions, from different users. It will be up to the "validators" to decide which one fits best.</p>

<h3>Validating</h3>

<p>The translation platform is open for any user to suggest a new translation. When they do, that leaves those strings with a status of "suggested". In order to transform them into "approved" strings (which are the only ones that are deployed), a validator needs to accept (or reject) those suggestions. Validators will see a "Bulk" link on the top left-hand corner of the screen which will allow them to select several strings at once and approve them, reject them or even bulk query Google Translate for suggestions. Strings suggested by Google Translate will have a status of "fuzzy", meaning that they'll need to be explicitly corrected (if need be) and approved, before they are "current".</p>

<p>In addition to these permissions, a validator can also:
<ul>
	<li> see only the "waiting" suggestions (suggested but not approved)</li>
	<li> see only the translations that have generated warnings</li>
	<li> see only the "fuzzy" translations (i.e. generated in bulk by Google Translate)</li>
	<li> upload external files</li>
	<li> discard warnings</li>
</ul>
Keep in mind that a string translated by a validator is automatically approved (but will still generate all applicable warnings)</p>

<h3>Importing external files</h3>

<p>There may be the case where a validator needs to import translations from an external file (current supported formats are .po, .android and .rrc). When the file is imported, only untranslated strings will be written. Also, if the imported file contains original strings not present in GlotPress' list, those strings will be ignored.</p>

<h3>Requesting access</h3>

<p>There is no technical limit on how many users can be validators, however, translation communities should only have a couple of validators so that we can keep track of who to talk to should the need arise. We suggest that you work it out among each other who the two or three should be who can validate, and have all others who want to collaborate organized on the <a href="http://wppolyglots.wordpress.com">wppolyglots p2</a>.</p>
HTML
);
?>
</div>
<? gp_tmpl_footer(); ?>
