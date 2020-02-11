<?php
/**
 * This file contains the Settings class.
 *
 * @package Remote_Backstop
 */

namespace Remote_Backstop;

class Log {

	use Singleton;

	/**
	 * Options key name for the log.
	 *
	 * @var string
	 */
	const OPTIONS_KEY = 'remote_backstop_log';

	/**
	 * Adds one time actions.
	 */
	public function setUp() {
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
	 * @return bool|mixed
	 */
	public static function get_last_log_time( $host ) {
		$log = self::get_log();
		foreach ( $log as $entry ) {
			if ( $host === $entry['host'] ) {
				return $entry['time'];
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
			if ( $last_time > 5 * MINUTE_IN_SECONDS ) {
				// Update with new info.
				$this->add_to_log( $host, $cache->url, $cache->request_args );
			}
		} else {
			// Create an entry for this host.
			$this->add_to_log( $host, $cache->url, $cache->request_args );
		}
	}

	/**
	 * Log the outage.
	 *
	 * @param $host string Host.
	 * @param $url string URL.
	 * @param $request_args array Request args.
	 */
	public function add_to_log( $host, $url, $request_args ) {
		$log = self::get_log();
		$entry = [
			'host' => $host,
			'url' => $url,
			'time' => time(), // @todo localized
			'args' => $request_args,
		];
		// Add this entry to the top of the log.
		array_unshift( $log, $entry );

		// Truncate to just the most recent 50 entries.
		$log = array_slice( $log, 0, 50 );
		// @todo prevent concurrency
		update_option( self::OPTIONS_KEY, $log );
	}

	/**
	 * Display Down Log.
	 *
	 * Displays the down log on the settings page.
	 *
	 * @param string               $out     Field markup.
	 * @param \Fieldmanager_Field  $fm      Field instance.
	 * @param mixed                $values  Current element values.
	 *
	 * @return string
	 */
	public static function display( $out, $fm, $values ) {
		ob_start();
		$log = self::get_log();
		if ( empty( $log ) ) :
			?><h2><?php esc_html_e( 'No Log.', 'remote-backstop' ); ?></h2><?php

		else :
			?>
			<table class="wp-list-table widefat fixed striped">
				<thead>
				<tr>
					<td><?php esc_html_e( 'Date/Time', 'remote-backstop' ); ?></td>
					<td><?php esc_html_e( 'URL', 'remote-backstop' ); ?></td>
					<td><?php esc_html_e( 'Args', 'remote-backstop' ); ?></td>
				</tr>
				</thead>
				<tbody>
				<?php
				foreach ( $log as $log_entry ) :
					?>
					<tr>
						<td><?php echo esc_html( $log_entry['time'] ); ?></td>
						<td><?php echo esc_html( $log_entry['url'] ); ?></td>
						<td><?php echo esc_html( wp_json_encode( $log_entry['args'] ) ); ?></td>
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
