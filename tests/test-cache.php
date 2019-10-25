<?php
/**
 * Class CacheTest
 *
 * @package Remote_Backstop
 */

namespace Remote_Backstop\Tests;

use Remote_Backstop\Cache;
use WP_UnitTestCase;

/**
 * Cache test cases.
 */
class CacheTest extends WP_UnitTestCase {

	public function setUp() {
		parent::setUp();

		// this url give a direct 200 response
		$url      = 'https://asdftestblog1.files.wordpress.com/2007/09/2007-06-30-dsc_4700-1.jpg';

		$this->cache = new Cache(
			'http://asdftestblog1.files.wordpress.com',
			[
				'headers' => [
					'x-test' => 'true',
				]
			]
		);
	}

	/**
	 * A single example test.
	 */
	public function test_down_cache_keys() {
		$headers = [ 'headers' => [ 'x-test' => 'true' ] ];

		$cache1 = new Cache( 'https://example.com/test-1', [] );
		$cache2 = new Cache( 'https://example.com/test-1', $headers );
		$cache3 = new Cache( 'https://example.com/test-2', $headers );
		$cache4 = new Cache( 'https://elsewhere.com/test-1', $headers );

		$keys1 = $cache1->down_cache_keys();
		$keys2 = $cache2->down_cache_keys();
		$keys3 = $cache3->down_cache_keys();
		$keys4 = $cache4->down_cache_keys();

		// Ensure host is the same for 1, 2, 3, but 4 is different.
		$this->assertSame( $keys1['host'], $keys2['host'] );
		$this->assertSame( $keys1['host'], $keys3['host'] );
		$this->assertNotSame( $keys1['host'], $keys4['host'] );

		// Ensure url is the same for 1 and 2, but 3 and 4 are unique.
		$this->assertSame( $keys1['url'], $keys2['url'] );
		$this->assertNotSame( $keys1['url'], $keys3['url'] );
		$this->assertNotSame( $keys1['url'], $keys4['url'] );
		$this->assertNotSame( $keys3['url'], $keys4['url'] );

		// Request should be different for 1-4.
		$this->assertNotSame( $keys1['request'], $keys2['request'] );
		$this->assertNotSame( $keys1['request'], $keys3['request'] );
		$this->assertNotSame( $keys1['request'], $keys4['request'] );
		$this->assertNotSame( $keys2['request'], $keys3['request'] );
		$this->assertNotSame( $keys2['request'], $keys4['request'] );
		$this->assertNotSame( $keys3['request'], $keys4['request'] );

		// Ensure that two identical requests have matching keychains.
		$cache5 = new Cache( 'https://example.com/test-2', $headers );
		$keys5 = $cache5->down_cache_keys();
		$this->assertSame( $keys3, $keys5 );
	}

	public function test_response_cache_success() {

	}

	public function test_response_cache_error() {
		$code    = 'test-error';
		$message = 'Testing response error';
		$error   = new \WP_Error( $code, $message );

		$cache = new Cache( 'localhost', [] );
		$cache->cache_response( $error );

		$response = $cache->load_request_from_cache();
		$this->assertWPError( $response );
		$this->assertSame( $error->get_error_code(), $response->get_error_code() );
		$this->assertSame( $error->get_error_message(), $response->get_error_message() );
	}
}
