<?php
/**
 * Class LogTest
 *
 * @package Remote_Backstop
 */

namespace Remote_Backstop\Tests;

use Remote_Backstop\Event_Log;
use WP_UnitTestCase;

/**
 * Cache test cases.
 */
class LogTest extends WP_UnitTestCase {

	private $log;

	public function set_up() {
		$this->log = new Event_Log();
	}

	/**
	 * Tests that we only have one entry to multiple requests
	 */
	public function test_log_hosts() {
		// Do 5 requests to the same host in rapid succession; only 1 log entry should exist.
		for ( $i = 0; $i < 5; $i++ ) {
			$this->log->log_resource_downtime( 'https://example.com/test-1', [ 'method' => 'GET' ] );
		}

		// Call the function that would run on shutdown hook.
		$this->log->write_events_to_log();

		$log = Event_Log::get_log();
		$this->assertEquals( 1, count( $log ) );
		$this->assertSame( 'example.com', $log[0]['host'] );
	}

	/**
	 * Tests that the log is truncated to 50 entries
	 * with the most recent entries being saved.
	 */
	public function test_log_max() {
		for ( $i = 0; $i < 52; $i++ ) {
			$this->log->log_resource_downtime(
				sprintf( 'https://example-%d.com/test', $i ),
				[ 'method' => 'GET' ]
			);
		}

		// Call the function that would run on shutdown hook.
		$this->log->write_events_to_log();

		$log = Event_Log::get_log();
		$this->assertEquals( 50, count( $log ) );
		$this->assertSame( 'example-51.com', $log[0]['host'] );
		$this->assertNotEmpty( $log[0]['time'] );
	}
}
