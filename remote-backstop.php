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

require_once __DIR__ . '/inc/interface-request-cache.php';
require_once __DIR__ . '/inc/class-cache.php';
require_once __DIR__ . '/inc/class-request-manager.php';

// Register the request manager and add the hooks.
$remote_backstop_request_manager = new Request_Manager();
$remote_backstop_request_manager->add_hooks();
