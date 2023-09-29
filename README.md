Remote Backstop
===============

A safety net for WordPress sites that depend on server-side remote requests.

## Overview

This plugin will cache remote requests that happen on a WordPress site. Should a remote resource become unavailable, the most recently-cached response will be served until the resource becomes available again, providing three distinct advantages:

1. When a site depends on an external resource to operate, and that resource becomes unavailable (especially in cases leading to long request times), that can lead to the site going down as well.
2. When a resource is down, we can help it resume normal functioning faster by reducing the amount of load put on it during a time of stress.
3. By serving the last good response for a resource, we mitigate the effect that this has on the end user. With this plugin, it's entirely possible that a resource may go down and later come back and the end user is none the wiser.

## Features

### Safe Defaults

Out of the box, only GET requests are cached, as it's assumed that other methods like POST, PUT, and DELETE are changing data. This behavior can be modified on a request-by-request basis using the `remote_backstop_should_intercept_request` filter. Requests are cached using the URL and any request arguments such as the request body, so any POST requests that don't change data would be safe to backstop using this plugin.

### Throttling

To reduce load on the troubled external resource, as well as impact on the current WordPress site, when the resource is determined to be "down", requests will be throttled to it for some period of time, the default for which is one minute. Requests can be scoped to different levels of granularity, either at the "host", "url", or "request" level, in determining if the resource should be considered "down" during this throttling period. By default, all requests are scoped at the "host" level, meaning if the host is `example.com`, no requests to `example.com` will be attempted until the throttle period expires regardless of the URI or other request arguments. If the scope is set to "url", then requests to `example.com/foo` will be throttled separately from `example.com/bar`. Requests scoped at the "request" level will checksum the entire request, including the body and headers (but not cookies). All of the above behavior is configurable using the `remote_backstop_request_options` filter.

### Logging

The plugin maintains a log of the last 50 events where it intervened. This log is presently only accessible by going directly to the object cache. To view the log using WP-CLI, one could call `wp cache get remote_backstop_log rb_down_log` or `wp shell` then execute `Remote_Backstop\Event_Log::get_log()`.

### Flexibility

Most of the plugin's functionality is easily manipulable using the provided filters. Beyond that, the `remote_backstop_request_manager()` function will return the `Request_Manager` object that orchestrates the plugin's functionality, and this object allows developers to replace wholesale components. For instance, if a developer did not want request data cached to the object cache and instead stored in a custom MySQL table, they could replace the cache factory that the `Request_Manager` uses with a custom service that changes that behavior.

## Caveats

* Cookies are not cached with the remote response.
* By default, this plugin **requires** a persistent object cache (e.g. memcached, redis).

## Customizations

### Filters

* `remote_backstop_request_options`: Filter options for handling a request in backstop.
    ```
    @param array  $options {
        Options for handling this request in backstop.

        @type string $scope_for_availability_check       What scope to consider when considering
                                                         a resource as "down". Defaults to 'host'.
        @type bool   $attempt_uncached_request_when_down Option of running a request for which
                                                         there is no cache, even though the
                                                         resource is down. Should the request
                                                         result in an error, that error will be
                                                         cached and the request will not be
                                                         attempted again during the 'outage'.
        @type int $retry_after                           Amount of time to flag a resource as
                                                         down.
    }
    @param string $url          Request URL.
    @param string $request_args Request arguments.
    ```
* `remote_backstop_failed_request_response`: Filters the failed request response.
    ```
    @param array|\WP_Error $response          Response.
    @param bool            $loaded_from_cache Whether or not the
                                              response was loaded
                                              from cache.
    @param string          $url               Request URL.
    @param string          $request_args      Request arguments.
    ```
* `remote_backstop_response_is_error`: Filters what is considered an "error" for the purposes of backstopping.
By default, a response is an error if it is a WP_Error object or if the response code was >= 500.
    ```
    @param bool            $is_error Was this response an error?
    @param array|\WP_Error $response Response.
    ```
* `remote_backstop_should_intercept_request`: Filters request specifics to determine whether or not a request should be intercepted.
    ```
    @param bool                 Should this request be intercepted?
                                Defaults to true for GET requests.
    @param string $url          The request URL.
    @param string $request_args HTTP request arguments.
    ```
