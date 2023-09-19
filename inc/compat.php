<?php
/**
 * Compatibility with other plugins.
 */

namespace Remote_Backstop;

/**
 * Ignore certain keys when caching requests.
 *
 * @param array $keys Cache Keys.
 *
 * @return array
 */
function ignore_cache_keys( array $keys ): array {
	$keys[] = '_qm_key'; // Query Monitor.
	return $keys;
}
add_filter( 'rb_request_cache_keys_to_ignore', __NAMESPACE__ . '\ignore_cache_keys', 10, 2 );
