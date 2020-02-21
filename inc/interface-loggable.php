<?php
/**
 * This file contains the Loggable Interface
 *
 * @package Remote_Backstop
 */

namespace Remote_Backstop;

/**
 * Loggable interface.
 */
interface Loggable {
	/**
	 * Get the complete log.
	 *
	 * @return array
	 */
	public static function get_log();

	/**
	 * Get the last log time for a specific host.
	 *
	 * @param string $host  Host.
	 *
	 * @return int|false The Unix timestamp, or false if the host is not found.
	 */
	public function get_last_log_time( $host );

	/**
	 * Log when a resource is down.
	 *
	 * @param string $url          Request URL.
	 * @param array  $request_args Request arguments.
	 */
	public function log_resource_downtime( string $url, array $request_args = [] ): void;
}
