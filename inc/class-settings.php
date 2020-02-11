<?php
/**
* This file contains the Settings class.
*
* @package Remote_Backstop
*/

namespace Remote_Backstop;

class Settings {

	use Singleton;

	/**
	 * Capability required to change settings and view log.
	 *
	 * @var string
	 */
	private $required_capability;

	/**
	 * Options key name for settings.
	 *
	 * @var string
	 */
	const OPTIONS_KEY = 'remote_backstop_settings';

	/**
	 * Set up the admin menu.
	 */
	private function setup() {

		$capability = 'manage_options';

		/**
		 * Filters the capability required for the settings page.
		 *
		 * @param string $capability          Response.
		 */
		$this->required_capability = apply_filters( 'remote_backstop_capabilitiy', $capability );

		add_action( 'after_setup_theme', [ $this, 'register_submenu_page'] );
		add_filter( 'remote_backstop_enabled', [ $this, 'remote_backstop_disable' ] );
		add_filter( 'remote_backstop_ttl', [ $this, 'remote_backstop_ttl'] );
	}

	public function register_submenu_page() {

		if ( function_exists( 'fm_register_submenu_page' ) ) {
			if ( current_user_can( $this->required_capability ) ) {
				fm_register_submenu_page(
					static::OPTIONS_KEY,
					'options-general.php',
					__( 'Remote Backstop', 'remote-backstop' )
				);
			}

			add_action(
				'fm_submenu_' . static::OPTIONS_KEY,
				array( $this, 'add_options' )
			);
		}
	}

	/**
	 * Render the options page for controlling which scripts get deferred.
	 */
	public function add_options() {
		$fm_options = new \Fieldmanager_Group(
			array(
				'name'     => static::OPTIONS_KEY,
				'children' => [
					'disable' => new \Fieldmanager_Checkbox ( [
						'label' => 'Disable Remote Backstop',
					] ),
					'ttl' => new \Fieldmanager_Textfield( [
						'label' => 'Cache TTL (seconds)',
						'description' => 'Set to 0 to cache indefinitiely.',
						'defalt_value' => '0',
						'attributes' => array(
							'size' => 6,
						),
					]),
				],
			)
		);
		$fm_options->activate_submenu_page();

		add_filter( 'fm_element_markup_end_' . static::OPTIONS_KEY, [ Log::instance(), 'display' ], 10, 3 );
	}

	/**
	 * @return mixed|void
	 */
	public static function get_options() {
		return get_option( self::OPTIONS_KEY );
	}

	/**
	 * Filters whether Remote Backstop is enabled.
	 *
	 * @param $enabled
	 *
	 * @return bool
	 */
	public function remote_backstop_disable( $enabled ) {
		$options = self::get_options();
		if ( ! empty( $options['disable'] ) ) {
			return false;
		}
		return $enabled;
	}

	/**
	 * @param $ttl
	 *
	 * @return int
	 */
	public function remote_backstop_ttl( $ttl ) {
		$options = self::get_options();
		if ( ! empty( $options['ttl'] ) ) {
			return (int) $options['ttl'];
		}
		return $ttl;
	}

}
