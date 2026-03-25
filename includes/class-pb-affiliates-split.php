<?php
/**
 * PagBank split for affiliate commission (PRIMARY store, SECONDARY affiliate + custody).
 *
 * @package PB_Affiliates
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PB_Affiliates_Split
 */
class PB_Affiliates_Split {

	/**
	 * Init filter on PagBank Connect.
	 */
	public static function init() {
		add_filter( 'pagbank_connect_split_for_order', array( __CLASS__, 'filter_split' ), 10, 3 );
	}

	/**
	 * @param mixed                                 $split Previous.
	 * @param WC_Order                              $order Order.
	 * @param string                                $payment_method_type PIX, BOLETO, CREDIT_CARD.
	 * @return \RM_PagBank\Object\Split|null
	 */
	public static function filter_split( $split, $order, $payment_method_type ) {
		$settings = PB_Affiliates_Settings::get();
		if ( 'split' !== ( $settings['payment_mode'] ?? 'manual' ) ) {
			return null;
		}
		if ( ! PB_Affiliates_Dependencies::can_use_affiliate_split() ) {
			return null;
		}
		if ( ! PB_Affiliates_Dependencies::has_pagbank_payment_available() ) {
			return null;
		}

		PB_Affiliates_Order::maybe_inherit_affiliate( $order );
		$aff_id = (int) $order->get_meta( '_pb_affiliate_id' );
		if ( ! $aff_id || ! PB_Affiliates_Role::user_is_affiliate( $aff_id ) ) {
			return null;
		}

		$aff_account = get_user_meta( $aff_id, 'pb_affiliate_pagbank_account_id', true );
		if ( ! $aff_account || ! self::is_valid_account_id( $aff_account ) ) {
			return null;
		}

		$primary = (string) get_option( 'woocommerce_rm-pagbank_merchant_account_id', '' );
		if ( ! $primary ) {
			$gw = get_option( 'woocommerce_rm-pagbank_settings', array() );
			$primary = isset( $gw['split_payments_primary_account_id'] ) ? (string) $gw['split_payments_primary_account_id'] : '';
		}
		if ( ! $primary || ! self::is_valid_account_id( $primary ) ) {
			return null;
		}

		$calc = PB_Affiliates_Commission::compute_commission_for_order( $order, $aff_id );
		if ( null === $calc ) {
			return null;
		}
		$commission = (float) $calc['amount'];
		if ( $commission <= 0 ) {
			return null;
		}

		$order_total = (float) $order->get_total();
		if ( $order_total <= 0 ) {
			return null;
		}

		// Split API uses % of the charge amount; align commission money to charge total.
		$pct_aff   = ( $commission / $order_total ) * 100;
		$pct_store = 100 - $pct_aff;
		if ( $pct_store < 0 || $pct_aff <= 0 ) {
			return null;
		}

		$split_obj = new \RM_PagBank\Object\Split();
		$split_obj->setMethod( \RM_PagBank\Object\Split::METHOD_PERCENTAGE );

		$primary_r = new \RM_PagBank\Object\Receiver();
		$primary_r->setAccount( array( 'id' => $primary ) );
		$primary_r->setAmount( array( 'value' => (float) round( $pct_store, 2 ) ) );
		$primary_r->setReason( __( 'Loja', 'pb-affiliates' ) );
		$primary_r->setType( \RM_PagBank\Object\Receiver::TYPE_PRIMARY );
		$primary_r->setCustody( false );
		$split_obj->addReceiver( $primary_r );

		$days      = absint( $settings['split_release_days'] ?? 7 );
		$scheduled = gmdate( 'c', strtotime( $order->get_date_created()->date( 'Y-m-d H:i:s' ) . ' +' . $days . ' days' ) );

		$sec = new \RM_PagBank\Object\Receiver();
		$sec->setAccount( array( 'id' => $aff_account ) );
		$sec->setAmount( array( 'value' => (float) round( $pct_aff, 2 ) ) );
		$sec->setReason( sprintf( 'Comissão pedido %d', $order->get_id() ) );
		$sec->setType( \RM_PagBank\Object\Receiver::TYPE_SECONDARY );
		$sec->setCustody( true, $scheduled );
		$split_obj->addReceiver( $sec );

		return $split_obj;
	}

	/**
	 * Whether string matches PagBank Account ID format (ACCO_…).
	 *
	 * @param string $id Account ID.
	 * @return bool
	 */
	public static function is_valid_account_id( $id ) {
		return (bool) preg_match( '/^ACCO_[A-Fa-f0-9]{8}-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{12}$/', (string) $id );
	}
}
