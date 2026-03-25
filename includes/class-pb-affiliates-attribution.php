<?php
/**
 * Resolve affiliate from code, coupon, cookie.
 *
 * @package PB_Affiliates
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PB_Affiliates_Attribution
 */
class PB_Affiliates_Attribution {

	/**
	 * Get user ID by affiliate public code.
	 *
	 * @param string $code Code.
	 * @return int 0 if not found.
	 */
	/**
	 * Normaliza identificador público (minúsculas, a-z 0-9 _ -).
	 *
	 * @param string $code Raw.
	 * @return string
	 */
	public static function sanitize_affiliate_code( $code ) {
		$code = strtolower( trim( (string) $code ) );
		$code = preg_replace( '/[^a-z0-9_-]/', '', $code );
		return substr( $code, 0, 40 );
	}

	/**
	 * @param string $code Code.
	 * @return int 0 if not found.
	 */
	public static function get_affiliate_id_by_code( $code ) {
		global $wpdb;
		$code = self::sanitize_affiliate_code( $code );
		if ( '' === $code ) {
			return 0;
		}
		// `meta_value` pode não estar em minúsculas; cupons WC são case-insensitive.
		$uid = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s AND LOWER(meta_value) = %s LIMIT 1",
				'pb_affiliate_code',
				$code
			)
		);
		return $uid ? (int) $uid : 0;
	}

	/**
	 * Unique code validation.
	 *
	 * @param string $code Code.
	 * @param int    $except_user_id User to exclude.
	 * @return bool
	 */
	public static function is_code_available( $code, $except_user_id = 0 ) {
		$code = self::sanitize_affiliate_code( $code );
		if ( strlen( $code ) < 3 ) {
			return false;
		}
		$id = self::get_affiliate_id_by_code( $code );
		return 0 === $id || (int) $except_user_id === (int) $id;
	}

	/**
	 * Cupons aplicados: meta `_pb_affiliate_id` no cupom ou código = código público do afiliado.
	 *
	 * @param string[] $codes Códigos de cupom (strings).
	 * @return array{affiliate_id:int,code:string,via:string}|null
	 */
	private static function resolve_from_coupon_codes( array $codes ) {
		foreach ( $codes as $coupon_code ) {
			$coupon_code = is_string( $coupon_code ) ? trim( $coupon_code ) : '';
			if ( '' === $coupon_code ) {
				continue;
			}
			$coupon = new WC_Coupon( $coupon_code );
			$aid    = (int) $coupon->get_meta( '_pb_affiliate_id' );
			if ( ! $aid ) {
				$aid = self::get_affiliate_id_by_code( $coupon_code );
			}
			// Atribuição ao programa: ativo ou pendente (comissão paga só a ativos em PB_Affiliates_Commission).
			if ( $aid && PB_Affiliates_Role::user_is_active_or_pending_affiliate( $aid ) ) {
				$code = get_user_meta( $aid, 'pb_affiliate_code', true );
				return array(
					'affiliate_id' => $aid,
					'code'         => $code ? $code : '',
					'via'          => 'coupon',
				);
			}
		}
		return null;
	}

	/**
	 * Resolve a partir do pedido (cupons no pedido, no carrinho e cookie).
	 *
	 * @param WC_Order $order Order.
	 * @return array{affiliate_id:int,code:string,via:string}|null
	 */
	public static function resolve_for_order( WC_Order $order ) {
		return self::resolve_for_assignment( $order );
	}

	/**
	 * Cupom no pedido → cupom no carrinho (checkout em blocos pode falhar num dos dois) → cookie.
	 *
	 * @param WC_Order $order Order.
	 * @return array{affiliate_id:int,code:string,via:string}|null
	 */
	private static function resolve_for_assignment( WC_Order $order ) {
		$codes = $order->get_coupon_codes();
		if ( ! empty( $codes ) ) {
			$from_coupons = self::resolve_from_coupon_codes( $codes );
			if ( $from_coupons ) {
				return $from_coupons;
			}
		}

		if ( function_exists( 'WC' ) && WC()->cart ) {
			$cart_codes = WC()->cart->get_applied_coupons();
			if ( ! empty( $cart_codes ) ) {
				$from_cart = self::resolve_from_coupon_codes( $cart_codes );
				if ( $from_cart ) {
					return $from_cart;
				}
			}
		}

		$data = PB_Affiliates_Tracking::get_cookie_data();
		if ( $data && ! empty( $data['id'] ) && PB_Affiliates_Role::user_is_active_or_pending_affiliate( (int) $data['id'] ) ) {
			return array(
				'affiliate_id' => (int) $data['id'],
				'code'         => isset( $data['code'] ) ? sanitize_text_field( $data['code'] ) : '',
				'via'          => isset( $data['via'] ) ? sanitize_key( $data['via'] ) : 'cookie_param',
			);
		}

		return null;
	}

	/**
	 * Resolve affiliate for cart/checkout: coupon > cookie.
	 *
	 * @param WC_Cart|null $cart Cart.
	 * @return array{affiliate_id:int,code:string,via:string}|null
	 */
	public static function resolve_for_cart( $cart = null ) {
		if ( ! $cart && function_exists( 'WC' ) ) {
			$cart = WC()->cart;
		}
		if ( $cart && $cart->get_applied_coupons() ) {
			$from_coupons = self::resolve_from_coupon_codes( $cart->get_applied_coupons() );
			if ( $from_coupons ) {
				return $from_coupons;
			}
		}

		$data = PB_Affiliates_Tracking::get_cookie_data();
		if ( $data && ! empty( $data['id'] ) && PB_Affiliates_Role::user_is_active_or_pending_affiliate( (int) $data['id'] ) ) {
			return array(
				'affiliate_id' => (int) $data['id'],
				'code'         => isset( $data['code'] ) ? sanitize_text_field( $data['code'] ) : '',
				'via'          => isset( $data['via'] ) ? sanitize_key( $data['via'] ) : 'cookie_param',
			);
		}

		return null;
	}

	/**
	 * Attach to order at checkout.
	 *
	 * @param WC_Order $order Order.
	 */
	public static function assign_order_affiliate( WC_Order $order ) {
		if ( (int) $order->get_meta( '_pb_affiliate_id' ) ) {
			return;
		}
		$resolved = self::resolve_for_assignment( $order );
		if ( ! $resolved ) {
			return;
		}
		$order->update_meta_data( '_pb_affiliate_id', $resolved['affiliate_id'] );
		$order->update_meta_data( '_pb_affiliate_code', $resolved['code'] );
		$order->update_meta_data( '_pb_attribution_source', $resolved['via'] );
		$coupon_id = 0;
		if ( 'coupon' === $resolved['via'] ) {
			$c_codes = $order->get_coupon_codes();
			if ( empty( $c_codes ) && function_exists( 'WC' ) && WC()->cart ) {
				$c_codes = WC()->cart->get_applied_coupons();
			}
			foreach ( (array) $c_codes as $cc ) {
				$c        = new WC_Coupon( $cc );
				$meta_aid = (int) $c->get_meta( '_pb_affiliate_id' );
				$by_code  = (int) self::get_affiliate_id_by_code( $cc );
				if ( $meta_aid === (int) $resolved['affiliate_id'] || $by_code === (int) $resolved['affiliate_id'] ) {
					$coupon_id = $c->get_id();
					break;
				}
			}
		}
		if ( $coupon_id ) {
			$order->update_meta_data( '_pb_affiliate_coupon_id', $coupon_id );
		}

		$cookie = PB_Affiliates_Tracking::get_cookie_data();
		if ( $cookie && (int) ( $cookie['id'] ?? 0 ) === (int) $resolved['affiliate_id'] && ! empty( $cookie['referer_host'] ) ) {
			$order->update_meta_data( '_pb_referer_host', substr( (string) $cookie['referer_host'], 0, 190 ) );
		}

		if ( $order->get_id() ) {
			$order->save();
		}
	}
}
