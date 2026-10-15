<?php
/**
 * Conversation search.
 *
 * @package WordPressdotorg\FreeScout\WPOrgSearch
 */

declare( strict_types = 1 );

namespace Modules\WPOrgSearch\Services;

use App\Conversation;
use App\User;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;

/**
 * Runs core's conversation search in one query instead of two, and matches messages through a full-text index once
 * the database has one.
 *
 * Core's search compares every message with `LIKE '%…%'`, which reads every thread, and then reads them all again to
 * count the results. Until `threads` has the index, searches without filters only cover the past year.
 */
final class Search {

	/**
	 * Name of the full-text index on `threads` that the database administrators add.
	 *
	 * @var string
	 */
	public const INDEX = 'threads_search_ft';

	/**
	 * Columns the index covers, in order; `MATCH()` has to name exactly these.
	 *
	 * @var string[]
	 */
	public const INDEX_COLUMNS = array( 'body', 'from', 'to', 'cc', 'bcc' );

	/**
	 * How far back searches without filters go while there's no index.
	 *
	 * @var string
	 */
	public const DEFAULT_AFTER = '-1 year';

	/**
	 * Cache key for what's known about the index.
	 *
	 * @var string
	 */
	private const CACHE_KEY = 'wporgsearch_fulltext';

	/**
	 * Minutes to remember whether the index exists.
	 *
	 * @var int
	 */
	private const CACHE_MINUTES = 60;

	/**
	 * InnoDB's longest indexed word; longer ones aren't in the index.
	 *
	 * @var int
	 */
	private const MAX_TOKEN_SIZE = 84;

	/**
	 * Alias of the matching conversations' IDs in the query.
	 *
	 * @var string
	 */
	private const MATCHES = 'wporgsearch_matches';

	/**
	 * Column carrying the number of results on every row.
	 *
	 * @var string
	 */
	private const TOTAL = 'wporgsearch_total';

	/**
	 * Whether this request's search got the default date.
	 *
	 * @var bool
	 */
	private $defaulted = false;

	/**
	 * Limits a search without filters to the past year, unless the index can answer it.
	 *
	 * An `after` filter that's there but empty means the agent cleared the date, to search everything.
	 *
	 * @param array  $filters Search filters from the request.
	 * @param string $q       Search query.
	 * @return array
	 */
	public function default_filters( array $filters, string $q ): array {
		if ( '' === $q || $this->uses_index( $q ) ) {
			return $filters;
		}

		if ( array_key_exists( 'after', $filters ) || array_filter( $filters ) ) {
			return $filters;
		}

		$filters['after'] = now()->modify( self::DEFAULT_AFTER )->format( 'Y-m-d' );
		$this->defaulted  = true;

		return $filters;
	}

	/**
	 * Whether this request's search got the default date.
	 *
	 * @return bool
	 */
	public function was_defaulted(): bool {
		return $this->defaulted;
	}

	/**
	 * Searches conversations, a page at a time, like core's search.
	 *
	 * @param string $q       Search query.
	 * @param array  $filters Search filters.
	 * @param User   $user    Agent searching.
	 * @return LengthAwarePaginator
	 */
	public function conversations( string $q, array $filters, User $user ): LengthAwarePaginator {
		// Core applies this after the hook that lets modules search, on AJAX pages too.
		if ( $user->canSeeOnlyAssignedConversations() ) {
			$filters['assigned'] = $user->id;
		}

		$terms = $this->uses_index( $q ) ? $this->boolean_query( $q ) : '';
		$query = '' === $terms ? Conversation::search( $q, $filters, $user ) : $this->indexed_query( $q, $terms, $filters, $user );

		return $this->paginate( $query );
	}

	/**
	 * Turns a search query into a full-text query in boolean mode that requires every word.
	 *
	 * Words are matched as prefixes, and "quoted phrases" as phrases. The index splits text at anything that isn't a
	 * letter, digit, or underscore, so the query does too: `jane@example.org` requires `jane`, `example`, and `org`.
	 * Words too short or too long for the index are left out.
	 *
	 * @param string $q Search query.
	 * @return string Empty when no word can be looked up in the index.
	 */
	public function boolean_query( string $q ): string {
		$terms = array();

		preg_match_all( '/"([^"]*)"/u', $q, $phrases );
		foreach ( $phrases[1] as $phrase ) {
			$words = $this->split_words( $phrase );
			if ( $this->indexed_words( $words ) ) {
				$terms[] = '+"' . implode( ' ', $words ) . '"';
			}
		}

		$rest = (string) preg_replace( '/"[^"]*"/u', ' ', $q );
		foreach ( $this->indexed_words( $this->split_words( $rest ) ) as $word ) {
			$terms[] = '+' . $word . '*';
		}

		return implode( ' ', array_unique( $terms ) );
	}

	/**
	 * What's known about the full-text index: whether it exists, and the shortest word it holds.
	 *
	 * @return array{index: bool, min_token_size: int}
	 */
	public function fulltext(): array {
		$known = \Cache::get( self::CACHE_KEY );
		if ( is_array( $known ) && isset( $known['index'], $known['min_token_size'] ) ) {
			return $known;
		}

		$known = array(
			'index'          => false,
			'min_token_size' => 3,
		);

		try {
			if ( 'mysql' === \DB::connection()->getDriverName() ) {
				$rows = \DB::select(
					'SELECT `COLUMN_NAME` AS `name` FROM `information_schema`.`STATISTICS`
					WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = ? AND `INDEX_NAME` = ? AND `INDEX_TYPE` = ?
					ORDER BY `SEQ_IN_INDEX`',
					array( \DB::getTablePrefix() . 'threads', self::INDEX, 'FULLTEXT' )
				);

				$columns = array_map(
					static function ( $row ): string {
						return strtolower( (string) $row->name );
					},
					$rows
				);

				$known['index'] = self::INDEX_COLUMNS === $columns;

				$size = \DB::selectOne( 'SELECT @@innodb_ft_min_token_size AS `size`' );
				if ( $size && (int) $size->size > 0 ) {
					$known['min_token_size'] = (int) $size->size;
				}
			}
		} catch ( \Throwable $e ) {
			\Log::error( '[WPOrgSearch] Could not look for the full-text index: ' . $e->getMessage() );
		}

		\Cache::put( self::CACHE_KEY, $known, self::CACHE_MINUTES );

		return $known;
	}

	/**
	 * Whether a search query's messages can be matched through the index.
	 *
	 * @param string $q Search query.
	 * @return bool
	 */
	private function uses_index( string $q ): bool {
		return $this->fulltext()['index'] && '' !== $this->boolean_query( $q );
	}

	/**
	 * Builds the search from the IDs of matching conversations, gathered first.
	 *
	 * Each source of matches is looked up once, on its own: messages through the index, subjects and senders'
	 * addresses and names with `LIKE`, which only reads short columns. Core's query adds the filters, and modules'.
	 *
	 * @param string $q       Search query.
	 * @param string $terms   Full-text query.
	 * @param array  $filters Search filters.
	 * @param User   $user    Agent searching.
	 * @return EloquentBuilder
	 */
	private function indexed_query( string $q, string $terms, array $filters, User $user ): EloquentBuilder {
		$query = Conversation::search( '', $filters, $user );
		$base  = $query->getQuery();
		$this->drop_unused_joins( $base );

		$matches = $this->matches( $q, $terms, $this->mailbox_ids( $filters, $user ) );
		$alias   = $base->getGrammar()->wrapTable( self::MATCHES );

		$base->join( \DB::raw( '(' . $matches->toSql() . ') as ' . $alias ), self::MATCHES . '.id', '=', 'conversations.id' );
		$base->addBinding( $matches->getBindings(), 'join' );

		return $query;
	}

	/**
	 * Builds the query for the IDs of the conversations that match.
	 *
	 * @param string $q           Search query.
	 * @param string $terms       Full-text query.
	 * @param int[]  $mailbox_ids Mailboxes searched.
	 * @return Builder
	 */
	private function matches( string $q, string $terms, array $mailbox_ids ): Builder {
		$like    = '%' . mb_strtolower( $q ) . '%';
		$grammar = \DB::getQueryGrammar();

		$columns = implode( ', ', array_map( array( $grammar, 'wrap' ), self::INDEX_COLUMNS ) );
		$matches = \DB::table( 'threads' )
			->select( 'conversation_id as id' )
			->whereRaw( 'MATCH(' . $columns . ') AGAINST (? IN BOOLEAN MODE)', array( $terms ) );

		$matches->union(
			\DB::table( 'conversations' )
				->select( 'id' )
				->whereIn( 'mailbox_id', $mailbox_ids )
				->where(
					static function ( Builder $where ) use ( $like ): void {
						$where->where( 'subject', 'like', $like )->orWhere( 'customer_email', 'like', $like );
					}
				)
		);

		$matches->union(
			\DB::table( 'customers' )
				->join( 'conversations', 'conversations.customer_id', '=', 'customers.id' )
				->select( 'conversations.id' )
				->whereIn( 'conversations.mailbox_id', $mailbox_ids )
				->where(
					static function ( Builder $where ) use ( $like, $q, $grammar ): void {
						$where->where( 'customers.first_name', 'like', $like )->orWhere( 'customers.last_name', 'like', $like );

						// Only a query with a space can match across both names.
						if ( str_contains( $q, ' ' ) ) {
							$where->orWhereRaw(
								'CONCAT(' . $grammar->wrap( 'customers.first_name' ) . ", ' ', " . $grammar->wrap( 'customers.last_name' ) . ') like ?',
								array( $like )
							);
						}
					}
				)
		);

		if ( ctype_digit( $q ) ) {
			$matches->union(
				\DB::table( 'conversations' )
					->select( 'id' )
					->where( Conversation::numberFieldName(), min( (int) $q, \Helper::DB_INT_MAX ) )
			);
		}

		return $matches;
	}

	/**
	 * Mailboxes searched, like core decides them.
	 *
	 * @param array $filters Search filters.
	 * @param User  $user    Agent searching.
	 * @return int[]
	 */
	private function mailbox_ids( array $filters, User $user ): array {
		if ( ! empty( $filters['mailbox'] ) && $user->hasAccessToMailbox( $filters['mailbox'] ) ) {
			return array( (int) $filters['mailbox'] );
		}

		return array_map( 'intval', (array) $user->mailboxesIdsCanView() );
	}

	/**
	 * Drops core's joins of `threads` and `customers` when nothing in the query uses them.
	 *
	 * Core always joins every thread, for filters like the message body. Without a filter that needs them, the join
	 * only multiplies the rows to group back into conversations.
	 *
	 * @param Builder $query Query.
	 * @return void
	 */
	private function drop_unused_joins( Builder $query ): void {
		foreach ( (array) $query->joins as $i => $join ) {
			if ( ! in_array( $join->table, array( 'threads', 'customers' ), true ) || $join->getBindings() ) {
				continue;
			}

			$without        = clone $query;
			$without->joins = array_values( array_diff_key( $query->joins, array( $i => true ) ) );

			$table = preg_quote( \DB::getTablePrefix() . $join->table, '/' );
			if ( ! preg_match( '/(?<![\w`])`?' . $table . '`?\./', $without->toSql() ) ) {
				unset( $query->joins[ $i ] );
			}
		}

		$query->joins = $query->joins ? array_values( $query->joins ) : null;
	}

	/**
	 * Fetches a page of results with their total, in one query.
	 *
	 * Core's paginator counts with a second, grouped query that sends every match's row back, as slow as the search.
	 *
	 * @param EloquentBuilder $query Search.
	 * @return LengthAwarePaginator
	 */
	private function paginate( EloquentBuilder $query ): LengthAwarePaginator {
		$per_page = Conversation::DEFAULT_LIST_SIZE;
		$page     = Paginator::resolveCurrentPage();

		$rows = $query->addSelect( \DB::raw( 'COUNT(*) OVER() AS ' . self::TOTAL ) )->forPage( $page, $per_page )->get();

		// A page past the last one has no rows to carry the total.
		$total = $rows->isEmpty() ? 0 : (int) $rows->first()->{ self::TOTAL };
		foreach ( $rows as $row ) {
			unset( $row->{ self::TOTAL } );
		}

		return new LengthAwarePaginator(
			$rows,
			$total,
			$per_page,
			$page,
			array(
				'path'     => Paginator::resolveCurrentPath(),
				'pageName' => 'page',
			)
		);
	}

	/**
	 * Splits text into words the way the index does, lowercased.
	 *
	 * @param string $text Text.
	 * @return string[]
	 */
	private function split_words( string $text ): array {
		return (array) preg_split( '/[^\p{L}\p{N}_]+/u', mb_strtolower( $text ), -1, PREG_SPLIT_NO_EMPTY );
	}

	/**
	 * Keeps the words the index holds.
	 *
	 * @param string[] $words Words.
	 * @return string[]
	 */
	private function indexed_words( array $words ): array {
		$min = $this->fulltext()['min_token_size'];

		return array_values(
			array_filter(
				$words,
				static function ( string $word ) use ( $min ): bool {
					$length = mb_strlen( $word );

					return $length >= $min && $length <= self::MAX_TOKEN_SIZE;
				}
			)
		);
	}
}
