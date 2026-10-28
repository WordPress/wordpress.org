<?php
/**
 * Tests photo description word counting.
 *
 * @package WordPressdotorg\Photo_Directory
 */

declare( strict_types = 1 );

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use WordPressdotorg\Photo_Directory\Uploads;

/**
 * Tests for `Uploads::count_description_words()`.
 *
 * @group photo-directory
 */
#[Group( 'photo-directory' )]
class Description_Word_Count_Test extends TestCase {

	/**
	 * Descriptions and their expected word counts.
	 *
	 * @return array<string, array{mixed, int}>
	 */
	public static function descriptions(): array {
		return array(
			'empty'                     => array( '', 0 ),
			'non-string'                => array( array( 'not text' ), 0 ),
			'ordinary prose'            => array( 'A bright red cardinal sits on a snowy tree branch.', 10 ),
			'line breaks'               => array( "Morning light\nshines through\tthe trees.", 6 ),
			'punctuation separates'     => array( 'Sunset, clouds; river—trees.', 4 ),
			'internal punctuation'      => array( "A close-up doesn't hide the photographer’s reflection.", 7 ),
			'markup is removed'         => array( '<strong>Bright red</strong> flowers beside a path.', 6 ),
			'script content is removed' => array( '<script>These words are not stored.</script> A visible bird.', 3 ),
			'encoded less-than'         => array( 'one two three four five six seven eight nine <', 9 ),
			'accented letters'          => array( 'A café beside the Rhône.', 5 ),
		);
	}

	/**
	 * Counts words after applying the same sanitization used for storage.
	 *
	 * @param mixed $description Description to count.
	 * @param int   $expected    Expected word count.
	 * @return void
	 *
	 * @dataProvider descriptions
	 */
	#[DataProvider( 'descriptions' )]
	public function test_count_description_words( $description, int $expected ): void {
		$this->assertSame( $expected, Uploads::count_description_words( $description ) );
	}
}
