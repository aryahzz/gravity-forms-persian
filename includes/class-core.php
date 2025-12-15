<?php

defined( 'ABSPATH' ) || exit;

if ( class_exists( 'GFPersian_Core' ) ) {
	return;
}

class GFPersian_Core {
	/**
	 * It'll maybe bool if settings haven't been saved
	 *
	 * @var array|false $settings
	 */
	private static $settings;

	/**
	 * Set whole Persian GForm settings in the $settings property and get value of $setting_name
	 *
	 * @param string $setting_name
	 * @param mixed  $default
	 *
	 * @return mixed
	 */
	public static function _option( string $setting_name = '', $default = null ) {

		if ( empty( self::$settings ) ) {

			if ( method_exists( 'GFPersian_Settings', 'get_plugin_settings' ) ) {
				self::$settings = GFPersian_Settings::get_instance()->get_plugin_settings();
			}

			if ( empty( self::$settings ) && defined( 'GF_PERSIAN_SLUG' ) ) {
				self::$settings = get_option( 'gravityformsaddon_' . GF_PERSIAN_SLUG . '_settings' );
			}

		}

		$settings = self::$settings;

		if ( ! empty( $setting_name ) ) {
			$settings = $settings[ $setting_name ] ?? '';
		}

		return ! empty( $settings ) || strval( $settings ) == '0' ? $settings : $default;
	}

	/**
	 * Get option from $settings property (wrapper of _option)
	 *
	 * @param string $setting_name
	 * @param mixed  $default
	 *
	 * @return mixed
	 */
	public function option( string $setting_name = '', $default = null ) {
		return self::_option( $setting_name, $default );
	}

	/**
	 * Check if it's a gravity page showing
	 *
	 * @return bool
	 */
	public function is_gravity_page(): bool {
		$is_gform     = class_exists( 'GFForms' ) && GFForms::is_gravity_page();
		$current_page = trim( strtolower( rgget( 'page' ) ) );

		return $is_gform || substr( $current_page, 0, 2 ) == 'gf' || stripos( $current_page, 'gravity' ) !== false;
	}

	/**
	 * Get plugin base url
	 *
	 * @return string
	 */
	public static function get_base_url(): string {
		return plugins_url( '', dirname( __FILE__ ) );
	}

	/**
	 * Returns the Entry object for a given Entry ID.
	 * It's a wrapper for GFAPI::get_entry with safe return of false
	 *
	 * @param ?int $entry_id
	 *
	 * @return array|false
	 */
	public static function get_entry( ?int $entry_id ) {

		if ( is_null( $entry_id ) ) {
			return false;
		}

		$entry = GFAPI::get_entry( $entry_id );

		if ( is_wp_error( $entry ) ) {
			return false;
		}

		return $entry;
	}

	/**
	 * Check if current showing page is Elementor based
	 *
	 * @return bool
	 */
	public static function is_elementor(): bool {
		try {

			if ( class_exists( '\Elementor\Plugin' ) ) {
				$instance         = \Elementor\Plugin::$instance;
				$elementor_action = rgar( $_REQUEST, 'action', '' ) == 'elementor';

				return $instance->editor->is_edit_mode() || $instance->preview->is_preview_mode() || $elementor_action;
			}

		} catch ( Exception $e ) {
			return false;
		}

		return false;
	}

	/**
	 * Get url of registered script
	 *
	 * @param string $handle
	 *
	 * @return string
	 */
	public static function get_registered_script_url( string $handle ): string {
		$scripts = wp_scripts();

		if ( isset( $scripts->registered[ $handle ] ) ) {
			return $scripts->registered[ $handle ]->src;
		}

		return '';
	}


	/**
	 * Generate Gravity Forms edit link with a custom form ID
	 *
	 * @param int $form_id Gravity Forms form ID
	 *
	 * @return string Admin URL to edit the form
	 */
	public static function get_form_edit_link( int $form_id ): string {
		$args = [
			'page' => 'gf_edit_forms',
			'id'   => intval( $form_id ),
		];

		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}

	/**
	 * Generate Gravity Forms entry link
	 *
	 * @param int $form_id  Gravity Forms form ID
	 *
	 * @param int $entry_id Form entry ID
	 *
	 * @return string Admin URL to edit the form
	 */
	public static function get_form_entry_link( int $form_id, int $entry_id ): string {
		$args = [
			'page' => 'gf_entries',
			'view' => 'entry',
			'id'   => intval( $form_id ),
			'lid'  => intval( $entry_id ),
		];

		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}

	/**
	 * Returns .min if minified script should get loaded
	 *
	 * @erturn string
	 */
	public static function minified(): string {
		return ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '.min' : '';
	}

}