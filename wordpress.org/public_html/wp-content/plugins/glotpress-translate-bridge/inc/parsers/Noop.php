<?php
namespace WordPressdotorg\Post_Translation\Parsers;

class Noop implements BlockParser {
	public function to_strings( array $block ) : array {
		return [];
	}

	public function replace_strings( array $block, array $replacements ) : array {
		return $block;
	}
}
