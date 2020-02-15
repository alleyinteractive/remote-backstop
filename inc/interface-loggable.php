<?php
/**
 * This file contains the Loggable Interface.
 *
 * @package Remote_Backstop
 */

namespace Remote_Backstop;

/**
 * Interface Loggable
 *
 * @package Remote_Backstop
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
	 * @param Cache $cache  Cache object.
	 */
	public function log_down( $cache );

	/**
	 * Log the outage.
	 *
	 * @param string $host          Host.
	 * @param string $url           URL.
	 * @param array  $request_args  Request args.
	 */
	public function add_to_log( $host, $url, $request_args );

}
