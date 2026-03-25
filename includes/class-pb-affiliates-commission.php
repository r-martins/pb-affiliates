<?php
/**
 * Commission calculation and DB rows.
 *
 * @package PB_Affiliates
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PB_Affiliates_Commission
 */
class PB_Affiliates_Commission {

	/**
	 * Init.
	 */
	public static function init() {
		add_action( 'woocommerce_payment_complete', array( __CLASS__, 'on_payment_complete' ), 20, 1 );
		// Pagamentos que não disparam payment_complete (ex.: alguns fluxos manuais) ou correção tardia da atribuição.
		add_action( 'woocommerce_order_status_processing', array( __CLASS__, 'on_paid_like_status' ), 20, 2 );
		add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'on_paid_like_status' ), 20, 2 );
	}

	/**
	 * @param int          $order_id Order ID.
	 * @param WC_Order|null $order   Instância (WC ≥ ordem dos argumentos).
	 */
	public static function on_paid_like_status( $order_id, $order = null ) {
		if ( $order instanceof WC_Order ) {
			$o = $order;
		} else {
			$o = wc_get_order( $order_id );
		}
		if ( ! $o ) {
			return;
		}
		self::create_commission_for_order( $o );
	}

	/**
	 * @param int $order_id Order ID.
	 */
	public static function on_payment_complete( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		self::create_commission_for_order( $order );
	}

	/**
	 * Calculate base amount for commission.
	 *
	 * @param WC_Order $order Order.
	 * @return float
	 */
	public static function get_commission_base( WC_Order $order ) {
		$settings = PB_Affiliates_Settings::get();
		$base     = (float) $order->get_subtotal();
		if ( 'yes' === ( $settings['exclude_shipping'] ?? 'yes' ) ) {
			// subtotal already excludes shipping typically; add shipping back if we used total?
			// Commission base = items subtotal + tax on items if needed — use subtotal which is line items sum.
			$base = (float) $order->get_subtotal();
		}
		if ( 'yes' === ( $settings['exclude_fees'] ?? 'yes' ) ) {
			foreach ( $order->get_fees() as $fee ) {
				$base -= (float) $fee->get_total();
			}
		}
		return max( 0, $base );
	}

	/**
	 * Comissão definida no cupom de afiliado (se houver).
	 *
	 * @param WC_Order $order Order.
	 * @return array{type:string,value:float}|null
	 */
	public static function get_coupon_commission_rate_if_any( WC_Order $order ) {
		$coupon_id = (int) $order->get_meta( '_pb_affiliate_coupon_id' );
		if ( ! $coupon_id ) {
			return null;
		}
		$coupon = new WC_Coupon( $coupon_id );
		$type   = $coupon->get_meta( '_pb_affiliate_commission_type' );
		$val    = (float) $coupon->get_meta( '_pb_affiliate_commission_value' );
		if ( $type && ( $val > 0 || 'fixed' === $type ) ) {
			return array( 'type' => (string) $type, 'value' => $val );
		}
		return null;
	}

	/**
	 * Comissão personalizada no perfil do afiliado (se houver).
	 *
	 * @param int $affiliate_id Affiliate ID.
	 * @return array{type:string,value:float}|null
	 */
	public static function get_affiliate_profile_commission_rate_if_any( $affiliate_id ) {
		$ut = get_user_meta( $affiliate_id, 'pb_affiliate_commission_type', true );
		$uv = (float) get_user_meta( $affiliate_id, 'pb_affiliate_commission_value', true );
		if ( $ut && ( $uv > 0 || 'fixed' === $ut ) ) {
			return array( 'type' => (string) $ut, 'value' => $uv );
		}
		return null;
	}

	/**
	 * Comissão padrão da loja (opções PB Afiliados).
	 *
	 * @return array{type:string,value:float}
	 */
	public static function get_store_default_commission_rate() {
		$settings = PB_Affiliates_Settings::get();
		return array(
			'type'  => $settings['default_commission_type'] ?? 'percent',
			'value' => (float) ( $settings['default_commission_value'] ?? 10 ),
		);
	}

	/**
	 * Aplica tipo/valor de comissão a uma base monetária (uma linha ou pedido inteiro).
	 *
	 * @param float $base Base (>0).
	 * @param array $rate array{type:string,value:float}.
	 * @return float
	 */
	public static function apply_rate_to_base( $base, array $rate ) {
		$base = (float) $base;
		if ( $base <= 0 ) {
			return 0.0;
		}
		$type = isset( $rate['type'] ) ? (string) $rate['type'] : 'percent';
		$val  = isset( $rate['value'] ) ? (float) $rate['value'] : 0.0;
		if ( 'percent' === $type ) {
			return (float) round( $base * ( $val / 100 ), wc_get_price_decimals() );
		}
		return (float) min( $val, $base );
	}

	/**
	 * Get commission type and value for affiliate on this order (regra única em todo o pedido).
	 * Não considera categorias — útil quando cupom ou perfil do afiliado fixam a comissão.
	 *
	 * @param WC_Order $order Order.
	 * @param int      $affiliate_id Affiliate ID.
	 * @return array{type:string,value:float}
	 */
	public static function get_rate_for_order( WC_Order $order, $affiliate_id ) {
		$coupon_rate = self::get_coupon_commission_rate_if_any( $order );
		if ( null !== $coupon_rate ) {
			return $coupon_rate;
		}
		$aff_rate = self::get_affiliate_profile_commission_rate_if_any( $affiliate_id );
		if ( null !== $aff_rate ) {
			return $aff_rate;
		}
		return self::get_store_default_commission_rate();
	}

	/**
	 * Comissão efetiva para o afiliado (meta + padrão global; sem cupom).
	 *
	 * @param int $affiliate_id Affiliate user ID.
	 * @return array{type:string,value:float}
	 */
	public static function get_effective_rate_for_affiliate( $affiliate_id ) {
		$affiliate_id = (int) $affiliate_id;
		$prof         = self::get_affiliate_profile_commission_rate_if_any( $affiliate_id );
		if ( null !== $prof ) {
			return $prof;
		}
		return self::get_store_default_commission_rate();
	}

	/**
	 * Texto para a área do afiliado: quanto ganha por venda (regra padrão).
	 *
	 * @param int $affiliate_id Affiliate user ID.
	 * @return string Plain text.
	 */
	public static function get_commission_rate_description_for_dashboard( $affiliate_id ) {
		$affiliate_id   = (int) $affiliate_id;
		$rate           = self::get_effective_rate_for_affiliate( $affiliate_id );
		$settings       = PB_Affiliates_Settings::get();
		$uses_defaults = null === self::get_affiliate_profile_commission_rate_if_any( $affiliate_id );

		if ( 'percent' === $rate['type'] ) {
			$parts = array();
			if ( 'yes' === ( $settings['exclude_shipping'] ?? 'yes' ) ) {
				$parts[] = __( 'o frete não entra na base de cálculo', 'pb-affiliates' );
			}
			if ( 'yes' === ( $settings['exclude_fees'] ?? 'yes' ) ) {
				$parts[] = __( 'taxas extras podem ser descontadas da base', 'pb-affiliates' );
			}
			if ( $uses_defaults ) {
				$parts[] = __( 'categorias de produto podem definir percentuais ou valores fixos diferentes; se o produto estiver em várias categorias com regra, usa-se a que gerar a menor comissão naquela linha', 'pb-affiliates' );
			}
			$suffix = '';
			if ( ! empty( $parts ) ) {
				$suffix = ' ' . sprintf( __( '(%s).', 'pb-affiliates' ), implode( '; ', $parts ) );
			}
			return sprintf(
				/* translators: 1: percentage, 2: optional suffix about base rules */
				__( 'Você ganha %1$s%% sobre o valor base dos produtos em cada pedido pago%2$s', 'pb-affiliates' ),
				wc_format_decimal( $rate['value'], 2 ),
				$suffix
			);
		}
		if ( $uses_defaults ) {
			$fix_parts  = array(
				__( 'categorias de produto podem definir comissões diferentes; se o produto estiver em várias categorias com regra, usa-se a que gerar a menor comissão naquela linha', 'pb-affiliates' ),
			);
			$fix_suffix = ' ' . sprintf( __( '(%s).', 'pb-affiliates' ), implode( '; ', $fix_parts ) );
			return sprintf(
				/* translators: 1: formatted price (plain), 2: suffix about categories */
				__( 'Você ganha até %1$s de comissão fixa por pedido pago (valor não pode ultrapassar o valor base da venda). %2$s', 'pb-affiliates' ),
				wp_strip_all_tags( wc_price( $rate['value'] ) ),
				$fix_suffix
			);
		}
		return sprintf(
			/* translators: %s: formatted price (plain) */
			__( 'Você ganha até %s de comissão fixa por pedido pago (valor não pode ultrapassar o valor base da venda).', 'pb-affiliates' ),
			wp_strip_all_tags( wc_price( $rate['value'] ) )
		);
	}

	/**
	 * Valor de comissão previsto (antes ou após pagamento; não grava na base).
	 *
	 * @param WC_Order $order Order.
	 * @param int      $aff_id Affiliate ID (deve coincidir com o pedido).
	 * @return array{amount:float,base:float,type:string,value:float}|null
	 */
	public static function calculate_commission_preview_for_order( WC_Order $order, $aff_id ) {
		$aff_id = (int) $aff_id;
		if ( $aff_id <= 0 || (int) $order->get_meta( '_pb_affiliate_id' ) !== $aff_id ) {
			return null;
		}
		if ( ! PB_Affiliates_Role::user_is_affiliate( $aff_id ) ) {
			return null;
		}
		return self::compute_commission_for_order( $order, $aff_id );
	}

	/**
	 * Cálculo efetivo: cupom ou perfil do afiliado = taxa única; caso contrário = por linha (categorias, menor por item).
	 *
	 * @param WC_Order $order    Order.
	 * @param int      $aff_id   Afiliado.
	 * @return array{amount:float,base:float,type:string,value:float}|null
	 */
	public static function compute_commission_for_order( WC_Order $order, $aff_id ) {
		$aff_id = (int) $aff_id;
		if ( $aff_id <= 0 || (int) $order->get_meta( '_pb_affiliate_id' ) !== $aff_id ) {
			return null;
		}
		if ( ! PB_Affiliates_Role::user_is_affiliate( $aff_id ) ) {
			return null;
		}

		$base = self::get_commission_base( $order );

		if ( null !== self::get_coupon_commission_rate_if_any( $order ) || null !== self::get_affiliate_profile_commission_rate_if_any( $aff_id ) ) {
			$rate = self::get_rate_for_order( $order, $aff_id );
			$amt  = self::apply_rate_to_base( $base, $rate );
			return array(
				'amount' => (float) $amt,
				'base'   => (float) $base,
				'type'   => (string) $rate['type'],
				'value'  => (float) $rate['value'],
			);
		}

		$fallback = self::get_store_default_commission_rate();
		$items    = $order->get_items( 'line_item' );
		if ( empty( $items ) ) {
			return array(
				'amount' => 0.0,
				'base'   => (float) $base,
				'type'   => (string) $fallback['type'],
				'value'  => (float) $fallback['value'],
			);
		}

		$subtotal_sum = 0.0;
		foreach ( $items as $item ) {
			if ( ! $item instanceof WC_Order_Item_Product ) {
				continue;
			}
			$subtotal_sum += (float) $item->get_subtotal();
		}
		if ( $subtotal_sum <= 0 ) {
			return array(
				'amount' => 0.0,
				'base'   => (float) $base,
				'type'   => (string) $fallback['type'],
				'value'  => (float) $fallback['value'],
			);
		}

		$total_amt   = 0.0;
		$line_rates = array();
		foreach ( $items as $item ) {
			if ( ! $item instanceof WC_Order_Item_Product ) {
				continue;
			}
			$product = $item->get_product();
			if ( ! $product ) {
				continue;
			}
			$pid        = (int) $product->get_id();
			$line_sub   = (float) $item->get_subtotal();
			$line_base  = $base * ( $line_sub / $subtotal_sum );
			$rate_line  = PB_Affiliates_Category_Commission::get_lowest_commission_rate_for_product_line( $pid, $line_base, $fallback );
			$line_rates[] = $rate_line;
			$total_amt   += self::apply_rate_to_base( $line_base, $rate_line );
		}

		$total_amt = (float) round( $total_amt, wc_get_price_decimals() );

		$first = isset( $line_rates[0] ) ? $line_rates[0] : $fallback;
		$same  = true;
		foreach ( $line_rates as $r ) {
			if ( $r['type'] !== $first['type'] || (float) $r['value'] !== (float) $first['value'] ) {
				$same = false;
				break;
			}
		}
		if ( $same && ! empty( $line_rates ) ) {
			$store_type  = (string) $first['type'];
			$store_value = (float) $first['value'];
		} else {
			$store_type  = 'mixed';
			$store_value = $base > 0 ? (float) round( ( $total_amt / $base ) * 100, 4 ) : 0.0;
		}

		return array(
			'amount' => $total_amt,
			'base'   => (float) $base,
			'type'   => $store_type,
			'value'  => $store_value,
		);
	}

	/**
	 * Create commission row once.
	 *
	 * @param WC_Order $order Order.
	 */
	public static function create_commission_for_order( WC_Order $order ) {
		if ( $order->get_meta( '_pb_commission_recorded' ) ) {
			return;
		}
		if ( PB_Affiliates_Order::is_recurring_renewal_order( $order ) && ! PB_Affiliates_Order::should_apply_affiliate_on_recurring_order( $order ) ) {
			return;
		}
		PB_Affiliates_Attribution::assign_order_affiliate( $order );
		PB_Affiliates_Order::maybe_inherit_affiliate( $order );

		$aff_id = (int) $order->get_meta( '_pb_affiliate_id' );
		if ( ! $aff_id || ! PB_Affiliates_Role::user_is_affiliate( $aff_id ) ) {
			return;
		}

		$calc = self::compute_commission_for_order( $order, $aff_id );
		if ( null === $calc ) {
			return;
		}
		$amt = $calc['amount'];

		if ( $amt <= 0 ) {
			$order->update_meta_data( '_pb_commission_recorded', '1' );
			$order->save();
			return;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'pagbank_affiliate_commissions';
		$via   = sanitize_key( (string) $order->get_meta( '_pb_attribution_source' ) );
		$coupon_id = (int) $order->get_meta( '_pb_affiliate_coupon_id' );
		if ( ! $coupon_id ) {
			$coupon_id = 0;
		}

		$settings = PB_Affiliates_Settings::get();
		$mode     = $settings['payment_mode'] ?? 'manual';
		$status   = 'pending';
		$avail    = null;
		if ( 'manual' === $mode ) {
			$ret_days = absint( $settings['manual_retention_days'] ?? 0 );
			$avail    = gmdate( 'Y-m-d H:i:s', time() + $ret_days * DAY_IN_SECONDS );
		} else {
			// Split: pending until PagBank settles — still track as pending/payable by cron later.
			$avail = gmdate( 'Y-m-d H:i:s', time() + absint( $settings['split_release_days'] ?? 7 ) * DAY_IN_SECONDS );
		}

		$wpdb->insert(
			$table,
			array(
				'order_id'          => $order->get_id(),
				'affiliate_id'      => $aff_id,
				'commission_amount' => $calc['amount'],
				'commission_base'   => $calc['base'],
				'commission_type'   => $calc['type'],
				'attributed_via'    => $via ? $via : 'unknown',
				'coupon_id'         => $coupon_id,
				'status'            => 'pending',
				'currency'          => $order->get_currency(),
				'created_at'        => current_time( 'mysql' ),
				'available_at'      => $avail,
				'paid_at'           => null,
				'payment_method'    => $mode,
			),
			array( '%d', '%d', '%f', '%f', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		$insert_id = (int) $wpdb->insert_id;
		$order->update_meta_data( '_pb_commission_recorded', '1' );
		$order->update_meta_data( '_pb_commission_id', $insert_id );
		$order->update_meta_data( '_pb_commission_amount', $amt );
		$order->save();

		if ( apply_filters( 'pb_affiliates_send_new_sale_email', true, $order, $aff_id, $amt ) ) {
			PB_Affiliates_Emails::send_new_sale( $order, $aff_id, $amt );
		}
	}

	/**
	 * Marca uma linha de comissão como paga ao afiliado (repasse registrado).
	 *
	 * @param int   $commission_id ID na tabela pagbank_affiliate_commissions.
	 * @param array $args          send_email (bool).
	 * @return bool|\WP_Error
	 */
	public static function mark_row_paid( $commission_id, $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'send_email' => true,
			)
		);
		global $wpdb;
		$table = $wpdb->prefix . 'pagbank_affiliate_commissions';
		$id    = absint( $commission_id );
		if ( $id <= 0 ) {
			return new \WP_Error( 'invalid', __( 'ID inválido.', 'pb-affiliates' ) );
		}
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, affiliate_id, commission_amount, status, payment_method FROM {$table} WHERE id = %d",
				$id
			),
			ARRAY_A
		);
		if ( ! $row ) {
			return new \WP_Error( 'not_found', __( 'Comissão não encontrada.', 'pb-affiliates' ) );
		}
		if ( 'pending' !== $row['status'] ) {
			return new \WP_Error( 'invalid', __( 'Só é possível marcar como pagas comissões pendentes.', 'pb-affiliates' ) );
		}
		$pm = isset( $row['payment_method'] ) ? (string) $row['payment_method'] : '';
		if ( 'split' === $pm ) {
			return new \WP_Error( 'invalid', __( 'Comissões em modo split são liquidadas pelo PagBank, não manualmente.', 'pb-affiliates' ) );
		}
		$wpdb->update(
			$table,
			array(
				'status'  => 'paid',
				'paid_at' => current_time( 'mysql' ),
			),
			array( 'id' => $id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
		$aff_id = (int) $row['affiliate_id'];
		$amount = (float) $row['commission_amount'];
		if ( $args['send_email'] && apply_filters( 'pb_affiliates_send_commission_paid_email', true, $aff_id, $amount, $id, '' ) ) {
			PB_Affiliates_Emails::send_commission_paid( $aff_id, $amount, '' );
		}
		return true;
	}
}
