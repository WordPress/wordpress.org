<?php
/**
 * Tests for Plugin_Share_Image stat helpers.
 *
 * @package WordPressdotorg\Plugin_Directory\Tests
 */

use PHPUnit\Framework\TestCase;
use WordPressdotorg\Plugin_Directory\Plugin_Share_Image;

/**
 * @group share-image
 */
class Plugin_Share_Image_Test extends TestCase {

	/**
	 * @param int $active_installs Active install count.
	 * @return array{icon: string, value: string, label: string}
	 */
	protected function get_install_stat_item( $active_installs ) {
		$reflection = new \ReflectionMethod( Plugin_Share_Image::class, 'get_install_stat_item' );
		$reflection->setAccessible( true );

		return $reflection->invoke( null, $active_installs );
	}

	public function test_install_stat_item_under_ten() {
		$stat = $this->get_install_stat_item( 0 );

		$this->assertSame( 'download', $stat['icon'] );
		$this->assertSame( '<10', $stat['value'] );
		$this->assertSame( 'Installs', $stat['label'] );
	}

	public function test_install_stat_item_hundreds() {
		$stat = $this->get_install_stat_item( 500 );

		$this->assertSame( 'download', $stat['icon'] );
		$this->assertSame( '500+', $stat['value'] );
	}

	public function test_install_stat_item_fifty_thousand_uses_formatted_display() {
		$stat = $this->get_install_stat_item( 50000 );

		$this->assertSame( '50,000+', $stat['value'] );
	}

	public function test_install_stat_item_hundred_thousands() {
		$stat = $this->get_install_stat_item( 150000 );

		$this->assertSame( '150K+', $stat['value'] );
	}

	public function test_install_stat_item_millions() {
		$stat = $this->get_install_stat_item( 2500000 );

		$this->assertSame( '2M+', $stat['value'] );
	}
}
