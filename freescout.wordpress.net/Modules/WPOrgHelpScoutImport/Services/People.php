<?php
/**
 * Finds the FreeScout users and senders for the people in HelpScout's data.
 *
 * @package WordPressdotorg\FreeScout\WPOrgHelpScoutImport
 */

declare( strict_types = 1 );

namespace Modules\WPOrgHelpScoutImport\Services;

use App\Customer;
use App\User;

/**
 * HelpScout users become the FreeScout users with the same email; senders become FreeScout senders.
 */
final class People {

	/**
	 * Email of the user that HelpScout users without a FreeScout user are credited to.
	 *
	 * Core's own robot users have addresses like this.
	 *
	 * @var string
	 */
	public const ROBOT_EMAIL = 'fs-helpscout-import@example.org';

	/**
	 * FreeScout users found so far, by HelpScout user ID; null if there's none.
	 *
	 * @var array
	 */
	private $users = array();

	/**
	 * The FreeScout user for a HelpScout user, matched by email.
	 *
	 * @param mixed $person HelpScout person object, like a thread's `createdBy`.
	 * @return User|null Null if it isn't a HelpScout user, or no FreeScout user has their email.
	 */
	public function user( $person ): ?User {
		if ( ! is_array( $person ) || 'user' !== ( $person['type'] ?? '' ) || empty( $person['email'] ) ) {
			return null;
		}

		$id = (int) ( $person['id'] ?? 0 );
		if ( ! array_key_exists( $id, $this->users ) ) {
			$this->users[ $id ] = User::query()->where( 'email', mb_strtolower( (string) $person['email'] ) )->first();
		}

		return $this->users[ $id ];
	}

	/**
	 * Lists a mailbox's HelpScout users who have no FreeScout user with their email.
	 *
	 * @param array[] $helpscout_users HelpScout users, as HelpScout lists them.
	 * @return string[] Their names and emails.
	 */
	public function missing_users( array $helpscout_users ): array {
		$missing = array();

		foreach ( $helpscout_users as $helpscout_user ) {
			$email = mb_strtolower( (string) ( $helpscout_user['email'] ?? '' ) );
			if ( '' === $email || User::query()->where( 'email', $email )->exists() ) {
				continue;
			}

			$missing[] = trim( ( $helpscout_user['firstName'] ?? '' ) . ' ' . ( $helpscout_user['lastName'] ?? '' ) ) . ' <' . $email . '>';
		}

		sort( $missing );

		return $missing;
	}

	/**
	 * The user that HelpScout users without a FreeScout user are credited to, created when first needed.
	 *
	 * A disabled robot: it can't log in, and isn't counted as an agent.
	 *
	 * @return User
	 */
	public function robot(): User {
		$robot = User::query()->where( 'email', self::ROBOT_EMAIL )->first();
		if ( $robot ) {
			return $robot;
		}

		$robot             = new User();
		$robot->first_name = 'HelpScout';
		$robot->last_name  = 'Import';
		$robot->email      = self::ROBOT_EMAIL;
		$robot->password   = User::getDummyPassword();
		$robot->role       = User::ROLE_USER;
		$robot->type       = User::TYPE_ROBOT;
		$robot->status     = User::STATUS_DISABLED;
		$robot->save();

		return $robot;
	}

	/**
	 * The FreeScout sender for a HelpScout customer, created if there's none with their email yet.
	 *
	 * @param mixed $person HelpScout person object, like a conversation's `primaryCustomer`.
	 * @return Customer|null Null if it has no email.
	 */
	public function sender( $person ): ?Customer {
		if ( ! is_array( $person ) || empty( $person['email'] ) ) {
			return null;
		}

		$customer = Customer::create(
			(string) $person['email'],
			array(
				'first_name' => (string) ( $person['first'] ?? '' ),
				'last_name'  => (string) ( $person['last'] ?? '' ),
			)
		);

		return $customer instanceof Customer ? $customer : null;
	}
}
