<?php
/**
 * This file contains the Cache_Factory class
 *
 * @package Remote_Backstop
 */

namespace Remote_Backstop;

/**
 * Cache factory. This creates new cache objects.
 */
class Cache_Factory {
	/**
	 * Factory method to build a new Request_Cache object.
	 *
	 * @param string $url          Request URL.
	 * @param array  $request_args Request args.
	 * @return Request_Cache
	 */
	public static function build_cache( string $url, array $request_args ): Request_Cache {
		return new Cache( $url, $request_args );
	}
}
