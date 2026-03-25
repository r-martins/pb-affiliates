<?php
/**
 * Settings helpers.
 *
 * @package PB_Affiliates
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PB_Affiliates_Settings
 */
class PB_Affiliates_Settings {

	/**
	 * Option key.
	 */
	const OPTION = 'pb_affiliates_settings';

	/**
	 * Get all settings merged with defaults.
	 *
	 * @return array
	 */
	public static function get() {
		$defaults = PB_Affiliates_Install::default_settings();
		$saved    = get_option( self::OPTION, array() );
		return wp_parse_args( is_array( $saved ) ? $saved : array(), $defaults );
	}

	/**
	 * Get single key.
	 *
	 * @param string $key Key.
	 * @param mixed  $default Default.
	 * @return mixed
	 */
	public static function get_var( $key, $default = null ) {
		$all = self::get();
		return isset( $all[ $key ] ) ? $all[ $key ] : $default;
	}

	/**
	 * Update settings (merge).
	 *
	 * @param array $new Partial settings.
	 */
	public static function update( array $new ) {
		$current = self::get();
		update_option( self::OPTION, array_merge( $current, $new ) );
	}
}
