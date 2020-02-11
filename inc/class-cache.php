<?php
/**
 * This file contains the Cache class
 *
 * @package Remote_Backstop
 */

namespace Remote_Backstop;

use WP_Error;

/**
 * Cache manager class.
 */
class Cache implements Request_Cache {
	/**
	 * Request URL.
	 *
	 * @var string
	 */
	protected $url;

	/**
	 * Request arguments.
	 *
	 * @var array
	 */
	protected $request_args;

	/**
	 * Cache constructor.
	 *
	 * @param string $url          Request URL.
	 * @param array  $request_args Request args.
	 */
	public function __construct( string $url, array $request_args ) {
		$this->url          = $url;
		$this->request_args = $request_args;
	}

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
	public function down_cache_keys(): array {
		$keys = [
			'host'    => null,
			'url'     => null,
			'request' => null,
		];

		if ( ! empty( $this->url ) ) {
			$keys['url']     = 'rb-down:' . md5( $this->url );
			$keys['request'] = 'rb-down:' . $this->request_hash();

			$host = wp_parse_url( $this->url, PHP_URL_HOST );
			if ( ! empty( $host ) ) {
				$keys['host'] = 'rb-down:' . md5( $host );
			}
		}

		return $keys;
	}

	/**
	 * Get the cache key for a request.
	 *
	 * @return string Cache key.
	 */
	public function request_hash(): string {
		return md5( $this->url . wp_json_encode( $this->request_args ) );
	}

	/**
	 * Load a request from cache.
	 *
	 * @return bool|array|WP_Error Response array or WP_Error if the request is
	 *                             cached, false if not.
	 */
	public function load_response_from_cache() {
		$response = wp_cache_get( $this->request_hash(), 'rb-request' );
		if ( is_array( $response ) && ! empty( $response['error'] ) ) {
			$response = new WP_Error( $response['code'], $response['message'] );
		}

		return $response;
	}

	/**
	 * Cache a remote request.
	 *
	 * @todo Consider support for including cookies in the response cache.
	 *
	 * @param WP_Error|array $response Request response.
	 */
	public function cache_response( $response ) {
		if ( is_wp_error( $response ) ) {
			$response = [
				'error'   => true,
				'code'    => $response->get_error_code(),
				'message' => $response->get_error_message(),
			];
		} elseif ( ! empty( $response['cookies'] ) ) {
			// Don't ever cache response cookies, just in case.
			$response['cookies'] = [];
		}
		wp_cache_set( $this->request_hash(), $response, 'rb-request' );
	}

	/**
	 * Cache downtime.
	 *
	 * @param int $duration How long to cache. Defaults to 1 minute.
	 */
	public function set_down_flag( int $duration = MINUTE_IN_SECONDS ) {
		$cache_keys = $this->down_cache_keys();
		// @todo do action
		foreach ( $cache_keys as $cache_key ) {
			if ( ! empty( $cache_key ) ) {
				set_transient( $cache_key, 1, $duration );
			}
		}
	}

	/**
	 * Get the cached flag determining if a resource is down (unavailable).
	 *
	 * @param string $granularity Optional. How granular should a request check
	 *                            for downtime. Defaults to 'host', also accepts
	 *                            'url' or 'request'.
	 * @return bool True if the resource is down, false if not.
	 */
	public function get_down_flag( string $granularity = 'host' ): bool {
		$cache_keys = $this->down_cache_keys();
		$cache_key  = $cache_keys[ $granularity ] ?? $cache_keys['host'];

		// Ensure we have a valid cache key before accessing it.
		if ( ! empty( $cache_key ) ) {
			return (bool) get_transient( $cache_key );
		}

		// If we failed in looking up the cache, fail gracefully.
		return true;
	}
}
