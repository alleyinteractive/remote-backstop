<?php
/**
 * Class CacheTest
 *
 * @package Remote_Backstop
 */

namespace Remote_Backstop\Tests;

use Remote_Backstop\Log;
use WP_UnitTestCase;

/**
 * Cache test cases.
 */
class LogTest extends WP_UnitTestCase {

	/**
	 * A single example test.
	 */
	public function test_log() {
		$mock = new Mock_Http_Response();
		$mock->intercept_next_request()
		     ->with_body( 'test 1' );

		// Prime the cache.
		$response = wp_remote_get( 'https://example.com/test-1' );

		$mock = new Mock_Http_Response();
		$mock->intercept_next_request()
		     ->with_response_code( 500 )
		     ->with_body( 'error' );

		// Repeat the response, but this time an error is received.
		$response = wp_remote_get( 'https://example.com/test-1' );

		$log = Log::get_log();

		$this->assertSame( 'example.com', $log[0]['host'] );

	}

}
