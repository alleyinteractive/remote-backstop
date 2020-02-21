<?php
/**
 * This file contains the Request_Cache interface
 *
 * @package Remote_Backstop
 */

namespace Remote_Backstop;

/**
 * Cache manager class.
 */
interface Request_Cache {
	/**
	 * Cache constructor.
	 *
	 * @param string $url          Request URL.
	 * @param array  $request_args Request args.
	 */
	public function __construct( string $url, array $request_args );

	/**
	 * Get the cache keys storing the "downtime" flags.
	 *
	 * @return array {
	 *     Cache keys.
	 *
	 *     @type string $host    Cache key for the host.
	 *     @type string $url     Cache key for the url.
	 *     @type string $request Cache key for the full request.
	 * }
	 */
	public function down_cache_keys(): array;

	/**
	 * Get the cache key for a request.
	 *
	 * @return string Cache key.
	 */
	public function request_hash(): string;

	/**
	 * Load a response from cache.
	 *
	 * @return bool|array|\WP_Error Response array or WP_Error if the request is
	 *                              cached, false if not.
	 */
	public function load_response_from_cache();

	/**
	 * Cache a remote request.
	 *
	 * @param \WP_Error|array $response  Request response.
	 * @param int             $ttl       Cache time to live.
	 */
	public function cache_response( $response, int $ttl = 0 );

	/**
	 * Cache downtime.
	 *
	 * @param int $duration How long to cache. Defaults to 1 minute.
	 */
	public function set_down_flag( int $duration = MINUTE_IN_SECONDS );

	/**
	 * Get the cached flag determining if a resource is down (unavailable).
	 *
	 * @param string $granularity Optional. How granular should a request check
	 *                            for downtime. Defaults to 'host', also accepts
	 *                            'url' or 'request'.
	 * @return bool True if the resource is down, false if not.
	 */
	public function get_down_flag( string $granularity = 'host' ): bool;
}
