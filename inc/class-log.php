<?php
/**
 * This file contains the Log class.
 *
 * @package Remote_Backstop
 */

namespace Remote_Backstop;

/**
 * Class Log
 *
 * @package Remote_Backstop
 */
class Log {

	use Singleton;

	/**
	 * Options key name for the log.
	 *
	 * @var string
	 */
	const OPTIONS_KEY = 'remote_backstop_log';

	/**
	 * Cache key for the log lock, used to prevent concurrency issues.
	 */
	const LOG_WRITE_LOCK = 'remote_backstop_log_lock';

	/**
	 * Cache group.
	 */
	const CACHE_GROUP = 'rb_down_log';

	/**
	 * Adds one time actions.
	 */
	public function setup() {
		/**
		 * Log when resources are down.
		 */
		add_action( 'remote_backstop_down_flag', [ $this, 'log_down' ], 10, 1 );
	}

	/**
	 * Get the complete down log.
	 *
	 * @return array
	 */
	public static function get_log() {
		$log = get_option( self::OPTIONS_KEY );
		return empty( $log ) ? [] : $log;
	}

	/**
	 * Get the last log time for a specific host.
	 *
	 * @param string $host Host.
	 *
	 * @return int|false The Unix timestamp, or false if the host is not found.
	 */
	public static function get_last_log_time( $host ) {
		$log = self::get_log();
		foreach ( $log as $entry ) {
			if ( $host === $entry['host'] ) {
				return (int) $entry['time'];
			}
		}
		return false;
	}

	/**
	 * Log when a resource is down.
	 *
	 * @param Cache $cache Cache object.
	 */
	public function log_down( $cache ) {
		$host = wp_parse_url( $cache->url, PHP_URL_HOST );

		$last_time = self::get_last_log_time( $host );
		if ( ! empty( $last_time ) ) {
			// Only add a new entry for the same host once every 5 minutes.
			if ( time() - $last_time > ( 5 * MINUTE_IN_SECONDS ) ) {
				// Update with new info.
				$this->add_to_log( $host, $cache->url, $cache->request_args );
			}
		} else {
			// Create the first entry for this host.
			$this->add_to_log( $host, $cache->url, $cache->request_args );
		}
	}

	/**
	 * Log the outage.
	 *
	 * @param string $host Host.
	 * @param string $url URL.
	 * @param array  $request_args Request args.
	 */
	public function add_to_log( $host, $url, $request_args ) {
		$log   = self::get_log();
		$entry = [
			'host'        => $host,
			'url'         => $url,
			'time'        => time(),
			'request_uri' => isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( $_SERVER['REQUEST_URI'] ) : '',
		];
		// Add this entry to the top of the log.
		array_unshift( $log, $entry );

		// Truncate to just the most recent 50 entries.
		$log = array_slice( $log, 0, 50 );

		if ( false === wp_cache_get( self::LOG_WRITE_LOCK, self::CACHE_GROUP ) ) {
			wp_cache_set( self::LOG_WRITE_LOCK, 1, self::CACHE_GROUP, 10 );
			update_option( self::OPTIONS_KEY, $log );
			wp_cache_delete( self::LOG_WRITE_LOCK, self::CACHE_GROUP );
		}
	}

	/**
	 * Display Down Log.
	 *
	 * Displays the down log on the settings page.
	 *
	 * @param string              $out     Field markup.
	 * @param \Fieldmanager_Field $fm      Field instance.
	 * @param mixed               $values  Current element values.
	 *
	 * @return string
	 */
	public static function display( $out, $fm, $values ) {
		ob_start();
		$log = self::get_log();
		if ( empty( $log ) ) :
			?>
			<h2><?php esc_html_e( 'No Log.', 'remote-backstop' ); ?></h2>
			<?php
		else :
			?>
			<h1><?php esc_html_e( 'Resource Down Log', 'remote-backstop' ); ?></h1>
			<div class="fm-item-description"><?php esc_html_e( 'Updated at 5 minute intervals.', 'remote-backstop' ); ?></div>
			<table class="wp-list-table widefat fixed striped">
				<thead>
				<tr>
					<td><?php esc_html_e( 'Date/Time', 'remote-backstop' ); ?></td>
					<td><?php esc_html_e( 'URL', 'remote-backstop' ); ?></td>
					<td><?php esc_html_e( 'Request URI', 'remote-backstop' ); ?></td>
				</tr>
				</thead>
				<tbody>
				<?php
				foreach ( $log as $log_entry ) :
					?>
					<tr>
						<td><?php echo esc_html( wp_date( 'm-d-Y g:i:s A', $log_entry['time'] ) ); ?></td>
						<td><?php echo esc_html( $log_entry['url'] ); ?></td>
						<td><?php echo esc_html( $log_entry['request_uri'] ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<?php
		endif;

		return $out . ob_get_clean();
	}
}

Log::instance();
