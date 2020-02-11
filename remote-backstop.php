<?php
/**
 * Plugin Name:     Remote Backstop
 * Plugin URI:      https://github.com/alleyinteractive/remote-backstop
 * Description:     Safety net for sites depending on remote requests
 * Author:          Matthew Boynes
 * Author URI:      https://www.alley.co/
 * Text Domain:     remote-backstop
 * Domain Path:     /languages
 * Version:         0.1.0
 *
 * @package         Remote_Backstop
 */

namespace Remote_Backstop;

require_once __DIR__ . '/inc/trait-singleton.php';
require_once __DIR__ . '/inc/interface-request-cache.php';
require_once __DIR__ . '/inc/class-cache.php';
require_once __DIR__ . '/inc/class-cache-factory.php';
require_once __DIR__ . '/inc/class-request-manager.php';
require_once __DIR__ . '/inc/class-log.php';
require_once __DIR__ . '/inc/class-settings.php';


/**
 * Create and return the request manager. If the request manager has already
 * been registered, this simply returns the operating manager.
 *
 * @return Request_Manager
 */
function remote_backstop_request_manager(): Request_Manager {
	static $request_manager;

	if ( ! isset( $request_manager ) ) {
		// Register the request manager and add the hooks.
		$request_manager = new Request_Manager( new Cache_Factory() );
	}

	return $request_manager;
}
remote_backstop_request_manager();
