Remote Backstop
===============

A safety net for WordPress sites that depend on server-side remote requests.

## Overview

This plugin will cache all remote requests that happen on a WordPress site. Should a remote resource become unavailable, the most recently-cached response will be served until the resource becomes available again, providing three distinct advantages:

1. When a site depends on an external resource to operate, and that resource becomes unavailable (especially in cases leading to long request times), that can lead to the site going down as well.
2. When a resource is down, we can help it resume normal functioning faster by reducing the amount of load put on it during a time of stress.
3. By serving the last good response for a resource, we mitigate the effect that this has on the end user. With this plugin, it's entirely possible that a resource may go down and later come back and the end user is none the wiser.

## Caveats

* Cookies are not cached with the remote response.
* This plugin **requires** an external object cache (e.g. memcached, redis).

## Customizations

### Filters

* `remote_backstop_request_options`: Filter options for handling a request in backstop.
    ```
    @param array  $options {
        Options for handling this request in backstop.

        @type string $scope_for_availability_check       What scope to consider when considering
                                                         a resource as "down".
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
* `'remote_backstop_should_intercept_request'`: Filters request specifics to determine whether or not a request should be intercepted.
    ```
    @param bool                 Should this request be intercepted?
                                Defaults to true for GET requests.
    @param string $url          The request URL.
    @param string $request_args HTTP request arguments.
    ```
