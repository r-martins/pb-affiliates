<?php
/**
 * Order hooks: inherit affiliate on renewals, checkout.
 *
 * @package PB_Affiliates
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PB_Affiliates_Order
 */
class PB_Affiliates_Order {

	/**
	 * Init.
	 */
	public static function init() {
		add_action( 'woocommerce_checkout_create_order', array( __CLASS__, 'checkout_create_order' ), 20, 2 );
		add_action( 'woocommerce_checkout_order_created', array( __CLASS__, 'checkout_order_created' ), 10, 1 );
		add_action( 'woocommerce_checkout_order_processed', array( __CLASS__, 'checkout_order_processed' ), 20, 1 );
		// Checkout em blocos: o carrinho é copiado para o rascunho aqui (cupons disponíveis). Não confiar só em `…_order_processed`.
		add_action( 'woocommerce_store_api_checkout_update_order_meta', array( __CLASS__, 'store_api_checkout_update_order_meta' ), 10, 1 );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( __CLASS__, 'checkout_order_processed' ), 20, 1 );
	}

	/**
	 * Opção guardada: comissão em recorrência.
	 *
	 * @return bool
	 */
	public static function commission_on_recurring_enabled() {
		return 'yes' === ( PB_Affiliates_Settings::get()['commission_recurring'] ?? 'no' );
	}

	/**
	 * Se afiliado deve ser herdado / comissão gerada em pedido de renovação (filtro + opção).
	 *
	 * @param WC_Order $order Order.
	 * @return bool
	 */
	public static function should_apply_affiliate_on_recurring_order( WC_Order $order ) {
		return (bool) apply_filters( 'pb_affiliates_commission_on_recurring_enabled', self::commission_on_recurring_enabled(), $order );
	}

	/**
	 * Cobrança subsequente da recorrência nativa PagBank Connect (não inclui o pedido inicial com _pagbank_recurring_initial).
	 *
	 * @param WC_Order $order Order.
	 * @return bool
	 */
	public static function is_recurring_renewal_order( WC_Order $order ) {
		if ( wc_string_to_bool( (string) $order->get_meta( '_pagbank_recurring_initial' ) ) ) {
			return false;
		}
		return wc_string_to_bool( (string) $order->get_meta( '_pagbank_is_recurring' ) );
	}

	/**
	 * Checkout Block / Store API: atribuir afiliado quando o pedido-rascunho recebe itens e cupons do carrinho.
	 *
	 * @param WC_Order $order Order.
	 */
	public static function store_api_checkout_update_order_meta( WC_Order $order ) {
		PB_Affiliates_Attribution::assign_order_affiliate( $order );
		self::maybe_inherit_affiliate( $order );
		if ( $order->get_id() ) {
			$order->save();
		}
	}

	/**
	 * @param WC_Order $order Order.
	 * @param array    $data Data.
	 */
	public static function checkout_create_order( $order, $data ) {
		PB_Affiliates_Attribution::assign_order_affiliate( $order );
		self::maybe_inherit_affiliate( $order );
	}

	/**
	 * Após o primeiro save do pedido (ID garantido); reforça atribuição se o carrinho/pedido divergirem.
	 *
	 * @param WC_Order $order Order.
	 */
	public static function checkout_order_created( WC_Order $order ) {
		PB_Affiliates_Attribution::assign_order_affiliate( $order );
		self::maybe_inherit_affiliate( $order );
		if ( $order->get_id() ) {
			$order->save();
		}
	}

	/**
	 * After order is fully created (Blocks + shortcode checkout).
	 *
	 * @param int|WC_Order $order Order ID or order.
	 */
	public static function checkout_order_processed( $order ) {
		$o = $order instanceof WC_Order ? $order : wc_get_order( $order );
		if ( ! $o ) {
			return;
		}
		PB_Affiliates_Attribution::assign_order_affiliate( $o );
		self::maybe_inherit_affiliate( $o );
		$o->save();
	}

	/**
	 * Herda afiliado do pedido inicial nas cobranças recorrentes PagBank Connect (parent = pedido inicial).
	 *
	 * @param WC_Order $order Order.
	 */
	public static function maybe_inherit_affiliate( WC_Order $order ) {
		if ( (int) $order->get_meta( '_pb_affiliate_id' ) ) {
			return;
		}
		if ( ! wc_string_to_bool( (string) $order->get_meta( '_pagbank_is_recurring' ) )
			|| wc_string_to_bool( (string) $order->get_meta( '_pagbank_recurring_initial' ) ) ) {
			return;
		}
		if ( ! self::should_apply_affiliate_on_recurring_order( $order ) ) {
			return;
		}
		$parent_id = (int) $order->get_parent_id();
		if ( $parent_id <= 0 ) {
			return;
		}
		$p = wc_get_order( $parent_id );
		if ( $p && (int) $p->get_meta( '_pb_affiliate_id' ) ) {
			self::copy_affiliate_from_source_order( $order, $p );
		}
	}

	/**
	 * @param WC_Order $order  Pedido renovação.
	 * @param WC_Order $source Pedido inicial ou pai com meta de afiliado.
	 */
	private static function copy_affiliate_from_source_order( WC_Order $order, WC_Order $source ) {
		$aid = (int) $source->get_meta( '_pb_affiliate_id' );
		if ( $aid <= 0 ) {
			return;
		}
		$order->update_meta_data( '_pb_affiliate_id', $aid );
		$order->update_meta_data( '_pb_affiliate_code', $source->get_meta( '_pb_affiliate_code' ) );
		$order->update_meta_data( '_pb_attribution_source', 'renewal' );
		$coupon_id = (int) $source->get_meta( '_pb_affiliate_coupon_id' );
		if ( $coupon_id > 0 ) {
			$order->update_meta_data( '_pb_affiliate_coupon_id', $coupon_id );
		}
		$rh = (string) $source->get_meta( '_pb_referer_host' );
		if ( '' !== $rh ) {
			$order->update_meta_data( '_pb_referer_host', substr( $rh, 0, 190 ) );
		}
	}
}
