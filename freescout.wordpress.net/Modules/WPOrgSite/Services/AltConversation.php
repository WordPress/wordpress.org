<?php
/**
 * The alternate conversation design.
 *
 * @package WordPressdotorg\FreeScout\WPOrgSite
 */

declare( strict_types = 1 );

namespace Modules\WPOrgSite\Services;

/**
 * Agents who choose so in their profile get conversations laid out otherwise: a pre-header with the subject, tags,
 * and actions, always in view; the number, assignee, and status at the top of the sidebar, which scrolls on its own;
 * the threads oldest first, scrolled to the newest; and Reply and Note buttons at the bottom, where the forms open.
 *
 * The page's body gets a class; alt-conversation.js lays the page out, and site.css styles it, only once it could. The
 * choice is a UserChoice.
 */
final class AltConversation {

	/**
	 * Where an agent's choice is kept, followed by their user ID.
	 *
	 * @var string
	 */
	public const OPTION = 'wporgsite_alt_conversation_';

	/**
	 * The profile's field.
	 *
	 * @var string
	 */
	public const FIELD = 'wporgsite_alt_conversation';

	/**
	 * The body's class, which tells alt-conversation.js to lay the conversation out.
	 *
	 * @var string
	 */
	public const BODY_CLASS = 'wporgsite-alt-conversation';
}
