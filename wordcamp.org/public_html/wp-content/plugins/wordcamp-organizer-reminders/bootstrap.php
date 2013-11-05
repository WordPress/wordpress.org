<?php

/*
 * Plugin Name: WordCamp Organizer Reminders
 * Description: Automatically e-mail WordCamp organizers with various reminders at specified intervals.
 * Version:     0.1
 * Author:      Ian Dunn 
 */

require_once( __DIR__ . '/wcor-mailer.php' );
$GLOBALS['WCOR_Mailer'] = new WCOR_Mailer();
register_activation_hook(   __FILE__, array( $GLOBALS['WCOR_Mailer'], 'activate' ) );
register_deactivation_hook( __FILE__, array( $GLOBALS['WCOR_Mailer'], 'deactivate' ) );

require_once( __DIR__ . '/wcor-reminder.php' );
$GLOBALS['WCOR_Reminder'] = new WCOR_Reminder();