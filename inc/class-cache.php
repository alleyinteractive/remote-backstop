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
		$host = wp_parse_url( $this->url, PHP_URL_HOST );
		return [
			'host'    => 'rb-down:' . md5( $host ),
			'url'     => 'rb-down:' . md5( $this->url ),
			'request' => 'rb-down:' . $this->request_hash(),
		];
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
	 * @return bool|array Request array if the request is cached, false if not.
	 */
	public function load_request_from_cache() {
		return wp_cache_get( $this->request_hash(), 'rb-request' );
	}

	/**
	 * Cache a remote request.
	 *
	 * @param \WP_Error|array $response Request response.
	 */
	public function cache_response( $response ) {
		if ( is_wp_error( $response ) ) {
			$response = [
				'error'   => true,
				'code'    => $response->get_error_code(),
				'message' => $response->get_error_message(),
			];
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
		foreach ( $cache_keys as $cache_key ) {
			set_transient( $cache_key, 1, $duration );
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
		return (bool) get_transient( $cache_key );
	}
}
