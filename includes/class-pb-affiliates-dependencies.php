<?php
/**
 * Dependency checks: PagBank payment methods available.
 *
 * @package PB_Affiliates
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PB_Affiliates_Dependencies
 */
class PB_Affiliates_Dependencies {

	/**
	 * True se pelo menos um meio PagBank Connect está habilitado na loja.
	 *
	 * - Não usar só {@see WC_Payment_Gateways::get_available_payment_gateways}: no admin o checkout
	 *   não existe e a lista costuma ficar vazia.
	 * - Não usar só {@see WC_Payment_Gateways::payment_gateways}: o filtro do Connect depende de
	 *   `$_GET['section']`. Na tela unificada (`rm-pagbank`) só existe o gateway `rm-pagbank`, que
	 *   pode ficar “desligado” enquanto PIX/cartão estão ativos como métodos standalone (`rm-pagbank-pix`,
	 *   `rm-pagbank-cc`) guardados em opções separadas.
	 * Por isso lemos primeiro `woocommerce_rm-pagbank*_settings['enabled']`.
	 *
	 * @return bool
	 */
	public static function has_pagbank_payment_available() {
		$settings_keys = array(
			'woocommerce_rm-pagbank_settings',
			'woocommerce_rm-pagbank-cc_settings',
			'woocommerce_rm-pagbank-pix_settings',
			'woocommerce_rm-pagbank-boleto_settings',
			'woocommerce_rm-pagbank-redirect_settings',
		);
		foreach ( $settings_keys as $option_name ) {
			$settings = get_option( $option_name, array() );
			if ( is_array( $settings ) && isset( $settings['enabled'] ) && 'yes' === $settings['enabled'] ) {
				return true;
			}
		}

		if ( ! function_exists( 'WC' ) || ! WC()->payment_gateways ) {
			return false;
		}
		foreach ( WC()->payment_gateways->payment_gateways() as $gateway ) {
			if ( ! is_object( $gateway ) || ! isset( $gateway->id ) ) {
				continue;
			}
			$id = (string) $gateway->id;
			if ( strpos( $id, 'rm-pagbank' ) !== 0 ) {
				continue;
			}
			if ( isset( $gateway->enabled ) && 'yes' === $gateway->enabled ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Whether gateway split is enabled (PagBank Connect).
	 * Uses the Gateway object so defaults/unserialization match the rest of PagBank Connect.
	 *
	 * @return bool
	 */
	public static function is_gateway_split_enabled() {
		if ( ! class_exists( '\RM_PagBank\Connect\Gateway' ) ) {
			return false;
		}
		$gateway = new \RM_PagBank\Connect\Gateway();
		return 'yes' === $gateway->get_option( 'split_payments_enabled', 'no' );
	}

	/**
	 * Whether Dokan split is enabled and Dokan is actually active.
	 * (Option alone can be stale; DokanSplitManager never applies split without Dokan.)
	 *
	 * @return bool
	 */
	public static function is_dokan_split_enabled() {
		if ( ! function_exists( 'dokan' ) && ! class_exists( 'WeDevs_Dokan', false ) ) {
			return false;
		}
		$integrations = get_option( 'woocommerce_rm-pagbank-integrations_settings', array() );
		if ( ! is_array( $integrations ) ) {
			$integrations = array();
		}
		return isset( $integrations['dokan_split_enabled'] ) && 'yes' === $integrations['dokan_split_enabled'];
	}

	/**
	 * Affiliate split mode can be used only if store split and dokan split are off.
	 *
	 * @return bool
	 */
	public static function can_use_affiliate_split() {
		return ! self::is_gateway_split_enabled() && ! self::is_dokan_split_enabled();
	}
}
