<?php
/**
 * Encurtamento zip1.io: o browser chama a API pública diretamente (sem proxy no WordPress).
 *
 * @package PB_Affiliates
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PB_Affiliates_Zip1
 */
class PB_Affiliates_Zip1 {

	const META_CACHE = 'pb_affiliate_zip1_cache';

	/**
	 * Admin ligou o recurso?
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		$s = PB_Affiliates_Settings::get();
		return isset( $s['zip1_shorten_enabled'] ) && in_array( (string) $s['zip1_shorten_enabled'], array( 'yes', '1' ), true );
	}

	/**
	 * Remove metadado legado de cache de link curto (ex.: após mudança de código de afiliado).
	 *
	 * @param int $user_id User ID.
	 */
	public static function clear_user_cache( $user_id ) {
		$user_id = (int) $user_id;
		if ( $user_id > 0 ) {
			delete_user_meta( $user_id, self::META_CACHE );
		}
	}

	/**
	 * URL pública da API create (exposta ao JS; filtro para testes).
	 *
	 * @return string
	 */
	public static function api_create_url() {
		return (string) apply_filters( 'pb_affiliates_zip1_api_create_url', 'https://zip1.io/api/create' );
	}
}
