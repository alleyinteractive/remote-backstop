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

	public function setUp() {
		add_filter( 'remote_backstop_failed_request_response', [ $this, 'log_failed_requests' ], 10, 4 );
	}

	public static function get_log() {
		$log = get_transient( self::OPTIONS_KEY );
		return empty( $log ) ? [] : $log;
	}

	public static function get_last_log_time( $host ) {
		$log = self::get_log();
		foreach ( $log as $entry ) {
			if ( $host === $entry['host'] ) {
				return $entry['time'];
			}
		}
		return false;
	}
	public function log_failed_requests( $response, $loaded_from_cache, $url, $request_args ) {
		$host = wp_parse_url( $url, PHP_URL_HOST );
		$last_time = self::get_last_log_time( $host );
		if ( ! empty( $last_time ) ) {
			// Only update
			if ( $last_time > 5 * MINUTE_IN_SECONDS ) {
				// Update with new info.
				$this->add_to_log( $host, $url, $loaded_from_cache );
			}
		} else {
			// Create an entry for this host.
			$this->add_to_log( $host, $url, $loaded_from_cache );
		}

		return $response;
	}

	public function add_to_log( $host, $url, $loaded_from_cache ) {
		$log = self::get_log();
		$entry = [
			'url' => $url,
			'time' => time(), // @todo localized
			'cached' => $loaded_from_cache ? 'Y' : 'N',
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
		$log = self::getLog();
		if ( empty( $log ) ) :
			?><h2><?php esc_html_e( 'No Log.', 'remote-backstop' ); ?></h2><?php

		else :
			?>
			<table class="wp-list-table widefat fixed striped">
				<thead>
				<tr>
					<td><?php esc_html_e( 'Date/Time', 'remote-backstop' ); ?></td>
					<td><?php esc_html_e( 'URL', 'remote-backstop' ); ?></td>
					<td><?php esc_html_e( 'Cached?', 'remote-backstop' ); ?></td>
				</tr>
				</thead>
				<tbody>
				<?php
				foreach ( $log as $log_entry ) :
					?>
					<tr>
						<td><?php echo esc_html( $log_entry['time'] ); ?></td>
						<td><?php echo esc_html( $log_entry['url'] ); ?></td>
						<td><?php echo esc_html( $log_entry['cached'] ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php
		endif;

		return $out . ob_get_clean();
	}
}
