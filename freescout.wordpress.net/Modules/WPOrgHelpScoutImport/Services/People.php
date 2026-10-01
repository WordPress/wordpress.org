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
use Modules\WPOrgHelpScoutImport\Entities\Agent;

/**
 * HelpScout users become the FreeScout users administrators chose, or with the same email; senders become senders.
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
	 * The FreeScout user for a HelpScout user: the one an administrator chose, or else the one with their email.
	 *
	 * @param mixed $person HelpScout person object, like a thread's `createdBy`.
	 * @return User|null Null if it isn't a HelpScout user, or there's no FreeScout user for them.
	 */
	public function user( $person ): ?User {
		if ( ! is_array( $person ) || 'user' !== ( $person['type'] ?? '' ) ) {
			return null;
		}

		$id = (int) ( $person['id'] ?? 0 );
		if ( ! array_key_exists( $id, $this->users ) ) {
			$this->users[ $id ] = self::chosen( $id ) ?? self::by_email( (string) ( $person['email'] ?? '' ) );
		}

		return $this->users[ $id ];
	}

	/**
	 * Lists a mailbox's HelpScout users with the FreeScout user each would be credited to.
	 *
	 * @param array[] $helpscout_users HelpScout users, as HelpScout lists them.
	 * @return array[] By name, each with `id`, `name`, `email`, `chosen` (the chosen user's ID, or null), and `by_email`
	 *                 (the user with their email, or null).
	 */
	public function agents( array $helpscout_users ): array {
		$agents = array();

		foreach ( $helpscout_users as $helpscout_user ) {
			$id     = (int) ( $helpscout_user['id'] ?? 0 );
			$chosen = self::chosen( $id );
			$email  = (string) ( $helpscout_user['email'] ?? '' );

			$agents[] = array(
				'id'       => $id,
				'name'     => trim( ( $helpscout_user['firstName'] ?? '' ) . ' ' . ( $helpscout_user['lastName'] ?? '' ) ),
				'email'    => $email,
				'chosen'   => $chosen ? (int) $chosen->id : null,
				'by_email' => self::by_email( $email ),
			);
		}

		usort(
			$agents,
			static function ( array $a, array $b ): int {
				return strcasecmp( $a['name'], $b['name'] );
			}
		);

		return $agents;
	}

	/**
	 * The FreeScout user an administrator chose for a HelpScout user.
	 *
	 * @param int $helpscout_user_id HelpScout user ID.
	 * @return User|null
	 */
	private static function chosen( int $helpscout_user_id ): ?User {
		$user_id = $helpscout_user_id ? Agent::query()->where( 'helpscout_user_id', $helpscout_user_id )->value( 'user_id' ) : null;

		return $user_id ? User::find( (int) $user_id ) : null;
	}

	/**
	 * The FreeScout user with an email.
	 *
	 * @param string $email Email.
	 * @return User|null
	 */
	private static function by_email( string $email ): ?User {
		return '' !== $email ? User::query()->where( 'email', mb_strtolower( $email ) )->first() : null;
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
