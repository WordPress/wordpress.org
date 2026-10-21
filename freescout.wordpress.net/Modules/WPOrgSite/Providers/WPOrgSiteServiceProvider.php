<?php
/**
 * WPOrgSite service provider.
 *
 * @package WordPressdotorg\FreeScout\WPOrgSite
 */

declare( strict_types = 1 );

namespace Modules\WPOrgSite\Providers;

use App\Conversation;
use App\Thread;
use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View;
use Modules\WPOrgSite\Http\Middleware\LockModules;
use Modules\WPOrgSite\Http\Middleware\RedirectHelpScoutLinks;
use Modules\WPOrgSite\Services\AltConversation;
use Modules\WPOrgSite\Services\EnvSettings;
use Modules\WPOrgSite\Services\HelpScoutLinks;
use Modules\WPOrgSite\Services\PinnedNote;
use Modules\WPOrgSite\Services\UserChoice;

/**
 * Adapts FreeScout to how WordPress.org runs it.
 */
final class WPOrgSiteServiceProvider extends ServiceProvider {

	/**
	 * Module alias.
	 *
	 * @var string
	 */
	public const ALIAS = 'wporgsite';

	/**
	 * Where the browser remembers whether the sidebar is collapsed, as sidebar.js's KEY.
	 *
	 * @var string
	 */
	public const SIDEBAR_KEY = 'wporgsite.sidebar';

	/**
	 * Where the browser remembers whether a conversation's header is pinned, as header.js's KEY.
	 *
	 * @var string
	 */
	public const HEADER_KEY = 'wporgsite.header';

	/**
	 * The choices in an agent's profile: each field, and the option it's kept in.
	 *
	 * @var array<string, string>
	 */
	private const CHOICES = array(
		PinnedNote::FIELD      => PinnedNote::OPTION,
		AltConversation::FIELD => AltConversation::OPTION,
	);

	/**
	 * Boots the module.
	 *
	 * @return void
	 */
	public function boot(): void {
		$this->app['router']->pushMiddlewareToGroup( 'web', LockModules::class );
		$this->app['router']->pushMiddlewareToGroup( 'web', RedirectHelpScoutLinks::class );
		$this->loadViewsFrom( __DIR__ . '/../Resources/views', self::ALIAS );
		$this->loadRoutesFrom( __DIR__ . '/../Http/routes.php' );
		$this->loadMigrationsFrom( __DIR__ . '/../Database/Migrations' );

		// Modules come with deploys, so the Modules page only lists installed ones; site.css hides the empty directory.
		\View::composer(
			'modules/modules',
			static function ( View $view ): void {
				$view->with(
					array(
						'modules_directory'   => array(),
						'third_party_modules' => array(),
					)
				);
			}
		);

		$images = array(
			'layout.favicon'     => 'favicon.svg',
			'layout.header_logo' => 'wordpress-mark-white.svg',
			'login.banner'       => 'freescout-logo.svg',
		);

		foreach ( $images as $filter => $image ) {
			\Eventy::addFilter( $filter, static fn(): string => asset( \Module::getPublicPath( self::ALIAS ) . '/img/' . $image ) );
		}

		\Eventy::addFilter(
			'stylesheets',
			static function ( array $styles ): array {
				$styles[] = \Module::getPublicPath( self::ALIAS ) . '/css/site.css';

				return $styles;
			}
		);

		\Eventy::addFilter(
			'javascripts',
			static function ( array $javascripts ): array {
				$javascripts[] = \Module::getPublicPath( self::ALIAS ) . '/js/modules.js';
				// Before the others: it lays the conversation out as the script runs, before they set it up.
				$javascripts[] = \Module::getPublicPath( self::ALIAS ) . '/js/alt-conversation.js';
				$javascripts[] = \Module::getPublicPath( self::ALIAS ) . '/js/conversation.js';
				$javascripts[] = \Module::getPublicPath( self::ALIAS ) . '/js/saved-replies.js';
				$javascripts[] = \Module::getPublicPath( self::ALIAS ) . '/js/sidebar.js';
				$javascripts[] = \Module::getPublicPath( self::ALIAS ) . '/js/header.js';
				$javascripts[] = \Module::getPublicPath( self::ALIAS ) . '/js/discard.js';
				$javascripts[] = \Module::getPublicPath( self::ALIAS ) . '/js/settings.js';

				return $javascripts;
			}
		);

		// Core loads scripts at the end of the page, which would show the sidebar expanded, and the header unpinned, first.
		\Eventy::addAction(
			'layout.head',
			static function (): void {
				echo self::head_script( self::uses_alt_design() );
			}
		);

		self::register_profile_hooks();
		self::register_settings_hooks();
		self::register_pinned_note_hooks();
		self::register_helpscout_link_hooks();

		// For alt-conversation.js, which lays the conversation out.
		\Eventy::addAction(
			'body.class',
			static function (): void {
				if ( ! self::is_print() && self::uses_alt_design() ) {
					echo ' ' . AltConversation::BODY_CLASS;
				}
			}
		);
	}

	/**
	 * Whether the agent viewing the page chose the alternate conversation design.
	 *
	 * @return bool Whether they did.
	 */
	private static function uses_alt_design(): bool {
		try {
			$user = auth()->user();

			return $user instanceof User && UserChoice::get( $user, AltConversation::OPTION );
		} catch ( \Throwable $e ) {
			\Log::error( '[WPOrgSite] Could not tell whether to use the alternate conversation design: ' . $e->getMessage() );

			return false;
		}
	}

	/**
	 * The choices in an agent's profile, each with its field, what it says, and whether it's on.
	 *
	 * @param User $user Agent.
	 * @return array<int, array<string, mixed>> Choices.
	 */
	public static function profile_choices( User $user ): array {
		return array(
			array(
				'field'   => PinnedNote::FIELD,
				'label'   => __( 'Latest note' ),
				'text'    => __( 'Pin it to the top of the conversation' ),
				'help'    => __( 'Once newer emails push a conversation\'s latest note down, a copy of it shows above them, so its context stays in sight. The note itself is dimmed where it is.' ),
				'enabled' => UserChoice::get( $user, PinnedNote::OPTION ),
			),
			array(
				'field'   => AltConversation::FIELD,
				'label'   => __( 'Conversation design' ),
				'text'    => __( 'Alternate conversation design' ),
				'help'    => __( 'The subject, tags, and actions stay in view at the top; the number, assignee, and status are at the top of the sidebar, which scrolls on its own; the emails go oldest first, opening at the newest; and Reply and Note buttons wait at the bottom, where the forms open.' ),
				'enabled' => UserChoice::get( $user, AltConversation::OPTION ),
			),
		);
	}

	/**
	 * Registers the hooks of the profile's choices: showing them, saving them, and forgetting them with the user.
	 *
	 * @return void
	 */
	private static function register_profile_hooks(): void {
		\Eventy::addAction(
			'user.edit.before_photo',
			static function ( mixed $user = null ): void {
				try {
					if ( $user instanceof User ) {
						echo \View::make( self::ALIAS . '::profile', array( 'choices' => self::profile_choices( $user ) ) )->render();
					}
				} catch ( \Throwable $e ) {
					\Log::error( '[WPOrgSite] Could not show the conversation choices in a profile: ' . $e->getMessage() );
				}
			}
		);

		\Eventy::addFilter(
			'user.save_profile',
			static function ( mixed $user = null, mixed $request = null ): mixed {
				if ( ! $user instanceof User || ! $request instanceof Request ) {
					return $user;
				}

				foreach ( self::CHOICES as $field => $option ) {
					try {
						// Saved elsewhere, the form doesn't have it. Laravel 5.5's requests have no boolean().
						if ( $request->has( $field ) ) {
							UserChoice::set( $user, $option, '1' === (string) $request->input( $field ) );
						}
					} catch ( \Throwable $e ) {
						\Log::error( '[WPOrgSite] Could not save ' . $field . ' in a profile: ' . $e->getMessage() );
					}
				}

				return $user;
			},
			20,
			2
		);

		\Eventy::addAction(
			'user.deleted',
			static function ( mixed $user = null ): void {
				try {
					if ( $user instanceof User ) {
						foreach ( self::CHOICES as $option ) {
							UserChoice::forget( (int) $user->id, $option );
						}
					}
				} catch ( \Throwable $e ) {
					\Log::error( '[WPOrgSite] Could not forget the conversation choices of a deleted user: ' . $e->getMessage() );
				}
			}
		);
	}

	/**
	 * Registers the hooks that keep Manage » Settings from writing `.env`, which is read-only on the server.
	 *
	 * Saving a setting FreeScout keeps there fails, and with it the rest of its section. They're left out when a section
	 * is saved, and settings.js shows them read-only.
	 *
	 * @return void
	 */
	private static function register_settings_hooks(): void {
		\Eventy::addFilter(
			'settings.alter_section_settings',
			static function ( mixed $settings = array(), mixed $section = '' ): mixed {
				try {
					$route = request()->route();
					if ( ! is_array( $settings ) || ! $route || 'App\Http\Controllers\SettingsController@save' !== $route->getActionName() ) {
						return $settings;
					}

					return array_diff_key( $settings, array_flip( EnvSettings::keys( (string) $section ) ) );
				} catch ( \Throwable $e ) {
					\Log::error( '[WPOrgSite] Could not leave the .env settings out of saving settings: ' . $e->getMessage() );

					return $settings;
				}
			},
			20,
			2
		);

		// Late, to wrap the view another module may have chosen for its section.
		\Eventy::addFilter(
			'settings.view',
			static function ( mixed $view = '', mixed $section = '' ): mixed {
				try {
					$keys = is_string( $view ) ? EnvSettings::keys( (string) $section ) : array();
					if ( ! $keys ) {
						return $view;
					}

					\View::share(
						'wporgsite_env_settings',
						array(
							'view' => $view,
							'keys' => $keys,
						)
					);

					return self::ALIAS . '::settings';
				} catch ( \Throwable $e ) {
					\Log::error( '[WPOrgSite] Could not mark the .env settings read-only: ' . $e->getMessage() );

					return $view;
				}
			},
			100,
			2
		);
	}

	/**
	 * Registers the hooks of the pinned note: the copy above the threads, and the note's label.
	 *
	 * Callbacks take what core passes untyped, and check it: a TypeError in a hook isn't caught, and would fail the
	 * request.
	 *
	 * @return void
	 */
	private static function register_pinned_note_hooks(): void {
		\Eventy::addAction(
			'conversation.before_threads',
			static function ( mixed $conversation = null ): void {
				try {
					$user = auth()->user();
					if ( ! $conversation instanceof Conversation || ! $user instanceof User || self::is_print() ) {
						return;
					}

					$note = PinnedNote::pin( $conversation, $user );
					if ( $note ) {
						echo self::render_pinned_note( $note, $conversation );
					}
				} catch ( \Throwable $e ) {
					\Log::error( '[WPOrgSite] Could not pin the latest note of a conversation: ' . $e->getMessage() );
				}
			}
		);

		\Eventy::addAction(
			'thread.info.prepend',
			static function ( mixed $thread = null ): void {
				try {
					if ( $thread instanceof Thread && PinnedNote::is_pinned( $thread ) ) {
						echo \View::make(
							self::ALIAS . '::pinned_label',
							// The alternate design shows the threads oldest first, so the copy is at the bottom.
							array( 'bottom' => self::uses_alt_design() )
						)->render();
					}
				} catch ( \Throwable $e ) {
					\Log::error( '[WPOrgSite] Could not label a pinned note: ' . $e->getMessage() );
				}
			}
		);
	}

	/**
	 * Records the HelpScout ID of each conversation the importer imports, for HelpScout's links.
	 *
	 * The importer's model is named rather than used: the importer goes once every mailbox has moved.
	 *
	 * @return void
	 */
	private static function register_helpscout_link_hooks(): void {
		\Event::listen(
			'eloquent.saved: ' . HelpScoutLinks::IMPORTER_MODEL,
			static function ( mixed $imported = null ): void {
				try {
					if ( $imported instanceof Model ) {
						HelpScoutLinks::record( (int) $imported->getAttribute( 'helpscout_id' ), (int) $imported->getAttribute( 'conversation_id' ) );
					}
				} catch ( \Throwable $e ) {
					\Log::error( '[WPOrgSite] Could not record the HelpScout ID of an imported conversation: ' . $e->getMessage() );
				}
			}
		);
	}

	/**
	 * The copy of a note above its conversation's threads: its body as core shows it in the thread, with modules'
	 * changes to it, and links to its attachments.
	 *
	 * @param Thread       $note         Note.
	 * @param Conversation $conversation Its conversation.
	 * @return string HTML.
	 */
	public static function render_pinned_note( Thread $note, Conversation $conversation ): string {
		$body = (string) \Eventy::filter( 'thread.body_output', $note->getBodyWithFormatedLinks(), $note, $conversation, $conversation->mailbox );
		// As core does in the thread.
		$body = (string) safe_raw_html( $body );

		$attachments = array();
		foreach ( $note->attachments as $attachment ) {
			$attachments[] = array(
				'url'  => (string) $attachment->url(),
				'name' => (string) $attachment->file_name,
			);
		}

		$author = $note->created_by_user;

		return \View::make(
			self::ALIAS . '::pinned_note',
			array(
				'note'        => $note,
				'body'        => $body,
				'attachments' => $attachments,
				'author'      => $author instanceof User ? $author->getFullName() : '',
				'ago'         => User::dateDiffForHumans( $note->created_at ),
				'date'        => User::dateFormat( $note->created_at ),
			)
		)->render();
	}

	/**
	 * Whether the page is a conversation's print view, which shows its threads as they are.
	 *
	 * @return bool Whether it is.
	 */
	private static function is_print(): bool {
		return method_exists( \Helper::class, 'isPrint' ) && \Helper::isPrint();
	}

	/**
	 * The script in the page's head that collapses the sidebar, and pins a conversation's header, if the agent's browser
	 * remembers them so, before the page shows; with core's nonce, which its Content Security Policy may ask for.
	 *
	 * @param bool $alt_design Whether the agent uses the alternate conversation design, which has no header to pin.
	 * @return string Script.
	 */
	public static function head_script( bool $alt_design = false ): string {
		$nonce = '';
		try {
			if ( method_exists( \Helper::class, 'cspNonceAttr' ) ) {
				$nonce = (string) \Helper::cspNonceAttr();
			}
		} catch ( \Throwable $e ) {
			\Log::error( '[WPOrgSite] Could not get the CSP nonce: ' . $e->getMessage() );
		}

		$classes = array( self::SIDEBAR_KEY => array( 'collapsed', 'wporgsite-sidebar-collapsed' ) );
		if ( ! $alt_design ) {
			$classes[ self::HEADER_KEY ] = array( 'pinned', 'wporgsite-header-pinned' );
		}

		$script = '';
		foreach ( $classes as $key => [ $value, $class ] ) {
			$script .= 'if(localStorage.getItem(' . json_encode( $key ) . ')===' . json_encode( $value ) . '){document.documentElement.classList.add(' . json_encode( $class ) . ')}';
		}

		return '<script' . $nonce . '>try{' . $script . '}catch(e){}</script>';
	}
}
