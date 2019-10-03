<?php
/**
 * This file contains the Cache class
 *
 * @package Remote_Backstop
 */

namespace Remote_Backstop;

/**
 * Cache manager class.
 */
class Cache {
	/**
	 * Get the cache keys storing the "downtime" flags.
	 *
	 * @param string $url Request URL.
	 * @param array  $r   Request args.
	 * @return array {
	 *     Cache keys.
	 *
	 *     @type string $host    Cache key for the host.
	 *     @type string $url     Cache key for the url.
	 *     @type string $request Cache key for the full request.
	 * }
	 */
	public static function down_cache_keys( string $url, array $r ): array {
		$host = wp_parse_url( $url, PHP_URL_HOST );
		return [
			'host'    => 'rb-down:' . md5( $host ),
			'url'     => 'rb-down:' . md5( $url ),
			'request' => 'rb-down:' . self::request_hash( $url, $r ),
		];
	}

	/**
	 * Get the cache key for a request.
	 *
	 * @param string $url Request URL.
	 * @param array  $r   Request args.
	 * @return string Cache key.
	 */
	public static function request_hash( string $url, array $r ): string {
		return md5( $url . wp_json_encode( $r ) );
	}

	/**
	 * Load a request from cache.
	 *
	 * @param string $url Request URL.
	 * @param array  $r   Request args.
	 * @return bool|array Request array if the request is cached, false if not.
	 */
	public static function load_request_from_cache( string $url, array $r ) {
		return wp_cache_get( self::request_hash( $url, $r ), 'rb-request' );
	}

	/**
	 * Cache a remote request.
	 *
	 * @param \WP_Error|array $response Request response.
	 * @param string          $url      Request URL.
	 * @param array           $r        Request args.
	 */
	public static function cache_response( $response, string $url, array $r ) {
		if ( is_wp_error( $response ) ) {
			$response = [
				'error'   => true,
				'code'    => $response->get_error_code(),
				'message' => $response->get_error_message(),
			];
		}
		wp_cache_set( self::request_hash( $url, $r ), $response, 'rb-request' );
	}

	/**
	 * Cache downtime.
	 *
	 * @param string $url      Request URL.
	 * @param array  $r        Request args.
	 * @param int    $duration How long to cache. Defaults to 1 minute.
	 */
	public static function set_down_flag( string $url, array $r, int $duration = MINUTE_IN_SECONDS ) {
		$cache_keys = self::down_cache_keys( $url, $r );
		foreach ( $cache_keys as $cache_key ) {
			set_transient( $cache_key, 1, $duration );
		}
	}

	/**
	 * Get the cached flag determining if a resource is down (unavailable).
	 *
	 * @param string $url         Request URL.
	 * @param array  $r           Request args.
	 * @param string $granularity Optional. How granular should a request check
	 *                            for downtime. Defaults to 'host', also accepts
	 *                            'url' or 'request'.
	 * @return bool True if the resource is down, false if not.
	 */
	public static function get_down_flag( string $url, array $r, string $granularity = 'host' ): bool {
		$cache_keys = self::down_cache_keys( $url, $r );
		$cache_key  = $cache_keys[ $granularity ] ?? $cache_keys['host'];
		return (bool) get_transient( $cache_key );
	}
}
