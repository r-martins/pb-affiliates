<?php
/**
 * Plugin Name:       PB Afiliados
 * Plugin URI:        https://pbintegracoes.com/
 * Description:       Programa de afiliados para WooCommerce com integração PagBank Connect. Exige PagBank Connect ativo com pelo menos um método de pagamento disponível.
 * Version:           1.0.24
 * Requires at least: 5.2
 * Requires PHP:      7.4
 * Author:            Ricardo Martins
 * License:           GPL-3.0
 * License URI:       https://opensource.org/license/gpl-3-0
 * Requires Plugins:  woocommerce, pagbank-connect
 * Text Domain:       pb-affiliates
 * Domain Path:       /languages
 *
 * @package PB_Affiliates
 */

defined( 'ABSPATH' ) || exit;

define( 'PB_AFFILIATES_VERSION', '1.0.24' );
define( 'PB_AFFILIATES_FILE', __FILE__ );
define( 'PB_AFFILIATES_PATH', plugin_dir_path( __FILE__ ) );
define( 'PB_AFFILIATES_URL', plugin_dir_url( __FILE__ ) );
define( 'PB_AFFILIATES_BASENAME', plugin_basename( __FILE__ ) );

require_once PB_AFFILIATES_PATH . 'includes/class-pb-affiliates-install.php';
require_once PB_AFFILIATES_PATH . 'includes/class-pb-affiliates-autoload.php';

register_activation_hook( __FILE__, array( 'PB_Affiliates_Install', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'PB_Affiliates_Install', 'deactivate' ) );

add_action( 'plugins_loaded', 'pb_affiliates_boot', 20 );

/**
 * Bootstrap plugin.
 */
function pb_affiliates_boot() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'pb_affiliates_notice_woocommerce' );
		return;
	}
	if ( ! defined( 'WC_PAGSEGURO_CONNECT_VERSION' ) && ! class_exists( 'RM_PagBank\Connect' ) ) {
		add_action( 'admin_notices', 'pb_affiliates_notice_pagbank' );
		return;
	}
	if ( ! PB_Affiliates_Install::is_hpos_order_storage_enabled() ) {
		add_action( 'admin_notices', 'pb_affiliates_notice_hpos' );
		return;
	}

	PB_Affiliates_Install::maybe_upgrade();
	PB_Affiliates::instance();
}

/**
 * Admin notice: WooCommerce missing.
 */
function pb_affiliates_notice_woocommerce() {
	echo '<div class="notice notice-error"><p>' . esc_html__( 'PB Afiliados requer o WooCommerce instalado e ativo.', 'pb-affiliates' ) . '</p></div>';
}

/**
 * Admin notice: PagBank Connect missing.
 */
function pb_affiliates_notice_pagbank() {
	echo '<div class="notice notice-error"><p>' . esc_html__( 'PB Afiliados requer o plugin PagBank Connect instalado e ativo.', 'pb-affiliates' ) . '</p></div>';
}

/**
 * Admin notice: HPOS (custom order tables) must be the active order storage.
 */
function pb_affiliates_notice_hpos() {
	echo '<div class="notice notice-error"><p>';
	echo wp_kses_post(
		__( 'PB Afiliados exige o armazenamento de pedidos de alto desempenho (HPOS) ativo. Em WooCommerce &gt; Configurações &gt; Avançado &gt; Recursos, ative as tabelas personalizadas de pedidos.', 'pb-affiliates' )
	);
	echo '</p></div>';
}
