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


		add_filter( 'remote_backstop_enable', [ $this, 'remote_backstop_enable' ] );

	}

	/**
	 * Render the options page for controlling which scripts get deferred.
	 */
	public function add_options() {
		$fm_options = new \Fieldmanager_Group(
			array(
				'name'     => static::OPTIONS_KEY,
				'children' => [
					'enable' => [
						'label' => 'Enable Remote Backstop',
					],
				]
			)
		);
		$fm_options->activate_submenu_page();

		add_filter( 'fm_element_markup_end_' . static::OPTIONS_KEY, [ Log::instance(), 'display' ], 10, 3 );
	}
}

// Initialize this tool after theme setup.
add_action( 'after_setup_theme', [ 'Settings', 'instance' ] );
