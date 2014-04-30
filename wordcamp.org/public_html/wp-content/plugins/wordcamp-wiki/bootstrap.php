<?php

/**
 * Plugin Name: WordCamp Wiki
 * Description: Allows Administrators to mark certain pages as being editable by Subscribers.
 * Version:     0.1
 * Author:      WordCamp.org
 */

/*
 * @todo
 *
 * Let users publish new pages if their parent is a wiki page.
 *
 * Automate adding subscribers to the site.
 *   Maybe a widget with a form that users can fill out.
 *   If they're not logged in, ask them to create a WordPress.org account and login.
 *   If they are, give them a button they can click to add to site.
 *   If they're already added, give them instructions on editing the page/
 */

require_once( __DIR__ . '/classes/wordcamp-wiki.php' );
$GLOBALS['WordCamp_Wiki'] = new WordCamp_Wiki();
