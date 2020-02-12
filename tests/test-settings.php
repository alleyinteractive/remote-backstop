<?php
/**
 * Class SettingsTest
 *
 * @package Remote_Backstop
 */

namespace Remote_Backstop\Tests;

use Remote_Backstop\Log;
use Remote_Backstop\Settings;
use WP_UnitTestCase;

/**
 * Cache test cases.
 */
class SettingsTest extends WP_UnitTestCase {

	/**
	 * Tests that we only have one entry to multiple requests
	 */
	public function test_custom_settings_override() {

		update_option( Settings::OPTIONS_KEY,
			[
				'global' =>
					[
						'disable'                            => 0,
						'ttl'                                => 43200,
						'scope_for_availability_check'       => 'request',
						'attempt_uncached_request_when_down' => '1',
					],
			]
		);
		$defaults = [
			'scope_for_availability_check'       => 'host',
			'attempt_uncached_request_when_down' => false,
			'retry_after'                        => MINUTE_IN_SECONDS,
		];
		$options  = Settings::instance()->remote_backstop_request_options( $defaults );

		$expected = [
			'scope_for_availability_check'       => 'request',
			'attempt_uncached_request_when_down' => '1',
			'retry_after'                        => 60,
			'disable'                            => 0,
			'ttl'                                => 43200,
		];

		$this->assertEquals( $expected, $options );
	}

}
