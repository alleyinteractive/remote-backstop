<?php
/**
 * This file contains the Log class.
 *
 * @package Remote_Backstop
 */

namespace Remote_Backstop;

/**
 * Class Log
 *
 * @package Remote_Backstop
 */
class Event_Log implements Loggable {

	/**
	 * Cache key name for the log.
	 *
	 * @var string
	 */
	const OPTIONS_KEY = 'remote_backstop_log';

	/**
	 * Cache key for the log lock, used to prevent concurrency issues.
	 */
	const LOG_WRITE_LOCK = 'remote_backstop_log_lock';

	/**
	 * Cache group.
	 */
	const CACHE_GROUP = 'rb_down_log';

	/**
	 * Get the complete down log.
	 *
	 * @return array
	 */
	public static function get_log() {
		$log = wp_cache_get( self::OPTIONS_KEY );
		return empty( $log ) ? [] : $log;
	}

	/**
	 * Get the last log time for a specific host.
	 *
	 * @param string $host Host.
	 *
	 * @return int|false The Unix timestamp, or false if the host is not found.
	 */
	public static function get_last_log_time( $host ) {
		$log = self::get_log();
		foreach ( $log as $entry ) {
			if ( $host === $entry['host'] ) {
				return (int) $entry['time'];
			}
		}
		return false;
	}

	/**
	 * Log when a resource is down.
	 *
	 * @param Cache $cache Cache object.
	 */
	public function log_down( $cache ) {
		$host = wp_parse_url( $cache->url, PHP_URL_HOST );

		$last_time = self::get_last_log_time( $host );
		if ( ! empty( $last_time ) ) {
			// Only add a new entry for the same host once every 5 minutes.
			if ( time() - $last_time > ( 5 * MINUTE_IN_SECONDS ) ) {
				// Update with new info.
				$this->add_to_log( $host, $cache->url, $cache->request_args );
			}
		} else {
			// Create the first entry for this host.
			$this->add_to_log( $host, $cache->url, $cache->request_args );
		}
	}

	/**
	 * Log the outage.
	 *
	 * @param string $host Host.
	 * @param string $url URL.
	 * @param array  $request_args Request args.
	 */
	public function add_to_log( $host, $url, $request_args ) {
		$log   = self::get_log();
		$entry = [
			'host'        => $host,
			'url'         => $url,
			'time'        => time(),
			'request_uri' => isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( $_SERVER['REQUEST_URI'] ) : '',
		];
		// Add this entry to the top of the log.
		array_unshift( $log, $entry );

		// Truncate to just the most recent 50 entries.
		$log = array_slice( $log, 0, 50 );

		if ( false === wp_cache_get( self::LOG_WRITE_LOCK, self::CACHE_GROUP, true ) ) {
			wp_cache_set( self::LOG_WRITE_LOCK, 1, self::CACHE_GROUP, 10 );
			wp_cache_set( self::OPTIONS_KEY, $log );
			wp_cache_delete( self::LOG_WRITE_LOCK, self::CACHE_GROUP );
		}
	}
}
