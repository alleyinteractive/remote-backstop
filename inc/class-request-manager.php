<?php
/**
 * This file contains the Request_Manager class
 *
 * @package Remote_Backstop
 */

namespace Remote_Backstop;

use Exception;
use WP_Error;

/**
 * Request manager.
 *
 * This class intercepts and caches remote requests.
 */
class Request_Manager {
	/**
	 * Have the hooks been added? Ensures that this plugin doeasn't get hooked
	 * more than once.
	 *
	 * @var bool
	 */
	public static $hooks_added = false;

	/**
	 * Cache factory.
	 *
	 * @var Cache_Factory
	 */
	protected $cache_factory;

	/**
	 * Loggable object.
	 *
	 * @var Loggable
	 */
	public $log;

	/**
	 * Request_Manager constructor.
	 *
	 * @param Cache_Factory $cache_factory  Cache factory to create caches.
	 * @param Loggable      $log            Log object for logging events.
	 */
	public function __construct( Cache_Factory $cache_factory, Loggable $log ) {
		$this->log = $log;
		$this->set_cache_factory( $cache_factory );
		$this->add_hooks();
	}

	/**
	 * Set the cache factory used by the instance..
	 *
	 * @param Cache_Factory $cache_factory Cache factory to create caches.
	 */
	public function set_cache_factory( Cache_Factory $cache_factory ) {
		$this->cache_factory = $cache_factory;
	}

	/**
	 * Register request hooks.
	 */
	public function add_hooks() {
		if ( ! self::$hooks_added ) {
			add_filter( 'pre_http_request', [ $this, 'pre_http_request' ], 1, 3 );

			self::$hooks_added = true;
		}
	}

	/**
	 * Get a new cache object.
	 *
	 * @param string $url          The request URL.
	 * @param array  $request_args HTTP request arguments.
	 * @return Request_Cache|null
	 */
	public function get_cache( string $url, array $request_args ): Request_Cache {
		return $this->cache_factory->build_cache( $url, $request_args );
	}

	/**
	 * Intercept HTTP requests to backstop them.
	 *
	 * @throws Exception (and immediately catches it).
	 *
	 * @param false|array|\WP_Error $preempt      Whether to preempt an HTTP
	 *                                            request's return value.
	 * @param array                 $request_args HTTP request arguments.
	 * @param string                $url          The request URL.
	 * @return false|array|\WP_Error
	 */
	public function pre_http_request( $preempt, $request_args, $url ) {
		// Force proper data types.
		$url          = (string) $url;
		$request_args = (array) $request_args;

		// If this request has already been preempted, don't affect that.
		if ( false !== $preempt ) {
			return $preempt;
		}

		/**
		 * Filter request specifics to determine whether or not the request should be intercepted.
		 *
		 * @param bool                 Should this request be intercepted?
		 *                             Defaults to true for GET requests.
		 * @param string $url          The request URL.
		 * @param string $request_args HTTP request arguments.
		 */
		$should_intercept_request = apply_filters(
			'remote_backstop_should_intercept_request',
			'GET' === $request_args['method'],
			$url,
			$request_args
		);
		if ( ! $should_intercept_request ) {
			return $preempt;
		}

		// Likely impossible edge case, but bail if the url is empty.
		if ( empty( $url ) ) {
			return $preempt;
		}

		$defaults = [
			'scope_for_availability_check'       => 'host',
			'attempt_uncached_request_when_down' => false,
			'retry_after'                        => MINUTE_IN_SECONDS,
		];

		/**
		 * Filter options for handling this request in backstop.
		 *
		 * @param array  $options      {
		 *     Options for handling this request in backstop.
		 *
		 *     @type string $scope_for_availability_check       What scope to consider when considering
		 *                                                      a resource as "down".
		 *     @type bool   $attempt_uncached_request_when_down Option of running a request for which
		 *                                                      there is no cache, even though the
		 *                                                      resource is down. Should the request
		 *                                                      result in an error, that error will be
		 *                                                      cached and the request will not be
		 *                                                      attempted again during the 'outage'.
		 *     @type int $retry_after  Amount of time to flag a resource as
		 *                                                      down.
		 * }
		 * @param string $url          Request URL.
		 * @param string $request_args Request arguments.
		 */
		$options = apply_filters(
			'remote_backstop_request_options',
			$defaults,
			$url,
			$request_args
		);

		// Build a new cache for the request.
		$cache = $this->get_cache( $url, $request_args );

		try {
			if ( $cache->get_down_flag( $options['scope_for_availability_check'] ) ) {
				// If the resource is unavailable, check for cached data.
				$cached_response = $cache->load_response_from_cache();
				if ( false === $cached_response ) {
					if ( ! $options['attempt_uncached_request_when_down'] ) {
						// If the request won't be attempted, fail the request.
						throw new Exception();
					}
				} else {
					// There is a cached response, that will be used.
					throw new Exception();
				}
			}

			// Remove this method from the hook to avoid infinite loops.
			remove_filter( 'pre_http_request', [ $this, 'pre_http_request' ], 1 );

			// Run the full request.
			$response = $this->safe_wp_remote_request( $url, $request_args );

			// Re-add this filter for future requests.
			add_filter( 'pre_http_request', [ $this, 'pre_http_request' ], 1, 3 );

			// If the response was an error, attempt to return data from cache.
			if ( $this->response_is_error( $response ) ) {
				$cache->set_down_flag( $options['retry_after'] );
				$this->log->log_resource_downtime( $url, $request_args );
				throw new Exception();
			}

			/**
			 * Filters the cache time to live.
			 *
			 * By default, the cache has no expiration.
			 *
			 * @param int    $ttl          When to expire the cache, in seconds.
			 *                             Default 0, no expiration.
			 * @param string $url          Request URL.
			 * @param string $request_args Request arguments.
			 */
			$ttl = (int) apply_filters( 'remote_backstop_ttl', 0, $url, $request_args );

			// Cache the response.
			$cache->cache_response( $response, $ttl );

			// Return the successful response.
			return $response;
		} catch ( Exception $e ) {
			// Load the cached response if it hasn't already been loaded.
			if ( ! isset( $cached_response ) ) {
				$cached_response = $cache->load_response_from_cache();
			}

			if ( false !== $cached_response ) {
				$loaded_from_cache = true;
				$response          = $cached_response;
			} else {
				$loaded_from_cache = false;
				if ( ! isset( $response ) ) {
					// We're not making another request, and the response isn't cached.
					$response = new WP_Error(
						'unavailable',
						__( 'Resource is unavailable', 'remote-backstop' )
					);
				} else {
					// There isn't a cached response available, so cache the error.
					$cache->cache_response( $response );
				}
			}

			/**
			 * Filters the failed request response.
			 *
			 * @param array|\WP_Error $response          Response.
			 * @param bool            $loaded_from_cache Whether or not the
			 *                                           response was loaded
			 *                                           from cache.
			 * @param string          $url               Request URL.
			 * @param string          $request_args      Request arguments.
			 */
			return apply_filters(
				'remote_backstop_failed_request_response',
				$response,
				$loaded_from_cache,
				$url,
				$request_args
			);
		}
	}

	/**
	 * Is a given response an "error"?
	 *
	 * @param array|\WP_Error $response Request response.
	 * @return bool
	 */
	protected function response_is_error( $response ): bool {
		$is_error = is_wp_error( $response )
			|| wp_remote_retrieve_response_code( $response ) >= 500;

		/**
		 * Filters what is considered an "error" for the purposes of
		 * backstopping.
		 *
		 * By default, a response is an error if it is a WP_Error object or if
		 * the response code was >= 500.
		 *
		 * @param bool            $is_error Was this response an error?
		 * @param array|\WP_Error $response Response.
		 */
		return (bool) apply_filters(
			'remote_backstop_response_is_error',
			$is_error,
			$response
		);
	}

	/**
	 * Wrapper for wp_remote_request.
	 *
	 * Similar to vip_safe_wp_remote_get, as it ensures a max timeout of 3 seconds.
	 * Less forgiving than vip_safe_wp_remote_get which will retry a request 3 times.
	 * This request manager starts to pull back requests after just one failure.
	 *
	 * @param string $url URL.
	 * @param array  $request_args Request Args.
	 *
	 * @return array|WP_Error
	 */
	protected function safe_wp_remote_request( $url, $request_args ) {
		// Ensure a max timeout is set.
		if ( empty( $request_args['timeout'] ) ) {
			$request_args['timeout'] = 1;
		}

		// Ensure the timeout is at most 3 seconds.
		$request_args['timeout'] = min( 3, (float) $request_args['timeout'] );

		return wp_remote_request( $url, $request_args );
	}
}
