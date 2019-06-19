<?php

/**
 * Template Name: Day of Event
 *
 * Shows attendees the content that is most relevant during the event (directions to the venue, schedule, etc).
 */

/*
 * todo
 *
 * add important stuff like dates/times, location
 *
 * determine a good way to make this appear automatically before the event begins, and go away after it ends
 * 	need to make sure organizers will be aware that that will happen, though, and maybe give a way to opt-out
 * maybe it should be an explicit/intentional action on the organizer's part? but then how to make sure that
 * organizers know it's available beyond just handbook documentation? don't want to spend all this time building it
 * and then nobody uses it.
 *
 * integrate w/ mu-plugins/blocks to share any common components
 *
 * use static header/footer and styles, instead of the current site?
 * 	see https://github.com/wceu/wordcamp-pwa-page/issues/6#issuecomment-499562295 and replies,
 * 	also https://make.wordpress.org/community/2019/04/30/wordcamp-pwa-an-update/#comment-26927
 *
 * include sidebar?
 *
 * what about default content when js fails to load? should show the full schedule instead of live schedule? or too much hassle?
 *
 * could show last 3 posts from php. do those even need to be live-updated during the event? how often are new posts published _during_ the event?
 */

namespace WordCamp\Theme_Templates;

defined( 'WPINC' ) || die();

get_header();

?>

<main id="day-of-event">
	<?php echo _e( 'Loading...', 'wordcamporg' ); ?>
</main>

<?php

get_footer();
