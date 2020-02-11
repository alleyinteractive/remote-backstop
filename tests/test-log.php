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
	 * Tests that we only have one entry to multiple requests
	 */
	public function test_log_hosts() {

		$mock = new Mock_Http_Response();

		// Do 5 requests to the same host in rapid succession; only 1 log entry should exist.
		for ( $i = 0; $i < 5; $i++ ) {
			$mock->intercept_next_request()
			     ->with_response_code( 500 )
			     ->with_body( 'error' );

			$response = wp_remote_get( 'https://example.com/test-1' );
		}
		$log = Log::get_log();

		$this->assertEquals( 1, count( $log ) );
		$this->assertSame( 'example.com', $log[0]['host'] );

	}

	public function test_log_max() {
		$mock = new Mock_Http_Response();

		// Do 5 requests to the same host in rapid succession; only 1 log entry should exist.
		for ( $i = 0; $i < 100; $i++ ) {
			$mock->intercept_next_request()
			     ->with_response_code( 500 )
			     ->with_body( 'error' );

			// Change the host with each request.
			$response = wp_remote_get( sprintf( 'https://example-%d.com/test', $i ) );
		}
		$log = Log::get_log();
		$this->assertEquals( 50, count( $log ) );
		$this->assertSame( 'example-99.com', $log[0]['host'] );
	}

}
