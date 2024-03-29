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
	const LOG_CACHE_KEY = 'remote_backstop_log';

	/**
	 * Cache key for the log lock, used to prevent concurrency issues.
	 */
	const LOG_WRITE_LOCK = 'remote_backstop_log_lock';

	/**
	 * Cache group.
	 */
	const CACHE_GROUP = 'rb_down_log';

	/**
	 * Stores the events until the shutdown hook.
	 *
	 * @var array
	 */
	protected $events = [];

	/**
	 * Has the shutdown event been hooked? The shutdown action is hooked into
	 * once an error event has been logged for writing.
	 *
	 * @var bool
	 */
	protected $shutdown_hooked = false;

	/**
	 * Get the complete down log from cache.
	 *
	 * @return array
	 */
	public static function get_log(): array {
		$log = wp_cache_get( self::LOG_CACHE_KEY, self::CACHE_GROUP, true );
		return empty( $log ) ? [] : $log;
	}

	/**
	 * Get the last log time for a specific host.
	 *
	 * @param string $host Host.
	 *
	 * @return int|false The Unix timestamp, or false if the host is not found.
	 */
	public function get_last_log_time( $host ) {
		$log = $this->events + self::get_log();
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
	 * @param string $url          Request URL.
	 * @param array  $request_args Request arguments.
	 */
	public function log_resource_downtime( string $url, array $request_args = [] ): void {
		$host = wp_parse_url( $url, PHP_URL_HOST );

		$last_time = $this->get_last_log_time( $host );
		if ( ! empty( $last_time ) ) {
			// Only add a new entry for the same host once every 5 minutes.
			if ( time() - $last_time > ( 5 * MINUTE_IN_SECONDS ) ) {
				// Update with new info.
				$this->record_event( $host, $url, $request_args );
			}
		} else {
			// Create the first entry for this host.
			$this->record_event( $host, $url, $request_args );
		}
	}

	/**
	 * Log the outage.
	 *
	 * @param string $host Host.
	 * @param string $url URL.
	 * @param array  $request_args Request args.
	 */
	protected function record_event( $host, $url, $request_args ) {
		$entry = [
			'host'        => $host,
			'url'         => $url,
			'time'        => time(),
			'request_uri' => isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( $_SERVER['REQUEST_URI'] ) : '',
		];
		// Add this entry to the top of the log.
		array_unshift( $this->events, $entry );

		// Ensure that the event gets written at shutdown.
		$this->add_shutdown_hook();
	}

	/**
	 * Writes the events to the cache.
	 *
	 * Called on the shutdown hook. This will attempt up to three times to write
	 * the events to the log cache, checking a write lock each time. If there is
	 * an active lock, it will `usleep()` for 0.01s. Therefore, this method may
	 * sleep for up to 0.02s total during shutdown.
	 */
	public function write_events_to_log() {
		// Because we're using a write lock, try 3 times in case it's locked.
		for ( $i = 0; $i < 3; $i++ ) {
			if ( false === wp_cache_get( self::LOG_WRITE_LOCK, self::CACHE_GROUP, true ) ) {
				wp_cache_set( self::LOG_WRITE_LOCK, 1, self::CACHE_GROUP, 10 );
				$log = self::get_log();
				$log = array_merge( $this->events, $log );
				// Truncate to just the most recent 50 entries.
				$log = array_slice( $log, 0, 50 );
				wp_cache_set( self::LOG_CACHE_KEY, $log, self::CACHE_GROUP );
				wp_cache_delete( self::LOG_WRITE_LOCK, self::CACHE_GROUP );
				return;
			} elseif ( $i < 2 ) {
				// Wait for 0.01 seconds before trying again.
				usleep( 10000 );
			}
		}
	}

	/**
	 * Clear the log for the current request, as well as the in memory log.
	 * Useful for unit testing.
	 */
	public function clear_log() {
		$this->events = [];
		wp_cache_delete( self::LOG_CACHE_KEY, self::CACHE_GROUP );
	}

	/**
	 * Add the shutdown hook to write the log events.
	 */
	protected function add_shutdown_hook() {
		if ( ! $this->shutdown_hooked ) {
			add_action( 'shutdown', [ $this, 'write_events_to_log' ] );
			$this->shutdown_hooked = true;
		}
	}
}
