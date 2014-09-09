/**
 * Add classes that allow animation once div is in view.
 * Uses jQuery Waypoints - v2.0.5
 */
jQuery(document).ready(function ($) {

    $("#features-animation-notification").waypoint(function () {
        $(this).addClass("animated bounceIn")
    }, {
        offset: "50%"
    });
    $("#features-animation-jetpack").waypoint(function () {
        $(this).addClass("animated fadeInDown")
    }, {
        offset: "50%"
    });
    $("#usecase3").waypoint(function () {
        $(this).addClass("slideRight")
    }, {
        offset: "50%"
    });
    $(".feature img").waypoint(function () {
        $(this).addClass("pulse")
    }, {
        offset: "50%"
    });

});