<?php
/**
 * Base test case for the freescout.wordpress.net modules.
 *
 * @package WordPressdotorg\FreeScout\Tests
 */

declare( strict_types = 1 );

namespace WordPressdotorg\FreeScout\Tests;

use App\Conversation;
use App\Customer;
use App\Folder;
use App\Mailbox;
use App\Thread;
use App\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

/**
 * Boots FreeScout against the test database; each test runs in a rolled-back transaction.
 *
 * Modules aren't active in the test database, so tests register the provider under test themselves.
 */
abstract class TestCase extends BaseTestCase {
	use DatabaseTransactions;

	/**
	 * Creates the FreeScout application.
	 *
	 * @return Application
	 */
	public function createApplication(): Application {
		$app = require FREESCOUT_PATH . '/bootstrap/app.php';

		/*
		 * Cached config would ignore phpunit.xml.dist's environment and .env, so tests would hit the dev database.
		 * FreeScout rebuilds it on the next freescout:clear-cache.
		 */
		if ( file_exists( $app->getCachedConfigPath() ) ) {
			unlink( $app->getCachedConfigPath() );
		}

		$app->make( Kernel::class )->bootstrap();

		return $app;
	}

	/**
	 * Creates an agent.
	 *
	 * @param int $role User role.
	 * @return User
	 */
	protected function create_user( int $role = User::ROLE_ADMIN ): User {
		return factory( User::class )->create( array( 'role' => $role ) );
	}

	/**
	 * Creates a mailbox.
	 *
	 * @param string $name Mailbox name.
	 * @return Mailbox
	 */
	protected function create_mailbox( string $name = 'Plugins' ): Mailbox {
		return factory( Mailbox::class )->create( array( 'name' => $name ) );
	}

	/**
	 * Creates a sender.
	 *
	 * @param string $email Sender's email address; unique by default.
	 * @return Customer
	 */
	protected function create_sender( string $email = '' ): Customer {
		$customer = factory( Customer::class )->create(
			array(
				'first_name' => 'Jane',
				'last_name'  => 'Sender',
			)
		);

		// FreeScout moves an email address to whichever customer claims it last.
		$customer->syncEmails( array( $email ? $email : uniqid( 'sender-' ) . '@example.org' ) );

		return $customer;
	}

	/**
	 * Creates a conversation started by a sender.
	 *
	 * @param Mailbox  $mailbox  Mailbox.
	 * @param Customer $customer Sender.
	 * @return Conversation
	 */
	protected function create_conversation( Mailbox $mailbox, Customer $customer ): Conversation {
		// FreeScout's factory looks up a random agent even when none is used, so one has to exist.
		if ( ! User::query()->exists() ) {
			$this->create_user();
		}

		return factory( Conversation::class )->create(
			array(
				'type'                   => Conversation::TYPE_EMAIL,
				'mailbox_id'             => $mailbox->id,
				'folder_id'              => $mailbox->folders()->where( 'type', Folder::TYPE_UNASSIGNED )->value( 'id' ),
				'customer_id'            => $customer->id,
				'customer_email'         => $customer->getMainEmail(),
				'created_by_user_id'     => null,
				'created_by_customer_id' => $customer->id,
				'source_via'             => Conversation::PERSON_CUSTOMER,
				'status'                 => Conversation::STATUS_ACTIVE,
				'user_id'                => null,
				'imported'               => false,
			)
		);
	}

	/**
	 * Adds a thread to a conversation.
	 *
	 * @param Conversation $conversation Conversation.
	 * @param int          $type         Thread type.
	 * @param string       $body         Thread body.
	 * @param User|null    $user         Agent who wrote it; the sender if null.
	 * @param string       $created_at   Thread's creation time; core keeps the conversation's own timestamps current.
	 * @return Thread
	 */
	protected function create_thread( Conversation $conversation, int $type, string $body, ?User $user, string $created_at ): Thread {
		// Senders write to the mailbox, agents to the sender.
		$to = $user ? $conversation->customer_email : $conversation->mailbox->email;

		return factory( Thread::class )->create(
			array(
				'conversation_id'        => $conversation->id,
				'type'                   => $type,
				'body'                   => $body,
				'customer_id'            => $conversation->customer_id,
				'to'                     => json_encode( array( $to ) ),
				'created_by_user_id'     => $user ? $user->id : null,
				'created_by_customer_id' => $user ? null : $conversation->customer_id,
				'source_via'             => $user ? Thread::PERSON_USER : Thread::PERSON_CUSTOMER,
				'state'                  => Thread::STATE_PUBLISHED,
				'created_at'             => $created_at,
			)
		);
	}
}
