<?php
/**
 * Class Remote_Manager_Test
 *
 * @package Remote_Backstop
 */

namespace Remote_Backstop\Tests;

use Remote_Backstop\Cache_Factory;
use WP_Error;
use WP_UnitTestCase;
use function Remote_Backstop\remote_backstop_request_manager;

/**
 * Remote Manager test cases.
 */
class Remote_Manager_Test extends WP_UnitTestCase {
	protected $cache;
	protected $cache_factory;

	public function set_up() {
		parent::set_up();

		// Set the request manager's cache class to a mock that we'll use.
		$this->cache = $this->createMock( '\Remote_Backstop\Cache' );
		$this->cache_factory = $this->createMock( '\Remote_Backstop\Cache_Factory' );
		$this->cache_factory->method( 'build_cache' )
		              ->willReturn( $this->cache );
		remote_backstop_request_manager()->set_cache_factory( $this->cache_factory );
	}

	public function tear_down() {
		// Restore the request manager's cache factory to the default.
		remote_backstop_request_manager()->set_cache_factory( new Cache_Factory() );

		parent::tear_down();
	}

	/**
	 * This test is a control for some other tests in the class, to verify that
	 * the method of testing intercepted requests works.
	 */
	public function test_successful_requests() {
		// The cache should be instantiated once in this request.
		$this->cache_factory->expects( $this->once() )->method( 'build_cache' );

		// Don't let the request actually run.
		add_filter(
			'pre_http_request',
			function() {
				return new WP_Error( 'test_successful_requests' );
			},
			PHP_INT_MAX
		);

		$response = wp_remote_get( 'http://localhost' ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.wp_remote_get_wp_remote_get
		$this->assertWPError( $response );
		$this->assertSame( 'test_successful_requests', $response->get_error_code() );
	}

	public function test_preempted_requests() {
		// The cache should never be checked in this request.
		$this->cache_factory->expects( $this->never() )->method( 'build_cache' );

		// Preempt the request.
		add_filter(
			'pre_http_request',
			function() {
				return new WP_Error( 'test_preempted_requests' );
			},
			0
		);

		$response = wp_remote_get( 'http://localhost' ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.wp_remote_get_wp_remote_get
		$this->assertWPError( $response );
		$this->assertSame( 'test_preempted_requests', $response->get_error_code() );
	}

	public function test_non_get_requests() {
		// The cache should never be checked in this request.
		$this->cache_factory->expects( $this->never() )->method( 'build_cache' );

		// Don't let the request actually run.
		add_filter(
			'pre_http_request',
			function() {
				return new WP_Error( 'test_non_get_requests' );
			},
			PHP_INT_MAX
		);

		$response = wp_remote_post( 'http://localhost' );
		$this->assertWPError( $response );
		$this->assertSame( 'test_non_get_requests', $response->get_error_code() );
	}

	public function test_do_not_hit_down_resources() {
		$this->cache->method( 'get_down_flag' )->willReturn( true );
		$this->cache->method( 'load_response_from_cache' )->willReturn( false );

		$response = wp_remote_get( 'https://example.com/' ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.wp_remote_get_wp_remote_get

		$this->assertWPError( $response );
		$this->assertSame( 'unavailable', $response->get_error_code() );
	}

	public function test_cached_down_resources() {
		$mock1 = new Mock_Http_Response();
		$mock1->with_body( 'cached response' );

		// This should not be hit.
		$mock2 = new Mock_Http_Response();
		$mock2->intercept_next_request()
		      ->with_body( 'uncached response' );

		$this->cache->method( 'get_down_flag' )->willReturn( true );
		$this->cache->method( 'load_response_from_cache' )->willReturn( $mock1->to_array() );

		$response = wp_remote_get( 'https://example.com/' ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.wp_remote_get_wp_remote_get

		$this->assertSame( 'cached response', wp_remote_retrieve_body( $response ) );
	}

	public function test_uncached_down_resources() {
		$mock1 = new Mock_Http_Response();
		$mock1->intercept_next_request()
		      ->with_body( 'uncached response' );

		// Change the options to attempt uncached requests even when down.
		add_filter(
			'remote_backstop_request_options',
			function( $options ) {
				$options['attempt_uncached_request_when_down'] = true;
				return $options;
			}
		);

		$this->cache->method( 'get_down_flag' )->willReturn( true );
		$this->cache->method( 'load_response_from_cache' )->willReturn( false );

		$response = wp_remote_get( 'https://example.com/' ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.wp_remote_get_wp_remote_get

		$this->assertSame( 'uncached response', wp_remote_retrieve_body( $response ) );
	}
}
