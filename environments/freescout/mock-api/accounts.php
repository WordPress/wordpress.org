<?php
/**
 * WordPress.org accounts known to the mock API and its identity provider.
 *
 * @package WordPressdotorg\FreeScout\Environment
 */

declare( strict_types = 1 );

return array(
	'admin'    => array(
		'display_name' => 'Admin User',
		'first_name'   => 'Admin',
		'last_name'    => 'User',
		'email'        => 'admin@wordpress.test',
		'two_factor'   => true,
		'blocked'      => false,
	),
	'reviewer' => array(
		'display_name' => 'Rita Reviewer',
		'first_name'   => 'Rita',
		'last_name'    => 'Reviewer',
		'email'        => 'reviewer@wordpress.test',
		'two_factor'   => true,
		'blocked'      => false,
	),
	'no2fa'    => array(
		'display_name' => 'Nico No2FA',
		'first_name'   => '',
		'last_name'    => '',
		'email'        => 'no2fa@wordpress.test',
		'two_factor'   => false,
		'blocked'      => false,
	),
	'blocked'  => array(
		'display_name' => 'Blake Blocked',
		'first_name'   => 'Blake',
		'last_name'    => 'Blocked',
		'email'        => 'blocked@wordpress.test',
		'two_factor'   => true,
		'blocked'      => true,
	),
);
