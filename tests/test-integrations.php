<?php
/**
 * Class Integrations_Test
 *
 * @package Remote_Backstop
 */

namespace Remote_Backstop\Tests;

use WP_UnitTestCase;

/**
 * Integration test cases.
 */
class Integrations_Test extends WP_UnitTestCase {

	/**
	 * A basic interception integration test.
	 */
	public function test_basic_cache_intercept() {
		$mock = new Mock_Http_Response();
		$mock->intercept_next_request()
		     ->with_body( 'test 1' );

		// Make the first request. This will cache the response.
		$response = wp_remote_get( 'https://example.com/test-1' );
		$this->assertSame( 'test 1', wp_remote_retrieve_body( $response ) );

		$mock = new Mock_Http_Response();
		$mock->intercept_next_request()
		     ->with_response_code( 500 )
		     ->with_body( 'error' );

		// The new mock will return an error and the cached response will return.
		$response = wp_remote_get( 'https://example.com/test-1' );
		$this->assertSame( 'test 1', wp_remote_retrieve_body( $response ) );

		$mock->intercept_next_request();

		// Test the test. The new domain will not be cached, and should hit the error response.
		$response = wp_remote_get( 'https://different-example.com/' );
		$this->assertSame( 'error', wp_remote_retrieve_body( $response ) );

		// Set up a mock that should never be queried, to verify the cache.
		$mock->intercept_next_request()
		     ->with_body( 'if you can read this, the test failed' );

		// Lastly, the response should hit the cache again.
		$response = wp_remote_get( 'https://example.com/test-1' );
		$this->assertSame( 'test 1', wp_remote_retrieve_body( $response ) );
	}
}
