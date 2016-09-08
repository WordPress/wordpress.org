<?php
// No direct or CLI access.
if ( ! defined( 'ABSPATH' ) || ! ABSPATH || php_sapi_name() != 'fpm' )
	return;

// Redirects for plan.wordcamp.org only.
if ( $_SERVER['HTTP_HOST'] != 'plan.wordcamp.org' )
	return;

add_action( 'init', function() {
	$mapping = array(
        '/' => '/',
		'/wordcamp-name-badge-templates/' => '/planning-details/wordcamp-name-badge-templates/',
		'/planning-details/submitting-payment-requests/' => '/first-steps/budget-and-finances/submitting-payment-requests/',
		'/global-community-sponsorship-for-event-organizers/' => '/planning-details/fundraising/global-community-sponsorship-for-event-organizers/',
		'/global-community-sponsorship-program-for-sponsors/' => '/planning-details/fundraising/global-community-sponsorship-program-for-sponsors/',
		'/first-steps/web-presence/using-the-wordcamp-theme/setting-up-a-local-wordcamp-org-sandbox/' => '/first-steps/web-presence/contributing-to-wordcamp-org/setting-up-a-local-wordcamp-org-sandbox/',
		'/global-community-sponsors-for-your-region/' => '/planning-details/fundraising/global-community-sponsorship-for-event-organizers/',
		'/community-sponsor-acknowledgement-post-latin-america/' => '/planning-details/fundraising/global-community-sponsorship-for-event-organizers/',
		'/community-sponsor-acknowledgement-post-europeafrica/' => '/planning-details/fundraising/global-community-sponsorship-for-event-organizers/',
		'/community-sponsor-acknowledgement-post-asia-pacific/' => '/planning-details/fundraising/global-community-sponsorship-for-event-organizers/',
		'/community-sponsor-acknowledgement-post-canada-only/' => '/planning-details/fundraising/global-community-sponsorship-for-event-organizers/',
		'/first-steps/web-presence/wordcamp-org-feedback/' => '/first-steps/web-presence/wordcamp-org-feedback/',
		'/wordcamp-mentors/' => '/wordcamp-organizer/first-steps/wordcamp-mentors/',
		'/wordcamp-sponsorship/' => '/planning-details/fundraising/wordcamp-sponsorship/',
		'/first-steps/web-presence/using-the-wordcamp-theme/widgets/' => '/first-steps/web-presence/setting-up-your-wordcamp-theme/widgets/',
        '/first-steps/wordcamp-mentoring/' => '/first-steps/wordcamp-mentors/',

		'/video/setting-up-your-video-camera/' => '/video/setting-up-your-video-equipment/',
		'/video/video-post-production/' => '/video/after-the-event-post-production/',
		'/video/uploading-your-video-to-wordpress-tv/' => '/video/after-the-event-post-production/#uploading-your-video-to-wordpress-tv/',
		'/video/foundation-camera-kit/' => '/video/foundation-camera-kit-list/',
		'/video/foundation-camera-kit/ella/' => '/video/foundation-camera-kit-list/ella/',
		'/video/foundation-camera-kit/benny/' => '/video/foundation-camera-kit-list/benny/',
		'/video/foundation-camera-kit/jimmy/' => '/video/foundation-camera-kit-list/jimmy/',
		'/video/foundation-camera-kit/duke/' => '/video/foundation-camera-kit-list/duke/',
		'/video/foundation-camera-kit/miles/' => '/video/foundation-camera-kit-list/miles/',
		'/video/foundation-camera-kit/billie/' => '/video/foundation-camera-kit-list/billie/',
		'/video/foundation-camera-kit/count/' => '/video/foundation-camera-kit-list/count/',
		'/video/foundation-camera-kit/oscar/' => '/video/foundation-camera-kit-list/oscar/',
		'/video/foundation-camera-kit/elvin/' => '/video/foundation-camera-kit-list/elvin/',
		'/video/foundation-camera-kit/dexter/' => '/video/foundation-camera-kit-list/dexter/',
        '/ella-fitzgerald/' => '/video/foundation-camera-kit-list/ella/',

		'/wordcamp-speaker-and-sponsor-expectations/' => '/first-steps/helpful-documents-and-templates/agreement-among-wordcamp-organizers-speakers-sponsors-and-volunteers/',
		'/multi-event-sponsors-acknowledgement-post-for-events-outside-north-america/' => '/planning-details/fundraising/global-community-sponsorship-for-event-organizers/',
		'/community-sponsor-acknowlegement-post-us-only/' => '/planning-details/fundraising/global-community-sponsorship-for-event-organizers/',
		'/multi-event-sponsor-logos/' => '/planning-details/fundraising/global-community-sponsor-logos/',
		'/simple-venue-agreement-template/' => '/first-steps/helpful-documents-and-templates/simple-venue-agreement-template/',
		'/100-gpl-vetting-checklist/' => '/first-steps/helpful-documents-and-templates/100-gpl-vetting-checklist/',
		'/code-of-conduct/' => '/planning-details/code-of-conduct/',
		'/running-the-money-through-wordpress-community-support-pbc/' => '/first-steps/budget-and-finances/running-the-money-through-wpcs/',
		'/agreement-among-wordcamp-organizers-speakers-sponsors-and-volunteers/' => '/first-steps/helpful-documents-and-templates/agreement-among-wordcamp-organizers-speakers-sponsors-and-volunteers/',
		'/speaking-at-a-wordcamp/' => '/planning-details/speakers/speaking-at-a-wordcamp/',
		'/helpful-documents-and-templates/create-wordcamp-badges-with-gravatars/' => '/first-steps/helpful-documents-and-templates/create-wordcamp-badges-with-gravatars/',
		'/helpful-documents-and-templates/' => '/first-steps/helpful-documents-and-templates/',
		'/tips-and-tricks-for-working-on-your-wordcamp-org-site/' => '/first-steps/web-presence/tips-and-tricks-for-working-on-your-wordcamp-org-site/',
		'/using-camptix/' => '/first-steps/web-presence/using-camptix-event-ticketing-plugin/',
		'/first-steps/web-presence/working-with-speakers-sessions-and-sponsors/' => '/first-steps/web-presence/custom-tools-for-building-wordcamp-content/',
		'/first-steps/web-presence/using-the-wordcamp-theme/shortcode-embeds/' => '/first-steps/web-presence/setting-up-your-wordcamp-theme/shortcode-embeds/',
		'/first-steps/web-presence/using-the-wordcamp-theme/' => '/first-steps/web-presence/setting-up-your-wordcamp-theme/',
        '/planning-details/av-release-form/' => '/planning-details/speakers/av-release-form/',

		'/2011/04/22/hello-world/' => '/',
		'/2014/10/29/wordcamp-volunteer-roles/' => '/planning-details/volunteers/',
		'/2014/10/28/wordcamp-organizer-roles/' => '/first-steps/the-organizing-team/',

		'/wordcamp-budget-repo/' => '/',
		'/planners-blog/' => '/',
		'/login/' => '/',
	);

	$path = parse_url( home_url( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH );
	foreach ( $mapping as $source => $destination ) {
		$source = strtolower( '#^' . preg_quote( $source ) . '?$#' );
		if ( preg_match( $source, $path ) ) {
			die( wp_redirect( esc_url_raw( 'https://make.wordpress.org/community/handbook/wordcamp-organizer' . $destination ), 301 ) );
		}
	}

	die( wp_redirect( esc_url_raw( 'https://make.wordpress.org/community/handbook/wordcamp-organizer' . $_SERVER['REQUEST_URI'] ), 301 ) );
});