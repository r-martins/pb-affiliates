<?php
/**
 * Main plugin singleton.
 *
 * @package PB_Affiliates
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PB_Affiliates
 */
final class PB_Affiliates {

	/**
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * @return self
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * PB_Affiliates constructor.
	 */
	private function __construct() {
		PB_Affiliates_Role::init();
		PB_Affiliates_Tracking::init();
		PB_Affiliates_Order::init();
		PB_Affiliates_Commission::init();
		if ( is_admin() ) {
			PB_Affiliates_Category_Commission::init();
		}
		PB_Affiliates_Coupon::init();
		PB_Affiliates_Split::init();
		PB_Affiliates_Promotional_Materials::init();
		PB_Affiliates_Account::init();
		PB_Affiliates_Bank_Combo::init();
		PB_Affiliates_Public::init();

		if ( is_admin() ) {
			PB_Affiliates_Admin_Materials::init();
			PB_Affiliates_Admin_Settings::init();
			PB_Affiliates_Admin::init();
			PB_Affiliates_User_Profile::init();
			PB_Affiliates_Order_Meta_Box::init();
		}

		add_action( 'woocommerce_init', array( $this, 'woocommerce_init' ) );
	}

	/**
	 * After WooCommerce loads.
	 */
	public function woocommerce_init() {
		add_action( 'admin_notices', array( $this, 'notice_no_pagbank_payment' ) );
	}

	/**
	 * Admin notice when no PagBank method available (storefront still works; split may be limited).
	 */
	public function notice_no_pagbank_payment() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		if ( PB_Affiliates_Dependencies::has_pagbank_payment_available() ) {
			return;
		}
		echo '<div class="notice notice-warning"><p>' . esc_html__( 'PB Afiliados: nenhum método de pagamento PagBank está disponível no checkout. Ative pelo menos um método no PagBank Connect.', 'pb-affiliates' ) . '</p></div>';
	}
}
