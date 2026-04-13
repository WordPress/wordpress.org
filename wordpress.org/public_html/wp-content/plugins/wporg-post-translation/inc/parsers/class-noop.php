<?php
namespace WordPressdotorg\Post_Translation\Parsers;

/**
 * No-op parser for container blocks (columns, groups, etc.).
 *
 * These blocks contain no translatable strings themselves; their
 * innerBlocks are processed separately by the Post_Parser.
 */
class Noop implements Block_Parser {
	public function to_strings( array $block ): array {
		return [];
	}

	public function replace_strings( array $block, array $replacements ): array {
		return $block;
	}
}
